<?php

namespace App\Models;

use App\Casts\RationalNumber;
use App\Entities\FrozenAccount;
use App\Entities\FrozenPeriod;
use App\Entities\RealAdjustedSummaryCalculation;
use App\Entities\RealFlowCalculation;
use App\Entities\RealUnadjustedSummaryCalculation;
use App\Entities\ItemCalculation;
use App\Libraries\Context;
use App\Libraries\Context\AccountCache;
use App\Libraries\Context\ItemConfigurationCache;
use App\Libraries\Context\ModifierAtomActivityCache;
use App\Libraries\Context\ModifierAtomCache;
use App\Libraries\Context\ModifierCache;
use App\Libraries\Resource;
use App\Models\FrozenAccountModel;
use App\Models\RealAdjustedSummaryCalculationModel;
use Brick\Math\BigRational;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use Faker\Generator;

class FrozenPeriodModel extends BaseResourceModel
{
    protected $table = "frozen_periods";
    protected $returnType = FrozenPeriod::class;
    protected $allowedFields = [
        "user_id",
        "started_at",
        "finished_at"
    ];
    protected $useTimestamps = false;
    protected $useSoftDeletes = false;

    protected $sortable_fields = [
        "started_at",
        "finished_at"
    ];

    public function fake(Generator &$faker)
    {
        return [
            "started_at"  => Time::yesterday()->toDateTimeString(),
            "finished_at"  => Time::now()->toDateTimeString()
        ];
    }

    public function limitSearchToUser(BaseResourceModel $query_builder, User $user)
    {
        return $query_builder->where("user_id", $user->id);
    }

    public static function findLatestPeriod(string $latest_time): ?FrozenPeriod
    {
        return model(FrozenPeriodModel::class, false)
            ->where("finished_at <", $latest_time)
            ->orderBy("finished_at", "DESC")
            ->first();
    }

    public static function createAncestorResources(int $user_id, array $options): array
    {
        return [
            [],
            isset($options["time_ranges"])
                ? array_map(fn ($range) => [
                    "started_at" => $range[0],
                    "finished_at" => $range[1],
                    "user_id" => $user_id
                ], $options["time_ranges"]) : [
                    [
                        "user_id" => $user_id
                    ]
                ]
        ];
    }

    public static function makeRawCalculations(
        User $user,
        Context $context,
        string $started_at,
        string $finished_at
    ): array {
        $financial_entry_model = model(FinancialEntryModel::class, false);
        $financial_entries = $financial_entry_model
            ->limitSearchToUser($financial_entry_model, $user)
            ->where("transacted_at >=", $started_at)
            ->where("transacted_at <=", $finished_at)
            ->withDeleted()
            ->findAll();

        // Happens for new users
        if (count($financial_entries) === 0) {
            return [
                [], // frozen accounts
                [], // real unadjusted summary calculations
                [], // real adjusted summary calculations
                [], // real flow calculations
            ];
        }

        [
            $associated_accounts,
            $associated_cash_flow_activities,
            $financial_entry_atoms
        ] = static::loadLinkedResourcesAndFinancialEntryAtoms(
            $context,
            $financial_entries
        );

        [
            // Used to determine previous period
            $earliest_transacted_time,
            // Use to determine the exchange rate to use
            $latest_entry_transacted_time
        ] = static::minMaxTransactedTimes($financial_entries);

        [
            $previous_keyed_real_raw_adjusted_summaries,
            $previous_keyed_raw_item_calculations
        ] = static::loadPreviousSummaryCalculations($context, $earliest_transacted_time);

        $known_linked_accounts = array_unique([
            ...array_values($associated_accounts),
            ...array_keys($previous_keyed_real_raw_adjusted_summaries)
        ]);
        $associated_account_hashes = static::generateAccountHashes(
            $started_at,
            $finished_at,
            $known_linked_accounts
        );

        [
            $keyed_real_raw_unadjusted_summaries,
            $keyed_real_raw_adjusted_summaries,
            $keyed_real_raw_flows,
            $keyed_raw_item_calculations
        ] = static::initiateRawCalculations(
            $context,
            $associated_accounts,
            $associated_cash_flow_activities,
            $associated_account_hashes,
            $previous_keyed_real_raw_adjusted_summaries,
            $previous_keyed_raw_item_calculations
        );

        [
            $keyed_real_raw_unadjusted_summaries,
            $keyed_real_raw_adjusted_summaries,
            $keyed_real_raw_flows,
            $keyed_raw_item_calculations
        ] = static::consolidateRawCalculations(
            $context,
            $keyed_real_raw_unadjusted_summaries,
            $keyed_real_raw_adjusted_summaries,
            $keyed_real_raw_flows,
            $keyed_raw_item_calculations,
            $associated_account_hashes,
            $associated_cash_flow_activities,
            $financial_entries,
            $financial_entry_atoms
        );

        [
            $frozen_accounts,
            $real_unadjusted_summaries,
            $real_adjusted_summaries,
            $real_flows,
            $item_calculations
        ] = static::makeResources(
            $associated_account_hashes,
            $keyed_real_raw_unadjusted_summaries,
            $keyed_real_raw_adjusted_summaries,
            $keyed_real_raw_flows,
            $keyed_raw_item_calculations
        );

        return [
            array_values($frozen_accounts),
            array_values($real_unadjusted_summaries),
            array_values($real_adjusted_summaries),
            array_values($real_flows),
            array_values($item_calculations)
        ];
    }

