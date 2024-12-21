<?php

namespace Tests\Feature\Resource;

use App\Exceptions\InvalidRequest;
use App\Exceptions\MissingResource;
use App\Models\AccountCollectionModel;
use App\Models\AccountModel;
use App\Models\CollectionModel;
use App\Models\CurrencyModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\Fabricator;
use Tests\Feature\Helper\AuthenticatedHTTPTestCase;
use Throwable;

class AccountCollectionTest extends AuthenticatedHTTPTestCase
{
    public function testDefaultIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currency = $currency_fabricator->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $account_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $account = $account_fabricator->create();
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
        ]);
        $collection = $collection_fabricator->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $account_collection_fabricator->setOverrides([
            "collection_id" => $collection->id,
            "account_id" => $account->id
        ]);
        $account_collection = $account_collection_fabricator->create();

        $result = $authenticated_info->getRequest()->get("/api/v1/account_collections");

        $result->assertOk();
        $result->assertJSONExact([
            "meta" => [
                "overall_filtered_count" => 1
            ],
            "collections" => json_decode(json_encode([ $collection ])),
            "accounts" => json_decode(json_encode([ $account ])),
            "account_collections" => json_decode(json_encode([ $account_collection ]))
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
        $account_fabricator = new Fabricator(AccountModel::class);
        $account_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $account = $account_fabricator->create();
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
        ]);
        $collection = $collection_fabricator->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $account_collection_fabricator->setOverrides([
            "collection_id" => $collection->id,
            "account_id" => $account->id
        ]);
        $account_collection = $account_collection_fabricator->create();

        $this->expectException(PageNotFoundException::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info
            ->getRequest()
            ->get("/api/v1/account_collections/$account_collection->id");
    }

    public function testDefaultCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currency = $currency_fabricator->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $account_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $account = $account_fabricator->create();
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
        ]);
        $collection = $collection_fabricator->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $account_collection_fabricator->setOverrides([
            "collection_id" => $collection->id,
            "account_id" => $account->id
        ]);
        $account_collection = $account_collection_fabricator->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/account_collections", [
                "account_collection" => $account_collection->toArray()
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "account_collection" => $account_collection->toArray()
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
        $account_fabricator = new Fabricator(AccountModel::class);
        $account_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $account = $account_fabricator->create();
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
        ]);
        $collection = $collection_fabricator->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $account_collection_fabricator->setOverrides([
            "collection_id" => $collection->id,
            "account_id" => $account->id
        ]);
        $account_collection = $account_collection_fabricator->create();
        $new_details = $account_collection_fabricator->make();

        $this->expectException(PageNotFoundException::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v1/account_collections/$account_collection->id", [
                "collection" => $new_details->toArray()
            ]);

        $this->seeInDatabase("account_collections", array_merge(
            [ "id" => $account_collection->id ],
            $account_collection->toArray()
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
        $account_fabricator = new Fabricator(AccountModel::class);
        $account_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $account = $account_fabricator->create();
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
        ]);
        $collection = $collection_fabricator->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $account_collection_fabricator->setOverrides([
            "collection_id" => $collection->id,
            "account_id" => $account->id
        ]);
        $account_collection = $account_collection_fabricator->create();

        $this->expectException(PageNotFoundException::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/account_collections/$collection->id");

        $this->seeInDatabase("account_collections", array_merge(
            [ "id" => $account_collection->id ]
        ));
    }

    public function testDefaultRestore()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currency = $currency_fabricator->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $account_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $account = $account_fabricator->create();
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
        ]);
        $collection = $collection_fabricator->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $account_collection_fabricator->setOverrides([
            "collection_id" => $collection->id,
            "account_id" => $account->id
        ]);
        $account_collection = $account_collection_fabricator->create();
        model(AccountCollectionModel::class)->delete($account_collection->id);

        $this->expectException(PageNotFoundException::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info
            ->getRequest()
            ->patch("/api/v1/account_collections/$account_collection->id");

        $this->dontSeeInDatabase("account_collections", [
            "id" => $account_collection->id
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
        $account_fabricator = new Fabricator(AccountModel::class);
        $account_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $account = $account_fabricator->create();
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
        ]);
        $collection = $collection_fabricator->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $account_collection_fabricator->setOverrides([
            "collection_id" => $collection->id,
            "account_id" => $account->id
        ]);
        $account_collection = $account_collection_fabricator->create();

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/account_collections/$account_collection->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "account_collections", []);
    }

    public function testEmptyIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $result = $authenticated_info->getRequest()->get("/api/v1/account_collections");

        $result->assertJSONExact([
            "meta" => [
                "overall_filtered_count" => 0
            ],
            "collections" => [],
            "accounts" => [],
            "account_collections" => []
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
        $account_fabricator = new Fabricator(AccountModel::class);
        $account_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $account = $account_fabricator->create();
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
        ]);
        $collection = $collection_fabricator->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $account_collection_fabricator->setOverrides([
            "collection_id" => $collection->id,
            "account_id" => $account->id
        ]);
        $account_collection = $account_collection_fabricator->create();

        $result = $authenticated_info->getRequest()->get("/api/v1/account_collections", [
            "page" => [
                "limit" => 5
            ]
        ]);
        $result->assertJSONExact([
            "meta" => [
                "overall_filtered_count" => 1
            ],
            "collections" => json_decode(json_encode([ $collection ])),
            "accounts" => json_decode(json_encode([ $account ])),
            "account_collections" => json_decode(json_encode([ $account_collection ]))
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

        $this->expectException(PageNotFoundException::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info->getRequest()->get("/api/v1/account_collections/$collection->id");
    }

    public function testInvalidCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $another_user->id
        ]);
        $currency = $currency_fabricator->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $account_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $account = $account_fabricator->create();
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
        ]);
        $collection = $collection_fabricator->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $account_collection_fabricator->setOverrides([
            "collection_id" => $collection->id,
            "account_id" => $account->id
        ]);
        $account_collection = $account_collection_fabricator->make();

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/account_collections", [
                "account_collection" => $account_collection->toArray()
            ]);
    }

    public function testUnownedDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $another_user->id
        ]);
        $currency = $currency_fabricator->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $account_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $account = $account_fabricator->create();
        $collection_fabricator = new Fabricator(CollectionModel::class);
        $collection_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
        ]);
        $collection = $collection_fabricator->create();
        $account_collection_fabricator = new Fabricator(AccountCollectionModel::class);
        $account_collection_fabricator->setOverrides([
            "collection_id" => $collection->id,
            "account_id" => $account->id
        ]);
        $account_collection = $account_collection_fabricator->create();

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v1/account_collections/$account_collection->id/force");
            $this->assertTrue(false);
        } catch (MissingResource $error) {
            $this->seeInDatabase("account_collections", array_merge(
                [ "id" => $account_collection->id ]
            ));
            $this->seeInDatabase("account_collections", [
                "id" => $account_collection->id
            ]);
            throw $error;
        } catch (Throwable $error) {
            $this->assertTrue(false);
        }
    }
}
