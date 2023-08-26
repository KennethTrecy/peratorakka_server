<?php

namespace App\Controllers;

use Brick\Math\BigRational;
use CodeIgniter\Validation\Validation;

use App\Contracts\OwnedResource;
use App\Entities\SummaryCalculation;
use App\Models\AccountModel;
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

    protected static function makeCreateValidation(): Validation {
        return static::makeValidation();
    }

    protected static function makeUpdateValidation(int $id): Validation {
        return static::makeValidation();
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

        $accounts = [];
        if (count($linked_accounts) > 0) {
            $accounts = model(AccountModel::class)
                ->whereIn("id", array_unique($linked_accounts))
                ->findAll();
        }
        $enriched_document["accounts"] = $accounts;

        $linked_currencies = [];
        foreach ($accounts as $document) {
            $currency_id = $document->currency_id;
            array_push($linked_currencies, $currency_id);
        }

        $currencies = [];
        if (count($linked_currencies) > 0) {
            $currencies = model(CurrencyModel::class)
                ->whereIn("id", array_unique($linked_currencies))
                ->findAll();
        }
        $enriched_document["currencies"] = $currencies;

        $enriched_document["@meta"] = [
            "statements" => static::makeStatements($currencies, $accounts, $summary_calculations)
        ];

        return $enriched_document;
    }

    protected static function processCreatedDocument(array $created_document): array {
        $main_document = $created_document[static::getIndividualName()];

        $financial_entries = model(FinancialEntryModel::class)
            ->where("transacted_at >=", $main_document["started_at"])
            ->where("transacted_at <=", $main_document["finished_at"])
            ->findAll();

        $raw_summary_calculations = static::makeRawSummaryCalculations($financial_entries);
        $raw_summary_calculations = array_map(
            function ($raw_summary_calculation) use ($main_document) {
                return array_merge(
                    [ "frozen_period_id" => $main_document["id"] ],
                    $raw_summary_calculation
                );
            },
            $raw_summary_calculations
        );

        model(SummaryCalculationModel::class)->insertBatch($raw_summary_calculations);

        return $created_document;
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
            "must_be_on_or_before_current_time"
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

        $accounts = [];
        if (count($linked_accounts) > 0) {
            $accounts = model(AccountModel::class)
                ->whereIn("id", array_unique($linked_accounts))
                ->findAll();
        }

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

        $raw_summary_calculations = array_reduce(
            $accounts,
            function ($raw_calculations, $account) {
                $raw_calculations[$account->id] = [
                    "account_id" => $account->id,
                    "unadjusted_debit_amount" => BigRational::zero(),
                    "unadjusted_credit_amount" => BigRational::zero(),
                    "adjusted_debit_amount" => BigRational::zero(),
                    "adjusted_credit_amount" => BigRational::zero()
                ];

                return $raw_calculations;
            },
            []
        );
        $raw_summary_calculations = array_reduce(
            $modifiers,
            function ($raw_calculations, $modifier) use ($grouped_financial_entries) {
                $financial_entries = $grouped_financial_entries[$modifier->id];

                foreach ($financial_entries as $financial_entry) {
                    $debit_account_id = $modifier->debit_account_id;
                    $debit_amount = $financial_entry->debit_amount;

                    $credit_account_id = $modifier->credit_account_id;
                    $credit_amount = $financial_entry->credit_amount;

                    if ($modifier->action !== "close") {
                        $raw_calculations[$debit_account_id]["unadjusted_debit_amount"]
                            = $raw_calculations[$debit_account_id]["unadjusted_debit_amount"]
                                ->plus($debit_amount);
                        $raw_calculations[$credit_account_id]["unadjusted_credit_amount"]
                            = $raw_calculations[$credit_account_id]["unadjusted_credit_amount"]
                                ->plus($credit_amount);
                    }

                    $raw_calculations[$debit_account_id]["adjusted_debit_amount"]
                    = $raw_calculations[$debit_account_id]["adjusted_debit_amount"]
                        ->plus($debit_amount);
                    $raw_calculations[$credit_account_id]["adjusted_credit_amount"]
                        = $raw_calculations[$credit_account_id]["adjusted_credit_amount"]
                            ->plus($credit_amount);
                }

                return $raw_calculations;
            },
            $raw_summary_calculations
        );
        $raw_calculations = array_map(
            function ($raw_calculation) {
                $unadjusted_debit_amount = $raw_calculation["unadjusted_debit_amount"];
                $unadjusted_credit_amount = $raw_calculation["unadjusted_credit_amount"];
                $adjusted_debit_amount = $raw_calculation["adjusted_debit_amount"];
                $adjusted_credit_amount = $raw_calculation["adjusted_credit_amount"];

                $unadjusted_balance = $unadjusted_debit_amount
                    ->minus($unadjusted_credit_amount)
                    ->simplified();
                $adjusted_balance = $adjusted_debit_amount
                    ->minus($adjusted_credit_amount)
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
                $raw_calculation["adjusted_debit_amount"] = $is_adjusted_balance_positive
                    ? $adjusted_balance
                    : BigRational::zero();
                $raw_calculation["adjusted_credit_amount"] = $is_adjusted_balance_negative
                    ? $adjusted_balance->negated()
                    : BigRational::zero();
                $raw_calculation = (new SummaryCalculation())->fill($raw_calculation);

                return $raw_calculation;
            },
            array_values($raw_summary_calculations)
        );

        return $raw_summary_calculations;
    }

    private static function makeStatements($currencies, $accounts, $summary_calculations): array {
        $keyed_calculations = array_reduce(
            $summary_calculations,
            function ($keyed_collection, $summary) {
                $keyed_collection[$summary->account_id] = $summary;

                return $keyed_collection;
            },
            []
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
        $statements = array_reduce(
            $currencies,
            function ($statements, $currency) use ($grouped_summaries) {
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

                $adjusted_total_expenses = array_reduce(
                    $summaries[EXPENSE_ACCOUNT_KIND],
                    function ($previous_total, $summary) {
                        return $previous_total
                            ->plus($summary->adjusted_debit_amount)
                            ->minus($summary->adjusted_credit_amount);
                    },
                    BigRational::zero()
                );
                $adjusted_total_assets = array_reduce(
                    $summaries[ASSET_ACCOUNT_KIND],
                    function ($previous_total, $summary) {
                        return $previous_total
                            ->plus($summary->adjusted_debit_amount)
                            ->minus($summary->adjusted_credit_amount);
                    },
                    BigRational::zero()
                );

                $unadjusted_trial_balance_total = $unadjusted_total_expenses
                    ->plus($unadjusted_total_assets);
                $income_statement_total = $unadjusted_total_income
                    ->minus($unadjusted_total_expenses);
                $adjusted_trial_balance_total = $adjusted_total_assets
                    ->plus($adjusted_total_expenses);

                array_push($statements, [
                    "currency_id" => $currency->id,
                    "unadjusted_trial_balance" => [
                        "total" => $unadjusted_trial_balance_total
                    ],
                    "income_statement" => [
                        "total" => $income_statement_total
                    ],
                    "balance_sheet" => [
                        "total_assets" => $unadjusted_total_assets,
                        "total_liabilities" => $unadjusted_total_liabilities,
                        "total_equities" => $unadjusted_total_equities
                            ->plus($income_statement_total)
                    ],
                    "adjusted_trial_balance" => [
                        "total" => $adjusted_trial_balance_total
                    ]
                ]);

                return $statements;
            },
            []
        );

        return $statements;
    }
}
