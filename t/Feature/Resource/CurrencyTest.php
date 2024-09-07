<?php

namespace Tests\Feature\Resource;

use App\Exceptions\InvalidRequest;
use App\Exceptions\MissingResource;

use App\Models\CurrencyModel;
use CodeIgniter\Test\Fabricator;
use Tests\Feature\Helper\AuthenticatedHTTPTestCase;
use Throwable;

class CurrencyTest extends AuthenticatedHTTPTestCase
{
    public function testDefaultIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currencies = $currency_fabricator->create(10);

        $result = $authenticated_info->getRequest()->get("/api/v1/currencies");

        $result->assertOk();
        $result->assertJSONExact([
            "meta" => [
                "overall_filtered_count" => 10
            ],
            "currencies" => json_decode(json_encode($currencies))
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

        $result = $authenticated_info->getRequest()->get("/api/v1/currencies/$currency->id");

        $result->assertOk();
        $result->assertJSONExact([
            "currency" => json_decode(json_encode($currency))
        ]);
    }

    public function testDefaultCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/currencies", [
                "currency" => $currency->toArray()
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "currency" => $currency->toArray()
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
        $new_details = $currency_fabricator->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v1/currencies/$currency->id", [
                "currency" => $new_details->toArray()
            ]);

        $result->assertStatus(204);
        $this->seeInDatabase("currencies", array_merge(
            [ "id" => $currency->id ],
            $new_details->toArray()
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

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/currencies/$currency->id");

        $result->assertStatus(204);
        $this->seeInDatabase("currencies", array_merge(
            [ "id" => $currency->id ]
        ));
        $this->dontSeeInDatabase("currencies", [
            "id" => $currency->id,
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
        model(CurrencyModel::class)->delete($currency->id);

        $result = $authenticated_info
            ->getRequest()
            ->patch("/api/v1/currencies/$currency->id");

        $result->assertStatus(204);
        $this->seeInDatabase("currencies", [
            "id" => $currency->id,
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
        model(CurrencyModel::class)->delete($currency->id);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/currencies/$currency->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "currencies", []);
    }

    public function testEmptyIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $result = $authenticated_info->getRequest()->get("/api/v1/currencies");

        $result->assertOk();
        $result->assertJSONExact([
            "meta" => [
                "overall_filtered_count" => 0
            ],
            "currencies" => []
        ]);
    }

    public function testQueriedIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currencies = $currency_fabricator->create(10);

        $result = $authenticated_info->getRequest()->get("/api/v1/currencies", [
            "page" => [
                "limit" => 5
            ]
        ]);

        $result->assertOk();
        $result->assertJSONExact([
            "meta" => [
                "overall_filtered_count" => 10
            ],
            "currencies" => json_decode(json_encode(array_slice($currencies, 0, 5)))
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
        $currency->id = $currency->id + 1;

        $this->expectException(MissingResource::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info->getRequest()->get("/api/v1/currencies/$currency->id");
    }

    public function testInvalidCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "name" => "@only alphanumeric characters only"
        ]);
        $currency = $currency_fabricator->make();

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/currencies", [
                "currency" => $currency->toArray()
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
        $currency_fabricator->setOverrides([
            "name" => "@only alphanumeric characters only"
        ]);
        $new_details = $currency_fabricator->make();

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v1/currencies/$currency->id", [
                "currency" => $new_details->toArray()
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

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v1/currencies/$currency->id");
            $this->assertTrue(false);
        } catch (MissingResource $error) {
            $this->seeInDatabase("currencies", array_merge(
                [ "id" => $currency->id ]
            ));
            $this->seeInDatabase("currencies", [
                "id" => $currency->id,
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

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $another_user->id
        ]);
        $currency = $currency_fabricator->create();
        model(CurrencyModel::class)->delete($currency->id);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v1/currencies/$currency->id");
        } catch (MissingResource $error) {
            $this->seeInDatabase("currencies", array_merge(
                [ "id" => $currency->id ]
            ));
            $this->dontSeeInDatabase("currencies", [
                "id" => $currency->id,
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

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $another_user->id
        ]);
        $currency = $currency_fabricator->create();

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->patch("/api/v1/currencies/$currency->id");
        } catch (MissingResource $error) {
            $this->seeInDatabase("currencies", [
                "id" => $currency->id,
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

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/currencies/$currency->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "currencies", []);
    }

    public function testDoubleForceDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $another_user->id
        ]);
        $currency = $currency_fabricator->create();
        model(CurrencyModel::class)->delete($currency->id, true);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v1/currencies/$currency->id/force");
        } catch (MissingResource $error) {
            $this->seeNumRecords(0, "currencies", []);
            throw $error;
        } catch (Throwable $error) {
            $this->assertTrue(false);
        }
    }
}
