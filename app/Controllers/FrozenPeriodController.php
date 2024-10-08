<?php

namespace App\Controllers;

use Brick\Math\BigRational;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Validation\Validation;

use App\Casts\ModifierAction;
use App\Casts\RationalNumber;
use App\Contracts\OwnedResource;
use App\Entities\FlowCalculation;
use App\Entities\SummaryCalculation;
use App\Exceptions\UnprocessableRequest;
use App\Models\AccountModel;
use App\Models\CashFlowActivityModel;
use App\Models\CurrencyModel;
use App\Models\FinancialEntryModel;
use App\Models\FlowCalculationModel;
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

        $flow_calculations = model(FlowCalculationModel::class)
            ->where("frozen_period_id", $initial_document[static::getIndividualName()]->id)
            ->findAll();
        $enriched_document["flow_calculations"] = $flow_calculations;

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
                    ->select("modifier_id")
                    ->where(
                        "transacted_at <=",
                        $initial_document[static::getIndividualName()]->finished_at
                    )
            )
            ->withDeleted()
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
                ->withDeleted()
                ->findAll()
            : [];

        $grouped_financial_entries = count($financial_entries) > 0
            ? static::groupFinancialEntriesByModifier($financial_entries)
            : [];

        $accounts = [];
        if (count($linked_accounts) > 0) {
            $accounts = model(AccountModel::class)
                ->whereIn("id", array_unique($linked_accounts))
                ->withDeleted()
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

        $linked_cash_flow_activities = [];
        foreach ($flow_calculations as $document) {
            $cash_flow_activity_id = $document->cash_flow_activity_id;
            array_push($linked_cash_flow_activities, $cash_flow_activity_id);
        }

        $cash_flow_activities = [];
        if (count($linked_cash_flow_activities) > 0) {
            $cash_flow_activities = model(CashFlowActivityModel::class)
                ->whereIn("id", array_unique($linked_cash_flow_activities))
                ->withDeleted()
                ->findAll();
        }
        $enriched_document["cash_flow_activities"] = $cash_flow_activities;

        $raw_exchange_rates = count($grouped_financial_entries) > 0
            ? static::makeExchangeRates(
                $exchange_modifiers,
                $accounts,
                $grouped_financial_entries
            ) : [];

        $enriched_document["@meta"] = [
            "statements" => static::makeStatements(
                $currencies,
                $cash_flow_activities,
                $accounts,
                $summary_calculations,
                $flow_calculations
            ),
            "exchange_rates" => $raw_exchange_rates
        ];

        return $enriched_document;
    }

    protected static function processCreatedDocument(array $created_document): array {
        $main_document = $created_document[static::getIndividualName()];

        [
            $cash_flow_activities,
            $accounts,
            $raw_summary_calculations,
            $raw_flow_calculations
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

        $raw_flow_calculations = array_map(
            function ($raw_flow_calculation) use ($main_document) {
                return array_merge(
                    [ "frozen_period_id" => $main_document["id"] ],
                    $raw_flow_calculation->toArray()
                );
            },
            $raw_flow_calculations
        );

        model(FlowCalculationModel::class)->insertBatch($raw_flow_calculations);

        return $created_document;
    }

    protected static function calculateValidSummaryCalculations(
        array $main_document,
        bool $must_be_strict
    ): array {
        $financial_entries = model(FinancialEntryModel::class)
            ->where("transacted_at >=", $main_document["started_at"])
            ->where("transacted_at <=", $main_document["finished_at"])
            ->withDeleted()
            ->findAll();

        [
            $cash_flow_activities,
            $accounts,
            $raw_summary_calculations,
            $raw_flow_calculations,
            $raw_exchange_rates
        ] = static::makeRawCalculations($financial_entries);
        $keyed_calculations = static::keyCalculationsWithAccounts($raw_summary_calculations);

        if ($must_be_strict) {
            foreach ($accounts as $account) {
                if (
                    (
                        $account->kind === EXPENSE_ACCOUNT_KIND
                        || $account->kind === INCOME_ACCOUNT_KIND
                    )
                    // Some accounts are temporary and exist only for closing other accounts.
                    // Therefore, they would not have any summary calculations.
                    && isset($keyed_calculations[$account->id])
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
            $cash_flow_activities,
            $accounts,
            $raw_summary_calculations,
            $raw_flow_calculations,
            $raw_exchange_rates
        ];
    }

    public function dry_run_create()
    {
        helper("auth");

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
                        $cash_flow_activities,
                        $accounts,
                        $raw_summary_calculations,
                        $raw_flow_calculations,
                        $raw_exchange_rates
                    ] = static::calculateValidSummaryCalculations(
                        $info,
                        false
                    );

                    $currencies = static::getRelatedCurrencies($accounts);
                    $statements = static::makeStatements(
                        $currencies,
                        $cash_flow_activities,
                        $accounts,
                        $raw_summary_calculations,
                        $raw_flow_calculations
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
                        "cash_flow_activities" => $cash_flow_activities,
                        "flow_calculations" => $raw_flow_calculations
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

    private static function makeRawCalculations(array $financial_entries): array {
        $linked_modifiers = [];
        foreach ($financial_entries as $document) {
            $modifier_id = $document->modifier_id;
            array_push($linked_modifiers, $modifier_id);
        }

        $modifiers = [];
        if (count($linked_modifiers) > 0) {
            $modifiers = model(ModifierModel::class)
                ->whereIn("id", array_unique($linked_modifiers))
                ->withDeleted()
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

        $linked_cash_flow_activities = [];
        foreach ($modifiers as $document) {
            $debit_cash_flow_activity_id = $document->debit_cash_flow_activity_id;
            $credit_cash_flow_activity_id = $document->credit_cash_flow_activity_id;
            array_push(
                $linked_cash_flow_activities,
                $debit_cash_flow_activity_id,
                $credit_cash_flow_activity_id
            );
        }

        $cash_flow_activities = [];
        if (count($linked_cash_flow_activities) > 0) {
            $cash_flow_activities = model(CashFlowActivityModel::class)
                ->whereIn("id", array_unique($linked_cash_flow_activities))
                ->withDeleted()
                ->findAll();
        }
        $keyed_cash_flow_activities = array_reduce(
            $cash_flow_activities,
            function ($keyed_items, $cash_flow_activity) {
                $keyed_items[$cash_flow_activity->id] = $cash_flow_activity;

                return $keyed_items;
            },
            []
        );

        $raw_flow_calculations = array_reduce(
            $modifiers,
            function ($raw_calculations, $modifier) {
                if ($modifier->debit_cash_flow_activity_id !== null) {
                    $activity_id = $modifier->debit_cash_flow_activity_id;
                    $account_id = $modifier->debit_account_id;

                    if (!isset($raw_calculations[$activity_id])) {
                        $raw_calculations[$activity_id] = [];
                    }

                    $raw_calculations[$activity_id][$account_id] = [
                        "cash_flow_activity_id" => $activity_id,
                        "account_id" => $account_id,
                        "net_amount" => BigRational::zero()
                    ];
                }

                if ($modifier->credit_cash_flow_activity_id !== null) {
                    $activity_id = $modifier->credit_cash_flow_activity_id;
                    $account_id = $modifier->credit_account_id;

                    if (!isset($raw_calculations[$activity_id])) {
                        $raw_calculations[$activity_id] = [];
                    }

                    $raw_calculations[$activity_id][$account_id] = [
                        "cash_flow_activity_id" => $activity_id,
                        "account_id" => $account_id,
                        "net_amount" => BigRational::zero()
                    ];
                }

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

        $last_entry_transacted_time = array_reduce(
            $financial_entries,
            function ($previous_time, $current_entry) {
                $current_time = $current_entry->transacted_at;
                $later_time = $previous_time->isAfter($current_time)
                    ? $previous_time
                    : $current_time;

                return $later_time;
            },
            $first_entry_transacted_time
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
                ->withDeleted()
                ->findAll();
        }
        $keyed_accounts = array_reduce(
            $accounts,
            function ($keyed_items, $account) {
                $keyed_items[$account->id] = $account;

                return $keyed_items;
            },
            []
        );

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
        $raw_flow_calculations = array_reduce(
            $modifiers,
            function ($raw_calculations, $modifier) use (
                $keyed_cash_flow_activities,
                $keyed_accounts,
                $grouped_financial_entries
            ) {
                $financial_entries = $grouped_financial_entries[$modifier->id];

                foreach ($financial_entries as $financial_entry) {
                    $debit_account_id = $modifier->debit_account_id;
                    $debit_activity_id = $modifier->debit_cash_flow_activity_id;
                    $debit_amount = $financial_entry->debit_amount;

                    $credit_activity_id = $modifier->credit_cash_flow_activity_id;
                    $credit_account_id = $modifier->credit_account_id;
                    $credit_amount = $financial_entry->credit_amount;

                    if ($modifier->action === CLOSE_MODIFIER_ACTION) continue;

                    if ($debit_activity_id !== null) {
                        $debit_flow_activity = $keyed_cash_flow_activities[$debit_activity_id];

                        $raw_calculations[$debit_activity_id][$debit_account_id]["net_amount"]
                            = $raw_calculations[$debit_activity_id][$debit_account_id]["net_amount"]
                                ->minus($debit_amount);
                    }

                    if ($credit_activity_id !== null) {
                        $credit_flow_activity = $keyed_cash_flow_activities[$credit_activity_id];
                        $raw_calculations[$credit_activity_id][$credit_account_id]["net_amount"]
                            = $raw_calculations
                                [$credit_activity_id][$credit_account_id]["net_amount"]
                                ->plus($credit_amount);
                    }
                }

                return $raw_calculations;
            },
            $raw_flow_calculations
        );
        $raw_summary_calculations = array_map(
            function ($raw_calculation) {
                $closed_debit_amount = $raw_calculation["closed_debit_amount"];
                $closed_credit_amount = $raw_calculation["closed_credit_amount"];

                $adjusted_balance = $closed_debit_amount
                    ->minus($closed_credit_amount)
                    ->simplified();

                $is_adjusted_balance_positive = $adjusted_balance->getSign() > 0;
                $is_adjusted_balance_negative = $adjusted_balance->getSign() < 0;

                $raw_calculation["opened_debit_amount"]
                    = $raw_calculation["opened_debit_amount"]->simplified();
                $raw_calculation["opened_credit_amount"]
                    = $raw_calculation["opened_credit_amount"]->simplified();
                $raw_calculation["unadjusted_debit_amount"]
                    = $raw_calculation["unadjusted_debit_amount"]->simplified();
                $raw_calculation["unadjusted_credit_amount"]
                    = $raw_calculation["unadjusted_credit_amount"]->simplified();
                $raw_calculation["closed_debit_amount"] = $is_adjusted_balance_positive
                    ? $adjusted_balance->simplified()
                    : BigRational::zero();
                $raw_calculation["closed_credit_amount"] = $is_adjusted_balance_negative
                    ? $adjusted_balance->negated()->simplified()
                    : BigRational::zero();
                $raw_calculation = (new SummaryCalculation())->fill($raw_calculation);

                return $raw_calculation;
            },
            array_values($raw_summary_calculations)
        );
        $raw_flow_calculations = array_merge(
            ...array_map(
                function ($raw_calculations_per_account) {
                    return array_map(
                        function ($raw_calculation) {
                            $raw_calculation["net_amount"] = $raw_calculation["net_amount"]
                                ->simplified();
                            return (new FlowCalculation())->fill($raw_calculation);
                        },
                        array_values($raw_calculations_per_account)
                    );
                },
                array_values($raw_flow_calculations)
            )
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

        $raw_flow_calculations = array_filter(
            $raw_flow_calculations,
            function ($raw_flow_calculation) {
                return $raw_flow_calculation->net_amount->getSign() !== 0;
            }
        );
        $retained_accounts_on_flow_calculations = array_map(
            function ($raw_flow_calculation) {
                return $raw_flow_calculation->account_id;
            },
            $raw_flow_calculations
        );
        $retained_accounts_on_calculations = array_unique(array_merge(
            $retained_accounts_on_summary_calculations,
            $retained_accounts_on_flow_calculations
        ));

        $exchange_modifiers = model(ModifierModel::class)
            ->where("action", ModifierAction::set(EXCHANGE_MODIFIER_ACTION))
            ->whereIn(
                "id",
                model(FinancialEntryModel::class, false)
                    ->builder()
                    ->select("modifier_id")
                    ->where(
                        "transacted_at <=",
                        $last_entry_transacted_time
                    )
            )
            ->withDeleted()
            ->findAll();

        $linked_exchange_accounts = [];
        foreach ($exchange_modifiers as $modifier) {
            $debit_account_id = $modifier->debit_account_id;
            $credit_account_id = $modifier->credit_account_id;
            array_push($linked_exchange_accounts, $debit_account_id, $credit_account_id);
        }

        $exchange_accounts = [];
        if (count($linked_exchange_accounts) > 0) {
            $exchange_accounts = model(AccountModel::class)
                ->whereIn("id", array_unique($linked_exchange_accounts))
                ->withDeleted()
                ->findAll();
        }

        $financial_entries = count($exchange_modifiers) > 0
            ? model(FinancialEntryModel::class)
                ->whereIn("modifier_id", array_map(
                    function ($modifier) {
                        return $modifier->id;
                    },
                    $exchange_modifiers
                ))
                ->orderBy("transacted_at", "DESC")
                ->withDeleted()
                ->findAll()
            : [];

        $grouped_financial_entries = count($financial_entries) > 0
            ? static::groupFinancialEntriesByModifier($financial_entries)
            : [];

        $raw_exchange_rates = count($grouped_financial_entries) > 0
            ? static::makeExchangeRates(
                $exchange_modifiers,
                $exchange_accounts,
                $grouped_financial_entries
            ) : [];

        $accounts = array_filter(
            $accounts,
            function ($account) use ($retained_accounts_on_calculations) {
                return in_array($account->id, $retained_accounts_on_calculations);
            }
        );

        return [
            array_values($cash_flow_activities),
            array_values($accounts),
            array_values($raw_summary_calculations),
            array_values($raw_flow_calculations),
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
                $may_use_debit_account_as_destination
                    = $debit_account->kind === GENERAL_ASSET_ACCOUNT_KIND
                        || $debit_account->kind === LIQUID_ASSET_ACCOUNT_KIND
                        || $debit_account->kind === DEPRECIATIVE_ASSET_ACCOUNT_KIND
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
        $raw_exchange_rates = array_map(
            function ($raw_exchange_rate) {
                $source = RationalNumber::get($raw_exchange_rate["source"]["value"]);
                $destination = RationalNumber::get($raw_exchange_rate["destination"]["value"]);
                $rate = $destination->dividedBy($source)->simplified();

                $raw_exchange_rate["source"]["value"] = $rate->getDenominator();
                $raw_exchange_rate["destination"]["value"] = $rate->getNumerator();
                return $raw_exchange_rate;
            },
            $raw_exchange_rates
        );

        return $raw_exchange_rates;
    }

    private static function makeStatements(
        $currencies,
        $cash_flow_activities,
        $accounts,
        $summary_calculations,
        $flow_calculations
    ): array {
        $keyed_summary_calculations = static::keyCalculationsWithAccounts($summary_calculations);
        $keyed_accounts = array_reduce(
            $accounts,
            function ($keyed_items, $account) {
                $keyed_items[$account->id] = $account;

                return $keyed_items;
            },
            []
        );
        $keyed_cash_flow_activities = array_reduce(
            $cash_flow_activities,
            function ($keyed_items, $cash_flow_activity) {
                $keyed_items[$cash_flow_activity->id] = $cash_flow_activity;

                return $keyed_items;
            },
            []
        );

        $grouped_summary_calculation = array_reduce(
            $accounts,
            function ($groups, $account) use ($keyed_summary_calculations) {
                if (!isset($groups[$account->currency_id])) {
                    $groups[$account->currency_id] = array_fill_keys(
                        [ ...ACCEPTABLE_ACCOUNT_KINDS ],
                        []
                    );
                }

                // Some accounts are temporary and exist only for closing other accounts.
                // Therefore, they would not have any summary calculations.
                if (isset($keyed_summary_calculations[$account->id])) {
                    array_push(
                        $groups[$account->currency_id][$account->kind],
                        $keyed_summary_calculations[$account->id]
                    );
                }

                return $groups;
            },
            []
        );
        $grouped_flow_calculation = array_reduce(
            $flow_calculations,
            function ($groups, $calculation) use ($keyed_accounts) {
                $account = $keyed_accounts[$calculation->account_id];

                if (!isset($groups[$account->currency_id])) {
                    $groups[$account->currency_id] = [];
                }

                if (!isset($groups[$account->currency_id][$calculation->cash_flow_activity_id])) {
                    $groups[$account->currency_id][$calculation->cash_flow_activity_id] = [];
                }

                array_push(
                    $groups[$account->currency_id][$calculation->cash_flow_activity_id],
                    $calculation
                );

                return $groups;
            },
            []
        );

        $statements = array_reduce(
            $currencies,
            function ($statements, $currency) use (
                $keyed_cash_flow_activities,
                $keyed_accounts,
                $keyed_summary_calculations,
                $grouped_summary_calculation,
                $grouped_flow_calculation,
            ) {
                if (!isset($grouped_summary_calculation[$currency->id])) {
                    // Include currencies only used in statements
                    return $statements;
                }

                // Compute for income statement and balance sheet
                $summaries = $grouped_summary_calculation[$currency->id];

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
                    array_merge(
                        $summaries[GENERAL_ASSET_ACCOUNT_KIND],
                        $summaries[LIQUID_ASSET_ACCOUNT_KIND],
                        $summaries[DEPRECIATIVE_ASSET_ACCOUNT_KIND]
                    ),
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
                    array_merge(
                        $summaries[GENERAL_ASSET_ACCOUNT_KIND],
                        $summaries[LIQUID_ASSET_ACCOUNT_KIND],
                        $summaries[DEPRECIATIVE_ASSET_ACCOUNT_KIND]
                    ),
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

                // Compute for cash flow statement
                $opened_liquid_amount = array_reduce(
                    $summaries[LIQUID_ASSET_ACCOUNT_KIND],
                    function ($previous_total, $summary) {
                        return $previous_total->plus($summary->opened_debit_amount);
                    },
                    BigRational::zero()
                );
                $closed_liquid_amount = $opened_liquid_amount;
                $illiquid_cash_flow_activity_subtotals = [];

                if (isset($grouped_flow_calculation[$currency->id])) {
                    $categorized_flows = $grouped_flow_calculation[$currency->id];

                    foreach ($categorized_flows as $cash_flow_activity_id => $flows) {
                        $activity = $keyed_cash_flow_activities[$cash_flow_activity_id];

                        if (!isset($illiquid_cash_flow_activity_subtotals[$activity->id])) {
                            $illiquid_cash_flow_activity_subtotals[$activity->id] = [
                                "cash_flow_activity_id" => $activity->id,
                                "net_income" => BigRational::zero(),
                                "subtotal" => BigRational::zero()
                            ];
                        }

                        foreach ($flows as $flow_info) {
                            $account = $keyed_accounts[$flow_info->account_id];

                            $closed_liquid_amount = $closed_liquid_amount
                                ->plus($flow_info->net_amount);

                            $illiquid_cash_flow_activity_subtotals[$activity->id]["subtotal"]
                                = $illiquid_cash_flow_activity_subtotals[$activity->id]["subtotal"]
                                    ->plus($flow_info->net_amount);

                            if (
                                !(
                                    $account->kind === EXPENSE_ACCOUNT_KIND
                                    || $account->kind === INCOME_ACCOUNT_KIND
                                )
                            ) continue;

                            $illiquid_cash_flow_activity_subtotals[$activity->id]["net_income"]
                                = $illiquid_cash_flow_activity_subtotals[$activity->id]["net_income"]
                                    ->plus($flow_info->net_amount);
                        }
                    }

                    $illiquid_cash_flow_activity_subtotals = array_map(
                        function ($subtotal_info) {
                            return array_merge($subtotal_info, [
                                "net_income" => $subtotal_info["net_income"]->simplified(),
                                "subtotal" => $subtotal_info["subtotal"]->simplified()
                            ]);
                        },
                        array_filter(
                            array_values($illiquid_cash_flow_activity_subtotals),
                            function($cash_flow_activity_subtotal) {
                                return $cash_flow_activity_subtotal["subtotal"]->getSign() !== 0;
                            }
                        )
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
                        "closed_liquid_amount" => $closed_liquid_amount->simplified(),
                        "liquid_amount_difference" => $closed_liquid_amount->minus(
                            $opened_liquid_amount
                        )->simplified(),
                        "subtotals" => $illiquid_cash_flow_activity_subtotals
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

            return $currencies->withDeleted()->findAll();
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

    private static function keyCalculationsWithAccounts(array $calculations): array {
        $keyed_calculations = array_reduce(
            $calculations,
            function ($keyed_collection, $calculation) {
                $keyed_collection[$calculation->account_id] = $calculation;

                return $keyed_collection;
            },
            []
        );

        return $keyed_calculations;
    }
}
