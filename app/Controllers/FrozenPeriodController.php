<?php

namespace App\Controllers;

use Brick\Math\BigRational;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Validation\Validation;

use App\Casts\ModifierAction;
use App\Contracts\OwnedResource;
use App\Entities\SummaryCalculation;
use App\Exceptions\UnprocessableRequest;
use App\Models\AccountModel;
use App\Models\CashFlowCategoryModel;
use App\Models\CurrencyModel;
use App\Models\FinancialEntryModel;
use App\Models\FrozenPeriodModel;
use App\Models\ModifierModel;
use App\Models\SummaryCalculationModel;

class FrozenPeriodController extends BaseOwnedResourceController
{
    protected static function getIndividualName(): string {
        return "frozen_period";
    }

    protected static function getCollectiveName(): string {
        return "frozen_periods";
    }

    protected static function getModelName(): string {
        return FrozenPeriodModel::class;
    }

    protected static function makeCreateValidation(User $owner): Validation {
        return static::makeValidation();
    }

    protected static function makeUpdateValidation(User $owner, int $resource_id): Validation {
        return static::makeValidation();
    }

    protected static function mustTransactForCreation(): bool {
        return true;
    }

    protected static function enrichResponseDocument(array $initial_document): array {
        $enriched_document = array_merge([], $initial_document);
        $is_single_main_document = isset($initial_document[static::getIndividualName()]);

        if (!$is_single_main_document) {
            return $enriched_document;
        }

        $summary_calculations = model(SummaryCalculationModel::class)
            ->where("frozen_period_id", $initial_document[static::getIndividualName()]->id)
            ->findAll();
        $enriched_document["summary_calculations"] = $summary_calculations;

        $linked_accounts = [];
        foreach ($summary_calculations as $document) {
            $account_id = $document->account_id;
            array_push($linked_accounts, $account_id);
        }

        $exchange_modifiers = model(ModifierModel::class)
            ->where("action", ModifierAction::set(EXCHANGE_MODIFIER_ACTION))
            ->whereIn(
                "id",
                model(FinancialEntryModel::class, false)
                    ->builder()
                    ->select("id")
                    ->where(
                        "transacted_at <=",
                        $initial_document[static::getIndividualName()]->finished_at
                    )
            )
            ->findAll();

        foreach ($exchange_modifiers as $modifier) {
            $debit_account_id = $modifier->debit_account_id;
            $credit_account_id = $modifier->credit_account_id;
            array_push($linked_accounts, $debit_account_id, $credit_account_id);
        }

        // TODO: Find a way to properly select entries by a date range.
        $financial_entries = count($exchange_modifiers) > 0
            ? model(FinancialEntryModel::class)
                ->whereIn("modifier_id", array_map(
                    function ($modifier) {
                        return $modifier->id;
                    },
                    $exchange_modifiers
                ))
                ->orderBy("transacted_at", "DESC")
                ->findAll()
            : [];

        $grouped_financial_entries = count($financial_entries) > 0
            ? static::groupFinancialEntriesByModifier($financial_entries)
            : [];

        $accounts = [];
        if (count($linked_accounts) > 0) {
            $accounts = model(AccountModel::class)
                ->whereIn("id", array_unique($linked_accounts))
                ->findAll();
        }
        $enriched_document["accounts"] = $accounts;

        $currencies = static::getRelatedCurrencies(
            $accounts,
            function ($currency_builder) use ($initial_document) {
                $financial_entry_subquery = model(FinancialEntryModel::class, false)
                    ->builder()
                    ->select("modifier_id")
                    ->where(
                        "transacted_at <=",
                        $initial_document[static::getIndividualName()]->finished_at
                    );
                return $currency_builder
                    ->whereIn(
                        "id",
                        model(AccountModel::class, false)
                            ->builder()
                            ->select("currency_id")
                            ->whereIn(
                                "id",
                                model(ModifierModel::class, false)
                                    ->builder()
                                    ->select("debit_account_id")
                                    ->whereIn("id", $financial_entry_subquery)
                            )
                            ->orWhereIn(
                                "id",
                                model(ModifierModel::class, false)
                                    ->builder()
                                    ->select("credit_account_id")
                                    ->whereIn("id", $financial_entry_subquery)
                            )

                    );
            });
        $enriched_document["currencies"] = $currencies;

        $linked_cash_flow_categories = [];
        foreach ($accounts as $account) {
            $cash_flow_category_id = $account->cash_flow_category_id;
            if (!is_null($cash_flow_category_id)) {
                array_push($linked_cash_flow_categories, $cash_flow_category_id);
            }
        }

        $cash_flow_categories = [];
        if (count($linked_cash_flow_categories) > 0) {
            $cash_flow_categories = model(CashFlowCategoryModel::class)
                ->whereIn("id", array_unique($linked_cash_flow_categories))
                ->findAll();
        }
        $enriched_document["cash_flow_categories"] = $cash_flow_categories;

        $raw_exchange_rates = count($grouped_financial_entries) > 0
            ? static::makeExchangeRates(
                $exchange_modifiers,
                $accounts,
                $grouped_financial_entries
            ) : [];

        $enriched_document["@meta"] = [
            "statements" => static::makeStatements(
                $currencies,
                $cash_flow_categories,
                $accounts,
                $summary_calculations
            ),
            "exchange_rates" => $raw_exchange_rates
        ];

        return $enriched_document;
    }

