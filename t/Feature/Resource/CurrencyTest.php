<?php

namespace Tests\Feature\Resource;

use CodeIgniter\Test\Fabricator;

use Tests\Feature\Helper\AuthenticatedHTTPTestCase;
use App\Models\CurrencyModel;

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
            "currencies" => []
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

        $result = $authenticated_info->getRequest()->get("/api/v1/currencies/$currency->id");

        $result->assertNotFound();
        $result->assertJSONFragment([
            "errors" => []
        ]);
    }

    public function testInvalidCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "name" => "@only alphanumeric characters only"
        ]);
        $currency = $currency_fabricator->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/currencies", [
                "currency" => $currency->toArray()
            ]);

        $result->assertInvalid();
        $result->assertJSONFragment([
            "errors" => []
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

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v1/currencies/$currency->id", [
                "currency" => $new_details->toArray()
            ]);

        $result->assertInvalid();
        $result->assertJSONFragment([
            "errors" => []
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

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/currencies/$currency->id");

        $result->assertNotFound();
        $this->seeInDatabase("currencies", array_merge(
            [ "id" => $currency->id ]
        ));
        $this->seeInDatabase("currencies", [
            "id" => $currency->id,
            "deleted_at" => null
        ]);
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

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/currencies/$currency->id");

        $result->assertNotFound();
        $this->seeInDatabase("currencies", array_merge(
            [ "id" => $currency->id ]
        ));
        $this->dontSeeInDatabase("currencies", [
            "id" => $currency->id,
            "deleted_at" => null
        ]);
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

        $result = $authenticated_info
            ->getRequest()
            ->patch("/api/v1/currencies/$currency->id");

        $result->assertNotFound();
        $this->seeInDatabase("currencies", [
            "id" => $currency->id,
            "deleted_at" => null
        ]);
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

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/currencies/$currency->id/force");

        $result->assertNotFound();
        $this->seeNumRecords(0, "currencies", []);
    }
}
