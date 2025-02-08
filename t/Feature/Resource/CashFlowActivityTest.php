<?php

namespace Tests\Feature\Resource;

use App\Exceptions\InvalidRequest;
use App\Exceptions\MissingResource;
use App\Models\CashFlowActivityModel;
use CodeIgniter\Test\Fabricator;
use Tests\Feature\Helper\AuthenticatedHTTPTestCase;
use Throwable;

class CashFlowActivityTest extends AuthenticatedHTTPTestCase
{
    public function testDefaultIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details
        ] = CashFlowActivityModel::createTestResources(
            $authenticated_info->getUser()->id,
            10,
            []
        );

        $result = $authenticated_info->getRequest()->get("/api/v2/cash_flow_activities");

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "overall_filtered_count" => 10
            ],
            "cash_flow_activities" => json_decode(json_encode($details))
        ]);
    }

    public function testDefaultShow()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details
        ] = CashFlowActivityModel::createTestResource($authenticated_info->getUser()->id, []);

        $result = $authenticated_info->getRequest()->get(
            "/api/v2/cash_flow_activities/{$details->id}"
        );

        $result->assertOk();
        $result->assertJSONExact([
            "cash_flow_activity" => json_decode(json_encode($details))
        ]);
    }

    public function testDefaultCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details
        ] = CashFlowActivityModel::makeTestResource($authenticated_info->getUser()->id, []);

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/cash_flow_activities", [
                "cash_flow_activity" => $details->toArray()
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "cash_flow_activity" => $details->toArray()
        ]);
    }

    public function testDefaultUpdate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details,
            $new_details
        ] = CashFlowActivityModel::createAndMakeTestResources(
            $authenticated_info->getUser()->id,
            []
        );

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v2/cash_flow_activities/$details->id", [
                "cash_flow_activity" => $new_details->toArray()
            ]);

        $result->assertStatus(204);
        $this->seeInDatabase("cash_flow_activities", array_merge(
            [ "id" => $details->id ],
            $new_details->toRawArray()
        ));
    }

    public function testDefaultDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details
        ] = CashFlowActivityModel::createTestResource($authenticated_info->getUser()->id, []);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v2/cash_flow_activities/$details->id");

        $result->assertStatus(204);
        $this->seeInDatabase("cash_flow_activities", array_merge(
            [ "id" => $details->id ]
        ));
        $this->dontSeeInDatabase("cash_flow_activities", [
            "id" => $details->id,
            "deleted_at" => null
        ]);
    }

    public function testDefaultRestore()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details
        ] = CashFlowActivityModel::createTestResource($authenticated_info->getUser()->id, []);
        model(CashFlowActivityModel::class)->delete($details->id);

        $result = $authenticated_info
            ->getRequest()
            ->patch("/api/v2/cash_flow_activities/$details->id");

        $result->assertStatus(204);
        $this->seeInDatabase("cash_flow_activities", [
            "id" => $details->id,
            "deleted_at" => null
        ]);
    }

    public function testDefaultForceDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details
        ] = CashFlowActivityModel::createTestResource($authenticated_info->getUser()->id, []);
        model(CashFlowActivityModel::class)->delete($details->id);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v2/cash_flow_activities/$details->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "cash_flow_activities", []);
    }

    public function testEmptyIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $result = $authenticated_info->getRequest()->get("/api/v2/cash_flow_activities");

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "overall_filtered_count" => 0
            ],
            "cash_flow_activities" => []
        ]);
    }

    public function testQueriedIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details
        ] = CashFlowActivityModel::createTestResources(
            $authenticated_info->getUser()->id,
            10,
            []
        );

        $result = $authenticated_info->getRequest()->get("/api/v2/cash_flow_activities", [
            "sort" => [ [ "id", "ASC" ] ],
            "page" => [
                "limit" => 5
            ]
        ]);

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "overall_filtered_count" => 10
            ],
            "cash_flow_activities" => json_decode(json_encode(array_slice($details, 0, 5)))
        ]);
    }

    public function testMissingShow()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details
        ] = CashFlowActivityModel::createTestResource($authenticated_info->getUser()->id, []);
        $details->id = $details->id + 1;

        $this->expectException(MissingResource::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info->getRequest()->get("/api/v2/cash_flow_activities/$details->id");
    }

    public function testInvalidCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details
        ] = CashFlowActivityModel::makeTestResource($authenticated_info->getUser()->id, [
            "overrides" => [
                "name" => "@only alphanumeric characters only"
            ]
        ]);

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/cash_flow_activities", [
                "cash_flow_activities" => $details->toArray()
            ]);
    }

    public function testInvalidUpdate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details,
            $new_details
        ] = CashFlowActivityModel::createAndMakeTestResources(
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
            ->put("/api/v2/cash_flow_activities/$details->id", [
                "cash_flow_activities" => $new_details->toArray()
            ]);
    }

    public function testUnownedDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        [
            $details
        ] = CashFlowActivityModel::createTestResource($another_user->id, []);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v2/cash_flow_activities/$details->id");
            $this->assertTrue(false);
        } catch (MissingResource $error) {
            $this->seeInDatabase("cash_flow_activities", array_merge(
                [ "id" => $details->id ]
            ));
            $this->seeInDatabase("cash_flow_activities", [
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

        [
            $details
        ] = CashFlowActivityModel::createTestResource($authenticated_info->getUser()->id, []);
        model(CashFlowActivityModel::class)->delete($details->id);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v2/cash_flow_activities/$details->id");
        } catch (MissingResource $error) {
            $this->seeInDatabase("cash_flow_activities", array_merge(
                [ "id" => $details->id ]
            ));
            $this->dontSeeInDatabase("cash_flow_activities", [
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

        [
            $details
        ] = CashFlowActivityModel::createTestResource($authenticated_info->getUser()->id, []);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->patch("/api/v2/cash_flow_activities/$details->id");
        } catch (MissingResource $error) {
            $this->seeInDatabase("cash_flow_activities", [
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
            $details
        ] = CashFlowActivityModel::createTestResource($authenticated_info->getUser()->id, []);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v2/cash_flow_activities/$details->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "cash_flow_activities", []);
    }

    public function testDoubleForceDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        [
            $details
        ] = CashFlowActivityModel::createTestResource($authenticated_info->getUser()->id, []);
        model(CashFlowActivityModel::class)->delete($details->id, true);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v2/cash_flow_activities/$details->id/force");
        } catch (MissingResource $error) {
            $this->seeNumRecords(0, "cash_flow_activities", []);
            throw $error;
        } catch (Throwable $error) {
            $this->assertTrue(false);
        }
    }
}
