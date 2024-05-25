<?php

namespace Tests\Feature\Resource;

use Throwable;

use CodeIgniter\Test\Fabricator;

use App\Exceptions\InvalidRequest;
use App\Exceptions\MissingResource;
use App\Models\CashFlowActivityModel;
use Tests\Feature\Helper\AuthenticatedHTTPTestCase;

class CashFlowActivityTest extends AuthenticatedHTTPTestCase
{
    public function testDefaultIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_activities = $cash_flow_activity_fabricator->create(10);

        $result = $authenticated_info->getRequest()->get("/api/v1/cash_flow_activities");

        $result->assertOk();
        $result->assertJSONExact([
            "meta" => [
                "overall_filtered_count" => 10
            ],
            "cash_flow_activities" => json_decode(json_encode($cash_flow_activities))
        ]);
    }

    public function testDefaultShow()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_activity = $cash_flow_activity_fabricator->create();

        $result = $authenticated_info->getRequest()->get("/api/v1/cash_flow_activities/$cash_flow_activity->id");

        $result->assertOk();
        $result->assertJSONExact([
            "cash_flow_activity" => json_decode(json_encode($cash_flow_activity))
        ]);
    }

    public function testDefaultCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity = $cash_flow_activity_fabricator->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/cash_flow_activities", [
                "cash_flow_activity" => $cash_flow_activity->toArray()
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "cash_flow_activity" => $cash_flow_activity->toArray()
        ]);
    }

    public function testDefaultUpdate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_activity = $cash_flow_activity_fabricator->create();
        $new_details = $cash_flow_activity_fabricator->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v1/cash_flow_activities/$cash_flow_activity->id", [
                "cash_flow_activity" => $new_details->toArray()
            ]);

        $result->assertStatus(204);
        $this->seeInDatabase("cash_flow_activities", array_merge(
            [ "id" => $cash_flow_activity->id ],
            $new_details->toRawArray()
        ));
    }

    public function testDefaultDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_activity = $cash_flow_activity_fabricator->create();

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/cash_flow_activities/$cash_flow_activity->id");

        $result->assertStatus(204);
        $this->seeInDatabase("cash_flow_activities", array_merge(
            [ "id" => $cash_flow_activity->id ]
        ));
        $this->dontSeeInDatabase("cash_flow_activities", [
            "id" => $cash_flow_activity->id,
            "deleted_at" => null
        ]);
    }

    public function testDefaultRestore()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_activity = $cash_flow_activity_fabricator->create();
        model(CashFlowActivityModel::class)->delete($cash_flow_activity->id);

        $result = $authenticated_info
            ->getRequest()
            ->patch("/api/v1/cash_flow_activities/$cash_flow_activity->id");

        $result->assertStatus(204);
        $this->seeInDatabase("cash_flow_activities", [
            "id" => $cash_flow_activity->id,
            "deleted_at" => null
        ]);
    }

    public function testDefaultForceDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_activity = $cash_flow_activity_fabricator->create();
        model(CashFlowActivityModel::class)->delete($cash_flow_activity->id);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/cash_flow_activities/$cash_flow_activity->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "cash_flow_activities", []);
    }

    public function testEmptyIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $result = $authenticated_info->getRequest()->get("/api/v1/cash_flow_activities");

        $result->assertOk();
        $result->assertJSONExact([
            "meta" => [
                "overall_filtered_count" => 0
            ],
            "cash_flow_activities" => []
        ]);
    }

    public function testQueriedIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_activities = $cash_flow_activity_fabricator->create(10);

        $result = $authenticated_info->getRequest()->get("/api/v1/cash_flow_activities", [
            "page" => [
                "limit" => 5
            ]
        ]);

        $result->assertOk();
        $result->assertJSONExact([
            "meta" => [
                "overall_filtered_count" => 10
            ],
            "cash_flow_activities" => json_decode(json_encode(array_slice($cash_flow_activities, 0, 5)))
        ]);
    }

    public function testMissingShow()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_activity = $cash_flow_activity_fabricator->create();
        $cash_flow_activity->id = $cash_flow_activity->id + 1;

        $this->expectException(MissingResource::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info->getRequest()->get("/api/v1/cash_flow_activities/$cash_flow_activity->id");
    }

    public function testInvalidCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity_fabricator->setOverrides([
            "name" => "@only alphanumeric characters only"
        ]);
        $cash_flow_activity = $cash_flow_activity_fabricator->make();

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/cash_flow_activities", [
                "cash_flow_activity" => $cash_flow_activity->toArray()
            ]);
    }

    public function testInvalidUpdate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_activity = $cash_flow_activity_fabricator->create();
        $cash_flow_activity_fabricator->setOverrides([
            "name" => "@only alphanumeric characters only"
        ]);
        $new_details = $cash_flow_activity_fabricator->make();

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v1/cash_flow_activities/$cash_flow_activity->id", [
                "cash_flow_activity" => $new_details->toArray()
            ]);
    }

    public function testUnownedDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $another_user->id
        ]);
        $cash_flow_activity = $cash_flow_activity_fabricator->create();

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v1/cash_flow_activities/$cash_flow_activity->id");
            $this->assertTrue(false);
        } catch (MissingResource $error) {
            $this->seeInDatabase("cash_flow_activities", array_merge(
                [ "id" => $cash_flow_activity->id ]
            ));
            $this->seeInDatabase("cash_flow_activities", [
                "id" => $cash_flow_activity->id,
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

        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $another_user->id
        ]);
        $cash_flow_activity = $cash_flow_activity_fabricator->create();
        model(CashFlowActivityModel::class)->delete($cash_flow_activity->id);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v1/cash_flow_activities/$cash_flow_activity->id");
        } catch (MissingResource $error) {
            $this->seeInDatabase("cash_flow_activities", array_merge(
                [ "id" => $cash_flow_activity->id ]
            ));
            $this->dontSeeInDatabase("cash_flow_activities", [
                "id" => $cash_flow_activity->id,
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

        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $another_user->id
        ]);
        $cash_flow_activity = $cash_flow_activity_fabricator->create();

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->patch("/api/v1/cash_flow_activities/$cash_flow_activity->id");
        } catch (MissingResource $error) {
            $this->seeInDatabase("cash_flow_activities", [
                "id" => $cash_flow_activity->id,
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

        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_activity = $cash_flow_activity_fabricator->create();

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/cash_flow_activities/$cash_flow_activity->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "cash_flow_activities", []);
    }

    public function testDoubleForceDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $another_user->id
        ]);
        $cash_flow_activity = $cash_flow_activity_fabricator->create();
        model(CashFlowActivityModel::class)->delete($cash_flow_activity->id, true);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v1/cash_flow_activities/$cash_flow_activity->id/force");
        } catch (MissingResource $error) {
            $this->seeNumRecords(0, "cash_flow_activities", []);
            throw $error;
        } catch (Throwable $error) {
            $this->assertTrue(false);
        }
    }
}
