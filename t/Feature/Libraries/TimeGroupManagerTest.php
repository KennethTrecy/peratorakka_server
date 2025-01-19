<?php

namespace Tests\Feature\Libraries;

use App\Casts\RationalNumber;
use App\Libraries\Context;
use App\Libraries\TimeGroup\PeriodicTimeGroup;
use App\Libraries\TimeGroup\UnfrozenTimeGroup;
use App\Libraries\TimeGroup\YearlyTimeGroup;
use App\Libraries\Context\TimeGroupManager;
use App\Models\AccountModel;
use App\Models\CashFlowActivityModel;
use App\Models\CurrencyModel;
use App\Models\FinancialEntryModel;
use App\Models\FlowCalculationModel;
use App\Models\FrozenPeriodModel;
use App\Models\ModifierModel;
use App\Models\SummaryCalculationModel;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\Fabricator;
use Tests\Feature\Helper\AuthenticatedHTTPTestCase;

class TimeGroupManagerTest extends AuthenticatedHTTPTestCase
{
    public function testTotalOpenedDebitAmount()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity = $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $equity_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EQUITY_ACCOUNT_KIND
        ])->create();
        $asset_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => LIQUID_ASSET_ACCOUNT_KIND
        ])->create();
        $expense_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EXPENSE_ACCOUNT_KIND
        ])->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $first_frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at" => Time::parse("-3 day")->toDateTimeString(),
            "finished_at" => Time::parse("-2 day")->toDateTimeString()
        ])->create();
        $summary_calculation_fabricator = new Fabricator(SummaryCalculationModel::class);
        $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id
        ]);
        $first_equity_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $equity_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "2500",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "2000"
        ])->create();
        $first_asset_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $asset_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "2500",
            "unadjusted_credit_amount" => "500",
            "closed_debit_amount" => "2000",
            "closed_credit_amount" => "0"
        ])->create();
        $first_expense_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $expense_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "500",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "0"
        ])->create();
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
        $first_equity_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $equity_account->id,
            "net_amount" => "2500"
        ])->create();
        $first_expense_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $expense_account->id,
            "net_amount" => "0"
        ])->create();
        $second_frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at" => Time::yesterday()->toDateTimeString(),
            "finished_at" => Time::now()->toDateTimeString()
        ])->create();
        $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id
        ]);
        $second_equity_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "account_id" => $equity_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "2000",
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "4000",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "3500"
        ])->create();
        $second_asset_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "account_id" => $asset_account->id,
            "opened_debit_amount" => "2000",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "4000",
            "unadjusted_credit_amount" => "500",
            "closed_debit_amount" => "3500",
            "closed_credit_amount" => "0"
        ])->create();
        $second_expense_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "account_id" => $expense_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "500",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "0"
        ])->create();
        $second_equity_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $equity_account->id,
            "net_amount" => "500"
        ])->create();
        $second_expense_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $expense_account->id,
            "net_amount" => "0"
        ])->create();

        $time_groups = [
            new PeriodicTimeGroup($first_frozen_period),
            new PeriodicTimeGroup($second_frozen_period)
        ];
        $time_group_manager = new TimeGroupManager(new Context(), $time_groups);

        $totals = $time_group_manager->totalOpenedDebitAmount([
            $asset_account->id,
            $expense_account->id
        ]);

        $this->assertEquals($totals, [
            [ RationalNumber::get("0") ],
            [ RationalNumber::get("2000") ]
        ]);
    }

    public function testTotalOpenedCreditAmount()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity = $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $equity_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EQUITY_ACCOUNT_KIND
        ])->create();
        $asset_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => LIQUID_ASSET_ACCOUNT_KIND
        ])->create();
        $expense_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EXPENSE_ACCOUNT_KIND
        ])->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $first_frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at" => Time::parse("-3 day")->toDateTimeString(),
            "finished_at" => Time::parse("-2 day")->toDateTimeString()
        ])->create();
        $summary_calculation_fabricator = new Fabricator(SummaryCalculationModel::class);
        $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id
        ]);
        $first_equity_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $equity_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "2500",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "2000"
        ])->create();
        $first_asset_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $asset_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "2500",
            "unadjusted_credit_amount" => "500",
            "closed_debit_amount" => "2000",
            "closed_credit_amount" => "0"
        ])->create();
        $first_expense_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $expense_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "500",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "0"
        ])->create();
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
        $first_equity_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $equity_account->id,
            "net_amount" => "2500"
        ])->create();
        $first_expense_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $expense_account->id,
            "net_amount" => "0"
        ])->create();
        $second_frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at" => Time::yesterday()->toDateTimeString(),
            "finished_at" => Time::now()->toDateTimeString()
        ])->create();
        $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id
        ]);
        $second_equity_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "account_id" => $equity_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "2000",
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "4000",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "3500"
        ])->create();
        $second_asset_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "account_id" => $asset_account->id,
            "opened_debit_amount" => "2000",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "4000",
            "unadjusted_credit_amount" => "500",
            "closed_debit_amount" => "3500",
            "closed_credit_amount" => "0"
        ])->create();
        $second_expense_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "account_id" => $expense_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "500",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "0"
        ])->create();
        $second_equity_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $equity_account->id,
            "net_amount" => "500"
        ])->create();
        $second_expense_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $expense_account->id,
            "net_amount" => "0"
        ])->create();

        $time_groups = [
            new PeriodicTimeGroup($first_frozen_period),
            new PeriodicTimeGroup($second_frozen_period)
        ];
        $time_group_manager = new TimeGroupManager(new Context(), $time_groups);

        $totals = $time_group_manager->totalOpenedCreditAmount([
            $equity_account->id
        ]);

        $this->assertEquals($totals, [
            [ RationalNumber::get("0") ],
            [ RationalNumber::get("2000") ]
        ]);
    }

    public function testTotalUnadjustedDebitAmount()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity = $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $equity_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EQUITY_ACCOUNT_KIND
        ])->create();
        $asset_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => LIQUID_ASSET_ACCOUNT_KIND
        ])->create();
        $expense_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EXPENSE_ACCOUNT_KIND
        ])->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $first_frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at" => Time::parse("-3 day")->toDateTimeString(),
            "finished_at" => Time::parse("-2 day")->toDateTimeString()
        ])->create();
        $summary_calculation_fabricator = new Fabricator(SummaryCalculationModel::class);
        $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id
        ]);
        $first_equity_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $equity_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "2500",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "2000"
        ])->create();
        $first_asset_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $asset_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "2500",
            "unadjusted_credit_amount" => "500",
            "closed_debit_amount" => "2000",
            "closed_credit_amount" => "0"
        ])->create();
        $first_expense_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $expense_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "500",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "0"
        ])->create();
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
        $first_equity_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $equity_account->id,
            "net_amount" => "2500"
        ])->create();
        $first_expense_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $expense_account->id,
            "net_amount" => "0"
        ])->create();
        $second_frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at" => Time::yesterday()->toDateTimeString(),
            "finished_at" => Time::now()->toDateTimeString()
        ])->create();
        $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id
        ]);
        $second_equity_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "account_id" => $equity_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "2000",
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "4000",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "3500"
        ])->create();
        $second_asset_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "account_id" => $asset_account->id,
            "opened_debit_amount" => "2000",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "4000",
            "unadjusted_credit_amount" => "500",
            "closed_debit_amount" => "3500",
            "closed_credit_amount" => "0"
        ])->create();
        $second_expense_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "account_id" => $expense_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "500",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "0"
        ])->create();
        $second_equity_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $equity_account->id,
            "net_amount" => "500"
        ])->create();
        $second_expense_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $expense_account->id,
            "net_amount" => "0"
        ])->create();

        $time_groups = [
            new PeriodicTimeGroup($first_frozen_period),
            new PeriodicTimeGroup($second_frozen_period)
        ];
        $time_group_manager = new TimeGroupManager(new Context(), $time_groups);

        $totals = $time_group_manager->totalUnadjustedDebitAmount([
            $asset_account->id,
            $expense_account->id
        ]);

        $this->assertEquals($totals, [
            [ RationalNumber::get("3000") ],
            [ RationalNumber::get("4500") ]
        ]);
    }

    public function testTotalUnadjustedCreditAmount()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity = $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $equity_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EQUITY_ACCOUNT_KIND
        ])->create();
        $asset_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => LIQUID_ASSET_ACCOUNT_KIND
        ])->create();
        $expense_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EXPENSE_ACCOUNT_KIND
        ])->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $first_frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at" => Time::parse("-3 day")->toDateTimeString(),
            "finished_at" => Time::parse("-2 day")->toDateTimeString()
        ])->create();
        $summary_calculation_fabricator = new Fabricator(SummaryCalculationModel::class);
        $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id
        ]);
        $first_equity_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $equity_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "2500",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "2000"
        ])->create();
        $first_asset_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $asset_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "2500",
            "unadjusted_credit_amount" => "500",
            "closed_debit_amount" => "2000",
            "closed_credit_amount" => "0"
        ])->create();
        $first_expense_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $expense_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "500",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "0"
        ])->create();
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
        $first_equity_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $equity_account->id,
            "net_amount" => "2500"
        ])->create();
        $first_expense_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $expense_account->id,
            "net_amount" => "0"
        ])->create();
        $second_frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at" => Time::yesterday()->toDateTimeString(),
            "finished_at" => Time::now()->toDateTimeString()
        ])->create();
        $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id
        ]);
        $second_equity_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "account_id" => $equity_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "2000",
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "4000",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "3500"
        ])->create();
        $second_asset_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "account_id" => $asset_account->id,
            "opened_debit_amount" => "2000",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "4000",
            "unadjusted_credit_amount" => "500",
            "closed_debit_amount" => "3500",
            "closed_credit_amount" => "0"
        ])->create();
        $second_expense_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "account_id" => $expense_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "500",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "0"
        ])->create();
        $second_equity_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $equity_account->id,
            "net_amount" => "500"
        ])->create();
        $second_expense_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $expense_account->id,
            "net_amount" => "0"
        ])->create();

        $time_groups = [
            new PeriodicTimeGroup($first_frozen_period),
            new PeriodicTimeGroup($second_frozen_period)
        ];
        $time_group_manager = new TimeGroupManager(new Context(), $time_groups);

        $totals = $time_group_manager->totalUnadjustedCreditAmount([
            $equity_account->id
        ]);

        $this->assertEquals($totals, [
            [ RationalNumber::get("2500") ],
            [ RationalNumber::get("4000") ]
        ]);
    }

    public function testTotalClosedDebitAmount()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity = $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $equity_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EQUITY_ACCOUNT_KIND
        ])->create();
        $asset_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => LIQUID_ASSET_ACCOUNT_KIND
        ])->create();
        $expense_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EXPENSE_ACCOUNT_KIND
        ])->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $first_frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at" => Time::parse("-3 day")->toDateTimeString(),
            "finished_at" => Time::parse("-2 day")->toDateTimeString()
        ])->create();
        $summary_calculation_fabricator = new Fabricator(SummaryCalculationModel::class);
        $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id
        ]);
        $first_equity_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $equity_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "2500",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "2000"
        ])->create();
        $first_asset_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $asset_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "2500",
            "unadjusted_credit_amount" => "500",
            "closed_debit_amount" => "2000",
            "closed_credit_amount" => "0"
        ])->create();
        $first_expense_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $expense_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "500",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "0"
        ])->create();
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
        $first_equity_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $equity_account->id,
            "net_amount" => "2500"
        ])->create();
        $first_expense_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $expense_account->id,
            "net_amount" => "0"
        ])->create();
        $second_frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at" => Time::yesterday()->toDateTimeString(),
            "finished_at" => Time::now()->toDateTimeString()
        ])->create();
        $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id
        ]);
        $second_equity_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "account_id" => $equity_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "2000",
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "4000",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "3500"
        ])->create();
        $second_asset_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "account_id" => $asset_account->id,
            "opened_debit_amount" => "2000",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "4000",
            "unadjusted_credit_amount" => "500",
            "closed_debit_amount" => "3500",
            "closed_credit_amount" => "0"
        ])->create();
        $second_expense_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "account_id" => $expense_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "500",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "0"
        ])->create();
        $second_equity_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $equity_account->id,
            "net_amount" => "500"
        ])->create();
        $second_expense_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $expense_account->id,
            "net_amount" => "0"
        ])->create();

        $time_groups = [
            new PeriodicTimeGroup($first_frozen_period),
            new PeriodicTimeGroup($second_frozen_period)
        ];
        $time_group_manager = new TimeGroupManager(new Context(), $time_groups);

        $totals = $time_group_manager->totalClosedDebitAmount([
            $asset_account->id,
            $expense_account->id
        ]);

        $this->assertEquals($totals, [
            [ RationalNumber::get("2000") ],
            [ RationalNumber::get("3500") ]
        ]);
    }

    public function testTotalClosedCreditAmount()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity = $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $equity_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EQUITY_ACCOUNT_KIND
        ])->create();
        $asset_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => LIQUID_ASSET_ACCOUNT_KIND
        ])->create();
        $expense_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EXPENSE_ACCOUNT_KIND
        ])->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $first_frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at" => Time::parse("-3 day")->toDateTimeString(),
            "finished_at" => Time::parse("-2 day")->toDateTimeString()
        ])->create();
        $summary_calculation_fabricator = new Fabricator(SummaryCalculationModel::class);
        $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id
        ]);
        $first_equity_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $equity_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "2500",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "2000"
        ])->create();
        $first_asset_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $asset_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "2500",
            "unadjusted_credit_amount" => "500",
            "closed_debit_amount" => "2000",
            "closed_credit_amount" => "0"
        ])->create();
        $first_expense_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $expense_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "500",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "0"
        ])->create();
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
        $first_equity_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $equity_account->id,
            "net_amount" => "2500"
        ])->create();
        $first_expense_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $expense_account->id,
            "net_amount" => "0"
        ])->create();
        $second_frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at" => Time::yesterday()->toDateTimeString(),
            "finished_at" => Time::now()->toDateTimeString()
        ])->create();
        $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id
        ]);
        $second_equity_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "account_id" => $equity_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "2000",
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "4000",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "3500"
        ])->create();
        $second_asset_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "account_id" => $asset_account->id,
            "opened_debit_amount" => "2000",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "4000",
            "unadjusted_credit_amount" => "500",
            "closed_debit_amount" => "3500",
            "closed_credit_amount" => "0"
        ])->create();
        $second_expense_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "account_id" => $expense_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "500",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "0"
        ])->create();
        $second_equity_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $equity_account->id,
            "net_amount" => "500"
        ])->create();
        $second_expense_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $expense_account->id,
            "net_amount" => "0"
        ])->create();

        $time_groups = [
            new PeriodicTimeGroup($first_frozen_period),
            new PeriodicTimeGroup($second_frozen_period)
        ];
        $time_group_manager = new TimeGroupManager(new Context(), $time_groups);

        $totals = $time_group_manager->totalClosedCreditAmount([
            $equity_account->id
        ]);

        $this->assertEquals($totals, [
            [ RationalNumber::get("2000") ],
            [ RationalNumber::get("3500") ]
        ]);
    }

    public function testTotalClosedCreditAmountWithUnfrozenTimeGroup()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity = $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $equity_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EQUITY_ACCOUNT_KIND
        ])->create();
        $asset_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => LIQUID_ASSET_ACCOUNT_KIND
        ])->create();
        $expense_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EXPENSE_ACCOUNT_KIND
        ])->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $normal_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $asset_account->id,
            "credit_account_id" => $equity_account->id,
            "debit_cash_flow_activity_id" => null,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $expense_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $expense_account->id,
            "credit_account_id" => $asset_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => null,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $close_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $equity_account->id,
            "credit_account_id" => $expense_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id,
            "action" => CLOSE_MODIFIER_ACTION
        ])->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $recorded_normal_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $normal_record_modifier->id,
            "debit_amount" => "1000",
            "credit_amount" => "1000"
        ])->create();
        $recorded_expense_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $expense_record_modifier->id,
            "debit_amount" => "250",
            "credit_amount" => "250"
        ])->create();
        $closed_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $close_modifier->id,
            "debit_amount" => $recorded_expense_financial_entry->credit_amount,
            "credit_amount" => $recorded_expense_financial_entry->debit_amount
        ])->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $first_frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at" => Time::parse("-3 day")->toDateTimeString(),
            "finished_at" => Time::parse("-2 day")->toDateTimeString()
        ])->create();
        $summary_calculation_fabricator = new Fabricator(SummaryCalculationModel::class);
        $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id
        ]);
        $first_equity_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $equity_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "2500",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "2000"
        ])->create();
        $first_asset_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $asset_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "2500",
            "unadjusted_credit_amount" => "500",
            "closed_debit_amount" => "2000",
            "closed_credit_amount" => "0"
        ])->create();
        $first_expense_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $expense_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "500",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "0"
        ])->create();
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
        $first_equity_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $equity_account->id,
            "net_amount" => "2500"
        ])->create();
        $first_expense_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $expense_account->id,
            "net_amount" => "0"
        ])->create();

        $time_groups = [
            new PeriodicTimeGroup($first_frozen_period),
            UnfrozenTimeGroup::make(Time::yesterday(), Time::now())
        ];
        $time_group_manager = new TimeGroupManager(new Context(), $time_groups);

        $totals = $time_group_manager->totalClosedCreditAmount([
            $equity_account->id
        ]);

        $this->assertEquals($totals, [
            [ RationalNumber::get("2000") ],
            [ RationalNumber::get("2750") ]
        ]);
    }

    public function testTotalClosedCreditAmountWithSemiUnfrozenYearlyTimeGroup()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity = $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $equity_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EQUITY_ACCOUNT_KIND
        ])->create();
        $asset_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => LIQUID_ASSET_ACCOUNT_KIND
        ])->create();
        $expense_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EXPENSE_ACCOUNT_KIND
        ])->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $normal_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $asset_account->id,
            "credit_account_id" => $equity_account->id,
            "debit_cash_flow_activity_id" => null,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $expense_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $expense_account->id,
            "credit_account_id" => $asset_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => null,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $close_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $equity_account->id,
            "credit_account_id" => $expense_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id,
            "action" => CLOSE_MODIFIER_ACTION
        ])->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $recorded_normal_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $normal_record_modifier->id,
            "debit_amount" => "1000",
            "credit_amount" => "1000"
        ])->create();
        $recorded_expense_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $expense_record_modifier->id,
            "debit_amount" => "250",
            "credit_amount" => "250"
        ])->create();
        $closed_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $close_modifier->id,
            "debit_amount" => $recorded_expense_financial_entry->credit_amount,
            "credit_amount" => $recorded_expense_financial_entry->debit_amount
        ])->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $first_frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at" => Time::parse("-3 day")->toDateTimeString(),
            "finished_at" => Time::parse("-2 day")->toDateTimeString()
        ])->create();
        $summary_calculation_fabricator = new Fabricator(SummaryCalculationModel::class);
        $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id
        ]);
        $first_equity_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $equity_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "2500",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "2000"
        ])->create();
        $first_asset_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $asset_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "2500",
            "unadjusted_credit_amount" => "500",
            "closed_debit_amount" => "2000",
            "closed_credit_amount" => "0"
        ])->create();
        $first_expense_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $expense_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "500",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "0"
        ])->create();
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
        $first_equity_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $equity_account->id,
            "net_amount" => "2500"
        ])->create();
        $first_expense_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $expense_account->id,
            "net_amount" => "0"
        ])->create();

        $first_year_time_subgroups = [
            new PeriodicTimeGroup($first_frozen_period),
            UnfrozenTimeGroup::make(Time::yesterday(), Time::now())
        ];
        $first_year_time_group = new YearlyTimeGroup(Time::now()->year, true);
        foreach ($first_year_time_subgroups as $time_subgroup) {
            $first_year_time_group->addTimeGroup($time_subgroup);
        }
        $time_groups = [
            $first_year_time_group
        ];
        $time_group_manager = new TimeGroupManager(new Context(), $time_groups);

        $totals = $time_group_manager->totalClosedCreditAmount([
            $equity_account->id
        ]);

        $this->assertEquals($totals, [
            [ RationalNumber::get("2000"), RationalNumber::get("2750") ]
        ]);
    }

    public function testTotalClosedCreditAmountWithFullyYearlyTimeGroup()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity = $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $equity_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EQUITY_ACCOUNT_KIND
        ])->create();
        $asset_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => LIQUID_ASSET_ACCOUNT_KIND
        ])->create();
        $expense_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EXPENSE_ACCOUNT_KIND
        ])->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $first_frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at" => Time::parse("-3 day")->toDateTimeString(),
            "finished_at" => Time::parse("-2 day")->toDateTimeString()
        ])->create();
        $summary_calculation_fabricator = new Fabricator(SummaryCalculationModel::class);
        $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id
        ]);
        $first_equity_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $equity_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "2500",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "2000"
        ])->create();
        $first_asset_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $asset_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "2500",
            "unadjusted_credit_amount" => "500",
            "closed_debit_amount" => "2000",
            "closed_credit_amount" => "0"
        ])->create();
        $first_expense_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $expense_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "500",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "0"
        ])->create();
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
        $first_equity_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $equity_account->id,
            "net_amount" => "2500"
        ])->create();
        $first_expense_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $expense_account->id,
            "net_amount" => "0"
        ])->create();
        $second_frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at" => Time::yesterday()->toDateTimeString(),
            "finished_at" => Time::now()->toDateTimeString()
        ])->create();
        $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id
        ]);
        $second_equity_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "account_id" => $equity_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "2000",
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "4000",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "3500"
        ])->create();
        $second_asset_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "account_id" => $asset_account->id,
            "opened_debit_amount" => "2000",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "4000",
            "unadjusted_credit_amount" => "500",
            "closed_debit_amount" => "3500",
            "closed_credit_amount" => "0"
        ])->create();
        $second_expense_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "account_id" => $expense_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "500",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "0"
        ])->create();
        $second_equity_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $equity_account->id,
            "net_amount" => "500"
        ])->create();
        $second_expense_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $expense_account->id,
            "net_amount" => "0"
        ])->create();

        $first_year_time_subgroups = [
            new PeriodicTimeGroup($first_frozen_period),
            new PeriodicTimeGroup($second_frozen_period)
        ];
        $first_year_time_group = new YearlyTimeGroup(Time::now()->year, true);
        foreach ($first_year_time_subgroups as $time_subgroup) {
            $first_year_time_group->addTimeGroup($time_subgroup);
        }
        $time_groups = [
            $first_year_time_group
        ];
        $time_group_manager = new TimeGroupManager(new Context(), $time_groups);

        $totals = $time_group_manager->totalClosedCreditAmount([
            $equity_account->id
        ]);

        $this->assertEquals($totals, [
            [ RationalNumber::get("2000"), RationalNumber::get("3500") ]
        ]);
    }
}
