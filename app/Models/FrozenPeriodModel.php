<?php

namespace App\Models;

use App\Casts\RationalNumber;
use App\Entities\FlowCalculation;
use App\Entities\FrozenPeriod;
use App\Entities\SummaryCalculation;
use App\Libraries\Resource;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use DateTimeInterface;
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

    public static function makeRawCalculations(string $started_at, string $finished_at): array
    {
        $financial_entries = model(FinancialEntryModel::class)
            ->where("transacted_at >=", $started_at)
            ->where("transacted_at <=", $finished_at)
            ->withDeleted()
            ->findAll();

        [
            $modifiers,
            $accounts,
            $cash_flow_activities
        ] = static::findLinkedResources($financial_entries);

        [
            $keyed_raw_summary_calculations,
            $keyed_raw_flow_calculations
        ] = static::prepareRawCalculations($modifiers, $accounts, $cash_flow_activities);

        [
            // Used to determine previous period
            $earliest_transacted_time,
            // Use to determine the exchange rate to use
            $latest_entry_transacted_time
        ] = static::minMaxTransactedTimes($financial_entries);

        [
            $keyed_raw_summary_calculations,
            $missing_accounts
        ] = static::mergePreviousSummaryCalculations(
            $earliest_transacted_time,
            $keyed_raw_summary_calculations
        );

        [
            $keyed_raw_summary_calculations,
            $keyed_raw_flow_calculations
        ] = static::consolidateRawCalculations(
            $financial_entries,
            $modifiers,
            $keyed_raw_summary_calculations,
            $keyed_raw_flow_calculations
        );

        [
            $summary_calculations,
            $flow_calculations
        ] = static::makeResources($keyed_raw_summary_calculations, $keyed_raw_flow_calculations);

        $accounts = static::removeUnusedAccounts(
            array_merge($accounts, $missing_accounts),
            $summary_calculations,
            $flow_calculations
        );

        $linked_currencies = AccountModel::extractLinkedCurrencies($accounts);
        $raw_exchange_rates = CurrencyModel::makeExchangeRates(
            $latest_entry_transacted_time,
            $linked_currencies
        );

        return [
            array_values($cash_flow_activities),
            array_values($accounts),
            array_values($summary_calculations),
            array_values($flow_calculations),
            $raw_exchange_rates
        ];
    }

    private static function findLinkedResources(array $financial_entries): array
    {
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

        $accounts = [];
        if (count($linked_accounts) > 0) {
            $accounts = model(AccountModel::class)
                ->whereIn("id", array_unique($linked_accounts))
                ->withDeleted()
                ->findAll();
        }

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

        return [
            $modifiers,
            $accounts,
            $cash_flow_activities,
        ];
    }

    private static function prepareRawCalculations(
        array $modifiers,
        array $accounts,
        array $cash_flow_activities
    ): array {
        $keyed_raw_summary_calculations = array_reduce(
            $accounts,
            function ($raw_calculations, $account) {
                $raw_calculations[$account->id] = [
                    "account_id" => $account->id,
                    "opened_debit_amount" => RationalNumber::zero(),
                    "opened_credit_amount" => RationalNumber::zero(),
                    "unadjusted_debit_amount" => RationalNumber::zero(),
                    "unadjusted_credit_amount" => RationalNumber::zero(),
                    "closed_debit_amount" => RationalNumber::zero(),
                    "closed_credit_amount" => RationalNumber::zero()
                ];

                return $raw_calculations;
            },
            []
        );

        $keyed_raw_flow_calculations = array_reduce(
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
                        "net_amount" => RationalNumber::zero()
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
                        "net_amount" => RationalNumber::zero()
                    ];
                }

                return $raw_calculations;
            },
            []
        );

        return [
            $keyed_raw_summary_calculations,
            $keyed_raw_flow_calculations
        ];
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

    private static function mergePreviousSummaryCalculations(
        string $earliest_transacted_time,
        array $keyed_raw_summary_calculations
    ): array {
        $previous_frozen_period = model(FrozenPeriodModel::class, false)
            ->where("finished_at <", $earliest_transacted_time)
            ->orderBy("finished_at", "DESC")
            ->first();

        $missing_accounts = [];

        if ($previous_frozen_period) {
            $missing_linked_accounts = [];

            $previous_summary_calculations = model(SummaryCalculationModel::class, false)
                ->where("frozen_period_id", $previous_frozen_period->id)
                ->findAll();

            foreach ($previous_summary_calculations as $previous_summary_calculation) {
                $account_id = $previous_summary_calculation->account_id;

                if (isset($keyed_raw_summary_calculations[$account_id])) {
                    $keyed_raw_summary_calculations[$account_id]["opened_debit_amount"]
                        = $keyed_raw_summary_calculations[$account_id]["opened_debit_amount"]
                            ->plus($previous_summary_calculation->closed_debit_amount);
                    $keyed_raw_summary_calculations[$account_id]["opened_credit_amount"]
                        = $keyed_raw_summary_calculations[$account_id]["opened_credit_amount"]
                            ->plus($previous_summary_calculation->closed_credit_amount);

                    $keyed_raw_summary_calculations[$account_id]["unadjusted_debit_amount"]
                        = $keyed_raw_summary_calculations[$account_id]["unadjusted_debit_amount"]
                            ->plus($previous_summary_calculation->closed_debit_amount);
                    $keyed_raw_summary_calculations[$account_id]["unadjusted_credit_amount"]
                        = $keyed_raw_summary_calculations[$account_id]["unadjusted_credit_amount"]
                            ->plus($previous_summary_calculation->closed_credit_amount);

                    $keyed_raw_summary_calculations[$account_id]["closed_debit_amount"]
                        = $keyed_raw_summary_calculations[$account_id]["closed_debit_amount"]
                            ->plus($previous_summary_calculation->closed_debit_amount);
                    $keyed_raw_summary_calculations[$account_id]["closed_credit_amount"]
                        = $keyed_raw_summary_calculations[$account_id]["closed_credit_amount"]
                            ->plus($previous_summary_calculation->closed_credit_amount);
                } else {
                    $keyed_raw_summary_calculations[$account_id] = [
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

                    array_push($missing_linked_accounts, $account_id);
                }
            }

            if (count($missing_linked_accounts) > 0) {
                $missing_accounts = model(AccountModel::class, false)
                    ->whereIn("id", array_unique($missing_linked_accounts))
                    ->withDeleted()
                    ->findAll();
            }
        }

        return [
            $keyed_raw_summary_calculations,
            $missing_accounts
        ];
    }

    private static function consolidateRawCalculations(
        array $financial_entries,
        array $modifiers,
        array $keyed_raw_summary_calculations,
        array $keyed_raw_flow_calculations
    ): array {
        $grouped_financial_entries = Resource::group(
            $financial_entries,
            function ($financial_entry) {
                return $financial_entry->modifier_id;
            }
        );

        $keyed_raw_summary_calculations = array_reduce(
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
            $keyed_raw_summary_calculations
        );

        $non_closing_modifiers = array_filter($modifiers, function ($modifier) {
            return $modifier->action !== CLOSE_MODIFIER_ACTION;
        });
        $keyed_raw_flow_calculations = array_reduce(
            $non_closing_modifiers,
            function ($raw_calculations, $modifier) use ($grouped_financial_entries) {
                $financial_entries = $grouped_financial_entries[$modifier->id];

                foreach ($financial_entries as $financial_entry) {
                    $debit_account_id = $modifier->debit_account_id;
                    $debit_activity_id = $modifier->debit_cash_flow_activity_id;
                    $debit_amount = $financial_entry->debit_amount;

                    $credit_activity_id = $modifier->credit_cash_flow_activity_id;
                    $credit_account_id = $modifier->credit_account_id;
                    $credit_amount = $financial_entry->credit_amount;

                    if ($debit_activity_id !== null) {
                        $raw_calculations[$debit_activity_id][$debit_account_id]["net_amount"]
                            = $raw_calculations[$debit_activity_id][$debit_account_id]["net_amount"]
                                ->minus($debit_amount);
                    }

                    if ($credit_activity_id !== null) {
                        $raw_calculations[$credit_activity_id][$credit_account_id]["net_amount"]
                            = $raw_calculations
                                [$credit_activity_id][$credit_account_id]["net_amount"]
                                ->plus($credit_amount);
                    }
                }

                return $raw_calculations;
            },
            $keyed_raw_flow_calculations
        );

        return [
            $keyed_raw_summary_calculations,
            $keyed_raw_flow_calculations
        ];
    }

    private static function makeResources(
        array $keyed_raw_summary_calculations,
        array $keyed_raw_flow_calculations
    ): array {
        $keyed_raw_summary_calculations = array_filter(
            $keyed_raw_summary_calculations,
            function ($keyed_raw_summary_calculation) {
                return $keyed_raw_summary_calculation["unadjusted_debit_amount"]->getSign() !== 0
                    || $keyed_raw_summary_calculation["unadjusted_credit_amount"]->getSign() !== 0
                    || $keyed_raw_summary_calculation["closed_debit_amount"]->getSign() !== 0
                    || $keyed_raw_summary_calculation["closed_credit_amount"]->getSign() !== 0;
            }
        );
        $summary_calculations = array_map(
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
                    : RationalNumber::zero();
                $raw_calculation["closed_credit_amount"] = $is_adjusted_balance_negative
                    ? $adjusted_balance->negated()->simplified()
                    : RationalNumber::zero();
                $raw_calculation = (new SummaryCalculation())->fill($raw_calculation);

                return $raw_calculation;
            },
            array_values($keyed_raw_summary_calculations)
        );
        $flow_calculations = array_merge(
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
                array_values($keyed_raw_flow_calculations)
            )
        );
        $flow_calculations = array_filter(
            $flow_calculations,
            function ($flow_calculation) {
                return $flow_calculation->net_amount->getSign() !== 0;
            }
        );

        return [
            $summary_calculations,
            $flow_calculations
        ];
    }

    private static function removeUnusedAccounts(
        array $accounts,
        array $summary_calculations,
        array $flow_calculations
    ): array {
        $retained_accounts_on_summary_calculations = array_map(
            function ($summary_calculation) {
                return $summary_calculation->account_id;
            },
            $summary_calculations
        );

        $retained_accounts_on_flow_calculations = array_map(
            function ($flow_calculation) {
                return $flow_calculation->account_id;
            },
            $flow_calculations
        );
        $retained_accounts_on_calculations = array_unique(array_merge(
            $retained_accounts_on_summary_calculations,
            $retained_accounts_on_flow_calculations
        ));

        $accounts = array_filter(
            $accounts,
            function ($account) use ($retained_accounts_on_calculations) {
                return in_array($account->id, $retained_accounts_on_calculations);
            }
        );

        return $accounts;
    }
}