    protected static function processCreatedDocument(array $created_document): array {
        $main_document = $created_document[static::getIndividualName()];

        [
            $accounts,
            $raw_summary_calculations
        ] = static::calculateValidSummaryCalculations($main_document, true);

        $raw_summary_calculations = array_map(
            function ($raw_summary_calculation) use ($main_document) {
                return array_merge(
                    [ "frozen_period_id" => $main_document["id"] ],
                    $raw_summary_calculation->toArray()
                );
            },
            $raw_summary_calculations
        );

        model(SummaryCalculationModel::class)->insertBatch($raw_summary_calculations);

        return $created_document;
    }

    protected static function calculateValidSummaryCalculations(
        array $main_document,
        bool $must_be_strict
    ): array {
        $financial_entries = model(FinancialEntryModel::class)
            ->where("transacted_at >=", $main_document["started_at"])
            ->where("transacted_at <=", $main_document["finished_at"])
            ->findAll();

        [
            $accounts,
            $raw_summary_calculations,
            $raw_exchange_rates
        ] = static::makeRawSummaryCalculations($financial_entries);
        $keyed_calculations = static::keySummaryCalculationsWithAccounts($raw_summary_calculations);

        if ($must_be_strict) {
            foreach ($accounts as $account) {
                if (
                    $account->kind === EXPENSE_ACCOUNT_KIND
                    || $account->kind === INCOME_ACCOUNT_KIND
                ) {
                    $raw_calculation = $keyed_calculations[$account->id];
                    if (
                        !(
                            $raw_calculation->closed_debit_amount->getSign() === 0
                            && $raw_calculation->closed_debit_amount->getSign() === 0
                        )
                    ) {
                        throw new UnprocessableRequest(
                            "Temporary accounts must be closed first to create the frozen period."
                        );
                    }
                }
            }
        }

        return [
            $accounts,
            $raw_summary_calculations,
            $raw_exchange_rates
        ];
    }

    public function dry_run_create()
    {
        $current_user = auth()->user();
        $controller = $this;
        $validation = $this->makeCreateValidation($current_user);
        return $this
            ->useValidInputsOnly(
                $validation,
                function($request_data) use ($controller, $current_user) {
                    $model = static::getModel();
                    $info = static::prepareRequestData($request_data);
                    [
                        $accounts,
                        $raw_summary_calculations,
                        $raw_exchange_rates
                    ] = static::calculateValidSummaryCalculations(
                        $info,
                        false
                    );

                    $linked_cash_flow_categories = [];
                    foreach ($accounts as $account) {
                        $cash_flow_category_id = $account->cash_flow_category_id;
                        if (!is_null($cash_flow_category_id)) {
                            array_push($linked_cash_flow_categories, $cash_flow_category_id);
                        }
                    }

                    $cash_flow_categories = [];
                    if (count($linked_cash_flow_categories) > 0) {
                        $cash_flow_categories = model(CashFlowCategoryModel::class)
                            ->whereIn("id", array_unique($linked_cash_flow_categories))
                            ->findAll();
                    }

                    $currencies = static::getRelatedCurrencies($accounts);
                    $statements = static::makeStatements(
                        $currencies,
                        $cash_flow_categories,
                        $accounts,
                        $raw_summary_calculations
                    );

                    $response_document = [
                        "@meta" => [
                            "statements" => $statements,
                            "exchange_rates" => $raw_exchange_rates
                        ],
                        static::getIndividualName() => $info,
                        "summary_calculations" => $raw_summary_calculations,
                        "accounts" => $accounts,
                        "currencies" => $currencies,
                        "cash_flow_categories" => $cash_flow_categories
                    ];

                    return $controller->response->setJSON($response_document);
                }
            );
    }

