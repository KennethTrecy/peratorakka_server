<?php

namespace App\Database\Seeds;

use App\Libraries\NumericalToolConfiguration\CollectionSource;
use App\Libraries\NumericalToolConfiguration\FormulaSource;
use App\Models\AccountCollectionModel;
use App\Models\AccountModel;
use App\Models\CashFlowActivityModel;
use App\Models\CollectionModel;
use App\Models\CurrencyModel;
use App\Models\PrecisionFormatModel;
use App\Models\Deprecated\DeprecatedFinancialEntryModel;
use App\Models\Deprecated\DeprecatedFlowCalculationModel;
use App\Models\Deprecated\DeprecatedSummaryCalculation;
use App\Models\FormulaModel;
use App\Models\FrozenPeriodModel;
use App\Models\ModifierModel;
use App\Models\NumericalToolModel;
use CodeIgniter\Database\Seeder;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\Fabricator;

class MakeTestUser extends Seeder
{
    public function run()
    {
        helper([ "auth" ]);

        $users = auth()->getProvider();

        $user = new User([
            'username' => 'Test Account',
            'email'    => 'test@example.com',
            'password' => '12345678',
        ]);
        $users->save($user);
        $user->id = $users->getInsertID();
        $users->makeInitialData($user);

        /*
        $last_one_month = Time::today()->setDay(1)->subMonths(1);
        $second_frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $first_frozen_period = $second_frozen_period_fabricator->setOverrides([
            "user_id" => $user_id,
            "started_at" => $last_one_month->toDateTimeString(),
            "finished_at" => $last_one_month->addMonths(1)->subDays(1)->toDateTimeString()
        ])->create();
        $second_frozen_period = $second_frozen_period_fabricator->setOverrides([
            "user_id" => $user_id,
        ])->create();

        $summary_calculation_fabricator = new Fabricator(SummaryCalculationModel::class);
        $first_asset_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $asset_account->id,
            "opened_debit_amount" => $first_recorded_normal_financial_entry->debit_amount,
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => $first_recorded_normal_financial_entry->debit_amount,
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => $first_recorded_normal_financial_entry->debit_amount,
            "closed_credit_amount" => "0"
        ])->create();
        $second_asset_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "account_id" => $asset_account->id,
            "opened_debit_amount" => $first_recorded_normal_financial_entry->debit_amount,
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => $first_recorded_normal_financial_entry
                ->debit_amount
                ->plus($second_recorded_normal_financial_entry->debit_amount)
                ->plus($second_recorded_income_financial_entry->debit_amount)
                ->plus($second_recorded_loan_financial_entry->debit_amount),
            "unadjusted_credit_amount" => $second_recorded_expense_a_financial_entry->credit_amount,
            "closed_debit_amount" => $first_recorded_normal_financial_entry
                ->debit_amount
                ->plus($second_recorded_normal_financial_entry->debit_amount)
                ->plus($second_recorded_loan_financial_entry->debit_amount)
                ->plus($second_recorded_income_financial_entry->debit_amount)
                ->minus($second_recorded_expense_a_financial_entry->credit_amount),
            "closed_credit_amount" => "0"
        ])->create();
        $first_equity_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $asset_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => $first_recorded_normal_financial_entry->credit_amount,
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => $first_recorded_normal_financial_entry->credit_amount,
            "closed_debit_amount" => "0",
            "closed_credit_amount" => $first_recorded_normal_financial_entry->credit_amount
        ])->create();
        $second_equity_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "account_id" => $equity_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => $first_recorded_normal_financial_entry->credit_amount,
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => $first_recorded_normal_financial_entry
                ->credit_amount
                ->plus($second_recorded_normal_financial_entry->credit_amount),
            "closed_debit_amount" => "0",
            "closed_credit_amount" => $first_recorded_normal_financial_entry
                ->credit_amount
                ->plus($second_recorded_normal_financial_entry->credit_amount)
                ->plus($second_closed_equity_financial_entry->credit_amount)
        ])->create();
        $second_liability_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "account_id" => $liability_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => $second_recorded_loan_financial_entry->credit_amount,
            "closed_debit_amount" => "0",
            "closed_credit_amount" => $second_recorded_loan_financial_entry->credit_amount
        ])->create();
        $second_expense_a_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "account_id" => $expense_a_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => $second_recorded_expense_a_financial_entry->debit_amount,
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "0"
        ])->create();
        $second_expense_b_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "account_id" => $expense_b_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => $second_recorded_expense_b_financial_entry->debit_amount,
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "0"
        ])->create();
        $second_income_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "account_id" => $income_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => $second_recorded_income_financial_entry->credit_amount,
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "0"
        ])->create();

        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
        $first_asset_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "cash_flow_activity_id" => $operating_cash_flow_activity->id,
            "account_id" => $asset_account->id,
            "net_amount" => $first_recorded_normal_financial_entry
                ->debit_amount
        ])->create();
        $second_asset_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "cash_flow_activity_id" => $operating_cash_flow_activity->id,
            "account_id" => $asset_account->id,
            "net_amount" => $second_recorded_normal_financial_entry
                ->debit_amount
                ->plus($second_closed_equity_financial_entry->debit_amount)
        ])->create();
        $first_equity_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "cash_flow_activity_id" => $operating_cash_flow_activity->id,
            "account_id" => $equity_account->id,
            "net_amount" => $first_recorded_normal_financial_entry
                ->credit_amount
        ])->create();
        $second_equity_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "cash_flow_activity_id" => $operating_cash_flow_activity->id,
            "account_id" => $equity_account->id,
            "net_amount" => $second_recorded_normal_financial_entry
                ->debit_amount
                ->plus($second_recorded_loan_financial_entry->debit_amount)
                ->plus($second_recorded_income_financial_entry->debit_amount)
                ->minus($second_recorded_expense_a_financial_entry->credit_amount)
                ->minus($second_recorded_expense_b_financial_entry->credit_amount)
        ])->create();
        $second_liability_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "cash_flow_activity_id" => $operating_cash_flow_activity->id,
            "account_id" => $liability_account->id,
            "net_amount" => $second_recorded_loan_financial_entry->credit_amount
        ])->create();
        $second_expense_a_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "cash_flow_activity_id" => $operating_cash_flow_activity->id,
            "account_id" => $expense_a_account->id,
            "net_amount" => $second_recorded_expense_a_financial_entry->debit_amount->negated()
        ])->create();
        $second_expense_b_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "cash_flow_activity_id" => $operating_cash_flow_activity->id,
            "account_id" => $expense_b_account->id,
            "net_amount" => $second_recorded_expense_b_financial_entry->debit_amount->negated()
        ])->create();
        $second_income_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "cash_flow_activity_id" => $operating_cash_flow_activity->id,
            "account_id" => $income_account->id,
            "net_amount" => $second_recorded_income_financial_entry->credit_amount
        ])->create();

        $numerical_tool_fabricator = new Fabricator(NumericalToolModel::class);
        $expense_pie_numerical_tool = $numerical_tool_fabricator->setOverrides([
            "user_id" => $user_id,
            "name" => "Expense Compositor",
            "kind" => PIE_NUMERICAL_TOOL_KIND,
            "recurrence" => PERIODIC_NUMERICAL_TOOL_RECURRENCE_PERIOD,
            "recency" => 1,
            "configuration" => json_encode([
                "sources" => [
                    [
                        "type" => CollectionSource::sourceType(),
                        "collection_id" => $expense_collection->id,
                        "currency_id" => $peso_currency->id,
                        "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
                        "stage_basis" => UNADJUSTED_AMOUNT_STAGE_BASIS,
                        "side_basis" => NET_DEBIT_AMOUNT_SIDE_BASIS,
                        "must_show_individual_amounts" => true,
                        "must_show_collective_sum" => true,
                        "must_show_collective_average" => true
                    ]
                ]
            ])
        ])->create();
        $credit_line_numerical_tool = $numerical_tool_fabricator->setOverrides([
            "user_id" => $user_id,
            "name" => "Credit Growth",
            "kind" => LINE_NUMERICAL_TOOL_KIND,
            "recurrence" => PERIODIC_NUMERICAL_TOOL_RECURRENCE_PERIOD,
            "recency" => -2,
            "configuration" => json_encode([
                "sources" => [
                    [
                        "type" => CollectionSource::sourceType(),
                        "collection_id" => $credit_collection->id,
                        "currency_id" => $peso_currency->id,
                        "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
                        "stage_basis" => UNADJUSTED_AMOUNT_STAGE_BASIS,
                        "side_basis" => NET_CREDIT_AMOUNT_SIDE_BASIS,
                        "must_show_individual_amounts" => true,
                        "must_show_collective_sum" => true,
                        "must_show_collective_average" => true
                    ]
                ]
            ])
        ])->create();
        */
    }
}
