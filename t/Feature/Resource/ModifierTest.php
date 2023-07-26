<?php

namespace Tests\Feature\Authentication;

use CodeIgniter\Test\Fabricator;

use Tests\Feature\Helper\AuthenticatedHTTPTestCase;
use App\Models\CurrencyModel;
use App\Models\AccountModel;
use App\Models\ModifierModel;

class ModifierTest extends AuthenticatedHTTPTestCase
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
        $opposite_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "account_id" => $account->id,
            "opposite_account_id" => $opposite_account->id
        ]);
        $modifiers = $modifier_fabricator->create(10);

        $result = $authenticated_info->getRequest()->get("/api/v1/modifiers");

        $result->assertOk();
        $result->assertJSONExact([
            "accounts" => json_decode(json_encode([ $account, $opposite_account ])),
            "currencies" => [ $currency ],
            "modifiers" => json_decode(json_encode($modifiers)),
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
        $opposite_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "account_id" => $account->id,
            "opposite_account_id" => $opposite_account->id
        ]);
        $modifier = $modifier_fabricator->create();

        $result = $authenticated_info->getRequest()->get("/api/v1/modifiers/$modifier->id");

        $result->assertOk();
        $result->assertJSONExact([
            "accounts" => json_decode(json_encode([ $account, $opposite_account ])),
            "currencies" => [ $currency ],
            "modifier" => json_decode(json_encode($modifier)),
        ]);
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
        $opposite_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "account_id" => $account->id,
            "opposite_account_id" => $opposite_account->id
        ]);
        $modifier = $modifier_fabricator->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/modifiers", [
                "modifier" => $account->toArray()
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "modifier" => $modifier->toArray()
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
        model(AccountModel::class)->delete($account->id);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/modifiers/$account->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "accounts", []);
    }

    public function testEmptyIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $result = $authenticated_info->getRequest()->get("/api/v1/modifiers");

        $result->assertOk();
        $result->assertJSONExact([
            "accounts" => [],
            "currencies" => [],
            "modifiers" => [],
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
        $account_fabricator = new Fabricator(AccountModel::class);
        $account_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $account = $account_fabricator->create();
        $opposite_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "account_id" => $account->id,
            "opposite_account_id" => $opposite_account->id
        ]);
        $modifier = $modifier_fabricator->create();
        $modifier->id = $modifier->id + 1;

        $result = $authenticated_info->getRequest()->get("/api/v1/modifiers/$modifier->id");

        $result->assertNotFound();
        $result->assertJSONFragment([
            "errors" => []
        ]);
    }

    public function testInvalidCreate()
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
        $account = $account_fabricator->create();
        $opposite_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "account_id" => $account->id,
            "opposite_account_id" => $opposite_account->id
        ]);
        $modifier = $modifier_fabricator->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/modifiers", [
                "modifier" => $modifier->toArray()
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

    public function testDoubleDelete()
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

    public function testDoubleRestore()
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

    public function testImmediateForceDelete()
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

    public function testDoubleForceDelete()
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
