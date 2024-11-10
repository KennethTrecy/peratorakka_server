<?php

namespace Tests\Feature\Resource;

use App\Exceptions\InvalidRequest;
use App\Exceptions\MissingResource;

use App\Casts\NumericalToolKind;
use App\Casts\NumericalToolRecurrencePeriod;
use App\Libraries\NumericalToolConfiguration\CollectionSource;
use App\Models\CurrencyModel;
use App\Models\NumericalToolModel;
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
                        "exchange_rate_basis" => PERIODIC_FORMULA_EXCHANGE_RATE_BASIS,
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
                        "exchange_rate_basis" => PERIODIC_FORMULA_EXCHANGE_RATE_BASIS,
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
                        "exchange_rate_basis" => PERIODIC_FORMULA_EXCHANGE_RATE_BASIS,
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
                        "exchange_rate_basis" => PERIODIC_FORMULA_EXCHANGE_RATE_BASIS,
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
                        "exchange_rate_basis" => PERIODIC_FORMULA_EXCHANGE_RATE_BASIS,
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
                        "exchange_rate_basis" => PERIODIC_FORMULA_EXCHANGE_RATE_BASIS,
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
                        "exchange_rate_basis" => PERIODIC_FORMULA_EXCHANGE_RATE_BASIS,
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
                        "exchange_rate_basis" => PERIODIC_FORMULA_EXCHANGE_RATE_BASIS,
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
                        "exchange_rate_basis" => PERIODIC_FORMULA_EXCHANGE_RATE_BASIS,
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
                        "exchange_rate_basis" => PERIODIC_FORMULA_EXCHANGE_RATE_BASIS,
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
                        "exchange_rate_basis" => PERIODIC_FORMULA_EXCHANGE_RATE_BASIS,
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
