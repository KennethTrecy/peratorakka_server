<?php

namespace App\Libraries;

use App\Libraries\Context;
use App\Libraries\Context\AccountCache;
use App\Libraries\Context\CashFlowActivityCache;
use App\Libraries\Context\ContextKeys;
use App\Models\AccountModel;

class ModifierAtomInputExaminer
{
    /**
     * @type ModifierAtomInputExaminer[]
     */
    private static array $instances = [];

    private readonly array $input;
    private readonly Context $context;

    public static function make(string $key, array $data): ModifierAtomInputExaminer
    {
        helper("array");

        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new self(dot_array_search($key, $data));
        }

        return self::$instances[$key];
    }

    public static function clear()
    {
        self::$instances = [];
    }

    private function __construct(array $input)
    {
        $this->context = Context::make();
        $this->input = $input;

        AccountCache::make($this->context);
        CashFlowActivityCache::make($this->context);
    }

    public function validateSchema(): bool
    {
        return is_array($this->input) && array_reduce(
            $this->input,
            fn ($previous_result, $input_element) => (
                $previous_result
                && isset($input_element["kind"])
                && isset($input_element["account_id"])
                && is_string($input_element["kind"])
                && in_array($input_element["kind"], ACCEPTABLE_MODIFIER_ATOM_KINDS)
                && is_int($input_element["account_id"])
                && (
                    !isset($input_element["cash_flow_activity_id"])
                    || is_int($input_element["cash_flow_activity_id"])
                )
            ),
            true
        );
    }

    public function validateOwnership(): bool
    {
        $account_IDs = $this->extractAccountIDs();
        $cash_flow_activity_IDs = $this->extractCashFlowActivityIDs();

        $account_cache = $this->context->getVariable(ContextKeys::ACCOUNT_CACHE);
        $account_cache->loadResources($account_IDs);
        $account_count = $account_cache->countLoadedResources();

        $cash_flow_activity_cache = $this->context->getVariable(
            ContextKeys::CASH_FLOW_ACTIVITY_CACHE
        );
        $cash_flow_activity_cache->loadResources($cash_flow_activity_IDs);
        $cash_flow_activity_count = $cash_flow_activity_cache->countLoadedResources();

        return $account_count === count($account_IDs)
            && $cash_flow_activity_count === count($cash_flow_activity_IDs);
    }

    public function validateCashFlowActivityAssociations(string $action): bool
    {
        $account_cache = $this->context->getVariable(ContextKeys::ACCOUNT_CACHE);
        $account_tally = [];
        foreach ($this->input as $atom) {
            $modifier_atom_kind = $atom["kind"];
            $account_kind = $account_cache->determineAccountKind($atom["account_id"]);

            $has_correct_cash_flow_activity_association = $action === CLOSE_MODIFIER_ACTION || (
                $action !== CLOSE_MODIFIER_ACTION
                && (
                    (
                        (
                            $modifier_atom_kind === REAL_DEBIT_MODIFIER_ATOM_KIND
                            || $modifier_atom_kind === REAL_CREDIT_MODIFIER_ATOM_KIND
                        ) && (
                            (
                                $account_kind !== LIQUID_ASSET_ACCOUNT_KIND
                                && isset($atom["cash_flow_activity_id"])
                            ) || (
                                $account_kind === LIQUID_ASSET_ACCOUNT_KIND
                                && !isset($atom["cash_flow_activity_id"])
                            )
                        )
                    ) || (
                        (
                            $modifier_atom_kind === IMAGINARY_DEBIT_MODIFIER_ATOM_KIND
                            || $modifier_atom_kind === IMAGINARY_CREDIT_MODIFIER_ATOM_KIND
                        ) && $account_kind !== LIQUID_ASSET_ACCOUNT_KIND
                        && isset($atom["cash_flow_activity_id"])
                    )
                )
            );

            if (!$has_correct_cash_flow_activity_association) {
                return false;
            }
        }

        return true;
    }

    public function validateAction(string $action): bool
    {
        $permission_matrix = $this->generatePermissionMatrix();

        $allowed_account_kinds_per_atom_kind = $permission_matrix[$action];

        $account_cache = $this->context->getVariable(ContextKeys::ACCOUNT_CACHE);
        $account_tally = [];
        foreach ($this->input as $atom) {
            $modifier_atom_kind = $atom["kind"];
            $account_kind = $account_cache->determineAccountKind($atom["account_id"]);

            if (!isset($account_tally[$modifier_atom_kind])) {
                $account_tally[$modifier_atom_kind] = [];
            }

            if (!isset($account_tally[$modifier_atom_kind][$account_kind])) {
                $account_tally[$modifier_atom_kind][$account_kind] = 0;
            }

            ++$account_tally[$modifier_atom_kind][$account_kind];
        }

        foreach ($allowed_account_kinds_per_atom_kind as $atom_kind => $account_condition_sets) {
            foreach ($account_condition_sets as $account_condition_set) {
                $condition = $account_condition_set[0];
                $involved_account_kinds = array_slice(array_keys($account_condition_set), 1);

                $tallied_account_kinds = isset($account_tally[$atom_kind])
                    ? $account_tally[$atom_kind]
                    : [];
                foreach ($involved_account_kinds as $involved_account_kind) {
                    if (isset($tallied_account_kinds[$involved_account_kind])) {
                        $quantifier = $account_condition_set[$involved_account_kind];
                        $tally = $tallied_account_kinds[$involved_account_kind];
                        if ($quantifier[0] <= $tally && $tally <= $quantifier[1]) {
                            continue;
                        } else {
                            return false;
                        }
                    } elseif (
                        $condition === "any"
                        || (
                            $condition === "all"
                            && $account_condition_set[$involved_account_kind][0] === 0
                        )
                    ) {
                        continue;
                    } else {
                        return false;
                    }
                }
            }
        }

        if ($action === EXCHANGE_MODIFIER_ACTION) {
            $debit_account_id = $this->input[0]["account_id"];
            $credit_account_id = $this->input[1]["account_id"];

            $source_currency_id = $account_cache->determineCurrencyID($credit_account_id);
            $destination_currency_id = $account_cache->determineCurrencyID($debit_account_id);

            return $source_currency_id !== $destination_currency_id;
        }

        return true;
    }

    private function extractAccountIDs(): array
    {
        return array_unique(array_map(
            fn ($input_element) => $input_element["account_id"],
            $this->input
        ));
    }

    private function extractCashFlowActivityIDs(): array
    {
        return array_unique(Resource::retainExistingElements(array_map(
            fn ($input_element) => ($input_element["cash_flow_activity_id"] ?? null),
            $this->input
        )));
    }

    private function generatePermissionMatrix(): array
    {
        $one_or_many_count = [ 1, PHP_INT_MAX ];
        $zero_or_many_count = [ 0, PHP_INT_MAX ];
        $one_count_only = [ 1, 1 ];
        $normal_atom_permissions = [
            [
                "any",
                GENERAL_ASSET_ACCOUNT_KIND => $one_or_many_count,
                LIABILITY_ACCOUNT_KIND => $one_or_many_count,
                EQUITY_ACCOUNT_KIND => $one_or_many_count,
                GENERAL_EXPENSE_ACCOUNT_KIND => $one_or_many_count,
                GENERAL_REVENUE_ACCOUNT_KIND => $one_or_many_count,
                LIQUID_ASSET_ACCOUNT_KIND => $one_or_many_count,
                DEPRECIATIVE_ASSET_ACCOUNT_KIND => $one_or_many_count,
                DIRECT_COST_ACCOUNT_KIND => $one_or_many_count,
                DIRECT_SALE_ACCOUNT_KIND => $one_or_many_count
            ]
        ];
        $exchange_atom_permissions = [
            [
                "any",
                GENERAL_ASSET_ACCOUNT_KIND => $one_count_only,
                LIABILITY_ACCOUNT_KIND => $one_count_only,
                EQUITY_ACCOUNT_KIND => $one_count_only,
                GENERAL_EXPENSE_ACCOUNT_KIND => $one_count_only,
                GENERAL_REVENUE_ACCOUNT_KIND => $one_count_only,
                LIQUID_ASSET_ACCOUNT_KIND => $one_count_only,
                DEPRECIATIVE_ASSET_ACCOUNT_KIND => $one_count_only
            ]
        ];

        return [
            RECORD_MODIFIER_ACTION => [
                REAL_DEBIT_MODIFIER_ATOM_KIND => $normal_atom_permissions,
                REAL_CREDIT_MODIFIER_ATOM_KIND => $normal_atom_permissions,
                IMAGINARY_DEBIT_MODIFIER_ATOM_KIND => $normal_atom_permissions,
                IMAGINARY_CREDIT_MODIFIER_ATOM_KIND => $normal_atom_permissions
            ],
            CLOSE_MODIFIER_ACTION => [
                REAL_DEBIT_MODIFIER_ATOM_KIND => [
                    [
                        "any",
                        EQUITY_ACCOUNT_KIND => $one_count_only,
                        GENERAL_REVENUE_ACCOUNT_KIND => $one_count_only,
                        GENERAL_TEMPORARY_ACCOUNT_KIND => $one_count_only,
                        DIRECT_SALE_ACCOUNT_KIND => $one_count_only
                    ]
                ],
                REAL_CREDIT_MODIFIER_ATOM_KIND => [
                    [
                        "any",
                        EQUITY_ACCOUNT_KIND => $one_count_only,
                        GENERAL_EXPENSE_ACCOUNT_KIND => $one_count_only,
                        GENERAL_TEMPORARY_ACCOUNT_KIND => $one_count_only,
                        DIRECT_COST_ACCOUNT_KIND => $one_count_only
                    ]
                ]
            ],
            EXCHANGE_MODIFIER_ACTION => [
                REAL_DEBIT_MODIFIER_ATOM_KIND => $exchange_atom_permissions,
                REAL_CREDIT_MODIFIER_ATOM_KIND => $exchange_atom_permissions
            ],
            BID_MODIFIER_ACTION => [
                REAL_DEBIT_MODIFIER_ATOM_KIND => [
                    [
                        "all",
                        ITEMIZED_ASSET_ACCOUNT_KIND => $one_or_many_count
                    ],
                    [
                        "any",
                        GENERAL_EXPENSE_ACCOUNT_KIND => $zero_or_many_count,
                        EQUITY_ACCOUNT_KIND => $zero_or_many_count
                    ]
                ],
                REAL_CREDIT_MODIFIER_ATOM_KIND => [
                    [
                        "any",
                        EQUITY_ACCOUNT_KIND => $zero_or_many_count
                    ],
                    [
                        "any",
                        GENERAL_ASSET_ACCOUNT_KIND => $one_count_only,
                        LIQUID_ASSET_ACCOUNT_KIND => $one_count_only,
                        DEPRECIATIVE_ASSET_ACCOUNT_KIND => $one_count_only
                    ]
                ],
                IMAGINARY_DEBIT_MODIFIER_ATOM_KIND => [
                    [
                        "any",
                        DIRECT_COST_ACCOUNT_KIND => $zero_or_many_count,
                        GENERAL_EXPENSE_ACCOUNT_KIND => $zero_or_many_count,
                        EQUITY_ACCOUNT_KIND => $one_or_many_count
                    ]
                ],
                IMAGINARY_CREDIT_MODIFIER_ATOM_KIND => [
                    [
                        "any",
                        EQUITY_ACCOUNT_KIND => $one_or_many_count
                    ]
                ],
                ITEM_COUNT_MODIFIER_ATOM_KIND => [
                    [
                        "any",
                        ITEMIZED_ASSET_ACCOUNT_KIND => $one_or_many_count
                    ]
                ],
                PRICE_MODIFIER_ATOM_KIND => [
                    [
                        "any",
                        ITEMIZED_ASSET_ACCOUNT_KIND => $one_or_many_count
                    ]
                ]
            ],
            ASK_MODIFIER_ACTION => [
                REAL_DEBIT_MODIFIER_ATOM_KIND => [
                    [
                        "any",
                        EQUITY_ACCOUNT_KIND => $zero_or_many_count,
                        DIRECT_COST_ACCOUNT_KIND => $zero_or_many_count
                    ],
                    [
                        "any",
                        GENERAL_ASSET_ACCOUNT_KIND => $one_count_only,
                        LIQUID_ASSET_ACCOUNT_KIND => $one_count_only,
                        DEPRECIATIVE_ASSET_ACCOUNT_KIND => $one_count_only
                    ]
                ],
                REAL_CREDIT_MODIFIER_ATOM_KIND => [
                    [
                        "all",
                        ITEMIZED_ASSET_ACCOUNT_KIND => $one_or_many_count,
                        DIRECT_SALE_ACCOUNT_KIND => $one_count_only
                    ],
                    [
                        "any",
                        GENERAL_EXPENSE_ACCOUNT_KIND => $zero_or_many_count,
                        EQUITY_ACCOUNT_KIND => $zero_or_many_count
                    ]
                ],
                IMAGINARY_DEBIT_MODIFIER_ATOM_KIND => [
                    [
                        "any",
                        EQUITY_ACCOUNT_KIND => $one_or_many_count,
                    ]
                ],
                IMAGINARY_CREDIT_MODIFIER_ATOM_KIND => [
                    [
                        "any",
                        DIRECT_COST_ACCOUNT_KIND => $zero_or_many_count,
                        GENERAL_EXPENSE_ACCOUNT_KIND => $zero_or_many_count
                    ]
                ],
                ITEM_COUNT_MODIFIER_ATOM_KIND => [
                    [
                        "any",
                        ITEMIZED_ASSET_ACCOUNT_KIND => $one_or_many_count
                    ]
                ],
                PRICE_MODIFIER_ATOM_KIND => [
                    [
                        "any",
                        ITEMIZED_ASSET_ACCOUNT_KIND => $one_or_many_count
                    ]
                ]
            ],
            TRANSFORM_MODIFIER_ACTION => [
                REAL_DEBIT_MODIFIER_ATOM_KIND => [
                    [
                        "any",
                        ITEMIZED_ASSET_ACCOUNT_KIND => $one_or_many_count
                    ]
                ],
                REAL_CREDIT_MODIFIER_ATOM_KIND => [
                    [
                        "any",
                        ITEMIZED_ASSET_ACCOUNT_KIND => $one_or_many_count
                    ]
                ],
                ITEM_COUNT_MODIFIER_ATOM_KIND => [
                    [
                        "any",
                        ITEMIZED_ASSET_ACCOUNT_KIND => $one_or_many_count
                    ]
                ]
            ],
            THROW_MODIFIER_ACTION => [
                REAL_DEBIT_MODIFIER_ATOM_KIND => [
                    [
                        "any",
                        GENERAL_EXPENSE_ACCOUNT_KIND => $one_or_many_count,
                        DIRECT_COST_ACCOUNT_KIND => $one_or_many_count
                    ]
                ],
                REAL_CREDIT_MODIFIER_ATOM_KIND => [
                    [
                        "any",
                        ITEMIZED_ASSET_ACCOUNT_KIND => $one_or_many_count
                    ]
                ],
                ITEM_COUNT_MODIFIER_ATOM_KIND => [
                    [
                        "any",
                        ITEMIZED_ASSET_ACCOUNT_KIND => $one_or_many_count
                    ]
                ]
            ],
            CATCH_MODIFIER_ACTION => [
                REAL_DEBIT_MODIFIER_ATOM_KIND => [
                    [
                        "any",
                        ITEMIZED_ASSET_ACCOUNT_KIND => $one_or_many_count
                    ]
                ],
                REAL_CREDIT_MODIFIER_ATOM_KIND => [
                    [
                        "any",
                        GENERAL_REVENUE_ACCOUNT_KIND => $one_or_many_count,
                        DIRECT_SALE_ACCOUNT_KIND => $one_or_many_count
                    ]
                ],
                ITEM_COUNT_MODIFIER_ATOM_KIND => [
                    [
                        "any",
                        ITEMIZED_ASSET_ACCOUNT_KIND => $one_or_many_count
                    ]
                ]
            ],
            CONDENSE_MODIFIER_ACTION => [
                ITEM_COUNT_MODIFIER_ATOM_KIND => [
                    [
                        "any",
                        ITEMIZED_ASSET_ACCOUNT_KIND => $one_or_many_count
                    ]
                ]
            ],
            DILUTE_MODIFIER_ACTION => [
                ITEM_COUNT_MODIFIER_ATOM_KIND => [
                    [
                        "any",
                        ITEMIZED_ASSET_ACCOUNT_KIND => $one_or_many_count
                    ]
                ]
            ]
        ];
    }
}