    protected static function prepareRequestData(array $raw_request_data): array {
        $current_user = auth()->user();

        return array_merge(
            [ "user_id" => $current_user->id ],
            $raw_request_data
        );
    }

    private static function makeValidation(): Validation {
        $validation = single_service("validation");
        $individual_name = static::getIndividualName();

        $validation->setRule($individual_name, "frozen period", [
            "required"
        ]);
        $validation->setRule("$individual_name.started_at", "start date", [
            "required",
            "valid_date[".DATE_TIME_STRING_FORMAT."]",
            "must_be_on_before_time_of_other_field[$individual_name.finished_at]"
        ]);
        $validation->setRule("$individual_name.finished_at", "finish date", [
            "required",
            "valid_date[".DATE_TIME_STRING_FORMAT."]",
            "must_be_before_incoming_midnight"
        ]);

        return $validation;
    }

    private static function makeRawSummaryCalculations(array $financial_entries): array {
        $linked_modifiers = [];
        foreach ($financial_entries as $document) {
            $modifier_id = $document->modifier_id;
            array_push($linked_modifiers, $modifier_id);
        }

        $modifiers = [];
        if (count($linked_modifiers) > 0) {
            $modifiers = model(ModifierModel::class)
                ->whereIn("id", array_unique($linked_modifiers))
                ->findAll();
        }

        $linked_accounts = [];
        foreach ($modifiers as $document) {
            $debit_account_id = $document->debit_account_id;
            $credit_account_id = $document->credit_account_id;
            array_push($linked_accounts, $debit_account_id, $credit_account_id);
        }

        $raw_summary_calculations = array_reduce(
            $linked_accounts,
            function ($raw_calculations, $account_id) {
                $raw_calculations[$account_id] = [
                    "account_id" => $account_id,
                    "opened_debit_amount" => BigRational::zero(),
                    "opened_credit_amount" => BigRational::zero(),
                    "unadjusted_debit_amount" => BigRational::zero(),
                    "unadjusted_credit_amount" => BigRational::zero(),
                    "closed_debit_amount" => BigRational::zero(),
                    "closed_credit_amount" => BigRational::zero()
                ];

                return $raw_calculations;
            },
            []
        );

        $first_entry_transacted_time = array_reduce(
            $financial_entries,
            function ($previous_time, $current_entry) {
                $current_time = $current_entry->transacted_at;
                $earlier_time = $previous_time->isBefore($current_time)
                    ? $previous_time
                    : $current_time;

                return $earlier_time;
            },
            Time::now()
        );

        $previous_frozen_period = model(FrozenPeriodModel::class, false)
            ->where("finished_at <", $first_entry_transacted_time)
            ->orderBy("finished_at", "DESC")
            ->first();
        if ($previous_frozen_period) {
            $previous_summary_calculations = model(SummaryCalculationModel::class, false)
                ->where("frozen_period_id", $previous_frozen_period->id)
                ->findAll();

            foreach ($previous_summary_calculations as $previous_summary_calculation) {
                $account_id = $previous_summary_calculation->account_id;

                if (isset($raw_summary_calculations[$account_id])) {
                    $raw_summary_calculations[$account_id]["opened_debit_amount"]
                        = $raw_summary_calculations[$account_id]["opened_debit_amount"]
                            ->plus($previous_summary_calculation->closed_debit_amount);
                    $raw_summary_calculations[$account_id]["opened_credit_amount"]
                        = $raw_summary_calculations[$account_id]["opened_credit_amount"]
                            ->plus($previous_summary_calculation->closed_credit_amount);

                    $raw_summary_calculations[$account_id]["unadjusted_debit_amount"]
                        = $raw_summary_calculations[$account_id]["unadjusted_debit_amount"]
                            ->plus($previous_summary_calculation->closed_debit_amount);
                    $raw_summary_calculations[$account_id]["unadjusted_credit_amount"]
                        = $raw_summary_calculations[$account_id]["unadjusted_credit_amount"]
                            ->plus($previous_summary_calculation->closed_credit_amount);

                    $raw_summary_calculations[$account_id]["closed_debit_amount"]
                        = $raw_summary_calculations[$account_id]["closed_debit_amount"]
                            ->plus($previous_summary_calculation->closed_debit_amount);
                    $raw_summary_calculations[$account_id]["closed_credit_amount"]
                        = $raw_summary_calculations[$account_id]["closed_credit_amount"]
                            ->plus($previous_summary_calculation->closed_credit_amount);
                } else {
                    $raw_summary_calculations[$account_id] = [
                        "account_id" => $account_id,
                        "opened_debit_amount"
                            => $previous_summary_calculation->closed_debit_amount,
                        "opened_credit_amount"
                            => $previous_summary_calculation->closed_credit_amount,
                        "unadjusted_debit_amount"
                            => $previous_summary_calculation->closed_debit_amount,
                        "unadjusted_credit_amount"
                            => $previous_summary_calculation->closed_credit_amount,
                        "closed_debit_amount"
                            => $previous_summary_calculation->closed_debit_amount,
                        "closed_credit_amount"
                            => $previous_summary_calculation->closed_credit_amount
                    ];

                    array_push($linked_accounts, $account_id);
                }
            }
        }

        $accounts = [];
        if (count($linked_accounts) > 0) {
            $accounts = model(AccountModel::class)
                ->whereIn("id", array_unique($linked_accounts))
                ->findAll();
        }

        $grouped_financial_entries = static::groupFinancialEntriesByModifier($financial_entries);

        $raw_summary_calculations = array_reduce(
            $modifiers,
            function ($raw_calculations, $modifier) use ($grouped_financial_entries) {
                $financial_entries = $grouped_financial_entries[$modifier->id];

                foreach ($financial_entries as $financial_entry) {
                    $debit_account_id = $modifier->debit_account_id;
                    $debit_amount = $financial_entry->debit_amount;

                    $credit_account_id = $modifier->credit_account_id;
                    $credit_amount = $financial_entry->credit_amount;

                    if ($modifier->action !== CLOSE_MODIFIER_ACTION) {
                        $raw_calculations[$debit_account_id]["unadjusted_debit_amount"]
                            = $raw_calculations[$debit_account_id]["unadjusted_debit_amount"]
                                ->plus($debit_amount);
                        $raw_calculations[$credit_account_id]["unadjusted_credit_amount"]
                            = $raw_calculations[$credit_account_id]["unadjusted_credit_amount"]
                                ->plus($credit_amount);
                    }

                    $raw_calculations[$debit_account_id]["closed_debit_amount"]
                    = $raw_calculations[$debit_account_id]["closed_debit_amount"]
                        ->plus($debit_amount);
                    $raw_calculations[$credit_account_id]["closed_credit_amount"]
                        = $raw_calculations[$credit_account_id]["closed_credit_amount"]
                            ->plus($credit_amount);
                }

                return $raw_calculations;
            },
            $raw_summary_calculations
        );
        $raw_summary_calculations = array_map(
            function ($raw_calculation) {
                $unadjusted_debit_amount = $raw_calculation["unadjusted_debit_amount"];
                $unadjusted_credit_amount = $raw_calculation["unadjusted_credit_amount"];
                $closed_debit_amount = $raw_calculation["closed_debit_amount"];
                $closed_credit_amount = $raw_calculation["closed_credit_amount"];

                $unadjusted_balance = $unadjusted_debit_amount
                    ->minus($unadjusted_credit_amount)
                    ->simplified();
                $adjusted_balance = $closed_debit_amount
                    ->minus($closed_credit_amount)
                    ->simplified();

                $is_unadjusted_balance_positive = $unadjusted_balance->getSign() > 0;
                $is_adjusted_balance_positive = $adjusted_balance->getSign() > 0;
                $is_unadjusted_balance_negative = $unadjusted_balance->getSign() < 0;
                $is_adjusted_balance_negative = $adjusted_balance->getSign() < 0;

                $raw_calculation["unadjusted_debit_amount"] = $is_unadjusted_balance_positive
                    ? $unadjusted_balance
                    : BigRational::zero();
                $raw_calculation["unadjusted_credit_amount"] = $is_unadjusted_balance_negative
                    ? $unadjusted_balance->negated()
                    : BigRational::zero();
                $raw_calculation["closed_debit_amount"] = $is_adjusted_balance_positive
                    ? $adjusted_balance
                    : BigRational::zero();
                $raw_calculation["closed_credit_amount"] = $is_adjusted_balance_negative
                    ? $adjusted_balance->negated()
                    : BigRational::zero();
                $raw_calculation = (new SummaryCalculation())->fill($raw_calculation);

                return $raw_calculation;
            },
            array_values($raw_summary_calculations)
        );

        $raw_summary_calculations = array_filter(
            $raw_summary_calculations,
            function ($raw_summary_calculation) {
                return $raw_summary_calculation->unadjusted_debit_amount->getSign() !== 0
                    || $raw_summary_calculation->unadjusted_credit_amount->getSign() !== 0
                    || $raw_summary_calculation->closed_debit_amount->getSign() !== 0
                    || $raw_summary_calculation->closed_credit_amount->getSign() !== 0;
            }
        );
        $retained_accounts_on_summary_calculations = array_map(
            function ($raw_summary_calculation) {
                return $raw_summary_calculation->account_id;
            },
            $raw_summary_calculations
        );

        $exchange_modifiers = array_filter(
            $modifiers,
            function ($modifier) {
                return $modifier->action === EXCHANGE_MODIFIER_ACTION;
            }
        );
        $raw_exchange_rates = static::makeExchangeRates(
            $exchange_modifiers,
            $accounts,
            $grouped_financial_entries
        );

        $accounts = array_filter(
            $accounts,
            function ($account) use ($retained_accounts_on_summary_calculations) {
                return in_array($account->id, $retained_accounts_on_summary_calculations);
            }
        );

        return [
            array_values($accounts),
            array_values($raw_summary_calculations),
            $raw_exchange_rates
        ];
    }

