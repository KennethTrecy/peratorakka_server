<?php

namespace Tests\Feature\Resource;

use App\Casts\NumericalToolKind;
use App\Casts\NumericalToolRecurrencePeriod;
use App\Casts\RationalNumber;
use App\Exceptions\InvalidRequest;
use App\Exceptions\MissingResource;
use App\Libraries\Constellation;
use App\Libraries\Constellation\AcceptableConstellationKind;
use App\Libraries\Constellation\Star;
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
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\Fabricator;
use Tests\Feature\Helper\AuthenticatedHTTPTestCase;
use Throwable;

class NumericalToolTest extends AuthenticatedHTTPTestCase
{
    public function testDefaultIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currency = $currency_fabricator->create();
        $numerical_tool_fabricator = new Fabricator(NumericalToolModel::class);
        $numerical_tool_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "configuration" => json_encode([
                "sources" => [
                    [
                        "type" => CollectionSource::sourceType(),
                        "collection_id" => 1,
                        "currency_id" => $currency->id,
                        "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
                        "stage_basis" => UNADJUSTED_AMOUNT_STAGE_BASIS,
                        "side_basis" => NET_DEBIT_AMOUNT_SIDE_BASIS,
                        "must_show_individual_amounts" => true,
                        "must_show_collective_sum" => true,
                        "must_show_collective_average" => true
                    ]
                ]
            ])
        ]);
        $numerical_tools = $numerical_tool_fabricator->create(10);

        $result = $authenticated_info->getRequest()->get("/api/v1/numerical_tools");

        $result->assertOk();
        $result->assertJSONExact([
            "meta" => [
                "overall_filtered_count" => 10
            ],
            "numerical_tools" => json_decode(json_encode($numerical_tools)),
            "currencies" => [ $currency ]
        ]);
    }

    public function testDefaultShow()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currency = $currency_fabricator->create();
        $numerical_tool_fabricator = new Fabricator(NumericalToolModel::class);
        $numerical_tool_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "configuration" => json_encode([
                "sources" => [
                    [
                        "type" => CollectionSource::sourceType(),
                        "collection_id" => 1,
                        "currency_id" => $currency->id,
                        "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
                        "stage_basis" => UNADJUSTED_AMOUNT_STAGE_BASIS,
                        "side_basis" => NET_DEBIT_AMOUNT_SIDE_BASIS,
                        "must_show_individual_amounts" => true,
                        "must_show_collective_sum" => true,
                        "must_show_collective_average" => true
                    ]
                ]
            ])
        ]);
        $numerical_tool = $numerical_tool_fabricator->create();

        $result = $authenticated_info->getRequest()
            ->get("/api/v1/numerical_tools/$numerical_tool->id");

        $result->assertOk();
        $result->assertJSONExact([
            "numerical_tool" => json_decode(json_encode($numerical_tool)),
            "currencies" => [ $currency ]
        ]);
    }

    public function testDefaultCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currency = $currency_fabricator->create();
        $numerical_tool_fabricator = new Fabricator(NumericalToolModel::class);
        $numerical_tool_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "configuration" => json_encode([
                "sources" => [
                    [
                        "type" => CollectionSource::sourceType(),
                        "collection_id" => 1,
                        "currency_id" => $currency->id,
                        "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
                        "stage_basis" => UNADJUSTED_AMOUNT_STAGE_BASIS,
                        "side_basis" => NET_DEBIT_AMOUNT_SIDE_BASIS,
                        "must_show_individual_amounts" => true,
                        "must_show_collective_sum" => true,
                        "must_show_collective_average" => true
                    ]
                ]
            ])
        ]);
        $numerical_tool = $numerical_tool_fabricator->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/numerical_tools", [
                "numerical_tool" => $numerical_tool->toArray()
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "numerical_tool" => $numerical_tool->toArray()
        ]);
    }

    public function testDefaultUpdate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currency = $currency_fabricator->create();
        $numerical_tool_fabricator = new Fabricator(NumericalToolModel::class);
        $numerical_tool_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "configuration" => json_encode([
                "sources" => [
                    [
                        "type" => CollectionSource::sourceType(),
                        "collection_id" => 1,
                        "currency_id" => $currency->id,
                        "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
                        "stage_basis" => UNADJUSTED_AMOUNT_STAGE_BASIS,
                        "side_basis" => NET_DEBIT_AMOUNT_SIDE_BASIS,
                        "must_show_individual_amounts" => true,
                        "must_show_collective_sum" => true,
                        "must_show_collective_average" => true
                    ]
                ]
            ])
        ]);
        $numerical_tool = $numerical_tool_fabricator->create();
        $new_details = $numerical_tool_fabricator->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v1/numerical_tools/$numerical_tool->id", [
                "numerical_tool" => $new_details->toArray()
            ]);

        $result->assertStatus(204);
        $this->seeInDatabase("numerical_tools", array_merge(
            [ "id" => $numerical_tool->id ],
            array_merge($new_details->toArray(), [
                "kind" => NumericalToolKind::set($new_details->kind),
                "recurrence" => NumericalToolRecurrencePeriod::set($new_details->recurrence)
            ])
        ));
    }

    public function testDefaultDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currency = $currency_fabricator->create();
        $numerical_tool_fabricator = new Fabricator(NumericalToolModel::class);
        $numerical_tool_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "configuration" => json_encode([
                "sources" => [
                    [
                        "type" => CollectionSource::sourceType(),
                        "collection_id" => 1,
                        "currency_id" => $currency->id,
                        "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
                        "stage_basis" => UNADJUSTED_AMOUNT_STAGE_BASIS,
                        "side_basis" => NET_DEBIT_AMOUNT_SIDE_BASIS,
                        "must_show_individual_amounts" => true,
                        "must_show_collective_sum" => true,
                        "must_show_collective_average" => true
                    ]
                ]
            ])
        ]);
        $numerical_tool = $numerical_tool_fabricator->create();

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/numerical_tools/$numerical_tool->id");

        $result->assertStatus(204);
        $this->seeInDatabase("numerical_tools", array_merge(
            [ "id" => $numerical_tool->id ]
        ));
        $this->dontSeeInDatabase("numerical_tools", [
            "id" => $numerical_tool->id,
            "deleted_at" => null
        ]);
    }

    public function testDefaultRestore()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currency = $currency_fabricator->create();
        $numerical_tool_fabricator = new Fabricator(NumericalToolModel::class);
        $numerical_tool_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "configuration" => json_encode([
                "sources" => [
                    [
                        "type" => CollectionSource::sourceType(),
                        "collection_id" => 1,
                        "currency_id" => $currency->id,
                        "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
                        "stage_basis" => UNADJUSTED_AMOUNT_STAGE_BASIS,
                        "side_basis" => NET_DEBIT_AMOUNT_SIDE_BASIS,
                        "must_show_individual_amounts" => true,
                        "must_show_collective_sum" => true,
                        "must_show_collective_average" => true
                    ]
                ]
            ])
        ]);
        $numerical_tool = $numerical_tool_fabricator->create();
        model(NumericalToolModel::class)->delete($numerical_tool->id);

        $result = $authenticated_info
            ->getRequest()
            ->patch("/api/v1/numerical_tools/$numerical_tool->id");

        $result->assertStatus(204);
        $this->seeInDatabase("numerical_tools", [
            "id" => $numerical_tool->id,
            "deleted_at" => null
        ]);
    }

    public function testDefaultForceDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currency = $currency_fabricator->create();
        $numerical_tool_fabricator = new Fabricator(NumericalToolModel::class);
        $numerical_tool_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "configuration" => json_encode([
                "sources" => [
                    [
                        "type" => CollectionSource::sourceType(),
                        "collection_id" => 1,
                        "currency_id" => $currency->id,
                        "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
                        "stage_basis" => UNADJUSTED_AMOUNT_STAGE_BASIS,
                        "side_basis" => NET_DEBIT_AMOUNT_SIDE_BASIS,
                        "must_show_individual_amounts" => true,
                        "must_show_collective_sum" => true,
                        "must_show_collective_average" => true
                    ]
                ]
            ])
        ]);
        $numerical_tool = $numerical_tool_fabricator->create();
        model(NumericalToolModel::class)->delete($numerical_tool->id);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/numerical_tools/$numerical_tool->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "numerical_tools", []);
    }

    public function testEmptyIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $result = $authenticated_info->getRequest()->get("/api/v1/numerical_tools");

        $result->assertOk();
        $result->assertJSONExact([
            "meta" => [
                "overall_filtered_count" => 0
            ],
            "numerical_tools" => []
        ]);
    }

    public function testQueriedIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currency = $currency_fabricator->create();
        $numerical_tool_fabricator = new Fabricator(NumericalToolModel::class);
        $numerical_tool_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "configuration" => json_encode([
                "sources" => [
                    [
                        "type" => CollectionSource::sourceType(),
                        "collection_id" => 1,
                        "currency_id" => $currency->id,
                        "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
                        "stage_basis" => UNADJUSTED_AMOUNT_STAGE_BASIS,
                        "side_basis" => NET_DEBIT_AMOUNT_SIDE_BASIS,
                        "must_show_individual_amounts" => true,
                        "must_show_collective_sum" => true,
                        "must_show_collective_average" => true
                    ]
                ]
            ])
        ]);
        $numerical_tools = $numerical_tool_fabricator->create(10);

        $result = $authenticated_info->getRequest()->get("/api/v1/numerical_tools", [
            "page" => [
                "limit" => 5
            ]
        ]);

        $result->assertOk();
        $result->assertJSONExact([
            "meta" => [
                "overall_filtered_count" => 10
            ],
            "numerical_tools" => json_decode(json_encode(array_slice($numerical_tools, 0, 5))),
            "currencies" => [ $currency ]
        ]);
    }

    public function testCalculatedFrozenOnlyMultiCurrencyCollection()
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
        $numerical_tool_fabricator = new Fabricator(NumericalToolModel::class);
        $numerical_tool_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "recurrence" => PERIODIC_NUMERICAL_TOOL_RECURRENCE_PERIOD,
            "recency" => 2,
            "configuration" => json_encode([
                "sources" => [
                    [
                        "type" => CollectionSource::sourceType(),
                        "collection_id" => $asset_collection->id,
                        "currency_id" => $currency_a->id,
                        "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
                        "stage_basis" => UNADJUSTED_AMOUNT_STAGE_BASIS,
                        "side_basis" => NET_DEBIT_AMOUNT_SIDE_BASIS,
                        "must_show_individual_amounts" => true,
                        "must_show_collective_sum" => true,
                        "must_show_collective_average" => true
                    ]
                ]
            ])
        ]);
        $numerical_tool = $numerical_tool_fabricator->create();

        $result = $authenticated_info->getRequest()->get(
            "/api/v1/numerical_tools/calculate/{$numerical_tool->id}"
        );

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "time_tags" => array_fill(0, 2, Time::now()->toLocalizedString("MMMM yyyy")),
                "constellations" => array_map(function ($constellation) {
                    return $constellation->toArray();
                }, [
                    new Constellation(
                        $asset_a_account->name,
                        AcceptableConstellationKind::Account,
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
                        $asset_b_account->name,
                        AcceptableConstellationKind::Account,
                        [
                            new Star(
                                "1000.00",
                                RationalNumber::get("1000")
                            ),
                            new Star(
                                "3000.00",
                                RationalNumber::get("3000")
                            )
                        ]
                    ),
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
                ])
            ],
            "currencies" => [ $currency_a ],
            "numerical_tool" => $numerical_tool
        ]);
    }

    public function testCalculatedNearlyMixedMultiCurrencyCollection()
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
        $numerical_tool_fabricator = new Fabricator(NumericalToolModel::class);
        $numerical_tool_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "recurrence" => PERIODIC_NUMERICAL_TOOL_RECURRENCE_PERIOD,
            "recency" => -2,
            "configuration" => json_encode([
                "sources" => [
                    [
                        "type" => CollectionSource::sourceType(),
                        "collection_id" => $asset_collection->id,
                        "currency_id" => $currency_a->id,
                        "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
                        "stage_basis" => UNADJUSTED_AMOUNT_STAGE_BASIS,
                        "side_basis" => NET_DEBIT_AMOUNT_SIDE_BASIS,
                        "must_show_individual_amounts" => true,
                        "must_show_collective_sum" => true,
                        "must_show_collective_average" => true
                    ]
                ]
            ])
        ]);
        $numerical_tool = $numerical_tool_fabricator->create();

        $result = $authenticated_info->getRequest()->get(
            "/api/v1/numerical_tools/calculate/{$numerical_tool->id}"
        );

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "time_tags" => array_fill(0, 2, Time::now()->toLocalizedString("MMMM yyyy")),
                "constellations" => array_map(function ($constellation) {
                    return $constellation->toArray();
                }, [
                    new Constellation(
                        $asset_a_account->name,
                        AcceptableConstellationKind::Account,
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
                        $asset_b_account->name,
                        AcceptableConstellationKind::Account,
                        [
                            new Star(
                                "1000.00",
                                RationalNumber::get("1000")
                            ),
                            new Star(
                                "3000.00",
                                RationalNumber::get("3000")
                            )
                        ]
                    ),
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
                    ])
                ])
            ],
            "currencies" => [ $currency_a ],
            "numerical_tool" => $numerical_tool
        ]);
    }

    public function testCalculatedTrulyMixedMultiCurrencyCollection()
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
        $normal_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $asset_a_account->id,
            "credit_account_id" => $equity_a_account->id,
            "debit_cash_flow_activity_id" => null,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $expense_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $expense_account->id,
            "credit_account_id" => $asset_a_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => null,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $close_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $equity_a_account->id,
            "credit_account_id" => $expense_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id,
            "action" => CLOSE_MODIFIER_ACTION
        ])->create();
        $normal_exchange_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $asset_a_account->id,
            "credit_account_id" => $asset_b_account->id,
            "action" => EXCHANGE_MODIFIER_ACTION
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
        $exchange_normal_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $normal_exchange_modifier->id,
            "debit_amount" => "100",
            "credit_amount" => "1",
            "transacted_at" => Time::parse("-4 day")->toDateTimeString()
        ])->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $first_frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at" => Time::parse("-4 day")->toDateTimeString(),
            "finished_at" => Time::parse("-3 day")->toDateTimeString()
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
            "started_at" => Time::parse("-3 day")->toDateTimeString(),
            "finished_at" => Time::parse("-2 day")->toDateTimeString()
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
        $numerical_tool_fabricator = new Fabricator(NumericalToolModel::class);
        $numerical_tool_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "recurrence" => PERIODIC_NUMERICAL_TOOL_RECURRENCE_PERIOD,
            "recency" => -2,
            "configuration" => json_encode([
                "sources" => [
                    [
                        "type" => CollectionSource::sourceType(),
                        "collection_id" => $asset_collection->id,
                        "currency_id" => $currency_a->id,
                        "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
                        "stage_basis" => UNADJUSTED_AMOUNT_STAGE_BASIS,
                        "side_basis" => NET_DEBIT_AMOUNT_SIDE_BASIS,
                        "must_show_individual_amounts" => true,
                        "must_show_collective_sum" => true,
                        "must_show_collective_average" => true
                    ]
                ]
            ])
        ]);
        $numerical_tool = $numerical_tool_fabricator->create();

        $result = $authenticated_info->getRequest()->get(
            "/api/v1/numerical_tools/calculate/{$numerical_tool->id}"
        );

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "time_tags" => array_fill(0, 3, Time::now()->toLocalizedString("MMMM yyyy")),
                "constellations" => array_map(function ($constellation) {
                    return $constellation->toArray();
                }, [
                    new Constellation(
                        $asset_a_account->name,
                        AcceptableConstellationKind::Account,
                        [
                            new Star(
                                "2000.00",
                                RationalNumber::get("2000")
                            ),
                            new Star(
                                "3500.00",
                                RationalNumber::get("3500")
                            ),
                            new Star(
                                "4250.00",
                                RationalNumber::get("4250")
                            )
                        ]
                    ),
                    new Constellation(
                        $asset_b_account->name,
                        AcceptableConstellationKind::Account,
                        [
                            new Star(
                                "1000.00",
                                RationalNumber::get("1000")
                            ),
                            new Star(
                                "3000.00",
                                RationalNumber::get("3000")
                            ),
                            new Star(
                                "3000.00",
                                RationalNumber::get("3000")
                            )
                        ]
                    ),
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
                            ),
                            new Star(
                                "7250.00",
                                RationalNumber::get("7250")
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
                            ),
                            new Star(
                                "3625.00",
                                RationalNumber::get("3625")
                            )
                        ]
                    )
                ])
            ],
            "currencies" => [ $currency_a ],
            "numerical_tool" => $numerical_tool
        ]);
    }

    public function testCalculatedRecentOnlyMultiCurrencyCollection()
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
        $normal_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $asset_a_account->id,
            "credit_account_id" => $equity_a_account->id,
            "debit_cash_flow_activity_id" => null,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $expense_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $expense_account->id,
            "credit_account_id" => $asset_a_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => null,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $close_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $equity_a_account->id,
            "credit_account_id" => $expense_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id,
            "action" => CLOSE_MODIFIER_ACTION
        ])->create();
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
            "started_at" => Time::parse("-4 day")->toDateTimeString(),
            "finished_at" => Time::parse("-3 day")->toDateTimeString()
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
            "started_at" => Time::parse("-3 day")->toDateTimeString(),
            "finished_at" => Time::parse("-2 day")->toDateTimeString()
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
        $numerical_tool_fabricator = new Fabricator(NumericalToolModel::class);
        $numerical_tool_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "recurrence" => PERIODIC_NUMERICAL_TOOL_RECURRENCE_PERIOD,
            "recency" => 0,
            "configuration" => json_encode([
                "sources" => [
                    [
                        "type" => CollectionSource::sourceType(),
                        "collection_id" => $asset_collection->id,
                        "currency_id" => $currency_a->id,
                        "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
                        "stage_basis" => UNADJUSTED_AMOUNT_STAGE_BASIS,
                        "side_basis" => NET_DEBIT_AMOUNT_SIDE_BASIS,
                        "must_show_individual_amounts" => true,
                        "must_show_collective_sum" => true,
                        "must_show_collective_average" => true
                    ]
                ]
            ])
        ]);
        $numerical_tool = $numerical_tool_fabricator->create();

        $result = $authenticated_info->getRequest()->get(
            "/api/v1/numerical_tools/calculate/{$numerical_tool->id}"
        );

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "time_tags" => array_fill(0, 1, Time::now()->toLocalizedString("MMMM yyyy")),
                "constellations" => array_map(function ($constellation) {
                    return $constellation->toArray();
                }, [
                    new Constellation(
                        $asset_a_account->name,
                        AcceptableConstellationKind::Account,
                        [
                            new Star(
                                "4250.00",
                                RationalNumber::get("4250")
                            )
                        ]
                    ),
                    new Constellation(
                        $asset_b_account->name,
                        AcceptableConstellationKind::Account,
                        [
                            new Star(
                                "3000.00",
                                RationalNumber::get("3000")
                            )
                        ]
                    ),
                    new Constellation(
                        "Total of $asset_collection->name",
                        AcceptableConstellationKind::Sum,
                        [
                            new Star(
                                "7250.00",
                                RationalNumber::get("7250")
                            )
                        ]
                    ),
                    new Constellation(
                        "Average of $asset_collection->name",
                        AcceptableConstellationKind::Average,
                        [
                            new Star(
                                "3625.00",
                                RationalNumber::get("3625")
                            )
                        ]
                    )
                ])
            ],
            "currencies" => [ $currency_a ],
            "numerical_tool" => $numerical_tool
        ]);
    }

    public function testUncalculatableCollection()
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
        $normal_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $asset_a_account->id,
            "credit_account_id" => $equity_a_account->id,
            "debit_cash_flow_activity_id" => null,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $expense_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $expense_account->id,
            "credit_account_id" => $asset_a_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => null,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $close_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $equity_a_account->id,
            "credit_account_id" => $expense_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id,
            "action" => CLOSE_MODIFIER_ACTION
        ])->create();
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
        $numerical_tool_fabricator = new Fabricator(NumericalToolModel::class);
        $numerical_tool_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "recurrence" => PERIODIC_NUMERICAL_TOOL_RECURRENCE_PERIOD,
            "recency" => 0,
            "configuration" => json_encode([
                "sources" => [
                    [
                        "type" => CollectionSource::sourceType(),
                        "collection_id" => $asset_collection->id,
                        "currency_id" => $currency_a->id,
                        "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
                        "stage_basis" => UNADJUSTED_AMOUNT_STAGE_BASIS,
                        "side_basis" => NET_DEBIT_AMOUNT_SIDE_BASIS,
                        "must_show_individual_amounts" => true,
                        "must_show_collective_sum" => true,
                        "must_show_collective_average" => true
                    ]
                ]
            ])
        ]);
        $numerical_tool = $numerical_tool_fabricator->create();

        $result = $authenticated_info->getRequest()->get(
            "/api/v1/numerical_tools/calculate/{$numerical_tool->id}"
        );

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "time_tags" => array_fill(0, 1, Time::now()->toLocalizedString("MMMM yyyy")),
                "constellations" => array_map(function ($constellation) {
                    return $constellation->toArray();
                }, [
                    new Constellation(
                        $asset_a_account->name,
                        AcceptableConstellationKind::Account,
                        [
                            new Star(
                                "4250.00",
                                RationalNumber::get("4250")
                            )
                        ]
                    ),
                    new Constellation(
                        $asset_b_account->name,
                        AcceptableConstellationKind::Account,
                        [
                            new Star(
                                "3000.00",
                                RationalNumber::get("3000")
                            )
                        ]
                    ),
                    new Constellation(
                        "Total of $asset_collection->name",
                        AcceptableConstellationKind::Sum,
                        [
                            new Star(
                                "7250.00",
                                RationalNumber::get("7250")
                            )
                        ]
                    ),
                    new Constellation(
                        "Average of $asset_collection->name",
                        AcceptableConstellationKind::Average,
                        [
                            new Star(
                                "3625.00",
                                RationalNumber::get("3625")
                            )
                        ]
                    )
                ])
            ],
            "currencies" => [ $currency_a ],
            "numerical_tool" => $numerical_tool
        ]);
    }

    public function testCalculatedTrulyMixedMultiCurrencyCollectionAndFormula()
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
        $normal_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $asset_a_account->id,
            "credit_account_id" => $equity_a_account->id,
            "debit_cash_flow_activity_id" => null,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $expense_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $expense_account->id,
            "credit_account_id" => $asset_a_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => null,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $close_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $equity_a_account->id,
            "credit_account_id" => $expense_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id,
            "action" => CLOSE_MODIFIER_ACTION
        ])->create();
        $normal_exchange_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $asset_a_account->id,
            "credit_account_id" => $asset_b_account->id,
            "action" => EXCHANGE_MODIFIER_ACTION
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
        $exchange_normal_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $normal_exchange_modifier->id,
            "debit_amount" => "100",
            "credit_amount" => "1",
            "transacted_at" => Time::parse("-4 day")->toDateTimeString()
        ])->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $first_frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at" => Time::parse("-4 day")->toDateTimeString(),
            "finished_at" => Time::parse("-3 day")->toDateTimeString()
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
            "started_at" => Time::parse("-3 day")->toDateTimeString(),
            "finished_at" => Time::parse("-2 day")->toDateTimeString()
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
        $formula_fabricator = new Fabricator(FormulaModel::class);
        $formula_fabricator->setOverrides([
            "currency_id" => $currency_a->id,
            "output_format" => CURRENCY_FORMULA_OUTPUT_FORMAT,
            "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
            "presentational_precision" => 2,
            "formula" => "TOTAL_UNADJUSTED_DEBIT_AMOUNT(COLLECTION[$asset_collection->id])"
        ]);
        $formula = $formula_fabricator->create();
        $numerical_tool_fabricator = new Fabricator(NumericalToolModel::class);
        $numerical_tool_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "recurrence" => PERIODIC_NUMERICAL_TOOL_RECURRENCE_PERIOD,
            "recency" => -2,
            "configuration" => json_encode([
                "sources" => [
                    [
                        "type" => CollectionSource::sourceType(),
                        "collection_id" => $asset_collection->id,
                        "currency_id" => $currency_a->id,
                        "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
                        "stage_basis" => UNADJUSTED_AMOUNT_STAGE_BASIS,
                        "side_basis" => NET_DEBIT_AMOUNT_SIDE_BASIS,
                        "must_show_individual_amounts" => true,
                        "must_show_collective_sum" => true,
                        "must_show_collective_average" => true
                    ],
                    [
                        "type" => FormulaSource::sourceType(),
                        "formula_id" => $formula->id
                    ]
                ]
            ])
        ]);
        $numerical_tool = $numerical_tool_fabricator->create();

        $result = $authenticated_info->getRequest()->get(
            "/api/v1/numerical_tools/calculate/{$numerical_tool->id}"
        );

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "time_tags" => array_fill(0, 3, Time::now()->toLocalizedString("MMMM yyyy")),
                "constellations" => array_map(function ($constellation) {
                    return $constellation->toArray();
                }, [
                    new Constellation(
                        $asset_a_account->name,
                        AcceptableConstellationKind::Account,
                        [
                        new Star(
                            "2000.00",
                            RationalNumber::get("2000")
                        ),
                        new Star(
                            "3500.00",
                            RationalNumber::get("3500")
                        ),
                        new Star(
                            "4250.00",
                            RationalNumber::get("4250")
                        )
                        ]
                    ),
                    new Constellation(
                        $asset_b_account->name,
                        AcceptableConstellationKind::Account,
                        [
                            new Star(
                                "1000.00",
                                RationalNumber::get("1000")
                            ),
                            new Star(
                                "3000.00",
                                RationalNumber::get("3000")
                            ),
                            new Star(
                                "3000.00",
                                RationalNumber::get("3000")
                            )
                        ]
                    ),
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
                            ),
                            new Star(
                                "7250.00",
                                RationalNumber::get("7250")
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
                            ),
                            new Star(
                                "3625.00",
                                RationalNumber::get("3625")
                            )
                        ]
                    ),
                    new Constellation(
                        $formula->name,
                        AcceptableConstellationKind::Formula,
                        [
                            new Star(
                                "3500.00",
                                RationalNumber::get("3500")
                            ),
                            new Star(
                                "7000.00",
                                RationalNumber::get("7000")
                            ),
                            new Star(
                                "7500.00",
                                RationalNumber::get("7500")
                            )
                        ]
                    )
                ])
            ],
            "currencies" => [ $currency_a ],
            "numerical_tool" => $numerical_tool
        ]);
    }

    public function testMissingShow()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currency = $currency_fabricator->create();
        $numerical_tool_fabricator = new Fabricator(NumericalToolModel::class);
        $numerical_tool_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "configuration" => json_encode([
                "sources" => [
                    [
                        "type" => CollectionSource::sourceType(),
                        "collection_id" => 1,
                        "currency_id" => $currency->id,
                        "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
                        "stage_basis" => UNADJUSTED_AMOUNT_STAGE_BASIS,
                        "side_basis" => NET_DEBIT_AMOUNT_SIDE_BASIS,
                        "must_show_individual_amounts" => true,
                        "must_show_collective_sum" => true,
                        "must_show_collective_average" => true
                    ]
                ]
            ])
        ]);
        $numerical_tool = $numerical_tool_fabricator->create();
        $numerical_tool->id = $numerical_tool->id + 1;

        $this->expectException(MissingResource::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info->getRequest()->get("/api/v1/numerical_tools/$numerical_tool->id");
    }

    public function testInvalidCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $numerical_tool_fabricator = new Fabricator(NumericalToolModel::class);
        $numerical_tool_fabricator->setOverrides([
            "name" => "@only alphanumeric characters only"
        ]);
        $numerical_tool = $numerical_tool_fabricator->make();

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/numerical_tools", [
                "numerical_tool" => $numerical_tool->toArray()
            ]);
    }

    public function testInvalidUpdate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currency = $currency_fabricator->create();
        $numerical_tool_fabricator = new Fabricator(NumericalToolModel::class);
        $numerical_tool_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "configuration" => json_encode([
                "sources" => [
                    [
                        "type" => CollectionSource::sourceType(),
                        "collection_id" => 1,
                        "currency_id" => $currency->id,
                        "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
                        "stage_basis" => UNADJUSTED_AMOUNT_STAGE_BASIS,
                        "side_basis" => NET_DEBIT_AMOUNT_SIDE_BASIS,
                        "must_show_individual_amounts" => true,
                        "must_show_collective_sum" => true,
                        "must_show_collective_average" => true
                    ]
                ]
            ])
        ]);
        $numerical_tool = $numerical_tool_fabricator->create();
        $numerical_tool_fabricator->setOverrides([
            "name" => "@only alphanumeric characters only"
        ]);
        $new_details = $numerical_tool_fabricator->make();

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v1/numerical_tools/$numerical_tool->id", [
                "numerical_tool" => $new_details->toArray()
            ]);
    }

    public function testUnownedDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        $numerical_tool_fabricator = new Fabricator(NumericalToolModel::class);
        $numerical_tool_fabricator->setOverrides([
            "user_id" => $another_user->id
        ]);
        $numerical_tool = $numerical_tool_fabricator->create();

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v1/numerical_tools/$numerical_tool->id");
            $this->assertTrue(false);
        } catch (MissingResource $error) {
            $this->seeInDatabase("numerical_tools", array_merge(
                [ "id" => $numerical_tool->id ]
            ));
            $this->seeInDatabase("numerical_tools", [
                "id" => $numerical_tool->id,
                "deleted_at" => null
            ]);
            throw $error;
        } catch (Throwable $error) {
            $this->assertTrue(false);
        }
    }

    public function testDoubleDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        $numerical_tool_fabricator = new Fabricator(NumericalToolModel::class);
        $numerical_tool_fabricator->setOverrides([
            "user_id" => $another_user->id
        ]);
        $numerical_tool = $numerical_tool_fabricator->create();
        model(NumericalToolModel::class)->delete($numerical_tool->id);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v1/numerical_tools/$numerical_tool->id");
        } catch (MissingResource $error) {
            $this->seeInDatabase("numerical_tools", array_merge(
                [ "id" => $numerical_tool->id ]
            ));
            $this->dontSeeInDatabase("numerical_tools", [
                "id" => $numerical_tool->id,
                "deleted_at" => null
            ]);
            throw $error;
        } catch (Throwable $error) {
            $this->assertTrue(false);
        }
    }

    public function testDoubleRestore()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        $numerical_tool_fabricator = new Fabricator(NumericalToolModel::class);
        $numerical_tool_fabricator->setOverrides([
            "user_id" => $another_user->id
        ]);
        $numerical_tool = $numerical_tool_fabricator->create();

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->patch("/api/v1/numerical_tools/$numerical_tool->id");
        } catch (MissingResource $error) {
            $this->seeInDatabase("numerical_tools", [
                "id" => $numerical_tool->id,
                "deleted_at" => null
            ]);
            throw $error;
        } catch (Throwable $error) {
            $this->assertTrue(false);
        }
    }

    public function testImmediateForceDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currency = $currency_fabricator->create();
        $numerical_tool_fabricator = new Fabricator(NumericalToolModel::class);
        $numerical_tool_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "configuration" => json_encode([
                "sources" => [
                    [
                        "type" => CollectionSource::sourceType(),
                        "collection_id" => 1,
                        "currency_id" => $currency->id,
                        "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
                        "stage_basis" => UNADJUSTED_AMOUNT_STAGE_BASIS,
                        "side_basis" => NET_DEBIT_AMOUNT_SIDE_BASIS,
                        "must_show_individual_amounts" => true,
                        "must_show_collective_sum" => true,
                        "must_show_collective_average" => true
                    ]
                ]
            ])
        ]);
        $numerical_tool = $numerical_tool_fabricator->create();

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/numerical_tools/$numerical_tool->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "numerical_tools", []);
    }

    public function testDoubleForceDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        $numerical_tool_fabricator = new Fabricator(NumericalToolModel::class);
        $numerical_tool_fabricator->setOverrides([
            "user_id" => $another_user->id
        ]);
        $numerical_tool = $numerical_tool_fabricator->create();
        model(NumericalToolModel::class)->delete($numerical_tool->id, true);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v1/numerical_tools/$numerical_tool->id/force");
        } catch (MissingResource $error) {
            $this->seeNumRecords(0, "numerical_tools", []);
            throw $error;
        } catch (Throwable $error) {
            $this->assertTrue(false);
        }
    }
}
