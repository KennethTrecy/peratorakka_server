<?php

namespace Tests\Feature\Resource;

use App\Exceptions\InvalidRequest;
use App\Exceptions\MissingResource;
use App\Models\AccountCollectionModel;
use App\Models\AccountModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\Fabricator;
use Tests\Feature\Helper\AuthenticatedHTTPTestCase;
use Throwable;

class AccountCollectionTest extends AuthenticatedHTTPTestCase
{
    public function testDefaultIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $accounts,
            $collections,
            $account_collections
        ] = AccountCollectionModel::createTestResources(
            $authenticated_info->getUser()->id,
            10,
            []
        );

        $result = $authenticated_info->getRequest()->get("/api/v2/account_collections");

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "overall_filtered_count" => 10
            ],
            "account_collections" => json_decode(json_encode($account_collections)),
            "accounts" => json_decode(json_encode($accounts)),
            "collections" => json_decode(json_encode($collections))
        ]);
    }

    public function testDefaultShow()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResource($authenticated_info->getUser()->id, []);

        $this->expectException(PageNotFoundException::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->get("/api/v2/account_collections/$details->id");
    }

    public function testDefaultCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $accounts,
            $collections,
            $details
        ] = AccountCollectionModel::makeTestResource($authenticated_info->getUser()->id, []);

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/account_collections", [
                "account_collection" => $details->toArray()
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "account_collection" => $details->toArray()
        ]);
    }

    public function testDefaultUpdate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $accounts,
            $collections,
            $details,
            $new_details
        ] = AccountCollectionModel::createAndMakeTestResources(
            $authenticated_info->getUser()->id,
            []
        );

        $this->expectException(PageNotFoundException::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v2/account_collections/$details->id", [
                "account_collection" => $new_details->toArray()
            ]);
    }

    public function testDefaultDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResource($authenticated_info->getUser()->id, []);

        $this->expectException(PageNotFoundException::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v2/account_collections/$details->id");
    }

    public function testDefaultRestore()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResource($authenticated_info->getUser()->id, []);
        model(AccountCollectionModel::class)->delete($details->id);

        $this->expectException(PageNotFoundException::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info
            ->getRequest()
            ->patch("/api/v2/account_collections/$details->id");
    }

    public function testDefaultForceDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResource($authenticated_info->getUser()->id, []);
        model(AccountCollectionModel::class)->delete($details->id);

        $this->expectException(MissingResource::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v2/account_collections/$details->id/force");
    }

    public function testEmptyIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $result = $authenticated_info->getRequest()->get("/api/v2/account_collections");

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "overall_filtered_count" => 0
            ],
            "account_collections" => [],
            "accounts" => [],
            "collections" => []
        ]);
    }

    public function testQueriedIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResources(
            $authenticated_info->getUser()->id,
            10,
            []
        );

        $result = $authenticated_info->getRequest()->get("/api/v2/account_collections", [
            "page" => [
                "limit" => 5
            ],
            "relationship" => [
                "collections"
            ]
        ]);

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "overall_filtered_count" => 10
            ],
            "account_collections" => json_decode(json_encode(array_slice($details, 0, 5))),
            "accounts" => json_decode(json_encode($accounts)),
            "collections" => json_decode(json_encode($collections))
        ]);
    }

    public function testMissingShow()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResource($authenticated_info->getUser()->id, []);
        $details->id = $details->id + 1;

        $this->expectException(PageNotFoundException::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info->getRequest()
            ->get("/api/v2/account_collections/$details->id");
    }

    public function testInvalidCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $accounts,
            $collections,
            $details
        ] = AccountCollectionModel::makeTestResource($authenticated_info->getUser()->id, []);

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/account_collections", [
                "account_collection" => [
                    "collection_id" => $details->collection_id
                ]
            ]);
    }

    public function testInvalidUpdate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $accounts,
            $collections,
            $details,
            $new_details
        ] = AccountCollectionModel::createAndMakeTestResources(
            $authenticated_info->getUser()->id,
            [
                "make_overrides" => [
                    "name" => "@only alphanumeric characters only"
                ]
            ]
        );

        $this->expectException(PageNotFoundException::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v2/account_collections/$details->id", [
                "account_collection" => $new_details->toArray()
            ]);
    }

    public function testUnownedDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        [
            $precision_formats,
            $currencies,
            $accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResource($another_user->id, []);

        $this->expectException(PageNotFoundException::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v2/account_collections/$details->id");
    }

    public function testDoubleDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        [
            $precision_formats,
            $currencies,
            $accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResource($another_user->id, []);
        model(AccountCollectionModel::class)->delete($details->id);

        $this->expectException(PageNotFoundException::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v2/account_collections/$details->id");
    }

    public function testDoubleRestore()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        [
            $precision_formats,
            $currencies,
            $accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResource($another_user->id, []);

        $this->expectException(PageNotFoundException::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info
            ->getRequest()
            ->patch("/api/v2/account_collections/$details->id");
    }

    public function testImmediateForceDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResource($authenticated_info->getUser()->id, []);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v2/account_collections/$details->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "account_collections_v2", []);
    }

    public function testDoubleForceDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        [
            $precision_formats,
            $currencies,
            $accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResource($another_user->id, []);
        model(AccountCollectionModel::class)->delete($details->id, true);

        $this->expectException(MissingResource::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v2/account_collections/$details->id/force");
    }
}