    public static function createTestPeriods(
        User $user,
        array $time_range_entry_groups,
        array $dependencies
    ): array {
        $currency_count = $dependencies["currency_count"];
        $cash_flow_activity_count = $dependencies["cash_flow_activity_count"];
        $modifier_actions = $dependencies["expected_modifier_actions"];

        [
            $precision_formats,
            $currencies
        ] = CurrencyModel::createTestResources(
            $user->id,
            $currency_count,
            $dependencies["currency_options"] ?? []
        );

        [
            $cash_flow_activities
        ] = $cash_flow_activity_count === 0
            ? [ [] ]
            : CashFlowActivityModel::createTestResources($user->id, $cash_flow_activity_count, []);
        [
            $modifiers
        ] = ModifierModel::createTestResources($user->id, 1, [
            "expected_actions" => $modifier_actions
        ]);

        $raw_account_infos = array_map(fn ($combination) => [
            "currency_id" => $currencies[$combination[0]]->id,
            "kind" => $combination[1]
        ], $dependencies["account_combinations"]);
        [ $accounts ] = AccountModel::createTestResources($user->id, 1, [
            "ancestor_data" => [
                [],
                $raw_account_infos
            ]
        ]);

        $item_detail_parents = null;
        foreach ($accounts as $index => $account) {
            if (count($dependencies["account_combinations"][$index]) === 3) {
                [
                    $precision_formats,
                    $currencies,
                    $parent_account,
                    $item_detail_parents,
                    $item_configuration
                ] = ItemConfigurationModel::createTestResource($user->id, [
                    "item_detail_parent" => $item_detail_parents,
                    "ancestor_accounts" => [
                        $precision_formats,
                        $currencies,
                        [ $account ]
                    ],
                    "expected_valuation_methods" => [
                        $dependencies["account_combinations"][$index][2]
                    ]
                ]);
            }
        }
        $raw_modifier_atom_infos = array_map(fn ($combination) => [
            "modifier_id" => $modifiers[$combination[0]]->id,
            "account_id" => $accounts[
                $combination[1]
            ]->id,
            "kind" => $combination[2]
        ], $dependencies["modifier_atom_combinations"]);

        [ $modifier_atoms ] = ModifierAtomModel::createTestResources($user->id, 1, [
            "ancestor_data" => [
                [],
                $raw_modifier_atom_infos
            ]
        ]);

        if (count($dependencies["modifier_atom_activity_combinations"]) > 0) {
            $raw_modifier_atom_activity_infos = array_map(fn ($combination) => [
                "modifier_atom_id" => $modifier_atoms[$combination[0]]->id,
                "cash_flow_activity_id" => $cash_flow_activities[$combination[1]]->id
            ], $dependencies["modifier_atom_activity_combinations"]);

            model(ModifierAtomActivityModel::class, false)
                ->insertBatch($raw_modifier_atom_activity_infos);
        }
        $modifier_atom_activities = model(ModifierAtomActivityModel::class, false)->findAll();

        [ $raw_financial_entry_infos, $raw_financial_entry_atom_infos ] = array_reduce(
            $time_range_entry_groups,
            fn ($previous_infos, $time_range_entry_group) => array_reduce(
                $time_range_entry_group["entries"],
                fn ($previous_entry_infos, $time_range_entry) => [
                    [
                        ...$previous_entry_infos[0],
                        [
                            "modifier_id" => $modifiers[$time_range_entry["modifier_index"]]->id,
                            "transacted_at" => $time_range_entry_group["started_at"]
                        ]
                    ],
                    [
                        ...$previous_entry_infos[1],
                        ...array_map(
                            fn ($time_range_entry_atom) => [
                                "financial_entry_index" => count($previous_entry_infos[0]),
                                "modifier_atom_index" => $time_range_entry_atom[0],
                                "kind" => count($time_range_entry_atom) > 2
                                    ? $time_range_entry_atom[1]
                                    : TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
                                "numerical_value" => count($time_range_entry_atom) > 2
                                    ? $time_range_entry_atom[2]
                                    : $time_range_entry_atom[1]
                            ],
                            $time_range_entry["atoms"]
                        )
                    ]
                ],
                $previous_infos
            ),
            [ [], [] ]
        );
        [ $financial_entries ] = FinancialEntryModel::createTestResources(
            $user->id,
            1,
            [
                "ancestor_data" => [
                    [],
                    $raw_financial_entry_infos
                ]
            ]
        );

        $raw_financial_entry_atom_infos = array_map(fn ($info) => [
            "financial_entry_id" => $financial_entries[$info["financial_entry_index"]]->id,
            "modifier_atom_id" => $modifier_atoms[$info["modifier_atom_index"]]->id,
            "kind" => $info["kind"],
            "numerical_value" => $info["numerical_value"]
        ], $raw_financial_entry_atom_infos);
        [ $financial_entry_atoms ] = FinancialEntryAtomModel::createTestResources(
            $user->id,
            1,
            [
                "ancestor_data" => [
                    [],
                    $raw_financial_entry_atom_infos
                ]
            ]
        );

        $frozen_periods = [];
        $frozen_accounts = [];
        $real_adjusted_summaries = [];
        $real_unadjusted_summaries = [];
        $real_flows = [];
        foreach ($time_range_entry_groups as $time_range_entry_group) {
            $started_at = $time_range_entry_group["started_at"];

            [
                $periodic_frozen_accounts,
                $periodic_real_unadjusted_summaries,
                $periodic_real_adjusted_summaries,
                $periodic_real_flows
            ] = FrozenPeriodModel::makeRawCalculations(
                $user,
                Context::make(),
                $started_at,
                $time_range_entry_group["finished_at"] ?? Time::now()
            );

            if (isset($time_range_entry_group["finished_at"])) {
                $finished_at = $time_range_entry_group["finished_at"];

                [ $frozen_period ] = FrozenPeriodModel::createTestResource(
                    $user->id,
                    [
                        "overrides" => [
                            "started_at" => $started_at,
                            "finished_at" => $finished_at
                        ]
                    ]
                );

                foreach ($periodic_frozen_accounts as $frozen_account) {
                    $frozen_account->frozen_period_id = $frozen_period->id;
                }
                model(FrozenAccountModel::class)->insertBatch($periodic_frozen_accounts);
                model(RealAdjustedSummaryCalculationModel::class)
                    ->insertBatch($periodic_real_adjusted_summaries);
                model(RealUnadjustedSummaryCalculationModel::class)
                    ->insertBatch($periodic_real_unadjusted_summaries);
                if (count($periodic_real_flows) > 0) {
                    model(RealFlowCalculationModel::class)->insertBatch($periodic_real_flows);
                }
                array_push($frozen_periods, $frozen_period);
            } else {
                array_push($frozen_periods, (new FrozenPeriod())->fill([
                    "started_at" => $time_range_entry_group["started_at"]
                ]));
            }

            array_push($frozen_accounts, ...$periodic_frozen_accounts);
            array_push($real_unadjusted_summaries, ...$periodic_real_unadjusted_summaries);
            array_push($real_adjusted_summaries, ...$periodic_real_adjusted_summaries);
            array_push($real_flows, ...$periodic_real_flows);
        }

        return [
            $precision_formats,
            $cash_flow_activities,
            $currencies,
            $accounts,
            $frozen_periods,
            $frozen_accounts,
            $real_adjusted_summaries,
            $real_unadjusted_summaries,
            $real_flows
        ];
    }

