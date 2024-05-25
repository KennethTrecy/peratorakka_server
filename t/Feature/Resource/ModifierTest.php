<?php

namespace Tests\Feature\Resource;

use Throwable;

use CodeIgniter\Test\Fabricator;

use App\Exceptions\InvalidRequest;
use App\Exceptions\MissingResource;
use App\Models\AccountModel;
use App\Models\CashFlowActivityModel;
use App\Models\CurrencyModel;
use App\Models\ModifierModel;
use Tests\Feature\Helper\AuthenticatedHTTPTestCase;

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
        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_activity = $cash_flow_activity_fabricator->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $account_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $debit_account = $account_fabricator->create();
        $credit_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "debit_account_id" => $debit_account->id,
            "credit_account_id" => $credit_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id
        ]);
        $modifiers = $modifier_fabricator->create(10);

        $result = $authenticated_info->getRequest()->get("/api/v1/modifiers");

        $result->assertOk();
        $result->assertJSONExact([
            "meta" => [
                "overall_filtered_count" => 10
            ],
            "accounts" => json_decode(json_encode([ $debit_account, $credit_account ])),
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
        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_activity = $cash_flow_activity_fabricator->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $account_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $debit_account = $account_fabricator->create();
        $credit_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "debit_account_id" => $debit_account->id,
            "credit_account_id" => $credit_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id
        ]);
        $modifier = $modifier_fabricator->create();

        $result = $authenticated_info->getRequest()->get("/api/v1/modifiers/$modifier->id");

        $result->assertOk();
        $result->assertJSONExact([
            "accounts" => json_decode(json_encode([ $debit_account, $credit_account ])),
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
        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_activity = $cash_flow_activity_fabricator->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $account_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $debit_account = $account_fabricator->create();
        $credit_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "debit_account_id" => $debit_account->id,
            "credit_account_id" => $credit_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id
        ]);
        $modifier = $modifier_fabricator->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/modifiers", [
                "modifier" => $modifier->toArray()
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
        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_activity = $cash_flow_activity_fabricator->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $account_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $debit_account = $account_fabricator->create();
        $credit_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "debit_account_id" => $debit_account->id,
            "credit_account_id" => $credit_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id
        ]);
        $modifier = $modifier_fabricator->create();
        $new_details = $modifier_fabricator->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v1/modifiers/$modifier->id", [
                "modifier" => $new_details->toArray()
            ]);

        $result->assertStatus(204);
        $this->seeInDatabase("modifiers", array_merge(
            [ "id" => $modifier->id ],
            $new_details->toRawArray()
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
        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_activity = $cash_flow_activity_fabricator->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $account_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $debit_account = $account_fabricator->create();
        $credit_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "debit_account_id" => $debit_account->id,
            "credit_account_id" => $credit_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id
        ]);
        $modifier = $modifier_fabricator->create();

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/modifiers/$modifier->id");

        $result->assertStatus(204);
        $this->seeInDatabase("modifiers", array_merge(
            [ "id" => $modifier->id ]
        ));
        $this->dontSeeInDatabase("modifiers", [
            "id" => $modifier->id,
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
        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_activity = $cash_flow_activity_fabricator->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $account_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $debit_account = $account_fabricator->create();
        $credit_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "debit_account_id" => $debit_account->id,
            "credit_account_id" => $credit_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id
        ]);
        $modifier = $modifier_fabricator->create();
        model(ModifierModel::class)->delete($modifier->id);

        $result = $authenticated_info
            ->getRequest()
            ->patch("/api/v1/modifiers/$modifier->id");

        $result->assertStatus(204);
        $this->seeInDatabase("modifiers", [
            "id" => $modifier->id,
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
        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_activity = $cash_flow_activity_fabricator->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $account_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $debit_account = $account_fabricator->create();
        $credit_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "debit_account_id" => $debit_account->id,
            "credit_account_id" => $credit_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id
        ]);
        $modifier = $modifier_fabricator->create();
        model(ModifierModel::class)->delete($modifier->id);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/modifiers/$modifier->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "modifiers", []);
    }

    public function testEmptyIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $result = $authenticated_info->getRequest()->get("/api/v1/modifiers");

        $result->assertOk();
        $result->assertJSONExact([
            "meta" => [
                "overall_filtered_count" => 0
            ],
            "accounts" => [],
            "currencies" => [],
            "modifiers" => [],
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
        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_activity = $cash_flow_activity_fabricator->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $account_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $debit_account = $account_fabricator->create();
        $credit_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "debit_account_id" => $debit_account->id,
            "credit_account_id" => $credit_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id
        ]);
        $modifiers = $modifier_fabricator->create(10);

        $result = $authenticated_info->getRequest()->get("/api/v1/modifiers", [
            "page" => [
                "limit" => 5
            ]
        ]);

        $result->assertOk();
        $result->assertJSONExact([
            "meta" => [
                "overall_filtered_count" => 10
            ],
            "accounts" => json_decode(json_encode([ $debit_account, $credit_account ])),
            "currencies" => [ $currency ],
            "modifiers" => json_decode(json_encode(array_slice($modifiers, 0, 5))),
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
        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_activity = $cash_flow_activity_fabricator->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $account_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $debit_account = $account_fabricator->create();
        $credit_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "debit_account_id" => $debit_account->id,
            "credit_account_id" => $credit_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id
        ]);
        $modifier = $modifier_fabricator->create();
        $modifier->id = $modifier->id + 1;

        $this->expectException(MissingResource::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info->getRequest()->get("/api/v1/modifiers/$modifier->id");
    }

    public function testInvalidCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currency = $currency_fabricator->create();
        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_activity = $cash_flow_activity_fabricator->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $account_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $debit_account = $account_fabricator->create();
        $credit_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "debit_account_id" => $debit_account->id,
            "credit_account_id" => $credit_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id,
            "name" => "@only alphanumeric characters only"
        ]);
        $modifier = $modifier_fabricator->make();

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/modifiers", [
                "modifier" => $modifier->toArray()
            ]);
    }

    public function testPartiallyUnownedCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currencyA = $currency_fabricator->create();
        $currency_fabricator->setOverrides([
            "user_id" => $another_user->id
        ]);
        $currencyB = $currency_fabricator->create();
        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_activity = $cash_flow_activity_fabricator->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $account_fabricator->setOverrides([
            "currency_id" => $currencyA->id
        ]);
        $debit_account = $account_fabricator->create();
        $account_fabricator->setOverrides([
            "currency_id" => $currencyB->id
        ]);
        $credit_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "debit_account_id" => $debit_account->id,
            "credit_account_id" => $credit_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id
        ]);
        $modifier = $modifier_fabricator->make();

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/modifiers", [
                "modifier" => $modifier->toArray()
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
        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_activity = $cash_flow_activity_fabricator->create();
        $debit_account = $account_fabricator->create();
        $credit_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "debit_account_id" => $debit_account->id,
            "credit_account_id" => $credit_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id
        ]);
        $modifier = $modifier_fabricator->create();
        $modifier_fabricator->setOverrides([
            "name" => "@only alphanumeric characters only"
        ]);
        $new_details = $modifier_fabricator->make();

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v1/modifiers/$modifier->id", [
                "modifier" => $new_details->toArray()
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
        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_activity = $cash_flow_activity_fabricator->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $account_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $debit_account = $account_fabricator->create();
        $credit_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "debit_account_id" => $debit_account->id,
            "credit_account_id" => $credit_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id
        ]);
        $modifier = $modifier_fabricator->create();

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v1/modifiers/$modifier->id");
            $this->assertTrue(false);
        } catch (MissingResource $error) {
            $this->seeInDatabase("modifiers", array_merge(
                [ "id" => $modifier->id ]
            ));
            $this->seeInDatabase("modifiers", [
                "id" => $modifier->id,
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
        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_activity = $cash_flow_activity_fabricator->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $account_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $debit_account = $account_fabricator->create();
        $credit_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "debit_account_id" => $debit_account->id,
            "credit_account_id" => $credit_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id
        ]);
        $modifier = $modifier_fabricator->create();
        model(ModifierModel::class)->delete($modifier->id);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v1/modifiers/$modifier->id");
            $this->assertTrue(false);
        } catch (MissingResource $error) {
            $this->seeInDatabase("modifiers", array_merge(
                [ "id" => $modifier->id ]
            ));
            $this->dontSeeInDatabase("modifiers", [
                "id" => $modifier->id,
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
        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_activity = $cash_flow_activity_fabricator->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $account_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $debit_account = $account_fabricator->create();
        $credit_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "debit_account_id" => $debit_account->id,
            "credit_account_id" => $credit_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id
        ]);
        $modifier = $modifier_fabricator->create();

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->patch("/api/v1/modifiers/$modifier->id");
        } catch (MissingResource $error) {
            $this->seeInDatabase("modifiers", [
                "id" => $modifier->id,
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
        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_activity = $cash_flow_activity_fabricator->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $account_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $debit_account = $account_fabricator->create();
        $credit_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "debit_account_id" => $debit_account->id,
            "credit_account_id" => $credit_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id
        ]);
        $modifier = $modifier_fabricator->create();

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/modifiers/$modifier->id/force");
        $result->assertStatus(204);
        $this->seeNumRecords(0, "modifiers", []);
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
        $cash_flow_activity_fabricator = new Fabricator(CashFlowActivityModel::class);
        $cash_flow_activity_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $cash_flow_activity = $cash_flow_activity_fabricator->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $account_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $debit_account = $account_fabricator->create();
        $credit_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "debit_account_id" => $debit_account->id,
            "credit_account_id" => $credit_account->id,
            "debit_cash_flow_activity_id" => $cash_flow_activity->id,
            "credit_cash_flow_activity_id" => $cash_flow_activity->id
        ]);
        $modifier = $modifier_fabricator->create();
        model(ModifierModel::class)->delete($modifier->id, true);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v1/modifiers/$modifier->id/force");
                $this->assertTrue(false);
        } catch (MissingResource $error) {
            $this->seeNumRecords(0, "modifiers", []);

            throw $error;
        } catch (Throwable $error) {
            $this->assertTrue(false);
        }
    }
}
