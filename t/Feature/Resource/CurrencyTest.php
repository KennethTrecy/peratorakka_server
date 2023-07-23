<?php

namespace Tests\Feature\Authentication;

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
        $currency_id = $currency["id"];

        $result = $authenticated_info->getRequest()->get("/api/v1/currencies/$currency_id");

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
                "currency" => $currency
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "currency" => $currency
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
        $currency_id = $currency["id"];
        $new_details = $currency_fabricator->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v1/currencies/$currency_id", [
                "currency" => $new_details
            ]);

        $result->assertNoContent();
        $this->seeInDatabase("currencies", array_merge(
            [ "id" => $currency["id"] ],
            $new_details
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
        $currency_id = $currency["id"];

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/currencies/$currency_id");

        $result->assertNoContent();
        $this->seeInDatabase("currencies", array_merge(
            [ "id" => $currency["id"] ]
        ));
        $this->dontSeeInDatabase("currencies", [
            "id" => $currency["id"],
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
        $currency_id = $currency["id"];

        $result = $authenticated_info
            ->getRequest()
            ->patch("/api/v1/currencies/$currency_id");
        model(CurrencyModel::class)->delete($currency_id);

        $result->assertNoContent();
        $this->seeInDatabase("currencies", [
            "id" => $currency["id"],
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
        $currency_id = $currency["id"];

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/currencies/$currency_id/force");
        model(CurrencyModel::class)->delete($currency_id);

        $result->assertNoContent();
        $this->dontSeeInDatabase("currencies", [
            "id" => $currency["id"]
        ]);
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
        $currency_id = $currency["id"] + 1;

        $result = $authenticated_info->getRequest()->get("/api/v1/currencies/$currency_id");

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
                "currency" => $currency
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
        $currency_id = $currency["id"];
        $currency_fabricator->setOverrides([
            "name" => "@only alphanumeric characters only"
        ]);
        $new_details = $currency_fabricator->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v1/currencies/$currency_id", [
                "currency" => $new_details
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
        $currency_id = $currency["id"];

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/currencies/$currency_id");

        $result->assertNotFound();
        $this->seeInDatabase("currencies", array_merge(
            [ "id" => $currency["id"] ]
        ));
        $this->seeInDatabase("currencies", [
            "id" => $currency["id"],
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
        $currency_id = $currency["id"];
        model(CurrencyModel::class)->delete($currency_id);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/currencies/$currency_id");

        $result->assertNotFound();
        $this->seeInDatabase("currencies", array_merge(
            [ "id" => $currency["id"] ]
        ));
        $this->dontSeeInDatabase("currencies", [
            "id" => $currency["id"],
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
        $currency_id = $currency["id"];

        $result = $authenticated_info
            ->getRequest()
            ->patch("/api/v1/currencies/$currency_id");

        $result->assertNotFound();
        $this->seeInDatabase("currencies", [
            "id" => $currency["id"],
            "deleted_at" => null
        ]);
    }

    public function testImmediateForceDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $another_user->id
        ]);
        $currency = $currency_fabricator->create();
        $currency_id = $currency["id"];

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/currencies/$currency_id/force");

        $result->assertNoContent();
        $this->dontSeeInDatabase("currencies", [
            "id" => $currency["id"]
        ]);
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
        $currency_id = $currency["id"];
        model(CurrencyModel::class)->delete($currency_id, true);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/currencies/$currency_id/force");

        $result->assertNotFound();
    }
}