    private static function loadLinkedResourcesAndFinancialEntryAtoms(
        Context $context,
        array $financial_entries
    ): array {
        $linked_modifiers = [];
        foreach ($financial_entries as $document) {
            $modifier_id = $document->modifier_id;
            array_push($linked_modifiers, $modifier_id);
        }
        $linked_modifiers = array_unique($linked_modifiers);

        ModifierCache::make($context)->loadResources($linked_modifiers);
        $modifier_atom_cache = ModifierAtomCache::make($context);
        $modifier_atom_cache->loadResourcesFromParentIDs($linked_modifiers);

        $associated_accounts = $modifier_atom_cache->extractAssociatedAccountIDs();
        $linked_accounts = array_unique(array_values($associated_accounts));
        AccountCache::make($context)->loadResources($linked_accounts);
        ItemConfigurationCache::make($context)->loadResourcesFromParentIDs($linked_accounts);

        $linked_modifier_atoms = array_keys($associated_accounts);
        $modifier_atom_activity_cache = ModifierAtomActivityCache::make($context);
        $modifier_atom_activity_cache->loadResourcesFromParentIDs($linked_modifier_atoms);

        $associated_cash_flow_activities = $modifier_atom_activity_cache
            ->extractAssociatedCashFlowActivityIDs();

        $linked_financial_entries = [];
        foreach ($financial_entries as $document) {
            $id = $document->id;
            array_push($linked_financial_entries, $id);
        }

        $financial_entry_atoms = [];
        if (count($linked_financial_entries) > 0) {
            $financial_entry_atoms = model(FinancialEntryAtomModel::class)
                ->whereIn("financial_entry_id", array_unique($linked_financial_entries))
                ->findAll();
        }

        return [
            $associated_accounts,
            $associated_cash_flow_activities,
            $financial_entry_atoms
        ];
    }

    private static function generateAccountHashes(
        string $started_at,
        string $finished_at,
        array $account_IDs
    ): array {
        $started_at = $started_at;
        $finished_at = $finished_at;
        $account_hashes = [];

        foreach ($account_IDs as $account_id) {
            $account_hashes[$account_id] = [
                "account_id" => $account_id,
                "hash" => FrozenAccountModel::generateAccountHash(
                    $started_at,
                    $finished_at,
                    $account_id
                )
            ];
        }

        return $account_hashes;
    }

    private static function minMaxTransactedTimes(array $financial_entries): array
    {
        $earliest_transacted_time = null;
        $latest_transacted_time = null;
        foreach ($financial_entries as $document) {
            $transacted_time = $document->transacted_at;
            if (
                $earliest_transacted_time === null
                || $transacted_time->isBefore($earliest_transacted_time)
            ) {
                $earliest_transacted_time = $transacted_time;
            }

            if (
                $latest_transacted_time === null
                || $transacted_time->isAfter($latest_transacted_time)
            ) {
                $latest_transacted_time = $transacted_time;
            }
        }

        return [ $earliest_transacted_time, $latest_transacted_time ];
    }

