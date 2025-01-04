<?php

namespace App\Database\Seeds;

use App\Libraries\NumericalToolConfiguration\CollectionSource;
use App\Libraries\NumericalToolConfiguration\FormulaSource;
use App\Models\AccountCollectionModel;
use App\Models\AccountModel;
use App\Models\CashFlowActivityModel;
use App\Models\CollectionModel;
use App\Models\CurrencyModel;
use App\Models\FinancialEntryModel;
use App\Models\FlowCalculationModel;
use App\Models\FormulaModel;
use App\Models\FrozenPeriodModel;
use App\Models\ModifierModel;
use App\Models\NumericalToolModel;
use App\Models\SummaryCalculationModel;
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

        $user_id = $users->getInsertID();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $peso_currency = $currency_fabricator->setOverrides([
            "user_id" => $user_id,
            "name" => "Philippine Peso",
            "code" => "PHP",
            "presentational_precision" => 2
        ])->create();

        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $operating_cash_flow_activity = $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $user_id,
            "name" => "Operating Activities",
            "description" => "Activities that are part or related to your normal life."
        ])->create();
        $financing_cash_flow_activity = $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $user_id,
            "name" => "Financing Activities",
            "description" => "Activities that are part or related to your loans."
        ])->create();

        $account_fabricator = new Fabricator(AccountModel::class);
        $asset_account = $account_fabricator->setOverrides([
            "currency_id" => $peso_currency->id,
            "name" => "Cash",
            "description" => "This is an example account.",
            "kind" => LIQUID_ASSET_ACCOUNT_KIND,
        ])->create();
        $expense_a_account = $account_fabricator->setOverrides([
            "currency_id" => $peso_currency->id,
            "name" => "Fare",
            "description" => "This is an example account.",
            "kind" => EXPENSE_ACCOUNT_KIND
        ])->create();
        $expense_b_account = $account_fabricator->setOverrides([
            "currency_id" => $peso_currency->id,
            "name" => "Food and Beverage",
            "description" => "This is an example account.",
            "kind" => EXPENSE_ACCOUNT_KIND
        ])->create();
        $equity_account = $account_fabricator->setOverrides([
            "currency_id" => $peso_currency->id,
            "name" => "Living Equity",
            "description" => "This is an example account.",
            "kind" => EQUITY_ACCOUNT_KIND
        ])->create();
        $liability_account = $account_fabricator->setOverrides([
            "currency_id" => $peso_currency->id,
            "name" => "Accounts Payable to Friend",
            "description" => "This is an example account.",
            "kind" => LIABILITY_ACCOUNT_KIND
        ])->create();
        $income_account = $account_fabricator->setOverrides([
            "currency_id" => $peso_currency->id,
            "name" => "Service Income",
            "description" => "This is an example account.",
            "kind" => INCOME_ACCOUNT_KIND
        ])->create();
        $closing_account = $account_fabricator->setOverrides([
            "currency_id" => $peso_currency->id,
            "name" => "Revenue and Expenses",
            "description" => "This is an example account.",
            "kind" => INCOME_ACCOUNT_KIND
        ])->create();


        $collection_fabricator = new Fabricator(CollectionModel::class);
        $credit_collection = $collection_fabricator->setOverrides([
            "user_id" => $user_id,
            "name" => "All Credits"
        ])->create();
        $expense_collection = $collection_fabricator->setOverrides([
            "user_id" => $user_id,
            "name" => "All Expenses"
        ])->create();

        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $collected_equity_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $credit_collection->id,
            "account_id" => $equity_account->id
        ])->create();
        $collected_liability_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $credit_collection->id,
            "account_id" => $liability_account->id
        ])->create();
        $collected_expense_a_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $expense_collection->id,
            "account_id" => $expense_a_account->id
        ])->create();
        $collected_expense_b_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $expense_collection->id,
            "account_id" => $expense_b_account->id
        ])->create();

        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $normal_record_modifier = $modifier_fabricator->setOverrides([
            "name" => "Record existing balance",
            "description" => "This is an example modifier.",
            "debit_account_id" => $asset_account->id,
            "credit_account_id" => $equity_account->id,
            "debit_cash_flow_activity_id" => null,
            "credit_cash_flow_activity_id" => $operating_cash_flow_activity->id,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $loan_record_modifier = $modifier_fabricator->setOverrides([
            "name" => "Borrow cash from a friend",
            "description" => "This is an example modifier.",
            "debit_account_id" => $asset_account->id,
            "credit_account_id" => $liability_account->id,
            "debit_cash_flow_activity_id" => null,
            "credit_cash_flow_activity_id" => $financing_cash_flow_activity->id,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $expense_a_record_modifier = $modifier_fabricator->setOverrides([
            "name" => "Pay fare",
            "description" => "This is an example modifier.",
            "debit_account_id" => $expense_a_account->id,
            "credit_account_id" => $asset_account->id,
            "debit_cash_flow_activity_id" => $operating_cash_flow_activity->id,
            "credit_cash_flow_activity_id" => null,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $expense_b_record_modifier = $modifier_fabricator->setOverrides([
            "name" => "Buy food and beverage",
            "description" => "This is an example modifier.",
            "debit_account_id" => $expense_b_account->id,
            "credit_account_id" => $asset_account->id,
            "debit_cash_flow_activity_id" => $operating_cash_flow_activity->id,
            "credit_cash_flow_activity_id" => null,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $income_record_modifier = $modifier_fabricator->setOverrides([
            "name" => "Collect service income",
            "description" => "This is an example modifier.",
            "debit_account_id" => $asset_account->id,
            "credit_account_id" => $income_account->id,
            "debit_cash_flow_activity_id" => null,
            "credit_cash_flow_activity_id" => $operating_cash_flow_activity->id,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $close_income_modifier = $modifier_fabricator->setOverrides([
            "name" => "Close service income",
            "description" => "This is an example modifier.",
            "debit_account_id" => $income_account->id,
            "credit_account_id" => $closing_account->id,
            "debit_cash_flow_activity_id" => $operating_cash_flow_activity->id,
            "credit_cash_flow_activity_id" => $operating_cash_flow_activity->id,
            "action" => CLOSE_MODIFIER_ACTION
        ])->create();
        $close_expense_a_modifier = $modifier_fabricator->setOverrides([
            "name" => "Close fare",
            "description" => "This is an example modifier.",
            "debit_account_id" => $closing_account->id,
            "credit_account_id" => $expense_a_account->id,
            "debit_cash_flow_activity_id" => $operating_cash_flow_activity->id,
            "credit_cash_flow_activity_id" => $operating_cash_flow_activity->id,
            "action" => CLOSE_MODIFIER_ACTION
        ])->create();
        $close_expense_b_modifier = $modifier_fabricator->setOverrides([
            "name" => "Close food and beverage",
            "description" => "This is an example modifier.",
            "debit_account_id" => $closing_account->id,
            "credit_account_id" => $expense_b_account->id,
            "debit_cash_flow_activity_id" => $operating_cash_flow_activity->id,
            "credit_cash_flow_activity_id" => $operating_cash_flow_activity->id,
            "action" => CLOSE_MODIFIER_ACTION
        ])->create();
        $close_equity_modifier = $modifier_fabricator->setOverrides([
            "name" => "Close net income",
            "description" => "This is an example modifier.",
            "debit_account_id" => $closing_account->id,
            "credit_account_id" => $equity_account->id,
            "debit_cash_flow_activity_id" => $operating_cash_flow_activity->id,
            "credit_cash_flow_activity_id" => $operating_cash_flow_activity->id,
            "action" => CLOSE_MODIFIER_ACTION
        ])->create();

        $last_one_month = Time::today()->setDay(1)->subMonths(1);
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $first_recorded_normal_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $normal_record_modifier->id,
            "debit_amount" => "1000",
            "credit_amount" => "1000",
            "transacted_at" => $last_one_month->toDateTimeString()
        ])->create();
        $second_recorded_normal_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $normal_record_modifier->id,
            "debit_amount" => "1000",
            "credit_amount" => "1000"
        ])->create();
        $second_recorded_loan_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $loan_record_modifier->id,
            "debit_amount" => "500",
            "credit_amount" => "500"
        ])->create();
        $second_recorded_expense_a_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $expense_a_record_modifier->id,
            "debit_amount" => "1000",
            "credit_amount" => "1000"
        ])->create();
        $second_recorded_expense_b_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $expense_b_record_modifier->id,
            "debit_amount" => "250",
            "credit_amount" => "250"
        ])->create();
        $second_recorded_income_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $income_record_modifier->id,
            "debit_amount" => "1500",
            "credit_amount" => "1500"
        ])->create();
        $second_closed_income_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $close_income_modifier->id,
            "debit_amount" => "1500",
            "credit_amount" => "1500"
        ])->create();
        $second_closed_expense_a_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $close_expense_a_modifier->id,
            "debit_amount" => "1000",
            "credit_amount" => "1000"
        ])->create();
        $second_closed_expense_b_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $close_expense_b_modifier->id,
            "debit_amount" => "250",
            "credit_amount" => "250"
        ])->create();
        $second_closed_equity_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $close_equity_modifier->id,
            "debit_amount" => "250",
            "credit_amount" => "250"
        ])->create();

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
    }
}
