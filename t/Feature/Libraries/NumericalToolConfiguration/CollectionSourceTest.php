<?php

namespace Tests\Feature\Libraries\NumericalToolConfiguration;

use App\Casts\RationalNumber;
use App\Libraries\Constellation;
use App\Libraries\Constellation\AcceptableConstellationKind;
use App\Libraries\Constellation\Star;
use App\Libraries\Context;
use App\Libraries\Context\ContextKeys;
use App\Libraries\Context\TimeGroupManager;
use App\Libraries\NumericalToolConfiguration\CollectionSource;
use App\Libraries\TimeGroup\PeriodicTimeGroup;
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

class CollectionSourceTest extends AuthenticatedHTTPTestCase
{
    public function testTotalUnadjustedDebitAmountForExpenseKind()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "presentational_precision" => 2
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
        $context = new Context();
        $time_group_manager = new TimeGroupManager($context, $time_groups);
        $context
            ->getVariable(ContextKeys::COLLECTION_CACHE)
            ->loadCollectedAccounts([ $expense_collection->id ]);

        $collection_source = CollectionSource::parseConfiguration([
            "collection_id" => $expense_collection->id,
            "currency_id" => $currency->id,
            "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
            "stage_basis" => UNADJUSTED_AMOUNT_STAGE_BASIS,
            "side_basis" => DEBIT_AMOUNT_SIDE_BASIS,
            "must_show_individual_amounts" => true,
            "must_show_collective_sum" => true,
            "must_show_collective_average" => true
        ]);
        $constellations = $collection_source->calculate($context);