    private static function loadPreviousSummaryCalculations(
        Context $context,
        string $earliest_transacted_time
    ): array {
        $previous_frozen_period = FrozenPeriodModel::findLatestPeriod($earliest_transacted_time);

        $keyed_real_raw_adjusted_summaries = [];
        $keyed_raw_item_calculations = [];

        if ($previous_frozen_period) {
            $previous_frozen_accounts = model(FrozenAccountModel::class, false)
                ->where("frozen_period_id", $previous_frozen_period->id)
                ->findAll();
            $keyed_frozen_accounts = Resource::key(
                $previous_frozen_accounts,
                fn ($frozen_account) => $frozen_account->hash,
            );
            $frozen_account_hashes = array_keys($keyed_frozen_accounts);

            $previous_real_adjusted_summaries = model(
                RealAdjustedSummaryCalculationModel::class,
                false
            )->whereIn("frozen_account_hash", $frozen_account_hashes)->findAll();

            foreach ($previous_real_adjusted_summaries as $summary_calculation) {
                $frozen_account_hash = $summary_calculation->frozen_account_hash;
                $account_id = $keyed_frozen_accounts[$frozen_account_hash]->account_id;

                $keyed_real_raw_adjusted_summaries[$account_id] = [
                    "opened_amount" => $summary_calculation->closed_amount,
                    "closed_amount" => $summary_calculation->closed_amount
                ];
            }

            $linked_accounts = array_keys($keyed_real_raw_adjusted_summaries);
            AccountCache::make($context)->loadResources($linked_accounts);
            ItemConfigurationCache::make($context)->loadResourcesFromParentIDs($linked_accounts);

            $previous_item_calculations = model(
                ItemCalculationModel::class,
                false
            )->whereIn("frozen_account_hash", $frozen_account_hashes)->findAll();

            foreach ($previous_item_calculations as $item_calculation) {
                $frozen_account_hash = $item_calculation->frozen_account_hash;
                $account_id = $keyed_frozen_accounts[$frozen_account_hash]->account_id;
                if (!isset($keyed_raw_item_calculations[$account_id])) {
                    $keyed_raw_item_calculations[$account_id] = [];
                }

                array_push(
                    $keyed_raw_item_calculations[$account_id],
                    [
                        "financial_entry_id" => $item_calculation->financial_entry_id,
                        "remaining_cost" => $item_calculation->remaining_cost,
                        "remaining_quantity" => $item_calculation->remaining_quantity
                    ]
                );
            }

        }

        return [
            $keyed_real_raw_adjusted_summaries,
            $keyed_raw_item_calculations
        ];
    }

    private static function initiateRawCalculations(
        Context $context,
        array $associated_accounts,
        array $associated_cash_flow_activities,
        array $associated_account_hashes,
        array $previous_keyed_real_raw_adjusted_summaries,
        array $previous_keyed_raw_item_calculations
    ): array {
        $account_cache = AccountCache::make($context);
        $keyed_real_raw_unadjusted_summaries = [];
        $keyed_real_raw_adjusted_summaries = [];
        $keyed_real_raw_flows = [];
        $keyed_raw_item_calculations = [];

        foreach ($associated_accounts as $modifier_atom_id => $account_id) {
            $account_hash = $associated_account_hashes[$account_id]["hash"];
            $keyed_real_raw_adjusted_summaries[$account_id] = isset(
                $previous_keyed_real_raw_adjusted_summaries[$account_id]
            ) ? $previous_keyed_real_raw_adjusted_summaries[$account_id] : [
                "opened_amount" => RationalNumber::zero(),
                "closed_amount" => RationalNumber::zero()
            ];
            $keyed_real_raw_adjusted_summaries[$account_id]["frozen_account_hash"] = $account_hash;

            $is_debited_normally = $account_cache->isDebitedNormally($account_id);
            $current_balance = $keyed_real_raw_adjusted_summaries[$account_id]["opened_amount"];

            $keyed_real_raw_unadjusted_summaries[$account_id] = [
                "frozen_account_hash" => $account_hash,
                "debit_amount" => $is_debited_normally ? $current_balance : RationalNumber::zero(),
                "credit_amount" => $is_debited_normally ? RationalNumber::zero() : $current_balance
            ];

            if (isset($associated_cash_flow_activities[$modifier_atom_id])) {
                $cash_flow_activity_id = $associated_cash_flow_activities[$modifier_atom_id];
                if (!isset($keyed_raw_flows[$cash_flow_activity_id])) {
                    $keyed_raw_flows[$cash_flow_activity_id] = [];
                }

                $keyed_real_raw_flows[$cash_flow_activity_id][$account_id] = [
                    "frozen_account_hash" => $account_hash,
                    "cash_flow_activity_id" => $cash_flow_activity_id,
                    "net_amount" => RationalNumber::zero()
                ];
            }
        }

        foreach ($previous_keyed_real_raw_adjusted_summaries as $account_id => $raw_summary) {
            if (!isset($keyed_real_raw_unadjusted_summaries[$account_id])) {
                $account_hash = $associated_account_hashes[$account_id]["hash"];
                $keyed_real_raw_adjusted_summaries[$account_id] = [
                    "frozen_account_hash" => $account_hash,
                    "opened_amount" => $raw_summary["opened_amount"]->simplified(),
                    "closed_amount" => $raw_summary["closed_amount"]->simplified()
                ];
            }
        }

        foreach ($previous_keyed_raw_item_calculations as $account_id => $raw_item_calculations) {
            if (!isset($keyed_raw_item_calculations[$account_id])) {
                $account_hash = $associated_account_hashes[$account_id]["hash"];
                $keyed_raw_item_calculation = [];
                foreach ($raw_item_calculations as $raw_item_calculation) {
                    array_push($keyed_raw_item_calculation, [
                        "frozen_account_hash" => $account_hash,
                        "financial_entry_id" => $raw_item_calculation["financial_entry_id"],
                        "remaining_cost" => $raw_item_calculation["remaining_cost"],
                        "remaining_quantity" => $raw_item_calculation["remaining_quantity"]
                    ]);
                }
                $keyed_raw_item_calculations[$account_id] = $keyed_raw_item_calculation;
            }
        }

        return [
            $keyed_real_raw_unadjusted_summaries,
            $keyed_real_raw_adjusted_summaries,
            $keyed_real_raw_flows,
            $keyed_raw_item_calculations
        ];
    }

