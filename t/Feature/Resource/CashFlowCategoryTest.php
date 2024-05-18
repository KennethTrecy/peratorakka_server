<?php

namespace Tests\Feature\Resource;

use Throwable;

use CodeIgniter\Test\Fabricator;

use App\Exceptions\InvalidRequest;
use App\Exceptions\MissingResource;
use App\Models\CashFlowCategoryModel;
use Tests\Feature\Helper\AuthenticatedHTTPTestCase;

class CashFlowCategoryTest extends AuthenticatedHTTPTestCase
{
    public function testDefaultIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $cash_flow_category_fabricator = new Fabricator(CashFlowCategoryModel::class);
        $cash_flow_category_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_categories = $cash_flow_category_fabricator->create(10);

        $result = $authenticated_info->getRequest()->get("/api/v1/cash_flow_categories");

        $result->assertOk();
        $result->assertJSONExact([
            "meta" => [
                "overall_filtered_count" => 10
            ],
            "cash_flow_categories" => json_decode(json_encode($cash_flow_categories))
        ]);
    }

    public function testDefaultShow()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $cash_flow_category_fabricator = new Fabricator(CashFlowCategoryModel::class);
        $cash_flow_category_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_category = $cash_flow_category_fabricator->create();

        $result = $authenticated_info->getRequest()->get("/api/v1/cash_flow_categories/$cash_flow_category->id");

        $result->assertOk();
        $result->assertJSONExact([
            "cash_flow_category" => json_decode(json_encode($cash_flow_category))
        ]);
    }

    public function testDefaultCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $cash_flow_category_fabricator = new Fabricator(CashFlowCategoryModel::class);
        $cash_flow_category = $cash_flow_category_fabricator->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/cash_flow_categories", [
                "cash_flow_category" => $cash_flow_category->toArray()
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "cash_flow_category" => $cash_flow_category->toArray()
        ]);
    }

    public function testDefaultUpdate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $cash_flow_category_fabricator = new Fabricator(CashFlowCategoryModel::class);
        $cash_flow_category_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_category = $cash_flow_category_fabricator->create();
        $new_details = $cash_flow_category_fabricator->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v1/cash_flow_categories/$cash_flow_category->id", [
                "cash_flow_category" => $new_details->toArray()
            ]);

        $result->assertStatus(204);
        $this->seeInDatabase("cash_flow_categories", array_merge(
            [ "id" => $cash_flow_category->id ],
            $new_details->toRawArray()
        ));
    }

    public function testDefaultDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $cash_flow_category_fabricator = new Fabricator(CashFlowCategoryModel::class);
        $cash_flow_category_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_category = $cash_flow_category_fabricator->create();

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/cash_flow_categories/$cash_flow_category->id");

        $result->assertStatus(204);
        $this->seeInDatabase("cash_flow_categories", array_merge(
            [ "id" => $cash_flow_category->id ]
        ));
        $this->dontSeeInDatabase("cash_flow_categories", [
            "id" => $cash_flow_category->id,
            "deleted_at" => null
        ]);
    }

    public function testDefaultRestore()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $cash_flow_category_fabricator = new Fabricator(CashFlowCategoryModel::class);
        $cash_flow_category_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_category = $cash_flow_category_fabricator->create();
        model(CashFlowCategoryModel::class)->delete($cash_flow_category->id);

        $result = $authenticated_info
            ->getRequest()
            ->patch("/api/v1/cash_flow_categories/$cash_flow_category->id");

        $result->assertStatus(204);
        $this->seeInDatabase("cash_flow_categories", [
            "id" => $cash_flow_category->id,
            "deleted_at" => null
        ]);
    }

    public function testDefaultForceDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $cash_flow_category_fabricator = new Fabricator(CashFlowCategoryModel::class);
        $cash_flow_category_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_category = $cash_flow_category_fabricator->create();
        model(CashFlowCategoryModel::class)->delete($cash_flow_category->id);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/cash_flow_categories/$cash_flow_category->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "cash_flow_categories", []);
    }

    public function testEmptyIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $result = $authenticated_info->getRequest()->get("/api/v1/cash_flow_categories");

        $result->assertOk();
        $result->assertJSONExact([
            "meta" => [
                "overall_filtered_count" => 0
            ],
            "cash_flow_categories" => []
        ]);
    }

    public function testQueriedIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $cash_flow_category_fabricator = new Fabricator(CashFlowCategoryModel::class);
        $cash_flow_category_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_categories = $cash_flow_category_fabricator->create(10);

        $result = $authenticated_info->getRequest()->get("/api/v1/cash_flow_categories", [
            "page" => [
                "limit" => 5
            ]
        ]);

        $result->assertOk();
        $result->assertJSONExact([
            "meta" => [
                "overall_filtered_count" => 10
            ],
            "cash_flow_categories" => json_decode(json_encode(array_slice($cash_flow_categories, 0, 5)))
        ]);
    }

    public function testMissingShow()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $cash_flow_category_fabricator = new Fabricator(CashFlowCategoryModel::class);
        $cash_flow_category_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_category = $cash_flow_category_fabricator->create();
        $cash_flow_category->id = $cash_flow_category->id + 1;

        $this->expectException(MissingResource::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info->getRequest()->get("/api/v1/cash_flow_categories/$cash_flow_category->id");
    }

    public function testInvalidCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $cash_flow_category_fabricator = new Fabricator(CashFlowCategoryModel::class);
        $cash_flow_category_fabricator->setOverrides([
            "name" => "@only alphanumeric characters only"
        ]);
        $cash_flow_category = $cash_flow_category_fabricator->make();

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/cash_flow_categories", [
                "cash_flow_category" => $cash_flow_category->toArray()
            ]);
    }

    public function testInvalidUpdate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $cash_flow_category_fabricator = new Fabricator(CashFlowCategoryModel::class);
        $cash_flow_category_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_category = $cash_flow_category_fabricator->create();
        $cash_flow_category_fabricator->setOverrides([
            "name" => "@only alphanumeric characters only"
        ]);
        $new_details = $cash_flow_category_fabricator->make();

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v1/cash_flow_categories/$cash_flow_category->id", [
                "cash_flow_category" => $new_details->toArray()
            ]);
    }

    public function testUnownedDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        $cash_flow_category_fabricator = new Fabricator(CashFlowCategoryModel::class);
        $cash_flow_category_fabricator->setOverrides([
            "user_id" => $another_user->id
        ]);
        $cash_flow_category = $cash_flow_category_fabricator->create();

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v1/cash_flow_categories/$cash_flow_category->id");
            $this->assertTrue(false);
        } catch (MissingResource $error) {
            $this->seeInDatabase("cash_flow_categories", array_merge(
                [ "id" => $cash_flow_category->id ]
            ));
            $this->seeInDatabase("cash_flow_categories", [
                "id" => $cash_flow_category->id,
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

        $cash_flow_category_fabricator = new Fabricator(CashFlowCategoryModel::class);
        $cash_flow_category_fabricator->setOverrides([
            "user_id" => $another_user->id
        ]);
        $cash_flow_category = $cash_flow_category_fabricator->create();
        model(CashFlowCategoryModel::class)->delete($cash_flow_category->id);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v1/cash_flow_categories/$cash_flow_category->id");
        } catch (MissingResource $error) {
            $this->seeInDatabase("cash_flow_categories", array_merge(
                [ "id" => $cash_flow_category->id ]
            ));
            $this->dontSeeInDatabase("cash_flow_categories", [
                "id" => $cash_flow_category->id,
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

        $cash_flow_category_fabricator = new Fabricator(CashFlowCategoryModel::class);
        $cash_flow_category_fabricator->setOverrides([
            "user_id" => $another_user->id
        ]);
        $cash_flow_category = $cash_flow_category_fabricator->create();

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->patch("/api/v1/cash_flow_categories/$cash_flow_category->id");
        } catch (MissingResource $error) {
            $this->seeInDatabase("cash_flow_categories", [
                "id" => $cash_flow_category->id,
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

        $cash_flow_category_fabricator = new Fabricator(CashFlowCategoryModel::class);
        $cash_flow_category_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_category = $cash_flow_category_fabricator->create();

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/cash_flow_categories/$cash_flow_category->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "cash_flow_categories", []);
    }

    public function testDoubleForceDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        $cash_flow_category_fabricator = new Fabricator(CashFlowCategoryModel::class);
        $cash_flow_category_fabricator->setOverrides([
            "user_id" => $another_user->id
        ]);
        $cash_flow_category = $cash_flow_category_fabricator->create();
        model(CashFlowCategoryModel::class)->delete($cash_flow_category->id, true);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v1/cash_flow_categories/$cash_flow_category->id/force");
        } catch (MissingResource $error) {
            $this->seeNumRecords(0, "cash_flow_categories", []);
            throw $error;
        } catch (Throwable $error) {
            $this->assertTrue(false);
        }
    }
}