        $this->assertEquals($constellations, [
            new Constellation($expense_account->name, AcceptableConstellationKind::Account, [
                new Star(
                    "500.00",
                    RationalNumber::get("500")
                ),
                new Star(
                    "500.00",
                    RationalNumber::get("500")
                )
            ]),
            new Constellation(
                "Total of $expense_collection->name",
                AcceptableConstellationKind::Sum,
                [
                    new Star(
                        "500.00",
                        RationalNumber::get("500")
                    ),
                    new Star(
                        "500.00",
                        RationalNumber::get("500")
                    )
                ]
            ),
            new Constellation(
                "Average of $expense_collection->name",
                AcceptableConstellationKind::Average,
                [
                    new Star(
                        "500.00",
                        RationalNumber::get("500")
                    ),
                    new Star(
                        "500.00",
                        RationalNumber::get("500")
                    )
                ]
            )
        ]);
    }

    public function testTotalOpenedDebitAmountForAssetCollection()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "presentational_precision" => 2
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
        $context = new Context();
        $time_group_manager = new TimeGroupManager($context, $time_groups);
        $context
            ->getVariable(ContextKeys::COLLECTION_CACHE)
            ->loadCollectedAccounts([ $asset_collection->id ]);

        $collection_source = CollectionSource::parseConfiguration([
            "collection_id" => $asset_collection->id,
            "currency_id" => $currency->id,
            "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
            "stage_basis" => OPENED_AMOUNT_STAGE_BASIS,
            "side_basis" => DEBIT_AMOUNT_SIDE_BASIS,
            "must_show_individual_amounts" => true,
            "must_show_collective_sum" => true,
            "must_show_collective_average" => true
        ]);
        $constellations = $collection_source->calculate($context);

        $this->assertEquals($constellations, [
            new Constellation($asset_account->name, AcceptableConstellationKind::Account, [
                new Star(
                    "0.00",
                    RationalNumber::get("0")
                ),
                new Star(
                    "2000.00",
                    RationalNumber::get("2000")
                )
            ]),
            new Constellation(
                "Total of $asset_collection->name",
                AcceptableConstellationKind::Sum,
                [
                    new Star(
                        "0.00",
                        RationalNumber::get("0")
                    ),
                    new Star(
                        "2000.00",
                        RationalNumber::get("2000")
                    )
                ]
            ),
            new Constellation(
                "Average of $asset_collection->name",
                AcceptableConstellationKind::Average,
                [
                    new Star(
                        "0.00",
                        RationalNumber::get("0")
                    ),
                    new Star(
                        "2000.00",
                        RationalNumber::get("2000")
                    )
                ]
            )
        ]);
    }

    public function testTotalOpenedCreditAmountForEquityCollection()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "presentational_precision" => 2
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
        $context = new Context();
        $time_group_manager = new TimeGroupManager($context, $time_groups);
        $context
            ->getVariable(ContextKeys::COLLECTION_CACHE)
            ->loadCollectedAccounts([ $equity_collection->id ]);

        $collection_source = CollectionSource::parseConfiguration([
            "collection_id" => $equity_collection->id,
            "currency_id" => $currency->id,
            "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
            "stage_basis" => OPENED_AMOUNT_STAGE_BASIS,
            "side_basis" => CREDIT_AMOUNT_SIDE_BASIS,
            "must_show_individual_amounts" => true,
            "must_show_collective_sum" => true,
            "must_show_collective_average" => true
        ]);
        $constellations = $collection_source->calculate($context);

        $this->assertEquals($constellations, [
            new Constellation($equity_account->name, AcceptableConstellationKind::Account, [
                new Star(
                    "0.00",
                    RationalNumber::get("0")
                ),
                new Star(
                    "2000.00",
                    RationalNumber::get("2000")
                )
            ]),
            new Constellation(
                "Total of $equity_collection->name",
                AcceptableConstellationKind::Sum,
                [
                    new Star(
                        "0.00",
                        RationalNumber::get("0")
                    ),
                    new Star(
                        "2000.00",
                        RationalNumber::get("2000")
                    )
                ]
            ),
            new Constellation(
                "Average of $equity_collection->name",
                AcceptableConstellationKind::Average,
                [
                    new Star(
                        "0.00",
                        RationalNumber::get("0")
                    ),
                    new Star(
                        "2000.00",
                        RationalNumber::get("2000")
                    )
                ]
            )
        ]);
    }

    public function testTotalUnadjustedNetDebitAmountForAssetCollection()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "presentational_precision" => 2
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
        $context = new Context();
        $time_group_manager = new TimeGroupManager($context, $time_groups);
        $context
            ->getVariable(ContextKeys::COLLECTION_CACHE)
            ->loadCollectedAccounts([ $asset_collection->id ]);

        $collection_source = CollectionSource::parseConfiguration([
            "collection_id" => $asset_collection->id,
            "currency_id" => $currency->id,
            "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
            "stage_basis" => UNADJUSTED_AMOUNT_STAGE_BASIS,
            "side_basis" => NET_DEBIT_AMOUNT_SIDE_BASIS,
            "must_show_individual_amounts" => true,
            "must_show_collective_sum" => true,
            "must_show_collective_average" => true
        ]);
        $constellations = $collection_source->calculate($context);

        $this->assertEquals($constellations, [
            new Constellation($asset_account->name, AcceptableConstellationKind::Account, [
                new Star(
                    "2000.00",
                    RationalNumber::get("2000")
                ),
                new Star(
                    "3500.00",
                    RationalNumber::get("3500")
                )
            ]),
            new Constellation(
                "Total of $asset_collection->name",
                AcceptableConstellationKind::Sum,
                [
                    new Star(
                        "2000.00",
                        RationalNumber::get("2000")
                    ),
                    new Star(
                        "3500.00",
                        RationalNumber::get("3500")
                    )
                ]
            ),
            new Constellation(
                "Average of $asset_collection->name",
                AcceptableConstellationKind::Average,
                [
                    new Star(
                        "2000.00",
                        RationalNumber::get("2000")
                    ),
                    new Star(
                        "3500.00",
                        RationalNumber::get("3500")
                    )
                ]
            )
        ]);
    }

    public function testTotalUnadjustedNetCreditAmountForAssetCollection()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "presentational_precision" => 2
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
        $context = new Context();
        $time_group_manager = new TimeGroupManager($context, $time_groups);
        $context
            ->getVariable(ContextKeys::COLLECTION_CACHE)
            ->loadCollectedAccounts([ $asset_collection->id ]);

        $collection_source = CollectionSource::parseConfiguration([
            "collection_id" => $asset_collection->id,
            "currency_id" => $currency->id,
            "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
            "stage_basis" => UNADJUSTED_AMOUNT_STAGE_BASIS,
            "side_basis" => NET_CREDIT_AMOUNT_SIDE_BASIS,
            "must_show_individual_amounts" => true,
            "must_show_collective_sum" => true,
            "must_show_collective_average" => true
        ]);
        $constellations = $collection_source->calculate($context);

        $this->assertEquals($constellations, [
            new Constellation($asset_account->name, AcceptableConstellationKind::Account, [
                new Star(
                    "-2000.00",
                    RationalNumber::get("-2000")
                ),
                new Star(
                    "-3500.00",
                    RationalNumber::get("-3500")
                )
            ]),
            new Constellation(
                "Total of $asset_collection->name",
                AcceptableConstellationKind::Sum,
                [
                    new Star(
                        "-2000.00",
                        RationalNumber::get("-2000")
                    ),
                    new Star(
                        "-3500.00",
                        RationalNumber::get("-3500")
                    )
                ]
            ),
            new Constellation(
                "Average of $asset_collection->name",
                AcceptableConstellationKind::Average,
                [
                    new Star(
                        "-2000.00",
                        RationalNumber::get("-2000")
                    ),
                    new Star(
                        "-3500.00",
                        RationalNumber::get("-3500")
                    )
                ]
            )
        ]);
    }

    public function testTotalClosedDebitAmountForAssetCollection()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "presentational_precision" => 2
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
        $context = new Context();
        $time_group_manager = new TimeGroupManager($context, $time_groups);
        $context
            ->getVariable(ContextKeys::COLLECTION_CACHE)
            ->loadCollectedAccounts([ $asset_collection->id ]);

        $collection_source = CollectionSource::parseConfiguration([
            "collection_id" => $asset_collection->id,
            "currency_id" => $currency->id,
            "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
            "stage_basis" => CLOSED_AMOUNT_STAGE_BASIS,
            "side_basis" => DEBIT_AMOUNT_SIDE_BASIS,
            "must_show_individual_amounts" => true,
            "must_show_collective_sum" => true,
            "must_show_collective_average" => true
        ]);
        $constellations = $collection_source->calculate($context);

        $this->assertEquals($constellations, [
            new Constellation($asset_account->name, AcceptableConstellationKind::Account, [
                new Star(
                    "2000.00",
                    RationalNumber::get("2000")
                ),
                new Star(
                    "3500.00",
                    RationalNumber::get("3500")
                )
            ]),
            new Constellation(
                "Total of $asset_collection->name",
                AcceptableConstellationKind::Sum,
                [
                    new Star(
                        "2000.00",
                        RationalNumber::get("2000")
                    ),
                    new Star(
                        "3500.00",
                        RationalNumber::get("3500")
                    )
                ]
            ),
            new Constellation(
                "Average of $asset_collection->name",
                AcceptableConstellationKind::Average,
                [
                    new Star(
                        "2000.00",
                        RationalNumber::get("2000")
                    ),
                    new Star(
                        "3500.00",
                        RationalNumber::get("3500")
                    )
                ]
            )
        ]);
    }

    public function testTotalClosedCreditAmountForEquityCollection()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "presentational_precision" => 2
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
        $context = new Context();
        $time_group_manager = new TimeGroupManager($context, $time_groups);
        $context
            ->getVariable(ContextKeys::COLLECTION_CACHE)
            ->loadCollectedAccounts([ $equity_collection->id ]);

        $collection_source = CollectionSource::parseConfiguration([
            "collection_id" => $equity_collection->id,
            "currency_id" => $currency->id,
            "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
            "stage_basis" => CLOSED_AMOUNT_STAGE_BASIS,
            "side_basis" => CREDIT_AMOUNT_SIDE_BASIS,
            "must_show_individual_amounts" => true,
            "must_show_collective_sum" => true,
            "must_show_collective_average" => true
        ]);
        $constellations = $collection_source->calculate($context);

        $this->assertEquals($constellations, [
            new Constellation($equity_account->name, AcceptableConstellationKind::Account, [
                new Star(
                    "2000.00",
                    RationalNumber::get("2000")
                ),
                new Star(
                    "3500.00",
                    RationalNumber::get("3500")
                )
            ]),
            new Constellation(
                "Total of $equity_collection->name",
                AcceptableConstellationKind::Sum,
                [
                    new Star(
                        "2000.00",
                        RationalNumber::get("2000")
                    ),
                    new Star(
                        "3500.00",
                        RationalNumber::get("3500")
                    )
                ]
            ),
            new Constellation(
                "Average of $equity_collection->name",
                AcceptableConstellationKind::Average,
                [
                    new Star(
                        "2000.00",
                        RationalNumber::get("2000")
                    ),
                    new Star(
                        "3500.00",
                        RationalNumber::get("3500")
                    )
                ]
            )
        ]);
    }

    public function testRobustnesslDueToUncollectedAssets()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "presentational_precision" => 2
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
        $context = new Context();
        $time_group_manager = new TimeGroupManager($context, $time_groups);
        $context
            ->getVariable(ContextKeys::COLLECTION_CACHE)
            ->loadCollectedAccounts([ $asset_collection->id ]);

        $collection_source = CollectionSource::parseConfiguration([
            "collection_id" => $asset_collection->id,
            "currency_id" => $currency->id,
            "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
            "stage_basis" => CLOSED_AMOUNT_STAGE_BASIS,
            "side_basis" => DEBIT_AMOUNT_SIDE_BASIS,
            "must_show_individual_amounts" => true,
            "must_show_collective_sum" => true,
            "must_show_collective_average" => true
        ]);
        $constellations = $collection_source->calculate($context);

        $this->assertEquals($constellations, []);
    }

    public function testExchangedTotalOpenedDebitAmountForAssetCollection()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_a = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "presentational_precision" => 2
        ])->create();
        $currency_b = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "presentational_precision" => 2
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

        $time_groups = [
            new PeriodicTimeGroup($first_frozen_period),
            new PeriodicTimeGroup($second_frozen_period)
        ];
        $context = new Context();
        $time_group_manager = new TimeGroupManager($context, $time_groups);
        $context
            ->getVariable(ContextKeys::COLLECTION_CACHE)
            ->loadCollectedAccounts([ $asset_collection->id ]);

        $collection_source = CollectionSource::parseConfiguration([
            "collection_id" => $asset_collection->id,
            "currency_id" => $currency_a->id,
            "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
            "stage_basis" => UNADJUSTED_AMOUNT_STAGE_BASIS,
            "side_basis" => NET_DEBIT_AMOUNT_SIDE_BASIS,
            "must_show_individual_amounts" => true,
            "must_show_collective_sum" => true,
            "must_show_collective_average" => true
        ]);
        $constellations = $collection_source->calculate($context);

        $this->assertEquals($constellations, [
            new Constellation($asset_a_account->name, AcceptableConstellationKind::Account, [
                new Star(
                    "2000.00",
                    RationalNumber::get("2000")
                ),
                new Star(
                    "3500.00",
                    RationalNumber::get("3500")
                )
            ]),
            new Constellation($asset_b_account->name, AcceptableConstellationKind::Account, [
                new Star(
                    "1000.00",
                    RationalNumber::get("1000")
                ),
                new Star(
                    "3000.00",
                    RationalNumber::get("3000")
                )
            ]),
            new Constellation(
                "Total of $asset_collection->name",
                AcceptableConstellationKind::Sum,
                [
                    new Star(
                        "3000.00",
                        RationalNumber::get("3000")
                    ),
                    new Star(
                        "6500.00",
                        RationalNumber::get("6500")
                    )
                ]
            ),
            new Constellation(
                "Average of $asset_collection->name",
                AcceptableConstellationKind::Average,
                [
                    new Star(
                        "1500.00",
                        RationalNumber::get("1500")
                    ),
                    new Star(
                        "3250.00",
                        RationalNumber::get("3250")
                    )
                ]
            )
        ]);
    }
}
