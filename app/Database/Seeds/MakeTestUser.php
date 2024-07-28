<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\Fabricator;

use App\Models\AccountModel;
use App\Models\CashFlowActivityModel;
use App\Models\CurrencyModel;
use App\Models\FinancialEntryModel;
use App\Models\FlowCalculationModel;
use App\Models\FrozenPeriodModel;
use App\Models\ModifierModel;
use App\Models\SummaryCalculationModel;

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
            "code" => "PHP"
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
            "description" => "This is an example account.",
            "kind" => LIQUID_ASSET_ACCOUNT_KIND,
        ])->create();
        $expense_account = $account_fabricator->setOverrides([
            "currency_id" => $peso_currency->id,
            "name" => "Fare",
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
        $expense_record_modifier = $modifier_fabricator->setOverrides([
            "name" => "Pay fare",
            "description" => "This is an example modifier.",
            "debit_account_id" => $expense_account->id,
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
        $close_expense_modifier = $modifier_fabricator->setOverrides([
            "name" => "Close fare",
            "description" => "This is an example modifier.",
            "debit_account_id" => $closing_account->id,
            "credit_account_id" => $expense_account->id,
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

        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $recorded_normal_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $normal_record_modifier->id,
            "debit_amount" => "1000",
            "credit_amount" => "1000"
        ])->create();
        $recorded_loan_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $loan_record_modifier->id,
            "debit_amount" => "500",
            "credit_amount" => "500"
        ])->create();
        $recorded_expense_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $expense_record_modifier->id,
            "debit_amount" => "1250",
            "credit_amount" => "1250"
        ])->create();
        $recorded_income_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $income_record_modifier->id,
            "debit_amount" => "1500",
            "credit_amount" => "1500"
        ])->create();
        $closed_income_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $close_income_modifier->id,
            "debit_amount" => "1500",
            "credit_amount" => "1500"
        ])->create();
        $closed_expense_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $close_expense_modifier->id,
            "debit_amount" => "1250",
            "credit_amount" => "1250"
        ])->create();
        $closed_equity_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $close_equity_modifier->id,
            "debit_amount" => "250",
            "credit_amount" => "250"
        ])->create();

        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $user_id,
        ])->create();

        $summary_calculation_fabricator = new Fabricator(SummaryCalculationModel::class);
        $asset_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $frozen_period->id,
            "account_id" => $asset_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => $recorded_normal_financial_entry
                ->debit_amount
                ->plus($recorded_income_financial_entry->debit_amount)
                ->plus($recorded_loan_financial_entry->debit_amount),
            "unadjusted_credit_amount" => $recorded_expense_financial_entry->credit_amount,
            "closed_debit_amount" => $recorded_normal_financial_entry
                ->debit_amount
                ->plus($recorded_loan_financial_entry->debit_amount)
                ->plus($recorded_income_financial_entry->debit_amount)
                ->minus($recorded_expense_financial_entry->credit_amount),
            "closed_credit_amount" => "0"
        ])->create();
        $equity_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $frozen_period->id,
            "account_id" => $equity_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => $recorded_normal_financial_entry->credit_amount,
            "closed_debit_amount" => "0",
            "closed_credit_amount" => $recorded_normal_financial_entry
                ->credit_amount
                ->plus($closed_equity_financial_entry->credit_amount)
        ])->create();
        $liability_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $frozen_period->id,
            "account_id" => $liability_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => $recorded_loan_financial_entry->credit_amount,
            "closed_debit_amount" => "0",
            "closed_credit_amount" => $recorded_loan_financial_entry->credit_amount
        ])->create();
        $expense_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $frozen_period->id,
            "account_id" => $expense_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => $recorded_expense_financial_entry->debit_amount,
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "0"
        ])->create();
        $income_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $frozen_period->id,
            "account_id" => $income_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => $recorded_income_financial_entry->credit_amount,
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "0"
        ])->create();

        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
        $asset_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $frozen_period->id,
            "cash_flow_activity_id" => $operating_cash_flow_activity->id,
            "account_id" => $asset_account->id,
            "net_amount" => $recorded_normal_financial_entry
                ->credit_amount
                ->plus($closed_equity_financial_entry->credit_amount)
        ])->create();
        $equity_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $frozen_period->id,
            "cash_flow_activity_id" => $operating_cash_flow_activity->id,
            "account_id" => $equity_account->id,
            "net_amount" => $recorded_normal_financial_entry
                ->debit_amount
                ->plus($recorded_loan_financial_entry->debit_amount)
                ->plus($recorded_income_financial_entry->debit_amount)
                ->minus($recorded_expense_financial_entry->credit_amount)
        ])->create();
        $liability_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $frozen_period->id,
            "cash_flow_activity_id" => $operating_cash_flow_activity->id,
            "account_id" => $liability_account->id,
            "net_amount" => $recorded_loan_financial_entry->credit_amount
        ])->create();
        $expense_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $frozen_period->id,
            "cash_flow_activity_id" => $operating_cash_flow_activity->id,
            "account_id" => $expense_account->id,
            "net_amount" => $recorded_expense_financial_entry->debit_amount->negated()
        ])->create();
        $income_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $frozen_period->id,
            "cash_flow_activity_id" => $operating_cash_flow_activity->id,
            "account_id" => $income_account->id,
            "net_amount" => $recorded_income_financial_entry->credit_amount
        ])->create();
    }
}