    private static function consolidateRawCalculations(
        Context $context,
        array $keyed_real_raw_unadjusted_summaries,
        array $keyed_real_raw_adjusted_summaries,
        array $keyed_real_raw_flows,
        array $keyed_raw_item_calculations,
        array $associated_account_hashes,
        array $associated_cash_flow_activities,
        array $financial_entries,
        array $financial_entry_atoms
    ): array {
        $account_cache = AccountCache::make($context);
        $modifier_cache = ModifierCache::make($context);
        $modifier_atom_cache = ModifierAtomCache::make($context);
        $item_configuration_cache = ItemConfigurationCache::make($context);
        $keyed_financial_entries = Resource::key($financial_entries, fn ($entry) => $entry->id);
        $new_unresolved_item_calculations = [];

        foreach ($financial_entry_atoms as $financial_entry_atom) {
            $numerical_value = $financial_entry_atom->numerical_value;
            $financial_entry_id = $financial_entry_atom->financial_entry_id;
            $financial_entry = $keyed_financial_entries[$financial_entry_id];
            $financial_entry_atom_key = $financial_entry->transacted_at->toDateTimeString()
                ."_".$financial_entry->modifier_id
                ."_".$financial_entry_atom->modifier_atom_id;

            $modifier_id = $financial_entry->modifier_id;
            $modifier_atom_id = $financial_entry_atom->modifier_atom_id;
            $account_id = $modifier_atom_cache->determineModifierAtomAccountID($modifier_atom_id);

            $modifier_action = $modifier_cache->determineModifierAction($modifier_id);
            $modifier_atom_kind = $modifier_atom_cache->determineModifierAtomKind(
                $modifier_atom_id
            );
            $is_debited_normally = $account_cache->isDebitedNormally($account_id);
            $is_normally_temporary = $account_cache->isNormallyTemporary($account_id);
            $is_itemized_normally = $account_cache->isItemizedNormally($account_id);

            $financial_entry_atom_kind = $financial_entry_atom->kind;

            if ($is_itemized_normally) {
                if (isset($new_unresolved_item_calculations[$financial_entry_atom_key])) {
                    $entry_atoms = [
                        $new_unresolved_item_calculations[$financial_entry_atom_key],
                        $financial_entry_atom
                    ];
                    unset($new_unresolved_item_calculations[$financial_entry_atom_key]);

                    $price_atom = array_values(array_filter(
                        $entry_atoms,
                        fn ($atom) => $atom->kind === PRICE_FINANCIAL_ENTRY_ATOM_KIND
                    ))[0] ?? null;

                    $quantity_atom = array_values(array_filter(
                        $entry_atoms,
                        fn ($atom) => $atom->kind === QUANTITY_FINANCIAL_ENTRY_ATOM_KIND
                    ))[0] ?? null;

                    $total_atom = array_values(array_filter(
                        $entry_atoms,
                        fn ($atom) => $atom->kind === TOTAL_FINANCIAL_ENTRY_ATOM_KIND
                    ))[0] ?? null;

                    $is_quantified_total_pair = $quantity_atom !== null && $total_atom !== null;
                    $is_priced_quantity_pair = $price_atom !== null && $quantity_atom !== null;
                    $is_priced_total_pair = $price_atom !== null && $total_atom !== null;

                    $hash = $associated_account_hashes[$account_id]["hash"];
                    $partial_item_calculation = [
                        "remaining_cost" => $is_priced_quantity_pair
                            ? $price_atom->numerical_value->multipliedBy(
                                $quantity_atom->numerical_value
                            )
                            : (
                                $total_atom === null
                                    ? "0"
                                    : $total_atom->numerical_value
                            ),
                        "remaining_quantity" => $is_priced_total_pair
                            ? $total_atom->numerical_value->dividedBy(
                                $price_atom->numerical_value
                            )
                            : (
                                $quantity_atom === null
                                    ? "0"
                                    : $quantity_atom->numerical_value
                            )
                    ];

                    if (
                        $is_debited_normally
                        && $modifier_atom_kind === REAL_DEBIT_MODIFIER_ATOM_KIND
                    ) {
                        $new_item_calculation = array_merge([
                            "frozen_account_hash" => $hash,
                            "financial_entry_id" => $financial_entry_id
                        ], $partial_item_calculation);

                        if (!isset($keyed_raw_item_calculations[$account_id])) {
                            $keyed_raw_item_calculations[$account_id] = [];
                        }

                        array_push(
                            $keyed_raw_item_calculations[$account_id],
                            $new_item_calculation
                        );

                        $numerical_value = $new_item_calculation["remaining_cost"];
                    } else if (
                        $is_debited_normally
                        && $modifier_atom_kind === REAL_CREDIT_MODIFIER_ATOM_KIND
                    ) {
                        $method = $item_configuration_cache->determineValuationMethodFromParent(
                            $account_id
                        );

                        switch ($method) {
                            case WEIGHTED_AVERAGE_VALUATION_METHOD:
                                $item_calculations = $keyed_raw_item_calculations[$account_id];
                                $return_subtotal = RationalNumber::zero();
                                $cost_subtotal = RationalNumber::zero();
                                [
                                    "remaining_cost" => $sale_total,
                                    "remaining_quantity" => $sold_quantity
                                ] = $partial_item_calculation;
                                $sale_price = $sale_total->dividedBy($sold_quantity);
                                $gross_total_price = array_reduce(
                                    $item_calculations,
                                    fn ($carry, $item_calculation) => $carry->plus(
                                        $item_calculation["remaining_cost"]
                                    ),
                                    RationalNumber::zero()
                                );
                                $gross_total_quantity = array_reduce(
                                    $item_calculations,
                                    fn ($carry, $item_calculation) => $carry->plus(
                                        $item_calculation["remaining_quantity"]
                                    ),
                                    RationalNumber::zero()
                                );

                                if ($gross_total_quantity->isLessThan($sold_quantity)) {
                                    // TODO: Throw appropriate error. It may happen due to incorrect
                                    // TODO: acceptance of financial entry atoms.
                                }

                                $consumption_factor = $sold_quantity->dividedBy(
                                    $gross_total_quantity
                                );
                                $new_item_calculations = [];

                                foreach ($item_calculations as $item_calculation) {
                                    [
                                        "frozen_account_hash" => $hash,
                                        "financial_entry_id" => $financial_entry_id,
                                        "remaining_cost" => $remaining_cost,
                                        "remaining_quantity" => $remaining_quantity
                                    ] = $item_calculation;

                                    $consumed_quantity = $consumption_factor->multipliedBy(
                                        $remaining_quantity
                                    );

                                    $consumed_cost = $consumption_factor->multipliedBy(
                                        $remaining_cost
                                    );

                                    $consumed_sale = $sale_price->multipliedBy(
                                        $consumed_quantity
                                    );

                                    $cost_subtotal = $cost_subtotal->plus($consumed_cost);

                                    $revenue = $consumed_sale->minus($consumed_cost);
                                    $return_subtotal = $return_subtotal->plus($revenue);

                                    $new_quantity = $remaining_quantity
                                        ->minus($consumed_quantity);
                                    $new_cost = $remaining_cost->minus($consumed_cost);

                                    if (!$new_quantity->isZero()) {
                                        array_push($new_item_calculations, [
                                            "frozen_account_hash" => $hash,
                                            "financial_entry_id" => $financial_entry_id,
                                            "remaining_cost" => $new_cost,
                                            "remaining_quantity" => $new_quantity
                                        ]);
                                    }
                                }

                                $keyed_raw_item_calculations[$account_id] = $new_item_calculations;

                                $numerical_value = $cost_subtotal;
                                $modifier_atoms = $modifier_atom_cache
                                    ->getLoadedResourcesFromParentIDs([ $modifier_id ]);

                                $target_modifier_atom = array_values(array_filter(
                                    $modifier_atoms,
                                    fn ($atom) => $atom->kind === REAL_EMERGENT_MODIFIER_ATOM_KIND
                                ))[0] ?? null;

                                if ($target_modifier_atom === null) {
                                    // TODO: Throw appropriate error. It may happen due to incorrect
                                    // TODO: acceptance of modifier atoms.
                                }

                                [
                                    $keyed_real_raw_unadjusted_summaries,
                                    $keyed_real_raw_adjusted_summaries,
                                    $keyed_real_raw_flows
                                ] = static::applyNumericalValue(
                                    $context,
                                    $keyed_real_raw_unadjusted_summaries,
                                    $keyed_real_raw_adjusted_summaries,
                                    $keyed_real_raw_flows,
                                    $associated_cash_flow_activities,
                                    $target_modifier_atom->account_id,
                                    $modifier_action,
                                    $target_modifier_atom->id,
                                    $return_subtotal
                                );

                                break;
                        }
                    }
                } else if (
                    $modifier_atom_kind === REAL_DEBITEM_MODIFIER_ATOM_KIND
                    || $modifier_atom_kind === REAL_CREDITEM_MODIFIER_ATOM_KIND
                ) {
                    if (
                        $is_debited_normally
                        && $modifier_atom_kind === REAL_DEBITEM_MODIFIER_ATOM_KIND
                    ) {
                        $new_item_calculation = [
                            "frozen_account_hash" => $hash,
                            "financial_entry_id" => $financial_entry_id,
                            "remaining_cost" => RationalNumber::zero(),
                            "remaining_quantity" => $financial_entry_atom->numerical_value
                        ];

                        if (!isset($keyed_raw_item_calculations[$account_id])) {
                            $keyed_raw_item_calculations[$account_id] = [];
                        }

                        array_push(
                            $keyed_raw_item_calculations[$account_id],
                            $new_item_calculation
                        );
                    } else if (
                        $is_debited_normally
                        && $modifier_atom_kind === REAL_CREDITEM_MODIFIER_ATOM_KIND
                    ) {
                        $new_item_calculation = [
                            "frozen_account_hash" => $hash,
                            "financial_entry_id" => $financial_entry_id,
                            "remaining_cost" => RationalNumber::zero(),
                            "remaining_quantity" => $financial_entry_atom
                                ->numerical_value
                                ->negated()
                        ];

                        if (!isset($keyed_raw_item_calculations[$account_id])) {
                            $keyed_raw_item_calculations[$account_id] = [];
                        }

                        array_push(
                            $keyed_raw_item_calculations[$account_id],
                            $new_item_calculation
                        );
                    }
                } else {
                    $new_unresolved_item_calculations[$financial_entry_atom_key]
                        = $financial_entry_atom;
                    continue;
                }
            }

            [
                $keyed_real_raw_unadjusted_summaries,
                $keyed_real_raw_adjusted_summaries,
                $keyed_real_raw_flows
            ] = static::applyNumericalValue(
                $context,
                $keyed_real_raw_unadjusted_summaries,
                $keyed_real_raw_adjusted_summaries,
                $keyed_real_raw_flows,
                $associated_cash_flow_activities,
                $account_id,
                $modifier_action,
                $modifier_atom_id,
                $numerical_value
            );
        }

        return [
            $keyed_real_raw_unadjusted_summaries,
            $keyed_real_raw_adjusted_summaries,
            $keyed_real_raw_flows,
            $keyed_raw_item_calculations
        ];
    }
    private static function applyNumericalValue(
        Context $context,
        array $keyed_real_raw_unadjusted_summaries,
        array $keyed_real_raw_adjusted_summaries,
        array $keyed_real_raw_flows,
        array $associated_cash_flow_activities,
        int $account_id,
        string $modifier_action,
        int $modifier_atom_id,
        BigRational $numerical_value
    ): array {
        if (
            $modifier_action === CONDENSE_MODIFIER_ACTION
            || $modifier_action === DILUTE_MODIFIER_ACTION
        ) {
            return [
                $keyed_real_raw_unadjusted_summaries,
                $keyed_real_raw_adjusted_summaries,
                $keyed_real_raw_flows
            ];
        }

        $account_cache = AccountCache::make($context);
        $modifier_atom_cache = ModifierAtomCache::make($context);

        $is_positive_numerical_value = $numerical_value->getSign() === 1;
        $is_debited_normally = $account_cache->isDebitedNormally($account_id);
        $modifier_atom_kind = $modifier_atom_cache->determineModifierAtomKind(
            $modifier_atom_id
        );
        $modifier_atom_kind = $modifier_atom_kind === REAL_EMERGENT_MODIFIER_ATOM_KIND
            ? (
                (
                    ($is_debited_normally && $is_positive_numerical_value)
                    || (!$is_debited_normally && !$is_positive_numerical_value)
                ) ? REAL_DEBIT_MODIFIER_ATOM_KIND : REAL_CREDIT_MODIFIER_ATOM_KIND
            ): $modifier_atom_kind;

        $adjusted_value = (
            $is_debited_normally === (
                $modifier_atom_kind === REAL_DEBIT_MODIFIER_ATOM_KIND
                || $modifier_atom_kind === IMAGINARY_DEBIT_MODIFIER_ATOM_KIND
            )
        ) || (
            !$is_debited_normally && (
                $modifier_atom_kind === REAL_CREDIT_MODIFIER_ATOM_KIND
                || $modifier_atom_kind === IMAGINARY_CREDIT_MODIFIER_ATOM_KIND
            )
        ) ? $numerical_value : $numerical_value->negated();

        $keyed_real_raw_adjusted_summaries[$account_id]["closed_amount"]
            = $keyed_real_raw_adjusted_summaries[$account_id]["closed_amount"]
                ->plus($adjusted_value);

        if ($modifier_action !== CLOSE_MODIFIER_ACTION) {
            switch ($modifier_atom_kind) {
                case REAL_DEBIT_MODIFIER_ATOM_KIND: {
                    $keyed_real_raw_unadjusted_summaries[$account_id]["debit_amount"]
                        = $keyed_real_raw_unadjusted_summaries[$account_id]["debit_amount"]
                            ->plus($numerical_value);

                    if (isset($associated_cash_flow_activities[$modifier_atom_id])) {
                        $cash_flow_activity_id = $associated_cash_flow_activities[
                            $modifier_atom_id
                        ];
                        $new_net_amount = $keyed_real_raw_flows[
                            $cash_flow_activity_id
                        ][$account_id]["net_amount"];

                        $new_net_amount = $new_net_amount->minus($numerical_value);

                        $keyed_real_raw_flows[$cash_flow_activity_id][$account_id]["net_amount"]
                            = $new_net_amount;
                    }
                    break;
                }
                case REAL_CREDIT_MODIFIER_ATOM_KIND: {
                    $keyed_real_raw_unadjusted_summaries[$account_id]["credit_amount"]
                        = $keyed_real_raw_unadjusted_summaries[$account_id]["credit_amount"]
                            ->plus($numerical_value);

                    if (isset($associated_cash_flow_activities[$modifier_atom_id])) {
                        $cash_flow_activity_id = $associated_cash_flow_activities[
                            $modifier_atom_id
                        ];
                        $new_net_amount = $keyed_real_raw_flows[
                            $cash_flow_activity_id
                        ][$account_id]["net_amount"];

                        $new_net_amount = $new_net_amount->plus($numerical_value);

                        $keyed_real_raw_flows[$cash_flow_activity_id][$account_id]["net_amount"]
                            = $new_net_amount;
                    }
                    break;
                }
                // TODO: Implement calculation for imaginary values.
                case IMAGINARY_DEBIT_MODIFIER_ATOM_KIND: {
                    break;
                }
                case IMAGINARY_CREDIT_MODIFIER_ATOM_KIND: {
                    break;
                }
                case PRICE_MODIFIER_ATOM_KIND: {
                    break;
                }
            }
        }

        return [
            $keyed_real_raw_unadjusted_summaries,
            $keyed_real_raw_adjusted_summaries,
            $keyed_real_raw_flows
        ];
    }

