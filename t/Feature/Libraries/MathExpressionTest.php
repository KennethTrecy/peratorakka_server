<?php

namespace Tests\Feature\Libraries;

use App\Casts\RationalNumber;
use App\Exceptions\ExpressionException;
use App\Libraries\MathExpression;
use App\Libraries\Context;
use App\Libraries\Context\ContextKeys;
use App\Libraries\TimeGroup\PeriodicTimeGroup;
use App\Libraries\TimeGroup\YearlyTimeGroup;
use App\Libraries\Context\TimeGroupManager;
use App\Models\AccountCollectionModel;
use App\Models\AccountModel;
use App\Models\CashFlowActivityModel;
use App\Models\CollectionModel;
use App\Models\CurrencyModel;
use App\Models\FinancialEntryModel;
use App\Models\FlowCalculationModel;
use App\Models\FrozenPeriodModel;
use App\Models\ModifierModel;
use App\Models\SummaryCalculationModel;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\Fabricator;
use Tests\Feature\Helper\AuthenticatedHTTPTestCase;

class MathExpressionTest extends AuthenticatedHTTPTestCase
{
    public function testTotalUnadjustedDebitAmountForExpenseKind()
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
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $equity_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Equities"
        ])->create();
        $asset_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Assets"
        ])->create();
        $expense_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Expenses"
        ])->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $collected_equity_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $equity_collection->id,
            "account_id" => $equity_account->id
        ])->create();
        $collected_asset_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $asset_collection->id,
            "account_id" => $asset_account->id
        ])->create();
        $collected_expense_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $expense_collection->id,
            "account_id" => $expense_account->id
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
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
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
        $math_expression = new MathExpression($time_group_manager);

        $formula = "TOTAL_UNADJUSTED_DEBIT_AMOUNT(EXPENSE_ACCOUNTS)";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            RationalNumber::get("500"),
            RationalNumber::get("500")
        ]);
    }

    public function testTotalOpenedDebitAmountForAssetCollection()
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
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $equity_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Equities"
        ])->create();
        $asset_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Assets"
        ])->create();
        $expense_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Expenses"
        ])->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $collected_equity_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $equity_collection->id,
            "account_id" => $equity_account->id
        ])->create();
        $collected_asset_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $asset_collection->id,
            "account_id" => $asset_account->id
        ])->create();
        $collected_expense_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $expense_collection->id,
            "account_id" => $expense_account->id
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
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
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
        $math_expression = new MathExpression($time_group_manager);

        $formula = "TOTAL_OPENED_DEBIT_AMOUNT(COLLECTION[$asset_collection->id])";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            RationalNumber::get("0"),
            RationalNumber::get("2000")
        ]);
    }

    public function testTotalOpenedCreditAmountForEquityCollection()
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
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $equity_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Equities"
        ])->create();
        $asset_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Assets"
        ])->create();
        $expense_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Expenses"
        ])->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $collected_equity_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $equity_collection->id,
            "account_id" => $equity_account->id
        ])->create();
        $collected_asset_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $asset_collection->id,
            "account_id" => $asset_account->id
        ])->create();
        $collected_expense_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $expense_collection->id,
            "account_id" => $expense_account->id
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
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
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
        $math_expression = new MathExpression($time_group_manager);

        $formula = "TOTAL_OPENED_CREDIT_AMOUNT(COLLECTION[$equity_collection->id])";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            RationalNumber::get("0"),
            RationalNumber::get("2000")
        ]);
    }

    public function testTotalUnadjustedDebitAmountForExpenseCollection()
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
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $equity_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Equities"
        ])->create();
        $asset_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Assets"
        ])->create();
        $expense_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Expenses"
        ])->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $collected_equity_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $equity_collection->id,
            "account_id" => $equity_account->id
        ])->create();
        $collected_asset_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $asset_collection->id,
            "account_id" => $asset_account->id
        ])->create();
        $collected_expense_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $expense_collection->id,
            "account_id" => $expense_account->id
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
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
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
        $math_expression = new MathExpression($time_group_manager);

        $formula = "TOTAL_UNADJUSTED_DEBIT_AMOUNT(COLLECTION[$expense_collection->id])";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            RationalNumber::get("500"),
            RationalNumber::get("500")
        ]);
    }

    public function testTotalUnadjustedCreditAmountForEquityCollection()
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
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $equity_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Equities"
        ])->create();
        $asset_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Assets"
        ])->create();
        $expense_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Expenses"
        ])->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $collected_equity_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $equity_collection->id,
            "account_id" => $equity_account->id
        ])->create();
        $collected_asset_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $asset_collection->id,
            "account_id" => $asset_account->id
        ])->create();
        $collected_expense_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $expense_collection->id,
            "account_id" => $expense_account->id
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
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
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
        $math_expression = new MathExpression($time_group_manager);

        $formula = "TOTAL_UNADJUSTED_CREDIT_AMOUNT(COLLECTION[$equity_collection->id])";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            RationalNumber::get("2500"),
            RationalNumber::get("4000")
        ]);
    }
    public function testTotalClosedDebitAmountForAssetCollection()
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
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $equity_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Equities"
        ])->create();
        $asset_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Assets"
        ])->create();
        $expense_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Expenses"
        ])->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $collected_equity_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $equity_collection->id,
            "account_id" => $equity_account->id
        ])->create();
        $collected_asset_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $asset_collection->id,
            "account_id" => $asset_account->id
        ])->create();
        $collected_expense_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $expense_collection->id,
            "account_id" => $expense_account->id
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
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
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
        $math_expression = new MathExpression($time_group_manager);

        $formula = "TOTAL_CLOSED_DEBIT_AMOUNT(COLLECTION[$asset_collection->id])";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            RationalNumber::get("2000"),
            RationalNumber::get("3500")
        ]);
    }

    public function testTotalClosedCreditAmountForEquityCollection()
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
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $equity_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Equities"
        ])->create();
        $asset_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Assets"
        ])->create();
        $expense_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Expenses"
        ])->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $collected_equity_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $equity_collection->id,
            "account_id" => $equity_account->id
        ])->create();
        $collected_asset_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $asset_collection->id,
            "account_id" => $asset_account->id
        ])->create();
        $collected_expense_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $expense_collection->id,
            "account_id" => $expense_account->id
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
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
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
        $math_expression = new MathExpression($time_group_manager);

        $formula = "TOTAL_CLOSED_CREDIT_AMOUNT(COLLECTION[$equity_collection->id])";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            RationalNumber::get("2000"),
            RationalNumber::get("3500")
        ]);
    }

    public function testFailurelDueToUnexpectedValueForTotalFunctions()
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
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $equity_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Equities"
        ])->create();
        $asset_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Assets"
        ])->create();
        $expense_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Expenses"
        ])->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $collected_equity_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $equity_collection->id,
            "account_id" => $equity_account->id
        ])->create();
        $collected_asset_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $asset_collection->id,
            "account_id" => $asset_account->id
        ])->create();
        $collected_expense_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $expense_collection->id,
            "account_id" => $expense_account->id
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
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
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
        $math_expression = new MathExpression($time_group_manager);

        $this->expectException(ExpressionException::class);
        $formula = "TOTAL_CLOSED_CREDIT_AMOUNT(#123)";
        $totals = $math_expression->evaluate($formula);
    }

    public function testRobustnesslDueToUncollectedAssets()
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
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $equity_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Equities"
        ])->create();
        $asset_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Assets"
        ])->create();
        $expense_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Expenses"
        ])->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $collected_equity_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $equity_collection->id,
            "account_id" => $equity_account->id
        ])->create();
        $collected_expense_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $expense_collection->id,
            "account_id" => $expense_account->id
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
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
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
        $math_expression = new MathExpression($time_group_manager);

        $formula = "TOTAL_CLOSED_DEBIT_AMOUNT(COLLECTION[$asset_collection->id])";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            RationalNumber::get("0"),
            RationalNumber::get("0")
        ]);
    }

    public function testRawRightHandAdditionToTotalClosedDebitAmountForAssetCollection()
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
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $equity_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Equities"
        ])->create();
        $asset_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Assets"
        ])->create();
        $expense_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Expenses"
        ])->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $collected_equity_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $equity_collection->id,
            "account_id" => $equity_account->id
        ])->create();
        $collected_asset_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $asset_collection->id,
            "account_id" => $asset_account->id
        ])->create();
        $collected_expense_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $expense_collection->id,
            "account_id" => $expense_account->id
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
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
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
        $math_expression = new MathExpression($time_group_manager);

        $formula = "TOTAL_CLOSED_DEBIT_AMOUNT(COLLECTION[$asset_collection->id]) + 1";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            RationalNumber::get("2001"),
            RationalNumber::get("3501")
        ]);
    }

    public function testRawLeftHandAdditionToTotalClosedDebitAmountForAssetCollection()
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
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $equity_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Equities"
        ])->create();
        $asset_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Assets"
        ])->create();
        $expense_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Expenses"
        ])->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $collected_equity_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $equity_collection->id,
            "account_id" => $equity_account->id
        ])->create();
        $collected_asset_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $asset_collection->id,
            "account_id" => $asset_account->id
        ])->create();
        $collected_expense_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $expense_collection->id,
            "account_id" => $expense_account->id
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
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
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
        $math_expression = new MathExpression($time_group_manager);

        $formula = "2 + TOTAL_CLOSED_DEBIT_AMOUNT(COLLECTION[$asset_collection->id])";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            RationalNumber::get("2002"),
            RationalNumber::get("3502")
        ]);
    }

    public function testRawRightHandSubtractionToTotalClosedDebitAmountForAssetCollection()
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
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $equity_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Equities"
        ])->create();
        $asset_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Assets"
        ])->create();
        $expense_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Expenses"
        ])->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $collected_equity_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $equity_collection->id,
            "account_id" => $equity_account->id
        ])->create();
        $collected_asset_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $asset_collection->id,
            "account_id" => $asset_account->id
        ])->create();
        $collected_expense_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $expense_collection->id,
            "account_id" => $expense_account->id
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
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
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
        $math_expression = new MathExpression($time_group_manager);

        $formula = "TOTAL_CLOSED_DEBIT_AMOUNT(COLLECTION[$asset_collection->id]) - 1";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            RationalNumber::get("1999"),
            RationalNumber::get("3499")
        ]);
    }

    public function testRawLeftHandSubtractionToTotalClosedDebitAmountForAssetCollection()
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
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $equity_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Equities"
        ])->create();
        $asset_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Assets"
        ])->create();
        $expense_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Expenses"
        ])->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $collected_equity_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $equity_collection->id,
            "account_id" => $equity_account->id
        ])->create();
        $collected_asset_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $asset_collection->id,
            "account_id" => $asset_account->id
        ])->create();
        $collected_expense_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $expense_collection->id,
            "account_id" => $expense_account->id
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
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
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
        $math_expression = new MathExpression($time_group_manager);

        $formula = "2 - TOTAL_CLOSED_DEBIT_AMOUNT(COLLECTION[$asset_collection->id])";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            RationalNumber::get("-1998"),
            RationalNumber::get("-3498")
        ]);
    }

    public function testRawRightHandMultiplicationToTotalClosedDebitAmountForAssetCollection()
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
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $equity_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Equities"
        ])->create();
        $asset_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Assets"
        ])->create();
        $expense_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Expenses"
        ])->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $collected_equity_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $equity_collection->id,
            "account_id" => $equity_account->id
        ])->create();
        $collected_asset_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $asset_collection->id,
            "account_id" => $asset_account->id
        ])->create();
        $collected_expense_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $expense_collection->id,
            "account_id" => $expense_account->id
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
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
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
        $math_expression = new MathExpression($time_group_manager);

        $formula = "TOTAL_CLOSED_DEBIT_AMOUNT(COLLECTION[$asset_collection->id]) * 2";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            RationalNumber::get("4000"),
            RationalNumber::get("7000")
        ]);
    }

    public function testRawLeftHandMultiplicationToTotalClosedDebitAmountForAssetCollection()
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
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $equity_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Equities"
        ])->create();
        $asset_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Assets"
        ])->create();
        $expense_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Expenses"
        ])->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $collected_equity_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $equity_collection->id,
            "account_id" => $equity_account->id
        ])->create();
        $collected_asset_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $asset_collection->id,
            "account_id" => $asset_account->id
        ])->create();
        $collected_expense_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $expense_collection->id,
            "account_id" => $expense_account->id
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
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
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
        $math_expression = new MathExpression($time_group_manager);

        $formula = "2 * TOTAL_CLOSED_DEBIT_AMOUNT(COLLECTION[$asset_collection->id])";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            RationalNumber::get("4000"),
            RationalNumber::get("7000")
        ]);
    }

    public function testRawRightHandDivisionToTotalClosedDebitAmountForAssetCollection()
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
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $equity_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Equities"
        ])->create();
        $asset_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Assets"
        ])->create();
        $expense_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Expenses"
        ])->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $collected_equity_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $equity_collection->id,
            "account_id" => $equity_account->id
        ])->create();
        $collected_asset_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $asset_collection->id,
            "account_id" => $asset_account->id
        ])->create();
        $collected_expense_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $expense_collection->id,
            "account_id" => $expense_account->id
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
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
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
        $math_expression = new MathExpression($time_group_manager);

        $formula = "TOTAL_CLOSED_DEBIT_AMOUNT(COLLECTION[$asset_collection->id]) / 2";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            RationalNumber::get("1000"),
            RationalNumber::get("1750")
        ]);
    }

    public function testRawLeftHandDivisionToTotalClosedDebitAmountForAssetCollection()
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
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $equity_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Equities"
        ])->create();
        $asset_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Assets"
        ])->create();
        $expense_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Expenses"
        ])->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $collected_equity_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $equity_collection->id,
            "account_id" => $equity_account->id
        ])->create();
        $collected_asset_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $asset_collection->id,
            "account_id" => $asset_account->id
        ])->create();
        $collected_expense_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $expense_collection->id,
            "account_id" => $expense_account->id
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
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
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
        $math_expression = new MathExpression($time_group_manager);

        $formula = "2 / TOTAL_CLOSED_DEBIT_AMOUNT(COLLECTION[$asset_collection->id])";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            RationalNumber::get("0.001"),
            RationalNumber::get("1/1750")
        ]);
    }

    public function testCollectiveLeftHandDivisionToTotalClosedDebitAmountForAssetCollection()
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
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $equity_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Equities"
        ])->create();
        $asset_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Assets"
        ])->create();
        $expense_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Expenses"
        ])->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $collected_equity_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $equity_collection->id,
            "account_id" => $equity_account->id
        ])->create();
        $collected_asset_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $asset_collection->id,
            "account_id" => $asset_account->id
        ])->create();
        $collected_expense_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $expense_collection->id,
            "account_id" => $expense_account->id
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
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
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
        $math_expression = new MathExpression($time_group_manager);

        $formula = "TOTAL_CLOSED_DEBIT_AMOUNT(COLLECTION[$asset_collection->id]) / TOTAL_CLOSED_CREDIT_AMOUNT(COLLECTION[$equity_collection->id])";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            RationalNumber::get("1"),
            RationalNumber::get("1")
        ]);
    }

    public function testExchangedTotalOpenedDebitAmountForAssetCollection()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_a = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $currency_b = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity = $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $equity_a_account = $account_fabricator->setOverrides([
            "currency_id" => $currency_a->id,
            "kind" => EQUITY_ACCOUNT_KIND
        ])->create();
        $equity_b_account = $account_fabricator->setOverrides([
            "currency_id" => $currency_b->id,
            "kind" => EQUITY_ACCOUNT_KIND
        ])->create();
        $asset_a_account = $account_fabricator->setOverrides([
            "currency_id" => $currency_a->id,
            "kind" => LIQUID_ASSET_ACCOUNT_KIND
        ])->create();
        $asset_b_account = $account_fabricator->setOverrides([
            "currency_id" => $currency_b->id,
            "kind" => LIQUID_ASSET_ACCOUNT_KIND
        ])->create();
        $expense_account = $account_fabricator->setOverrides([
            "currency_id" => $currency_a->id,
            "kind" => EXPENSE_ACCOUNT_KIND
        ])->create();
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $equity_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Equities"
        ])->create();
        $asset_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Assets"
        ])->create();
        $expense_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Expenses"
        ])->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $collected_equity_a_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $equity_collection->id,
            "account_id" => $equity_a_account->id
        ])->create();
        $collected_equity_b_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $equity_collection->id,
            "account_id" => $equity_b_account->id
        ])->create();
        $collected_asset_a_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $asset_collection->id,
            "account_id" => $asset_a_account->id
        ])->create();
        $collected_asset_b_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $asset_collection->id,
            "account_id" => $asset_b_account->id
        ])->create();
        $collected_expense_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $expense_collection->id,
            "account_id" => $expense_account->id
        ])->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $normal_exchange_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $asset_a_account->id,
            "credit_account_id" => $asset_b_account->id,
            "action" => EXCHANGE_MODIFIER_ACTION
        ])->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $exchange_normal_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $normal_exchange_modifier->id,
            "debit_amount" => "100",
            "credit_amount" => "1",
            "transacted_at" => Time::parse("-4 day")->toDateTimeString()
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
        $first_equity_a_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $equity_a_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "2500",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "2000"
        ])->create();
        $first_asset_a_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $asset_a_account->id,
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
        $first_equity_b_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $equity_b_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "10",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "10"
        ])->create();
        $first_asset_b_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $asset_b_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "10",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "10",
            "closed_credit_amount" => "0"
        ])->create();
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
        $first_equity_a_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $equity_a_account->id,
            "net_amount" => "2500"
        ])->create();
        $first_equity_b_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $equity_b_account->id,
            "net_amount" => "10"
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
        $second_equity_a_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "account_id" => $equity_a_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "2000",
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "4000",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "3500"
        ])->create();
        $second_asset_a_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "account_id" => $asset_a_account->id,
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
        $second_equity_b_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "account_id" => $equity_b_account->id,
            "opened_debit_amount" => "0",
            "opened_credit_amount" => "10",
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "30",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "30"
        ])->create();
        $second_asset_b_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "account_id" => $asset_b_account->id,
            "opened_debit_amount" => "10",
            "opened_credit_amount" => "0",
            "unadjusted_debit_amount" => "30",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "30",
            "closed_credit_amount" => "0"
        ])->create();
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
        $second_equity_a_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $equity_a_account->id,
            "net_amount" => "500"
        ])->create();
        $second_equity_b_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $equity_b_account->id,
            "net_amount" => "20"
        ])->create();
        $second_expense_flow_calculation = $flow_calculation_fabricator->setOverrides([
            "frozen_period_id" => $second_frozen_period->id,
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "account_id" => $expense_account->id,
            "net_amount" => "0"
        ])->create();

        $context = new Context();
        $context->setVariable(ContextKeys::DESTINATION_CURRENCY_ID, $currency_a->id);
        $time_groups = [
            new PeriodicTimeGroup($first_frozen_period),
            new PeriodicTimeGroup($second_frozen_period)
        ];
        $time_group_manager = new TimeGroupManager($context, $time_groups);
        $math_expression = new MathExpression($time_group_manager);

        $formula = "TOTAL_OPENED_DEBIT_AMOUNT(COLLECTION[$asset_collection->id])";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            RationalNumber::get("0"),
            RationalNumber::get("3000")
        ]);
    }

    public function testPeriodicDayCount()
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
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $equity_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Equities"
        ])->create();
        $asset_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Assets"
        ])->create();
        $expense_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Expenses"
        ])->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $collected_equity_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $equity_collection->id,
            "account_id" => $equity_account->id
        ])->create();
        $collected_asset_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $asset_collection->id,
            "account_id" => $asset_account->id
        ])->create();
        $collected_expense_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $expense_collection->id,
            "account_id" => $expense_account->id
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
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
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
        $math_expression = new MathExpression($time_group_manager);

        $formula = "TOTAL_UNADJUSTED_DEBIT_AMOUNT(EXPENSE_ACCOUNTS) / CYCLE_DAY_COUNT";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            RationalNumber::get("250"),
            RationalNumber::get("250")
        ]);
    }

    public function testYearlyDayCount()
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
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $equity_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Equities"
        ])->create();
        $asset_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Assets"
        ])->create();
        $expense_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Expenses"
        ])->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $collected_equity_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $equity_collection->id,
            "account_id" => $equity_account->id
        ])->create();
        $collected_asset_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $asset_collection->id,
            "account_id" => $asset_account->id
        ])->create();
        $collected_expense_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $expense_collection->id,
            "account_id" => $expense_account->id
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
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
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
        $math_expression = new MathExpression($time_group_manager);

        $formula = "TOTAL_UNADJUSTED_DEBIT_AMOUNT(EXPENSE_ACCOUNTS) / CYCLE_DAY_COUNT";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            RationalNumber::get("250")
        ]);
    }

    public function testPeriodicDayPrecountPerYear()
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
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $equity_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Equities"
        ])->create();
        $asset_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Assets"
        ])->create();
        $expense_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Expenses"
        ])->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $collected_equity_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $equity_collection->id,
            "account_id" => $equity_account->id
        ])->create();
        $collected_asset_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $asset_collection->id,
            "account_id" => $asset_account->id
        ])->create();
        $collected_expense_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $expense_collection->id,
            "account_id" => $expense_account->id
        ])->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $first_frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at" => Time::parse("2024/01/15")->toDateTimeString(),
            "finished_at" => Time::parse("2025/01/14")->toDateTimeString()
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
            "started_at" => Time::parse("2025/01/15")->toDateTimeString(),
            "finished_at" => Time::parse("2026/01/14")->toDateTimeString()
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
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
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
        $math_expression = new MathExpression($time_group_manager);

        $formula = "CYCLE_DAY_PRECOUNT_PER_YEAR";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            RationalNumber::get("366"),
            RationalNumber::get("365")
        ]);
    }

    public function testPeriodicDayPostcountPerYear()
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
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $equity_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Equities"
        ])->create();
        $asset_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Assets"
        ])->create();
        $expense_collection = $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "name" => "All Expenses"
        ])->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $collected_equity_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $equity_collection->id,
            "account_id" => $equity_account->id
        ])->create();
        $collected_asset_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $asset_collection->id,
            "account_id" => $asset_account->id
        ])->create();
        $collected_expense_account = $account_collection_fabricator->setOverrides([
            "collection_id" => $expense_collection->id,
            "account_id" => $expense_account->id
        ])->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $first_frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at" => Time::parse("2022/01/15")->toDateTimeString(),
            "finished_at" => Time::parse("2023/01/14")->toDateTimeString()
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
            "started_at" => Time::parse("2023/01/15")->toDateTimeString(),
            "finished_at" => Time::parse("2024/01/14")->toDateTimeString()
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
        $flow_calculation_fabricator = new Fabricator(FlowCalculationModel::class);
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
        $math_expression = new MathExpression($time_group_manager);

        $formula = "CYCLE_DAY_POSTCOUNT_PER_YEAR";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            RationalNumber::get("365"),
            RationalNumber::get("366")
        ]);
    }
}
