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
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "Philippine Peso",
            "code" => "PHP"
        ])->create();

        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $operating_cash_flow_activity = $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "Operating Activities",
            "description" => "Activities that are part or related to your normal life."
        ])->create();
        $financing_cash_flow_activity = $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "Financing Activities",
            "description" => "Activities that are part or related to your loans."
        ])->create();

        $account_fabricator = new Fabricator(AccountModel::class);
        $asset_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "name" => "Cash",
            "kind" => LIQUID_ASSET_ACCOUNT_KIND
        ])->create();
        $expense_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "name" => "Fare",
            "kind" => EXPENSE_ACCOUNT_KIND
        ])->create();
        $equity_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "name" => "Living Equity",
            "kind" => EQUITY_ACCOUNT_KIND
        ])->create();
        $liability_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "name" => "Accounts Payable to Friend",
            "kind" => LIABILITY_ACCOUNT_KIND
        ])->create();
        $income_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "name" => "Service Income",
            "kind" => INCOME_ACCOUNT_KIND
        ])->create();
        $closing_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "name" => "Revenue and Expenses",
            "kind" => INCOME_ACCOUNT_KIND
        ])->create();

        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $normal_record_modifier = $modifier_fabricator->setOverrides([
            "name" => "Record existing balance",
            "debit_account_id" => $asset_account->id,
            "credit_account_id" => $equity_account->id,
            "debit_cash_flow_activity_id" => null,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $expense_record_modifier = $modifier_fabricator->setOverrides([
            "name" => "Pay fare",
            "debit_account_id" => $expense_account->id,
            "credit_account_id" => $asset_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => null,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $income_record_modifier = $modifier_fabricator->setOverrides([
            "name" => "Collect service income",
            "debit_account_id" => $asset_account->id,
            "credit_account_id" => $income_account->id,
            "debit_cash_flow_activity_id" => null,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $close_income_modifier = $modifier_fabricator->setOverrides([
            "name" => "Close service income",
            "debit_account_id" => $income_account->id,
            "credit_account_id" => $closing_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id,
            "action" => CLOSE_MODIFIER_ACTION
        ])->create();
        $close_expense_modifier = $modifier_fabricator->setOverrides([
            "name" => "Close fare",
            "debit_account_id" => $closing_account->id,
            "credit_account_id" => $expense_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id,
            "action" => CLOSE_MODIFIER_ACTION
        ])->create();
        $close_equity_modifier = $modifier_fabricator->setOverrides([
            "name" => "Close net income",
            "debit_account_id" => $closing_account->id,
            "credit_account_id" => $equity_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id,
            "action" => CLOSE_MODIFIER_ACTION
        ])->create();
    }
}
