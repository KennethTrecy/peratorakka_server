<?php

namespace Tests\Feature\Resource;

use App\Exceptions\InvalidRequest;
use App\Exceptions\MissingResource;
use App\Models\CurrencyModel;
use App\Models\PrecisionFormatModel;
use CodeIgniter\Test\Fabricator;
use Tests\Feature\Helper\AuthenticatedHTTPTestCase;
use Throwable;

class CurrencyTest extends AuthenticatedHTTPTestCase
{
    public function testDefaultIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies
        ] = CurrencyModel::createTestResources(
            $authenticated_info->getUser()->id,
            10,
            []
        );

        $result = $authenticated_info->getRequest()->get("/api/v2/currencies");

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "overall_filtered_count" => 10
            ],
            "currencies" => json_decode(json_encode($currencies))
        ]);
    }

    public function testDefaultShow()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $details
        ] = CurrencyModel::createTestResource($authenticated_info->getUser()->id, []);

        $result = $authenticated_info->getRequest()->get("/api/v2/currencies/$details->id");

        $result->assertOk();
        $result->assertJSONExact([
            "currency" => json_decode(json_encode($details)),
            "precision_formats" => json_decode(json_encode($precision_formats))
        ]);
    }

    public function testDefaultCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $details
        ] = CurrencyModel::makeTestResource($authenticated_info->getUser()->id, []);

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/currencies", [
                "currency" => $details->toArray()
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "currency" => $details->toArray()
        ]);
    }

    public function testDefaultUpdate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $details,
            $new_details
        ] = CurrencyModel::createAndMakeTestResources(
            $authenticated_info->getUser()->id,
            []
        );

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v2/currencies/$details->id", [
                "currency" => $new_details->toArray()
            ]);

        $result->assertStatus(204);
        $this->seeInDatabase("currencies_v2", array_merge(
            [ "id" => $details->id ],
            $new_details->toRawArray()
        ));
    }

    public function testDefaultDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $details
        ] = CurrencyModel::createTestResource($authenticated_info->getUser()->id, []);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v2/currencies/$details->id");

        $result->assertStatus(204);
        $this->seeInDatabase("currencies_v2", array_merge(
            [ "id" => $details->id ]
        ));
        $this->dontSeeInDatabase("currencies_v2", [
            "id" => $details->id,
            "deleted_at" => null
        ]);
    }

    public function testDefaultRestore()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $details
        ] = CurrencyModel::createTestResource($authenticated_info->getUser()->id, []);
        model(CurrencyModel::class)->delete($details->id);

        $result = $authenticated_info
            ->getRequest()
            ->patch("/api/v2/currencies/$details->id");

        $result->assertStatus(204);
        $this->seeInDatabase("currencies_v2", [
            "id" => $details->id,
            "deleted_at" => null
        ]);
    }

    public function testDefaultForceDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $details
        ] = CurrencyModel::createTestResource($authenticated_info->getUser()->id, []);
        model(CurrencyModel::class)->delete($details->id);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v2/currencies/$details->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "currencies_v2", []);
    }

    public function testEmptyIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $result = $authenticated_info->getRequest()->get("/api/v2/currencies");

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "overall_filtered_count" => 0
            ],
            "currencies" => []
        ]);
    }

    public function testQueriedIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $details
        ] = CurrencyModel::createTestResources(
            $authenticated_info->getUser()->id,
            10,
            []
        );

        $result = $authenticated_info->getRequest()->get("/api/v2/currencies", [
            "page" => [
                "limit" => 5,
                "must_be_enriched" => true
            ]
        ]);

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "overall_filtered_count" => 10
            ],
            "currencies" => json_decode(json_encode(array_slice($details, 0, 5))),
            "precision_formats" => json_decode(json_encode($precision_formats))
        ]);
    }

    public function testMissingShow()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $details
        ] = CurrencyModel::createTestResource($authenticated_info->getUser()->id, []);
        $details->id = $details->id + 1;

        $this->expectException(MissingResource::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info->getRequest()->get("/api/v2/currencies/$details->id");
    }

    public function testInvalidCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $details
        ] = CurrencyModel::makeTestResource($authenticated_info->getUser()->id, [
            "overrides" => [
                "name" => "@only alphanumeric characters only"
            ]
        ]);

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/currencies", [
                "currency" => $details->toArray()
            ]);
    }

    public function testInvalidUpdate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $details,
            $new_details
        ] = CurrencyModel::createAndMakeTestResources(
            $authenticated_info->getUser()->id,
            [
                "make_overrides" => [
                    "name" => "@only alphanumeric characters only"
                ]
            ]
        );

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v2/currencies/$details->id", [
                "currency" => $new_details->toArray()
            ]);
    }

    public function testUnownedDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        [
            $precision_formats,
            $details
        ] = CurrencyModel::createTestResource($another_user->id, []);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v2/currencies/$details->id");
            $this->assertTrue(false);
        } catch (MissingResource $error) {
            $this->seeInDatabase("currencies_v2", array_merge(
                [ "id" => $details->id ]
            ));
            $this->seeInDatabase("currencies_v2", [
                "id" => $details->id,
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

        [
            $precision_formats,
            $details
        ] = CurrencyModel::createTestResource($another_user->id, []);
        model(CurrencyModel::class)->delete($details->id);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v2/currencies/$details->id");
        } catch (MissingResource $error) {
            $this->seeInDatabase("currencies_v2", array_merge(
                [ "id" => $details->id ]
            ));
            $this->dontSeeInDatabase("currencies_v2", [
                "id" => $details->id,
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

        [
            $precision_formats,
            $details
        ] = CurrencyModel::createTestResource($another_user->id, []);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->patch("/api/v2/currencies/$details->id");
        } catch (MissingResource $error) {
            $this->seeInDatabase("currencies_v2", [
                "id" => $details->id,
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

        [
            $precision_formats,
            $details
        ] = CurrencyModel::createTestResource($authenticated_info->getUser()->id, []);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v2/currencies/$details->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "currencies_v2", []);
    }

    public function testDoubleForceDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        [
            $precision_formats,
            $details
        ] = CurrencyModel::createTestResource($another_user->id, []);
        model(CurrencyModel::class)->delete($details->id, true);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v2/currencies/$details->id/force");
        } catch (MissingResource $error) {
            $this->seeNumRecords(0, "currencies_v2", []);
            throw $error;
        } catch (Throwable $error) {
            $this->assertTrue(false);
        }
    }
}