    private static function makeExchangeRates(
        array $exchange_modifiers,
        array $accounts,
        array $grouped_financial_entries
    ): array {
        $exchange_modifiers = array_map(
            function ($exchange_modifier) use ($accounts) {
                $debit_account = array_values(array_filter(
                    $accounts,
                    function ($account) use ($exchange_modifier) {
                        return $account->id === $exchange_modifier->debit_account_id;
                    }
                ))[0];
                $credit_account = array_values(array_filter(
                    $accounts,
                    function ($account) use ($exchange_modifier) {
                        return $account->id === $exchange_modifier->credit_account_id;
                    }
                ))[0];

                return [
                    "id" => $exchange_modifier->id,
                    "debit_account" => $debit_account,
                    "credit_account" => $credit_account
                ];
            },
            $exchange_modifiers
        );
        $raw_exchange_entries = array_reduce(
            $exchange_modifiers,
            function ($raw_entries, $modifier) use ($grouped_financial_entries) {
                $financial_entries = $grouped_financial_entries[$modifier["id"]];

                foreach ($financial_entries as $financial_entry) {
                    if (isset($raw_entries[$modifier["id"]])) {
                        if (
                            $financial_entry
                            ->updated_at
                            ->isAfter($raw_entries[$modifier["id"]]->updated_at)
                        ) {
                            $raw_entries[$modifier["id"]] = $financial_entry;
                        }
                    } else {
                        $raw_entries[$modifier["id"]] = $financial_entry;
                    }
                }

                return $raw_entries;
            },
            []
        );
        $raw_exchange_rates = array_reduce(
            $exchange_modifiers,
            function ($raw_exchanges, $modifier) use ($raw_exchange_entries) {
                $financial_entry = $raw_exchange_entries[$modifier["id"]];
                $debit_account = $modifier["debit_account"];
                $credit_account = $modifier["credit_account"];
                $may_use_debit_account_as_destination = $debit_account->kind === ASSET_ACCOUNT_KIND
                    || $debit_account->kind === EXPENSE_ACCOUNT_KIND;
                $debit_currency_id = $debit_account->currency_id;
                $credit_currency_id = $credit_account->currency_id;
                $debit_value = $financial_entry->debit_amount;
                $credit_value = $financial_entry->credit_amount;

                $source_currency_id = $may_use_debit_account_as_destination
                    ? $credit_currency_id
                    : $debit_currency_id;
                $destination_currency_id = $may_use_debit_account_as_destination
                    ? $debit_currency_id
                    : $credit_currency_id;

                $exchange_id = $source_currency_id."_".$destination_currency_id;

                $source_value = $may_use_debit_account_as_destination
                    ? $credit_value
                    : $debit_value;
                $destination_value = $may_use_debit_account_as_destination
                    ? $debit_value
                    : $credit_value;

                if (isset($raw_exchanges[$exchange_id])) {
                    if (
                        $financial_entry
                        ->updated_at
                        ->isAfter($raw_exchanges[$exchange_id]["updated_at"])
                    ) {
                        $raw_exchanges[$exchange_id]["source"]["value"] = $source_value;
                        $raw_exchanges[$exchange_id]["destination"]["value"] = $destination_value;
                    }
                } else {
                    $raw_exchanges[$exchange_id] = [
                        "source" => [
                            "currency_id" => $source_currency_id,
                            "value" => $source_value
                        ],
                        "destination" => [
                            "currency_id" => $destination_currency_id,
                            "value" => $destination_value
                        ],
                        "updated_at" => $financial_entry->updated_at->toDateTimeString()
                    ];
                }

                return $raw_exchanges;
            },
            []
        );
        $raw_exchange_rates = array_values($raw_exchange_rates);

        return $raw_exchange_rates;
    }

