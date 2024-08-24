<?php

namespace Tests\Feature\Resource;

use Throwable;

use CodeIgniter\Test\Fabricator;

use App\Exceptions\InvalidRequest;
use App\Exceptions\MissingResource;
use App\Models\CollectionModel;
use Tests\Feature\Helper\AuthenticatedHTTPTestCase;

class CollectionTest extends AuthenticatedHTTPTestCase
{
    public function testDefaultIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $collection_fabricator = new Fabricator(CollectionModel::class);
        $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $collections = $collection_fabricator->create(10);

        $result = $authenticated_info->getRequest()->get("/api/v1/collections");

        $result->assertOk();
        $result->assertJSONExact([
            "meta" => [
                "overall_filtered_count" => 10
            ],
            "collections" => json_decode(json_encode($collections))
        ]);
    }

    public function testDefaultShow()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $collection_fabricator = new Fabricator(CollectionModel::class);
        $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $collection = $collection_fabricator->create();

        $result = $authenticated_info->getRequest()->get("/api/v1/collections/$collection->id");

        $result->assertOk();
        $result->assertJSONExact([
            "collection" => json_decode(json_encode($collection))
        ]);
    }

    public function testDefaultCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $collection_fabricator = new Fabricator(CollectionModel::class);
        $collection = $collection_fabricator->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/collections", [
                "collection" => $collection->toArray()
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "collection" => $collection->toArray()
        ]);
    }

    public function testDefaultUpdate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $collection_fabricator = new Fabricator(CollectionModel::class);
        $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $collection = $collection_fabricator->create();
        $new_details = $collection_fabricator->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v1/collections/$collection->id", [
                "collection" => $new_details->toArray()
            ]);

        $result->assertStatus(204);
        $this->seeInDatabase("collections", array_merge(
            [ "id" => $collection->id ],
            $new_details->toArray()
        ));
    }

    public function testDefaultDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $collection_fabricator = new Fabricator(CollectionModel::class);
        $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $collection = $collection_fabricator->create();

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/collections/$collection->id");

        $result->assertStatus(204);
        $this->seeInDatabase("collections", array_merge(
            [ "id" => $collection->id ]
        ));
        $this->dontSeeInDatabase("collections", [
            "id" => $collection->id,
            "deleted_at" => null
        ]);
    }

    public function testDefaultRestore()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $collection_fabricator = new Fabricator(CollectionModel::class);
        $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $collection = $collection_fabricator->create();
        model(CollectionModel::class)->delete($collection->id);

        $result = $authenticated_info
            ->getRequest()
            ->patch("/api/v1/collections/$collection->id");

        $result->assertStatus(204);
        $this->seeInDatabase("collections", [
            "id" => $collection->id,
            "deleted_at" => null
        ]);
    }

    public function testDefaultForceDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $collection_fabricator = new Fabricator(CollectionModel::class);
        $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $collection = $collection_fabricator->create();
        model(CollectionModel::class)->delete($collection->id);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/collections/$collection->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "collections", []);
    }

    public function testEmptyIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $result = $authenticated_info->getRequest()->get("/api/v1/collections");

        $result->assertOk();
        $result->assertJSONExact([
            "meta" => [
                "overall_filtered_count" => 0
            ],
            "collections" => []
        ]);
    }

    public function testQueriedIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $collection_fabricator = new Fabricator(CollectionModel::class);
        $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $collections = $collection_fabricator->create(10);

        $result = $authenticated_info->getRequest()->get("/api/v1/collections", [
            "page" => [
                "limit" => 5
            ]
        ]);

        $result->assertOk();
        $result->assertJSONExact([
            "meta" => [
                "overall_filtered_count" => 10
            ],
            "collections" => json_decode(json_encode(array_slice($collections, 0, 5)))
        ]);
    }

    public function testMissingShow()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $collection_fabricator = new Fabricator(CollectionModel::class);
        $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $collection = $collection_fabricator->create();
        $collection->id = $collection->id + 1;

        $this->expectException(MissingResource::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info->getRequest()->get("/api/v1/collections/$collection->id");
    }

    public function testInvalidCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $collection_fabricator = new Fabricator(CollectionModel::class);
        $collection_fabricator->setOverrides([
            "name" => "@only alphanumeric characters only"
        ]);
        $collection = $collection_fabricator->make();

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/collections", [
                "collection" => $collection->toArray()
            ]);
    }

    public function testInvalidUpdate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $collection_fabricator = new Fabricator(CollectionModel::class);
        $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $collection = $collection_fabricator->create();
        $collection_fabricator->setOverrides([
            "name" => "@only alphanumeric characters only"
        ]);
        $new_details = $collection_fabricator->make();

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v1/collections/$collection->id", [
                "collection" => $new_details->toArray()
            ]);
    }

    public function testUnownedDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        $collection_fabricator = new Fabricator(CollectionModel::class);
        $collection_fabricator->setOverrides([
            "user_id" => $another_user->id
        ]);
        $collection = $collection_fabricator->create();

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v1/collections/$collection->id");
            $this->assertTrue(false);
        } catch (MissingResource $error) {
            $this->seeInDatabase("collections", array_merge(
                [ "id" => $collection->id ]
            ));
            $this->seeInDatabase("collections", [
                "id" => $collection->id,
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

        $collection_fabricator = new Fabricator(CollectionModel::class);
        $collection_fabricator->setOverrides([
            "user_id" => $another_user->id
        ]);
        $collection = $collection_fabricator->create();
        model(CollectionModel::class)->delete($collection->id);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v1/collections/$collection->id");
        } catch (MissingResource $error) {
            $this->seeInDatabase("collections", array_merge(
                [ "id" => $collection->id ]
            ));
            $this->dontSeeInDatabase("collections", [
                "id" => $collection->id,
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

        $collection_fabricator = new Fabricator(CollectionModel::class);
        $collection_fabricator->setOverrides([
            "user_id" => $another_user->id
        ]);
        $collection = $collection_fabricator->create();

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->patch("/api/v1/collections/$collection->id");
        } catch (MissingResource $error) {
            $this->seeInDatabase("collections", [
                "id" => $collection->id,
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

        $collection_fabricator = new Fabricator(CollectionModel::class);
        $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $collection = $collection_fabricator->create();

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/collections/$collection->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "collections", []);
    }

    public function testDoubleForceDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        $collection_fabricator = new Fabricator(CollectionModel::class);
        $collection_fabricator->setOverrides([
            "user_id" => $another_user->id
        ]);
        $collection = $collection_fabricator->create();
        model(CollectionModel::class)->delete($collection->id, true);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v1/collections/$collection->id/force");
        } catch (MissingResource $error) {
            $this->seeNumRecords(0, "collections", []);
            throw $error;
        } catch (Throwable $error) {
            $this->assertTrue(false);
        }
    }
}
