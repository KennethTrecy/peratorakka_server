<?php

namespace Tests\Feature\Resource;

use CodeIgniter\Test\Fabricator;

use Tests\Feature\Helper\AuthenticatedHTTPTestCase;
use App\Models\CurrencyModel;
use App\Models\AccountModel;
use App\Models\ModifierModel;
use App\Models\FinancialEntryModel;
use App\Models\FrozenPeriodModel;

class FrozenPeriodTest extends AuthenticatedHTTPTestCase
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
        $asset_account = $account_fabricator->setOverrides([
            "kind" => ASSET_ACCOUNT_KIND
        ], false)->create();
        $equity_account = $account_fabricator->setOverrides([
            "kind" => EQUITY_ACCOUNT_KIND
        ], false)->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $asset_account->id,
            "credit_account_id" => $equity_account->id
        ], false)->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id
        ]);
        $financial_entry = $financial_entry_fabricator->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $frozen_period = $frozen_period_fabricator->create();

        $result = $authenticated_info->getRequest()->get("/api/v1/frozen_periods");

        $result->assertOk();
        $result->assertJSONExact([
            "frozen_periods" => json_decode(json_encode([ $frozen_period ]))
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
        $equity_account = $account_fabricator->setOverrides([
            "kind" => EQUITY_ACCOUNT_KIND
        ], false)->create();
        $asset_account = $account_fabricator->setOverrides([
            "kind" => ASSET_ACCOUNT_KIND
        ], false)->create();
        $expense_account = $account_fabricator->setOverrides([
            "kind" => EXPENSE_ACCOUNT_KIND
        ], false)->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $normal_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $asset_account->id,
            "credit_account_id" => $equity_account->id,
            "action" => RECORD_MODIFIER_ACTION
        ], false)->create();
        $expense_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $expense_account->id,
            "credit_account_id" => $asset_account->id,
            "action" => RECORD_MODIFIER_ACTION
        ], false)->create();
        $close_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $equity_account->id,
            "credit_account_id" => $expense_account->id,
            "action" => CLOSE_MODIFIER_ACTION
        ], false)->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $recorded_normal_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $normal_record_modifier->id,
            "debit_amount" => "1000",
            "credit_amount" => "1000"
        ], false)->create();
        $recorded_expense_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $expense_record_modifier->id,
            "debit_amount" => "250",
            "credit_amount" => "250"
        ], false)->create();
        $closed_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $close_modifier->id,
            "debit_amount" => $recorded_expense_financial_entry->credit_amount,
            "credit_amount" => $recorded_expense_financial_entry->debit_amount
        ], false)->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $frozen_period = $frozen_period_fabricator->create();

        $result = $authenticated_info
            ->getRequest()
            ->get("/api/v1/frozen_periods/$frozen_period->id");

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "statements" => [
                    [
                        "currency_id" => $currency->id,
                        "unadjusted_trial_balance" => [
                            "total" => $recorded_normal_financial_entry->credit_amount
                        ],
                        "income_statement" => [
                            "total" => $recorded_expense_financial_entry->debit_amount->negated()
                        ],
                        "balance_sheet" => [
                            "total_assets" => $recorded_normal_financial_entry
                                ->debit_amount
                                ->minus($recorded_expense_financial_entry->credit_amount),
                            "total_liabilities" => "0",
                            "total_equities" => $recorded_normal_financial_entry
                                ->credit_amount
                                ->minus($closed_financial_entry->debit_amount)
                        ],
                        "adjusted_trial_balance" => [
                            "total" => $recorded_normal_financial_entry
                                ->credit_amount
                                ->minus($closed_financial_entry->debit_amount)
                        ]
                    ]
                ]
            ],
            "accounts" => json_decode(json_encode([
                $equity_account,
                $asset_account,
                $expense_account
            ])),
            "currencies" => [ $currency ],
            "frozen_period" => json_decode(json_encode($frozen_period)),
            "summary_calculations" => json_decode(json_encode([
                [
                    "account_id" => $equity_account->id,
                    "unadjusted_debit_amount" => "0",
                    "unadjusted_credit_amount" => $recorded_normal_financial_entry->credit_amount,
                    "adjusted_debit_amount" => "0",
                    "adjusted_credit_amount" => $recorded_normal_financial_entry
                        ->credit_amount
                        ->minus($closed_financial_entry->debit_amount)
                ],
                [
                    "account_id" => $asset_account->id,
                    "unadjusted_debit_amount" => $recorded_normal_financial_entry->debit_amount,
                    "unadjusted_credit_amount" => $recorded_expense_financial_entry->credit_amount,
                    "adjusted_debit_amount" => $recorded_normal_financial_entry->debit_amount,
                    "adjusted_credit_amount" => $recorded_expense_financial_entry->credit_amount
                ],
                [
                    "account_id" => $expense_account->id,
                    "unadjusted_debit_amount" => $recorded_expense_financial_entry->debit_amount,
                    "unadjusted_credit_amount" => "0",
                    "adjusted_debit_amount" => "0",
                    "adjusted_credit_amount" => "0"
                ]
            ])),
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
        $debit_account = $account_fabricator->create();
        $credit_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "debit_account_id" => $debit_account->id,
            "credit_account_id" => $credit_account->id
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

        $result->assertInvalid();
        $result->assertOk();
        $result->assertJSONFragment([
            "financial_entry" => [
                ...$financial_entry->toArray(),
                "debit_amount" => $financial_entry->debit_amount->toScale(4),
                "credit_amount" => $financial_entry->credit_amount->toScale(4)
            ]
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
        $debit_account = $account_fabricator->create();
        $credit_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "debit_account_id" => $debit_account->id,
            "credit_account_id" => $credit_account->id
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
        $debit_account = $account_fabricator->create();
        $credit_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "debit_account_id" => $debit_account->id,
            "credit_account_id" => $credit_account->id
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
        $debit_account = $account_fabricator->create();
        $credit_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "debit_account_id" => $debit_account->id,
            "credit_account_id" => $credit_account->id
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
        $debit_account = $account_fabricator->create();
        $credit_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "debit_account_id" => $debit_account->id,
            "credit_account_id" => $credit_account->id
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

        $result = $authenticated_info->getRequest()->get("/api/v1/frozen_periods");

        $result->assertOk();
        $result->assertJSONExact([
            "frozen_periods" => json_decode(json_encode([ $frozen_period ])),
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
        $asset_account = $account_fabricator->setOverrides([
            "kind" => ASSET_ACCOUNT_KIND
        ], false)->create();
        $equity_account = $account_fabricator->setOverrides([
            "kind" => EQUITY_ACCOUNT_KIND
        ], false)->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $asset_account->id,
            "credit_account_id" => $equity_account->id
        ], false)->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id
        ]);
        $financial_entry = $financial_entry_fabricator->create();
        $financial_entry = $financial_entry_fabricator->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $frozen_period = $frozen_period_fabricator->create();
        $frozen_period->id = $frozen_period->id + 1;

        $result = $authenticated_info
            ->getRequest()
            ->get("/api/v1/frozen_periods/$frozen_period->id");

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
            "currency_id" => $currency->id
        ]);
        $debit_account = $account_fabricator->create();
        $credit_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "debit_account_id" => $debit_account->id,
            "credit_account_id" => $credit_account->id,
        ]);
        $modifier = $modifier_fabricator->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id,
            "remarks" => "@ characters not allowed here"
        ]);
        $financial_entry = $financial_entry_fabricator->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/financial_entries", [
                "financial_entry" => $financial_entry->toArray()
            ]);

        $result->assertInvalid();
        $result->assertJSONFragment([
            "errors" => []
        ]);
    }

    public function DualCurrencyCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currencyA = $currency_fabricator->create();
        $currencyB = $currency_fabricator->create();
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
            "credit_account_id" => $credit_account->id
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

    public function DualCurrencyUpdate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currencyA = $currency_fabricator->create();
        $currencyB = $currency_fabricator->create();
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
            "credit_account_id" => $credit_account->id
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

    public function PartiallyUnownedCreate()
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
            "credit_account_id" => $credit_account->id
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
                "financial_entry" => $financial_entry->toArray()
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
        $debit_account = $account_fabricator->create();
        $credit_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "debit_account_id" => $debit_account->id,
            "credit_account_id" => $credit_account->id
        ]);
        $modifier = $modifier_fabricator->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id,
            "remarks" => "@ characters not allowed here"
        ]);
        $financial_entry = $financial_entry_fabricator->create();
        $new_details = $financial_entry_fabricator->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v1/financial_entries/$financial_entry->id", [
                "financial_entry" => $new_details->toArray()
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
        $debit_account = $account_fabricator->create();
        $credit_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "debit_account_id" => $debit_account->id,
            "credit_account_id" => $credit_account->id
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

        $result->assertNotFound();
        $this->seeInDatabase("financial_entries", array_merge(
            [ "id" => $financial_entry->id ]
        ));
        $this->seeInDatabase("financial_entries", [
            "id" => $financial_entry->id,
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
        $debit_account = $account_fabricator->create();
        $credit_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "debit_account_id" => $debit_account->id,
            "credit_account_id" => $credit_account->id
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
            ->delete("/api/v1/financial_entries/$financial_entry->id");

        $result->assertNotFound();
        $this->seeInDatabase("financial_entries", array_merge(
            [ "id" => $financial_entry->id ]
        ));
        $this->dontSeeInDatabase("financial_entries", [
            "id" => $financial_entry->id,
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
        $debit_account = $account_fabricator->create();
        $credit_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "debit_account_id" => $debit_account->id,
            "credit_account_id" => $credit_account->id
        ]);
        $modifier = $modifier_fabricator->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id
        ]);
        $financial_entry = $financial_entry_fabricator->create();

        $result = $authenticated_info
            ->getRequest()
            ->patch("/api/v1/financial_entries/$financial_entry->id");

        $result->assertNotFound();
        $this->seeInDatabase("financial_entries", [
            "id" => $financial_entry->id,
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
        $debit_account = $account_fabricator->create();
        $credit_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "debit_account_id" => $debit_account->id,
            "credit_account_id" => $credit_account->id
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
        $debit_account = $account_fabricator->create();
        $credit_account = $account_fabricator->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier_fabricator->setOverrides([
            "debit_account_id" => $debit_account->id,
            "credit_account_id" => $credit_account->id
        ]);
        $modifier = $modifier_fabricator->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id
        ]);
        $financial_entry = $financial_entry_fabricator->create();
        model(FinancialEntryModel::class)->delete($financial_entry->id, true);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/financial_entries/$financial_entry->id/force");

        $result->assertNotFound();
        $this->seeNumRecords(0, "financial_entries", []);
    }
}