    private static function makeStatements(
        $currencies,
        $cash_flow_categories,
        $accounts,
        $summary_calculations
    ): array {
        $keyed_calculations = static::keySummaryCalculationsWithAccounts($summary_calculations);
        $accounts = array_filter(
            $accounts,
            function ($account) use ($keyed_calculations) {
                return isset($keyed_calculations[$account->id]);
            }
        );

        $grouped_summaries = array_reduce(
            $accounts,
            function ($groups, $account) use ($keyed_calculations) {
                if (!isset($groups[$account->currency_id])) {
                    $groups[$account->currency_id] = array_fill_keys(
                        [ ...ACCEPTABLE_ACCOUNT_KINDS ],
                        []
                    );
                }

                array_push(
                    $groups[$account->currency_id][$account->kind],
                    $keyed_calculations[$account->id]
                );

                return $groups;
            },
            []
        );

        $keyed_categories = array_reduce(
            $cash_flow_categories,
            function ($groups, $category) {
                $groups[$category->id] = $category;

                return $groups;
            },
            []
        );
        $categorized_summaries = array_reduce(
            $accounts,
            function ($categories, $account) use ($keyed_categories, $keyed_calculations) {
                $currency_id = $account->currency_id;
                if (is_null($account->cash_flow_category_id)) return $categories;

                if (!isset($categories[$currency_id])) {
                    $categories[$currency_id] = [];
                }

                $category = $keyed_categories[$account->cash_flow_category_id];

                if (!isset($categories[$currency_id][$category->kind])) {
                    $categories[$currency_id][$category->kind]= array_fill_keys(
                        [ ...ACCEPTABLE_ACCOUNT_KINDS ],
                        []
                    );
                }

                array_push(
                    $categories[$currency_id][$category->kind][$account->kind],
                    $keyed_calculations[$account->id]
                );

                return $categories;
            },
            []
        );

        $statements = array_reduce(
            $currencies,
            function ($statements, $currency) use ($grouped_summaries, $categorized_summaries) {
                if (!isset($grouped_summaries[$currency->id])) {
                    // Include currencies only used in statements
                    return $statements;
                }

                // Compute for income statement and balance sheet
                $summaries = $grouped_summaries[$currency->id];

                $unadjusted_total_income = array_reduce(
                    $summaries[INCOME_ACCOUNT_KIND],
                    function ($previous_total, $summary) {
                        return $previous_total
                            ->plus($summary->unadjusted_credit_amount)
                            ->minus($summary->unadjusted_debit_amount);
                    },
                    BigRational::zero()
                );
                $unadjusted_total_expenses = array_reduce(
                    $summaries[EXPENSE_ACCOUNT_KIND],
                    function ($previous_total, $summary) {
                        return $previous_total
                            ->plus($summary->unadjusted_debit_amount)
                            ->minus($summary->unadjusted_credit_amount);
                    },
                    BigRational::zero()
                );
                $unadjusted_total_assets = array_reduce(
                    $summaries[ASSET_ACCOUNT_KIND],
                    function ($previous_total, $summary) {
                        return $previous_total
                            ->plus($summary->unadjusted_debit_amount)
                            ->minus($summary->unadjusted_credit_amount);
                    },
                    BigRational::zero()
                );
                $unadjusted_total_liabilities = array_reduce(
                    $summaries[LIABILITY_ACCOUNT_KIND],
                    function ($previous_total, $summary) {
                        return $previous_total
                            ->plus($summary->unadjusted_credit_amount)
                            ->minus($summary->unadjusted_debit_amount);
                    },
                    BigRational::zero()
                );
                $unadjusted_total_equities = array_reduce(
                    $summaries[EQUITY_ACCOUNT_KIND],
                    function ($previous_total, $summary) {
                        return $previous_total
                            ->plus($summary->unadjusted_credit_amount)
                            ->minus($summary->unadjusted_debit_amount);
                    },
                    BigRational::zero()
                );

                $adjusted_total_income = array_reduce(
                    $summaries[INCOME_ACCOUNT_KIND],
                    function ($previous_total, $summary) {
                        return $previous_total
                            ->plus($summary->closed_credit_amount)
                            ->minus($summary->closed_debit_amount);
                    },
                    BigRational::zero()
                );
                $adjusted_total_expenses = array_reduce(
                    $summaries[EXPENSE_ACCOUNT_KIND],
                    function ($previous_total, $summary) {
                        return $previous_total
                            ->plus($summary->closed_debit_amount)
                            ->minus($summary->closed_credit_amount);
                    },
                    BigRational::zero()
                );
                $adjusted_total_assets = array_reduce(
                    $summaries[ASSET_ACCOUNT_KIND],
                    function ($previous_total, $summary) {
                        return $previous_total
                            ->plus($summary->closed_debit_amount)
                            ->minus($summary->closed_credit_amount);
                    },
                    BigRational::zero()
                );
                $adjusted_total_liabilities = array_reduce(
                    $summaries[LIABILITY_ACCOUNT_KIND],
                    function ($previous_total, $summary) {
                        return $previous_total
                            ->plus($summary->closed_credit_amount)
                            ->minus($summary->closed_debit_amount);
                    },
                    BigRational::zero()
                );
                $adjusted_total_equities = array_reduce(
                    $summaries[EQUITY_ACCOUNT_KIND],
                    function ($previous_total, $summary) {
                        return $previous_total
                            ->plus($summary->closed_credit_amount)
                            ->minus($summary->closed_debit_amount);
                    },
                    BigRational::zero()
                );

                $unadjusted_trial_balance_debit_total = $unadjusted_total_expenses
                    ->plus($unadjusted_total_assets);
                $unadjusted_trial_balance_credit_total = $unadjusted_total_equities
                    ->plus($unadjusted_total_liabilities)
                    ->plus($unadjusted_total_income);
                $income_statement_total = $unadjusted_total_income
                    ->minus($unadjusted_total_expenses);
                $adjusted_trial_balance_debit_total = $adjusted_total_expenses
                    ->plus($adjusted_total_assets);
                $adjusted_trial_balance_credit_total = $adjusted_total_equities
                    ->plus($adjusted_total_liabilities)
                    ->plus($adjusted_total_income);

                $opened_liquid_amount = BigRational::zero();
                $closed_liquid_amount = BigRational::zero();

                // Compute for cash flow statement
                if (isset($categorized_summaries[$currency->id])) {
                    $summaries = $categorized_summaries[$currency->id];
                    $liquid_cash_flow_category_subtotals = array_map(
                        function ($summary) {
                            return $summary->opened_debit_amount
                                ->minus($summary->opened_credit_amount);
                        },
                        $summaries[LIQUID_CASH_FLOW_CATEGORY_KIND][ASSET_ACCOUNT_KIND]
                    );

                    $opened_liquid_amount = array_reduce(
                        $liquid_cash_flow_category_subtotals,
                        function ($previous_total, $calculation) {
                            return $previous_total->plus($calculation);
                        },
                        $opened_liquid_amount
                    );

                    $illiquid_summaries = $summaries[ILLIQUID_CASH_FLOW_CATEGORY_KIND];
                    $illiquid_cash_flow_category_subtotals = array_merge(
                        array_map(
                            function ($summary) {
                                return $summary->opened_debit_amount
                                    ->minus($summary->closed_debit_amount)
                                    ->plus($summary->closed_credit_amount)
                                    ->minus($summary->opened_credit_amount);
                            },
                            array_merge(
                                $illiquid_summaries[ASSET_ACCOUNT_KIND],
                                $illiquid_summaries[LIABILITY_ACCOUNT_KIND]
                            )
                        ),
                        array_map(
                            function ($summary) {
                                return $summary->unadjusted_credit_amount
                                    ->minus($summary->opened_credit_amount)
                                    ->minus($summary->unadjusted_debit_amount)
                                    ->plus($summary->opened_debit_amount);
                            },
                            $illiquid_summaries[EQUITY_ACCOUNT_KIND]
                        )
                    );

                    $closed_liquid_amount = array_reduce(
                        $illiquid_cash_flow_category_subtotals,
                        function ($previous_total, $calculation) {
                            return $previous_total->plus($calculation);
                        },
                        $opened_liquid_amount->plus($income_statement_total)
                    );
                }

                array_push($statements, [
                    "currency_id" => $currency->id,
                    "unadjusted_trial_balance" => [
                        "debit_total" => $unadjusted_trial_balance_debit_total->simplified(),
                        "credit_total" => $unadjusted_trial_balance_credit_total->simplified()
                    ],
                    "income_statement" => [
                        "net_total" => $income_statement_total->simplified()
                    ],
                    "balance_sheet" => [
                        "total_assets" => $unadjusted_total_assets->simplified(),
                        "total_liabilities" => $unadjusted_total_liabilities->simplified(),
                        "total_equities" => $unadjusted_total_equities
                            ->plus($income_statement_total)
                            ->simplified()
                    ],
                    "cash_flow_statement" => [
                        "opened_liquid_amount" => $opened_liquid_amount->simplified(),
                        "closed_liquid_amount" => $closed_liquid_amount->simplified()
                    ],
                    "adjusted_trial_balance" => [
                        "debit_total" => $adjusted_trial_balance_debit_total->simplified(),
                        "credit_total" => $adjusted_trial_balance_credit_total->simplified()
                    ]
                ]);

                return $statements;
            },
            []
        );

        return $statements;
    }

