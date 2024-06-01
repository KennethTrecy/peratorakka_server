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
            "kind" => LIQUID_ASSET_ACCOUNT_KIND
        ])->create();
        $expense_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EXPENSE_ACCOUNT_KIND
        ])->create();
        $equity_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EQUITY_ACCOUNT_KIND
        ])->create();
        $liability_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => LIABILITY_ACCOUNT_KIND
        ])->create();
        $income_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => INCOME_ACCOUNT_KIND
        ])->create();
    }
}
