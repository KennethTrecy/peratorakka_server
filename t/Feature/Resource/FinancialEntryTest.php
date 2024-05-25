<?php

namespace Tests\Feature\Resource;

use Throwable;

use CodeIgniter\Test\Fabricator;
use CodeIgniter\I18n\Time;

use App\Exceptions\InvalidRequest;
use App\Exceptions\MissingResource;
use App\Models\AccountModel;
use App\Models\CashFlowActivityModel;
use App\Models\CurrencyModel;
use App\Models\FinancialEntryModel;
use App\Models\ModifierModel;
use App\Models\FrozenPeriodModel;
use Tests\Feature\Helper\AuthenticatedHTTPTestCase;

class FinancialEntryTest extends AuthenticatedHTTPTestCase
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
        $modifier = $modifier_fabricator->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id
        ]);
        $financial_entries = $financial_entry_fabricator->create(10);

        $result = $authenticated_info->getRequest()->get("/api/v1/financial_entries");

        $result->assertOk();
        $result->assertJSONExact([
            "meta" => [
                "overall_filtered_count" => 10
            ],
            "accounts" => json_decode(json_encode([ $debit_account, $credit_account ])),
            "currencies" => [ $currency ],
            "financial_entries" => json_decode(json_encode($financial_entries)),
            "modifiers" => json_decode(json_encode([ $modifier ])),
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
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id
        ]);
        $financial_entry = $financial_entry_fabricator->create();

        $result = $authenticated_info
            ->getRequest()
            ->get("/api/v1/financial_entries/$financial_entry->id");

        $result->assertOk();
        $result->assertJSONExact([
            "accounts" => json_decode(json_encode([ $debit_account, $credit_account ])),
            "currencies" => [ $currency ],
            "financial_entry" => json_decode(json_encode($financial_entry)),
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
        $modifier = $modifier_fabricator->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id
        ]);
        $financial_entry = $financial_entry_fabricator->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/financial_entries", [
                "financial_entry" => [
                    ...$financial_entry->toArray(),
                    "debit_amount" => $financial_entry->debit_amount->toScale(4),
                    "credit_amount" => $financial_entry->credit_amount->toScale(4)
                ]
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "financial_entry" => [
                ...$financial_entry->toArray(),
                "debit_amount" => $financial_entry->debit_amount->toScale(4),
                "credit_amount" => $financial_entry->credit_amount->toScale(4)
            ]
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
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id
        ]);
        $financial_entry = $financial_entry_fabricator->create();
        $new_details = $financial_entry_fabricator->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v1/financial_entries/$financial_entry->id", [
                "financial_entry" => [
                    ...$new_details->toArray(),
                    "debit_amount" => $new_details->debit_amount->toScale(4),
                    "credit_amount" => $new_details->credit_amount->toScale(4)
                ]
            ]);

        $result->assertStatus(204);
        $this->seeInDatabase("financial_entries", array_merge(
            [ "id" => $financial_entry->id ],
            [
                ...$new_details->toArray(),
                "debit_amount" => $new_details->debit_amount->toScale(4),
                "credit_amount" => $new_details->credit_amount->toScale(4)
            ]
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
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id
        ]);
        $financial_entry = $financial_entry_fabricator->create();

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/financial_entries/$financial_entry->id");

        $result->assertStatus(204);
        $this->seeInDatabase("financial_entries", array_merge(
            [ "id" => $financial_entry->id ]
        ));
        $this->dontSeeInDatabase("financial_entries", [
            "id" => $financial_entry->id,
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
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id
        ]);
        $financial_entry = $financial_entry_fabricator->create();
        model(FinancialEntryModel::class)->delete($financial_entry->id);

        $result = $authenticated_info
            ->getRequest()
            ->patch("/api/v1/financial_entries/$financial_entry->id");

        $result->assertStatus(204);
        $this->seeInDatabase("financial_entries", [
            "id" => $financial_entry->id,
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
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id
        ]);
        $financial_entry = $financial_entry_fabricator->create();
        model(FinancialEntryModel::class)->delete($financial_entry->id);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/financial_entries/$financial_entry->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "financial_entries", []);
    }

    public function testEmptyIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $result = $authenticated_info->getRequest()->get("/api/v1/financial_entries");

        $result->assertOk();
        $result->assertJSONExact([
            "meta" => [
                "overall_filtered_count" => 0
            ],
            "accounts" => [],
            "currencies" => [],
            "financial_entries" => [],
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
        $modifier = $modifier_fabricator->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id
        ]);
        $financial_entries = $financial_entry_fabricator->create(10);

        $result = $authenticated_info->getRequest()->get("/api/v1/financial_entries", [
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
            "financial_entries" => json_decode(json_encode(array_slice($financial_entries, 0, 5))),
            "modifiers" => json_decode(json_encode([ $modifier ])),
        ]);
    }

    public function testFilteredIndex()
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
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id,
            "transacted_at" => Time::today()->toDateTimeString()
        ]);
        $financial_entries_today = $financial_entry_fabricator->create(3);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id,
            "transacted_at" => Time::tomorrow()->toDateTimeString()
        ]);
        $financial_entry_fabricator->create(5);

        $result = $authenticated_info->getRequest()->get("/api/v1/financial_entries", [
            "filter" => [
                "begin_date" => Time::yesterday()->toDateTimeString(),
                "end_date" => Time::today()->toDateTimeString()
            ],
            "page" => [
                "limit" => 5
            ]
        ]);

        $result->assertOk();
        $result->assertJSONExact([
            "meta" => [
                "overall_filtered_count" => 3
            ],
            "accounts" => json_decode(json_encode([ $debit_account, $credit_account ])),
            "currencies" => [ $currency ],
            "financial_entries" => json_decode(json_encode($financial_entries_today)),
            "modifiers" => json_decode(json_encode([ $modifier ])),
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
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id
        ]);
        $financial_entry = $financial_entry_fabricator->create();
        $financial_entry->id = $financial_entry->id + 1;

        $this->expectException(MissingResource::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info
            ->getRequest()
            ->get("/api/v1/financial_entries/$financial_entry->id");
    }

    public function testInvalidCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currency = $currency_fabricator->create();
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
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id,
            "remarks" => "@ characters not allowed here"
        ]);
        $financial_entry = $financial_entry_fabricator->make();

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/financial_entries", [
                "financial_entry" => $financial_entry->toArray()
            ]);
    }

    public function testDualCurrencyCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currencyA = $currency_fabricator->create();
        $currencyB = $currency_fabricator->create();
        $currency = $currency_fabricator->create();
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
        $modifier = $modifier_fabricator->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id,
            "debit_amount" => "1.0"
        ]);
        $financial_entry = $financial_entry_fabricator->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/financial_entries", [
                "financial_entry" => [
                    ...$financial_entry->toArray(),
                    "debit_amount" => $financial_entry->debit_amount->toScale(4),
                    "credit_amount" => $financial_entry->credit_amount->toScale(4)
                ]
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "financial_entry" => [
                ...$financial_entry->toArray(),
                "debit_amount" => $financial_entry->debit_amount->toScale(4),
                "credit_amount" => $financial_entry->credit_amount->toScale(4)
            ]
        ]);
    }

    public function testDualCurrencyUpdate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currencyA = $currency_fabricator->create();
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
        $modifier = $modifier_fabricator->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id,
            "debit_amount" => "1.0"
        ]);
        $financial_entry = $financial_entry_fabricator->create();
        $new_details = $financial_entry_fabricator->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v1/financial_entries/$financial_entry->id", [
                "financial_entry" => [
                    ...$new_details->toArray(),
                    "debit_amount" => $new_details->debit_amount->toScale(4),
                    "credit_amount" => $new_details->credit_amount->toScale(4)
                ]
            ]);

        $result->assertStatus(204);
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
        $modifier = $modifier_fabricator->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id
        ]);
        $financial_entry = $financial_entry_fabricator->make();

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/financial_entries", [
                "financial_entry" => $financial_entry->toArray()
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
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id,
            "remarks" => "@ characters not allowed here"
        ]);
        $financial_entry = $financial_entry_fabricator->create();
        $new_details = $financial_entry_fabricator->make();

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v1/financial_entries/$financial_entry->id", [
                "financial_entry" => $new_details->toArray()
            ]);
    }

    public function testFrozenUpdate()
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
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id,
            "transacted_at" => Time::now()->toDateTimeString()
        ]);
        $financial_entry = $financial_entry_fabricator->create();
        $new_details = $financial_entry_fabricator->make();

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v1/financial_entries/$financial_entry->id", [
                "financial_entry" => $new_details->toArray()
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
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id
        ]);
        $financial_entry = $financial_entry_fabricator->create();

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v1/financial_entries/$financial_entry->id");
            $this->assertTrue(false);
        } catch (MissingResource $error) {
            $this->seeInDatabase("financial_entries", array_merge(
                [ "id" => $financial_entry->id ]
            ));
            $this->seeInDatabase("financial_entries", [
                "id" => $financial_entry->id,
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
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id
        ]);
        $financial_entry = $financial_entry_fabricator->create();
        model(FinancialEntryModel::class)->delete($financial_entry->id);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v1/financial_entries/$financial_entry->id");
            $this->assertTrue(false);
        } catch (MissingResource $error) {
            $this->seeInDatabase("financial_entries", array_merge(
                [ "id" => $financial_entry->id ]
            ));
            $this->dontSeeInDatabase("financial_entries", [
                "id" => $financial_entry->id,
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
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id
        ]);
        $financial_entry = $financial_entry_fabricator->create();

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->patch("/api/v1/financial_entries/$financial_entry->id");
            $this->assertTrue(false);
        } catch (MissingResource $error) {
            $this->seeInDatabase("financial_entries", [
                "id" => $financial_entry->id,
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
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id
        ]);
        $financial_entry = $financial_entry_fabricator->create();

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/financial_entries/$financial_entry->id/force");
        $result->assertStatus(204);
        $this->seeNumRecords(0, "financial_entries", []);
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
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id
        ]);
        $financial_entry = $financial_entry_fabricator->create();
        model(FinancialEntryModel::class)->delete($financial_entry->id, true);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v1/financial_entries/$financial_entry->id/force");
            $this->assertTrue(false);
        } catch (MissingResource $error) {
            $this->seeNumRecords(0, "financial_entries", []);
            throw $error;
        } catch (Throwable $error) {
            $this->assertTrue(false);
        }
    }
}