    private static function getRelatedCurrencies(
        array $accounts,
        ?callable $currency_modifier = null
    ): array {
        $linked_currencies = [];
        foreach ($accounts as $document) {
            $currency_id = $document->currency_id;
            array_push($linked_currencies, $currency_id);
        }

        $currencies = [];
        if (count($linked_currencies) > 0) {
            $currencies = model(CurrencyModel::class)
                ->whereIn("id", array_unique($linked_currencies));

            if (is_callable($currency_modifier)) {
                $currencies = $currency_modifier($currencies);
            }

            return $currencies->findAll();
        }

        return $currencies;
    }

    private static function groupFinancialEntriesByModifier(array $financial_entries): array {
        $grouped_financial_entries = array_reduce(
            $financial_entries,
            function ($groups, $entry) {
                if (!isset($groups[$entry->modifier_id])) {
                    $groups[$entry->modifier_id] = [];
                }

                array_push($groups[$entry->modifier_id], $entry);

                return $groups;
            },
            []
        );

        return $grouped_financial_entries;
    }

    private static function keySummaryCalculationsWithAccounts(array $summary_calculations): array {
        $keyed_calculations = array_reduce(
            $summary_calculations,
            function ($keyed_collection, $summary) {
                $keyed_collection[$summary->account_id] = $summary;

                return $keyed_collection;
            },
            []
        );

        return $keyed_calculations;
    }
}