    private static function makeResources(
        array $associated_accounts,
        array $keyed_real_raw_unadjusted_summaries,
        array $keyed_real_raw_adjusted_summaries,
        array $keyed_real_raw_flows,
        array $keyed_raw_item_calculations
    ): array {
        $frozen_accounts = array_values(array_map(
            fn ($raw_associated_account) => (new FrozenAccount())->fill($raw_associated_account),
            $associated_accounts
        ));

        $keyed_real_raw_unadjusted_summaries = array_filter(
            $keyed_real_raw_unadjusted_summaries,
            function ($real_raw_unadjusted_summary) {
                return $real_raw_unadjusted_summary["debit_amount"]->getSign() !== 0
                    || $real_raw_unadjusted_summary["credit_amount"]->getSign() !== 0;
            }
        );
        $real_unadjusted_summaries = array_map(
            function ($raw_calculation) {
                $raw_calculation["debit_amount"] = $raw_calculation["debit_amount"]->simplified();
                $raw_calculation["credit_amount"] = $raw_calculation["credit_amount"]->simplified();

                $raw_calculation = (new RealUnadjustedSummaryCalculation())->fill($raw_calculation);

                return $raw_calculation;
            },
            $keyed_real_raw_unadjusted_summaries
        );

        $keyed_real_raw_adjusted_summaries = array_filter(
            $keyed_real_raw_adjusted_summaries,
            function ($real_raw_adjusted_summary) {
                return $real_raw_adjusted_summary["opened_amount"]->getSign() !== 0
                    || $real_raw_adjusted_summary["closed_amount"]->getSign() !== 0;
            }
        );
        $real_adjusted_summaries = array_map(
            function ($raw_calculation, $account_id) {
                $raw_calculation["opened_amount"] = $raw_calculation["opened_amount"]->simplified();
                $raw_calculation["closed_amount"] = $raw_calculation["closed_amount"]->simplified();

                $raw_calculation = (new RealAdjustedSummaryCalculation())->fill($raw_calculation);

                return $raw_calculation;
            },
            $keyed_real_raw_adjusted_summaries,
            array_keys($keyed_real_raw_adjusted_summaries)
        );

        $real_flows = [];
        foreach ($keyed_real_raw_flows as $cash_flow_activity_id => $real_flows_per_activity) {
            foreach ($real_flows_per_activity as $account_id => $real_flow) {
                if (!$real_flow["net_amount"]->isZero()) {
                    $raw_calculation = (new RealFlowCalculation())->fill([
                        "frozen_account_hash" => $real_flow["frozen_account_hash"],
                        "cash_flow_activity_id" => $cash_flow_activity_id,
                        "net_amount" => $real_flow["net_amount"]->simplified()
                    ]);

                    array_push($real_flows, $raw_calculation);
                }
            }
        }


        $item_calculations = [];
        foreach ($keyed_raw_item_calculations as $account_id => $item_calculations_per_account) {
            foreach ($item_calculations_per_account as $item_calculation) {
                if (!$item_calculation["remaining_quantity"]->isZero()) {
                    $raw_calculation = (new ItemCalculation())->fill([
                        "frozen_account_hash" => $item_calculation["frozen_account_hash"],
                        "financial_entry_id" => $item_calculation["financial_entry_id"],
                        "remaining_cost" => $item_calculation["remaining_cost"]->simplified(),
                        "remaining_quantity" => $item_calculation["remaining_quantity"]
                            ->simplified()
                    ]);

                    array_push($item_calculations, $raw_calculation);
                }
            }
        }

        // Ensure that frozen accounts are used at least once.
        $keyed_frozen_accounts = Resource::key(
            $frozen_accounts,
            fn ($frozen_account) => $frozen_account->hash
        );
        $used_frozen_accounts = [];

        foreach ($real_unadjusted_summaries as $summary_calculation) {
            $frozen_account_hash = $summary_calculation->frozen_account_hash;

            $used_frozen_accounts[$frozen_account_hash]
                = $keyed_frozen_accounts[$frozen_account_hash];
        }

        foreach ($real_adjusted_summaries as $summary_calculation) {
            $frozen_account_hash = $summary_calculation->frozen_account_hash;

            $used_frozen_accounts[$frozen_account_hash]
                = $keyed_frozen_accounts[$frozen_account_hash];
        }

        foreach ($real_flows as $flow_calculation) {
            $frozen_account_hash = $flow_calculation->frozen_account_hash;

            $used_frozen_accounts[$frozen_account_hash]
                = $keyed_frozen_accounts[$frozen_account_hash];
        }

        foreach ($item_calculations as $item_calculation) {
            $frozen_account_hash = $item_calculation->frozen_account_hash;

            $used_frozen_accounts[$frozen_account_hash]
                = $keyed_frozen_accounts[$frozen_account_hash];
        }

        $frozen_accounts = array_values($used_frozen_accounts);

        return [
            $frozen_accounts,
            $real_unadjusted_summaries,
            $real_adjusted_summaries,
            $real_flows,
            $item_calculations
        ];
    }
}
