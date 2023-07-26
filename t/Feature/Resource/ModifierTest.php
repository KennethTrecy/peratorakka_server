<?php

namespace Tests\Feature\Authentication;

use CodeIgniter\Test\Fabricator;

use Tests\Feature\Helper\AuthenticatedHTTPTestCase;
use App\Models\CurrencyModel;
use App\Models\AccountModel;
use App\Models\ModifierModel;

class ModifierTest extends AuthenticatedHTTPTestCase
{
    public function DefaultIndex()
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
        $accounts = $account_fabricator->create(10);

        $result = $authenticated_info->getRequest()->get("/api/v1/modifiers");

        $result->assertOk();
        $result->assertJSONExact([
            "accounts" => json_decode(json_encode($accounts)),
            "currencies" => [ $currency ],
        ]);
    }

    public function DefaultShow()
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

        $result = $authenticated_info->getRequest()->get("/api/v1/modifiers/$account->id");

        $result->assertOk();
        $result->assertJSONExact([
            "account" => json_decode(json_encode($account)),
            "currencies" => json_decode(json_encode([ $currency ]))
        ]);
    }

    public function DefaultCreate()
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
        $account = $account_fabricator->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/modifiers", [
                "account" => $account->toArray()
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "account" => $account->toArray()
        ]);
    }

    public function DefaultUpdate()
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
        $new_details = $account_fabricator->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v1/modifiers/$account->id", [
                "account" => $new_details->toArray()
            ]);

        $result->assertStatus(204);
        $this->seeInDatabase("accounts", array_merge(
            [ "id" => $account->id ],
            $new_details->toArray()
        ));
    }

    public function DefaultDelete()
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

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/modifiers/$account->id");

        $result->assertStatus(204);
        $this->seeInDatabase("accounts", array_merge(
            [ "id" => $account->id ]
        ));
        $this->dontSeeInDatabase("accounts", [
            "id" => $account->id,
            "deleted_at" => null
        ]);
    }

    public function DefaultRestore()
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
        model(AccountModel::class)->delete($account->id);

        $result = $authenticated_info
            ->getRequest()
            ->patch("/api/v1/modifiers/$account->id");

        $result->assertStatus(204);
        $this->seeInDatabase("accounts", [
            "id" => $account->id,
            "deleted_at" => null
        ]);
    }

    public function DefaultForceDelete()
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
        model(AccountModel::class)->delete($account->id);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/modifiers/$account->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "accounts", []);
    }

    public function EmptyIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $result = $authenticated_info->getRequest()->get("/api/v1/modifiers");

        $result->assertOk();
        $result->assertJSONExact([
            "accounts" => [],
            "currencies" => [],
        ]);
    }

    public function MissingShow()
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
        $account->id = $account->id + 1;

        $result = $authenticated_info->getRequest()->get("/api/v1/modifiers/$account->id");

        $result->assertNotFound();
        $result->assertJSONFragment([
            "errors" => []
        ]);
    }

    public function InvalidCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currency = $currency_fabricator->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "name" => "@only alphanumeric characters only"
        ]);
        $account = $account_fabricator->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/modifiers", [
                "account" => $account->toArray()
            ]);

        $result->assertInvalid();
        $result->assertJSONFragment([
            "errors" => []
        ]);
    }

    public function InvalidUpdate()
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
        $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "name" => "@only alphanumeric characters only"
        ]);
        $new_details = $account_fabricator->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v1/modifiers/$account->id", [
                "account" => $new_details->toArray()
            ]);

        $result->assertInvalid();
        $result->assertJSONFragment([
            "errors" => []
        ]);
    }

    public function UnownedDelete()
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

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/modifiers/$account->id");

        $result->assertNotFound();
        $this->seeInDatabase("accounts", array_merge(
            [ "id" => $account->id ]
        ));
        $this->seeInDatabase("accounts", [
            "id" => $account->id,
            "deleted_at" => null
        ]);
    }

    public function DoubleDelete()
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
        model(AccountModel::class)->delete($account->id);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/modifiers/$account->id");

        $result->assertNotFound();
        $this->seeInDatabase("accounts", array_merge(
            [ "id" => $account->id ]
        ));
        $this->dontSeeInDatabase("accounts", [
            "id" => $account->id,
            "deleted_at" => null
        ]);
    }

    public function DoubleRestore()
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

        $result = $authenticated_info
            ->getRequest()
            ->patch("/api/v1/modifiers/$account->id");

        $result->assertNotFound();
        $this->seeInDatabase("accounts", [
            "id" => $account->id,
            "deleted_at" => null
        ]);
    }

    public function ImmediateForceDelete()
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

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/modifiers/$account->id/force");
        $result->assertStatus(204);
        $this->seeNumRecords(0, "accounts", []);
    }

    public function DoubleForceDelete()
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
        model(AccountModel::class)->delete($account->id, true);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/modifiers/$account->id/force");

        $result->assertNotFound();
        $this->seeNumRecords(0, "accounts", []);
    }
}