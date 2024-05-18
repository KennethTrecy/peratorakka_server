<?php

namespace Tests\Feature\Resource;

use Throwable;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\Fabricator;

use Tests\Feature\Helper\AuthenticatedHTTPTestCase;

use App\Exceptions\InvalidRequest;
use App\Exceptions\MissingResource;
use App\Exceptions\UnprocessableRequest;
use App\Models\AccountModel;
use App\Models\CurrencyModel;
use App\Models\FinancialEntryModel;
use App\Models\FrozenPeriodModel;
use App\Models\ModifierModel;
use App\Models\SummaryCalculationModel;

class FrozenPeriodTest extends AuthenticatedHTTPTestCase
{
    public function testDefaultIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $asset_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => ASSET_ACCOUNT_KIND
        ])->create();
        $equity_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EQUITY_ACCOUNT_KIND
        ])->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $asset_account->id,
            "credit_account_id" => $equity_account->id
        ])->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id
        ]);
        $financial_entry = $financial_entry_fabricator->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();

        $result = $authenticated_info->getRequest()->get("/api/v1/frozen_periods");

        $result->assertOk();
        $result->assertJSONExact([
            "meta" => [
                "overall_filtered_count" => 1
            ],
            "frozen_periods" => json_decode(json_encode([ $frozen_period ]))
        ]);
    }

    public function testDefaultShow()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $equity_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EQUITY_ACCOUNT_KIND
        ])->create();
        $asset_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => ASSET_ACCOUNT_KIND
        ])->create();
        $expense_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EXPENSE_ACCOUNT_KIND
        ])->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $normal_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $asset_account->id,
            "credit_account_id" => $equity_account->id,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $expense_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $expense_account->id,
            "credit_account_id" => $asset_account->id,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $close_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $equity_account->id,
            "credit_account_id" => $expense_account->id,
            "action" => CLOSE_MODIFIER_ACTION
        ])->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $recorded_normal_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $normal_record_modifier->id,
            "debit_amount" => "1000",
            "credit_amount" => "1000"
        ])->create();
        $recorded_expense_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $expense_record_modifier->id,
            "debit_amount" => "250",
            "credit_amount" => "250"
        ])->create();
        $closed_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $close_modifier->id,
            "debit_amount" => $recorded_expense_financial_entry->credit_amount,
            "credit_amount" => $recorded_expense_financial_entry->debit_amount
        ])->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $summary_calculation_fabricator = new Fabricator(SummaryCalculationModel::class);
        $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $frozen_period->id
        ]);
        $equity_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $frozen_period->id,
            "account_id" => $equity_account->id,
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => $recorded_normal_financial_entry->credit_amount,
            "closed_debit_amount" => "0",
            "closed_credit_amount" => $recorded_normal_financial_entry
                ->credit_amount
                ->minus($closed_financial_entry->debit_amount)
        ])->create();
        $asset_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $frozen_period->id,
            "account_id" => $asset_account->id,
            "unadjusted_debit_amount" => $recorded_normal_financial_entry->debit_amount,
            "unadjusted_credit_amount" => $recorded_expense_financial_entry->credit_amount,
            "closed_debit_amount" => $recorded_normal_financial_entry->debit_amount,
            "closed_credit_amount" => $recorded_expense_financial_entry->credit_amount
        ])->create();
        $expense_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $frozen_period->id,
            "account_id" => $expense_account->id,
            "unadjusted_debit_amount" => $recorded_expense_financial_entry->debit_amount,
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "0"
        ])->create();

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
                            "debit_total" => $recorded_normal_financial_entry->debit_amount,
                            "credit_total" => $recorded_normal_financial_entry->credit_amount
                        ],
                        "income_statement" => [
                            "net_total" => $recorded_expense_financial_entry
                                ->debit_amount
                                ->negated()
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
                        "cash_flow_statement" => [
                            "opening_liquid_amount" => "0",
                            "closing_liquid_amount" => "0"
                        ],
                        "adjusted_trial_balance" => [
                            "debit_total" => $recorded_normal_financial_entry
                                ->debit_amount
                                ->minus($recorded_expense_financial_entry->credit_amount),
                            "credit_total" => $recorded_normal_financial_entry
                                ->credit_amount
                                ->minus($closed_financial_entry->debit_amount)
                        ]
                    ]
                ],
                "exchange_rates" => []
            ],
            "accounts" => json_decode(json_encode([
                $equity_account,
                $asset_account,
                $expense_account
            ])),
            "currencies" => [ $currency ],
            "frozen_period" => json_decode(json_encode($frozen_period)),
            "cash_flow_categories" => [],
            "summary_calculations" => json_decode(json_encode([
                $equity_summary_calculation,
                $asset_summary_calculation,
                $expense_summary_calculation
            ])),
        ]);
    }

    public function testDefaultCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $equity_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EQUITY_ACCOUNT_KIND
        ])->create();
        $asset_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => ASSET_ACCOUNT_KIND
        ])->create();
        $expense_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EXPENSE_ACCOUNT_KIND
        ])->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $normal_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $asset_account->id,
            "credit_account_id" => $equity_account->id,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $expense_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $expense_account->id,
            "credit_account_id" => $asset_account->id,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $close_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $equity_account->id,
            "credit_account_id" => $expense_account->id,
            "action" => CLOSE_MODIFIER_ACTION
        ])->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $recorded_normal_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $normal_record_modifier->id,
            "debit_amount" => "1000",
            "credit_amount" => "1000"
        ])->create();
        $recorded_expense_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $expense_record_modifier->id,
            "debit_amount" => "250",
            "credit_amount" => "250"
        ])->create();
        $closed_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $close_modifier->id,
            "debit_amount" => $recorded_expense_financial_entry->credit_amount,
            "credit_amount" => $recorded_expense_financial_entry->debit_amount
        ])->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/frozen_periods", [
                "frozen_period" => $frozen_period->toArray()
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "frozen_period" => $frozen_period->toArray()
        ]);
        $this->seeNumRecords(1, "frozen_periods", []);
        $this->seeNumRecords(3, "summary_calculations", []);
    }

    public function testDefaultUpdate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $asset_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => ASSET_ACCOUNT_KIND
        ])->create();
        $equity_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EQUITY_ACCOUNT_KIND
        ])->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $asset_account->id,
            "credit_account_id" => $equity_account->id
        ])->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id
        ]);
        $financial_entry = $financial_entry_fabricator->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $new_details = $frozen_period_fabricator->make();

        try {
            $result = $authenticated_info
                ->getRequest()
                ->withBodyFormat("json")
                ->put("/api/v1/frozen_periods/$frozen_period->id", [
                    "frozen_period" => $new_details->toArray()
                ]);
            $this->assertTrue(false);
        } catch(PageNotFoundException $error) {
            $this->assertTrue(true);
        }
    }

    public function testDefaultDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $asset_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => ASSET_ACCOUNT_KIND
        ])->create();
        $equity_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EQUITY_ACCOUNT_KIND
        ])->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $asset_account->id,
            "credit_account_id" => $equity_account->id
        ])->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id
        ]);
        $financial_entry = $financial_entry_fabricator->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();

        try {
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v1/frozen_periods/$financial_entry->id");
            $this->assertTrue(false);
        } catch(PageNotFoundException $error) {
            $this->assertTrue(true);
        }
    }

    public function testDefaultRestore()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $asset_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => ASSET_ACCOUNT_KIND
        ])->create();
        $equity_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EQUITY_ACCOUNT_KIND
        ])->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $asset_account->id,
            "credit_account_id" => $equity_account->id
        ])->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id
        ]);
        $financial_entry = $financial_entry_fabricator->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        model(FrozenPeriodModel::class)->delete($frozen_period->id);

        try {
            $result = $authenticated_info
                ->getRequest()
                ->patch("/api/v1/frozen_periods/$financial_entry->id");
            $this->assertTrue(false);
        } catch(PageNotFoundException $error) {
            $this->assertTrue(true);
        }
    }

    public function testDefaultForceDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $asset_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => ASSET_ACCOUNT_KIND
        ])->create();
        $equity_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EQUITY_ACCOUNT_KIND
        ])->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $asset_account->id,
            "credit_account_id" => $equity_account->id
        ])->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id
        ]);
        $financial_entry = $financial_entry_fabricator->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        // Uncomment below if frozen period can be soft deleted.
        // model(FrozenPeriodModel::class)->delete($frozen_period->id);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/frozen_periods/$financial_entry->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "frozen_periods", []);
    }

    public function testDefaultCheck()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $equity_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EQUITY_ACCOUNT_KIND
        ])->create();
        $asset_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => ASSET_ACCOUNT_KIND
        ])->create();
        $expense_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EXPENSE_ACCOUNT_KIND
        ])->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $normal_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $asset_account->id,
            "credit_account_id" => $equity_account->id,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $expense_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $expense_account->id,
            "credit_account_id" => $asset_account->id,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $close_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $equity_account->id,
            "credit_account_id" => $expense_account->id,
            "action" => CLOSE_MODIFIER_ACTION
        ])->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $recorded_normal_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $normal_record_modifier->id,
            "debit_amount" => "1000",
            "credit_amount" => "1000"
        ])->create();
        $recorded_expense_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $expense_record_modifier->id,
            "debit_amount" => "250",
            "credit_amount" => "250"
        ])->create();
        $closed_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $close_modifier->id,
            "debit_amount" => $recorded_expense_financial_entry->credit_amount,
            "credit_amount" => $recorded_expense_financial_entry->debit_amount
        ])->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $first_frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at" => Time::parse("-3 day")->toDateTimeString(),
            "finished_at" => Time::parse("-2 day")->toDateTimeString()
        ])->create();
        $summary_calculation_fabricator = new Fabricator(SummaryCalculationModel::class);
        $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id
        ]);
        $first_equity_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $equity_account->id,
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "2500",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "2000"
        ])->create();
        $first_asset_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $asset_account->id,
            "unadjusted_debit_amount" => "2500",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "2000",
            "closed_credit_amount" => "0"
        ])->create();
        $first_expense_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $expense_account->id,
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "0"
        ])->create();
        $second_frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at" => Time::yesterday()->toDateTimeString(),
            "finished_at" => Time::now()->toDateTimeString()
        ])->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/frozen_periods/dry_run", [
                "frozen_period" => $second_frozen_period->toArray()
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "@meta" => [
                "statements" => [
                    [
                        "currency_id" => $currency->id,
                        "unadjusted_trial_balance" => [
                            "debit_total" => "3000",
                            "credit_total" => "3000"
                        ],
                        "income_statement" => [
                            "net_total" => "-250"
                        ],
                        "balance_sheet" => [
                            "total_assets" => "2750",
                            "total_liabilities" => "0",
                            "total_equities" => "2750"
                        ],
                        "adjusted_trial_balance" => [
                            "debit_total" => "2750",
                            "credit_total" => "2750"
                        ]
                    ]
                ]
            ],
            "frozen_period" => $second_frozen_period->toArray(),
            "summary_calculations" => [
                [
                    "account_id" => $asset_account->id,
                    "unadjusted_debit_amount" => "2750",
                    "unadjusted_credit_amount" => "0",
                    "closed_debit_amount" => "2750",
                    "closed_credit_amount" => "0"
                ],
                [
                    "account_id" => $equity_account->id,
                    "unadjusted_debit_amount" => "0",
                    "unadjusted_credit_amount" => "3000",
                    "closed_debit_amount" => "0",
                    "closed_credit_amount" => "2750"
                ],
                [
                    "account_id" => $expense_account->id,
                    "unadjusted_debit_amount" => "250",
                    "unadjusted_credit_amount" => "0",
                    "closed_debit_amount" => "0",
                    "closed_credit_amount" => "0"
                ]
            ],
            "accounts" => [],
            "currencies" => []
        ]);
        $this->seeNumRecords(1, "frozen_periods", []);
        $this->seeNumRecords(3, "summary_calculations", []);
    }

    public function testEmptyIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $result = $authenticated_info->getRequest()->get("/api/v1/frozen_periods");

        $result->assertOk();
        $result->assertJSONExact([
            "meta" => [
                "overall_filtered_count" => 0
            ],
            "frozen_periods" => json_decode(json_encode([])),
        ]);
    }

    public function testQueriedIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $asset_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => ASSET_ACCOUNT_KIND
        ])->create();
        $equity_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EQUITY_ACCOUNT_KIND
        ])->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $asset_account->id,
            "credit_account_id" => $equity_account->id
        ])->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id
        ]);
        $financial_entry = $financial_entry_fabricator->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();

        $result = $authenticated_info->getRequest()->get("/api/v1/frozen_periods", [
            "page" => [
                "offset" => 1,
                "limit" => 5
            ]
        ]);

        $result->assertOk();
        $result->assertJSONExact([
            "meta" => [
                "overall_filtered_count" => 1
            ],
            "frozen_periods" => []
        ]);
    }

    public function testMissingShow()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $asset_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => ASSET_ACCOUNT_KIND
        ])->create();
        $equity_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EQUITY_ACCOUNT_KIND
        ])->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $asset_account->id,
            "credit_account_id" => $equity_account->id
        ])->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $financial_entry_fabricator->setOverrides([
            "modifier_id" => $modifier->id
        ]);
        $financial_entry = $financial_entry_fabricator->create();
        $financial_entry = $financial_entry_fabricator->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $frozen_period->id = $frozen_period->id + 1;

        $this->expectException(MissingResource::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info
            ->getRequest()
            ->get("/api/v1/frozen_periods/$frozen_period->id");
    }

    public function testValidChainCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $equity_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EQUITY_ACCOUNT_KIND
        ])->create();
        $asset_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => ASSET_ACCOUNT_KIND
        ])->create();
        $expense_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EXPENSE_ACCOUNT_KIND
        ])->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $normal_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $asset_account->id,
            "credit_account_id" => $equity_account->id,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $expense_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $expense_account->id,
            "credit_account_id" => $asset_account->id,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $close_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $equity_account->id,
            "credit_account_id" => $expense_account->id,
            "action" => CLOSE_MODIFIER_ACTION
        ])->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $recorded_normal_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $normal_record_modifier->id,
            "debit_amount" => "1000",
            "credit_amount" => "1000"
        ])->create();
        $recorded_expense_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $expense_record_modifier->id,
            "debit_amount" => "250",
            "credit_amount" => "250"
        ])->create();
        $closed_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $close_modifier->id,
            "debit_amount" => $recorded_expense_financial_entry->credit_amount,
            "credit_amount" => $recorded_expense_financial_entry->debit_amount
        ])->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $first_frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at" => Time::parse("-3 day")->toDateTimeString(),
            "finished_at" => Time::parse("-2 day")->toDateTimeString()
        ])->create();
        $summary_calculation_fabricator = new Fabricator(SummaryCalculationModel::class);
        $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id
        ]);
        $first_equity_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $equity_account->id,
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "2500",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "2000"
        ])->create();
        $first_asset_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $asset_account->id,
            "unadjusted_debit_amount" => "2500",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "2000",
            "closed_credit_amount" => "0"
        ])->create();
        $first_expense_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $expense_account->id,
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "0"
        ])->create();
        $second_frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at" => Time::yesterday()->toDateTimeString(),
            "finished_at" => Time::now()->toDateTimeString()
        ])->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/frozen_periods", [
                "frozen_period" => $second_frozen_period->toArray()
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "frozen_period" => $second_frozen_period->toArray()
        ]);
        $this->seeNumRecords(2, "frozen_periods", []);
        $this->seeNumRecords(6, "summary_calculations", []);
        $this->seeInDatabase("summary_calculations", [
            "account_id" => $asset_account->id,
            "unadjusted_debit_amount" => "2750",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "2750",
            "closed_credit_amount" => "0"
        ]);
        $this->seeInDatabase("summary_calculations", [
            "account_id" => $equity_account->id,
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "3000",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "2750"
        ]);
    }

    public function testValidIncompleteChainCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $equity_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EQUITY_ACCOUNT_KIND
        ])->create();
        $asset_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => ASSET_ACCOUNT_KIND
        ])->create();
        $asset_b_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => ASSET_ACCOUNT_KIND
        ])->create();
        $expense_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EXPENSE_ACCOUNT_KIND
        ])->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $normal_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $asset_account->id,
            "credit_account_id" => $equity_account->id,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $expense_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $expense_account->id,
            "credit_account_id" => $asset_account->id,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $close_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $equity_account->id,
            "credit_account_id" => $expense_account->id,
            "action" => CLOSE_MODIFIER_ACTION
        ])->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $recorded_normal_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $normal_record_modifier->id,
            "debit_amount" => "1000",
            "credit_amount" => "1000"
        ])->create();
        $recorded_expense_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $expense_record_modifier->id,
            "debit_amount" => "250",
            "credit_amount" => "250"
        ])->create();
        $closed_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $close_modifier->id,
            "debit_amount" => $recorded_expense_financial_entry->credit_amount,
            "credit_amount" => $recorded_expense_financial_entry->debit_amount
        ])->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $first_frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at" => Time::parse("-3 day")->toDateTimeString(),
            "finished_at" => Time::parse("-2 day")->toDateTimeString()
        ])->create();
        $summary_calculation_fabricator = new Fabricator(SummaryCalculationModel::class);
        $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id
        ]);
        $first_equity_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $equity_account->id,
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "5500",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "4000"
        ])->create();
        $first_asset_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $asset_account->id,
            "unadjusted_debit_amount" => "2500",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "2000",
            "closed_credit_amount" => "0"
        ])->create();
        $first_asset_b_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $asset_b_account->id,
            "unadjusted_debit_amount" => "3000",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "2000",
            "closed_credit_amount" => "0"
        ])->create();
        $first_expense_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $expense_account->id,
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "0"
        ])->create();
        $second_frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at" => Time::yesterday()->toDateTimeString(),
            "finished_at" => Time::now()->toDateTimeString()
        ])->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/frozen_periods", [
                "frozen_period" => $second_frozen_period->toArray()
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "frozen_period" => $second_frozen_period->toArray()
        ]);
        $this->seeNumRecords(2, "frozen_periods", []);
        $this->seeNumRecords(8, "summary_calculations", []);
        $this->seeInDatabase("summary_calculations", [
            "account_id" => $asset_account->id,
            "unadjusted_debit_amount" => "2750",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "2750",
            "closed_credit_amount" => "0"
        ]);
        $this->seeInDatabase("summary_calculations", [
            "account_id" => $asset_b_account->id,
            "unadjusted_debit_amount" => "2000",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "2000",
            "closed_credit_amount" => "0"
        ]);
        $this->seeInDatabase("summary_calculations", [
            "account_id" => $equity_account->id,
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "5000",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "4750"
        ]);
    }

    public function testInvalidCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $equity_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EQUITY_ACCOUNT_KIND
        ])->create();
        $asset_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => ASSET_ACCOUNT_KIND
        ])->create();
        $expense_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EXPENSE_ACCOUNT_KIND
        ])->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $normal_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $asset_account->id,
            "credit_account_id" => $equity_account->id,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $expense_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $expense_account->id,
            "credit_account_id" => $asset_account->id,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $close_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $equity_account->id,
            "credit_account_id" => $expense_account->id,
            "action" => CLOSE_MODIFIER_ACTION
        ])->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $recorded_normal_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $normal_record_modifier->id,
            "debit_amount" => "1000",
            "credit_amount" => "1000"
        ])->create();
        $recorded_expense_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $expense_record_modifier->id,
            "debit_amount" => "250",
            "credit_amount" => "250"
        ])->create();
        $closed_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $close_modifier->id,
            "debit_amount" => $recorded_expense_financial_entry->credit_amount,
            "credit_amount" => $recorded_expense_financial_entry->debit_amount
        ])->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at"  => Time::tomorrow()->toDateTimeString(),
        ])->make();

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/frozen_periods", [
                "frozen_period" => $frozen_period->toArray()
            ]);
    }

    public function testImbalanceCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $equity_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EQUITY_ACCOUNT_KIND
        ])->create();
        $asset_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => ASSET_ACCOUNT_KIND
        ])->create();
        $expense_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EXPENSE_ACCOUNT_KIND
        ])->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $normal_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $asset_account->id,
            "credit_account_id" => $equity_account->id,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $expense_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $expense_account->id,
            "credit_account_id" => $asset_account->id,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $close_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $equity_account->id,
            "credit_account_id" => $expense_account->id,
            "action" => CLOSE_MODIFIER_ACTION
        ])->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $recorded_normal_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $normal_record_modifier->id,
            "debit_amount" => "1000",
            "credit_amount" => "1000"
        ])->create();
        $recorded_expense_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $expense_record_modifier->id,
            "debit_amount" => "250",
            "credit_amount" => "250"
        ])->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->make();

        try {
            $this->expectException(UnprocessableRequest::class);
            $this->expectExceptionCode(422);
            $result = $authenticated_info
                ->getRequest()
                ->withBodyFormat("json")
                ->post("/api/v1/frozen_periods", [
                    "frozen_period" => $frozen_period->toArray()
                ]);
            $this->assertTrue(false);
        } catch (UnprocessableRequest $error) {
            $this->seeNumRecords(0, "frozen_periods", []);
            $this->seeNumRecords(0, "summary_calculations", []);

            throw $error;
        } catch (Throwable $exception) {
            $this->assertTrue(true);
        }
    }

    public function testValidOpenCheck()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $equity_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EQUITY_ACCOUNT_KIND
        ])->create();
        $asset_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => ASSET_ACCOUNT_KIND
        ])->create();
        $expense_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EXPENSE_ACCOUNT_KIND
        ])->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $normal_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $asset_account->id,
            "credit_account_id" => $equity_account->id,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $expense_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $expense_account->id,
            "credit_account_id" => $asset_account->id,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $close_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $equity_account->id,
            "credit_account_id" => $expense_account->id,
            "action" => CLOSE_MODIFIER_ACTION
        ])->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $recorded_normal_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $normal_record_modifier->id,
            "debit_amount" => "1000",
            "credit_amount" => "1000"
        ])->create();
        $recorded_expense_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $expense_record_modifier->id,
            "debit_amount" => "250",
            "credit_amount" => "250"
        ])->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $first_frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at" => Time::parse("-3 day")->toDateTimeString(),
            "finished_at" => Time::parse("-2 day")->toDateTimeString()
        ])->create();
        $summary_calculation_fabricator = new Fabricator(SummaryCalculationModel::class);
        $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id
        ]);
        $first_equity_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $equity_account->id,
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "2500",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "2000"
        ])->create();
        $first_asset_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $asset_account->id,
            "unadjusted_debit_amount" => "2500",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "2000",
            "closed_credit_amount" => "0"
        ])->create();
        $first_expense_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $expense_account->id,
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "0"
        ])->create();
        $second_frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at" => Time::yesterday()->toDateTimeString(),
            "finished_at" => Time::now()->toDateTimeString()
        ])->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/frozen_periods/dry_run", [
                "frozen_period" => $second_frozen_period->toArray()
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "@meta" => [
                "statements" => [
                    [
                        "currency_id" => $currency->id,
                        "unadjusted_trial_balance" => [
                            "debit_total" => "3000",
                            "credit_total" => "3000"
                        ],
                        "income_statement" => [
                            "net_total" => "-250"
                        ],
                        "balance_sheet" => [
                            "total_assets" => "2750",
                            "total_liabilities" => "0",
                            "total_equities" => "2750"
                        ],
                        "adjusted_trial_balance" => [
                            "debit_total" => "3000",
                            "credit_total" => "3000"
                        ]
                    ]
                ]
            ],
            "frozen_period" => $second_frozen_period->toArray(),
            "summary_calculations" => [
                [
                    "account_id" => $asset_account->id,
                    "unadjusted_debit_amount" => "2750",
                    "unadjusted_credit_amount" => "0",
                    "closed_debit_amount" => "2750",
                    "closed_credit_amount" => "0"
                ],
                [
                    "account_id" => $equity_account->id,
                    "unadjusted_debit_amount" => "0",
                    "unadjusted_credit_amount" => "3000",
                    "closed_debit_amount" => "0",
                    "closed_credit_amount" => "3000"
                ],
                [
                    "account_id" => $expense_account->id,
                    "unadjusted_debit_amount" => "250",
                    "unadjusted_credit_amount" => "0",
                    "closed_debit_amount" => "250",
                    "closed_credit_amount" => "0"
                ]
            ],
            "accounts" => [],
            "currencies" => []
        ]);
        $this->seeNumRecords(1, "frozen_periods", []);
        $this->seeNumRecords(3, "summary_calculations", []);
    }

    public function testValidIncompleteChainOpenCheck()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $equity_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EQUITY_ACCOUNT_KIND
        ])->create();
        $asset_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => ASSET_ACCOUNT_KIND
        ])->create();
        $asset_b_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => ASSET_ACCOUNT_KIND
        ])->create();
        $expense_account = $account_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "kind" => EXPENSE_ACCOUNT_KIND
        ])->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $normal_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $asset_account->id,
            "credit_account_id" => $equity_account->id,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $expense_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $expense_account->id,
            "credit_account_id" => $asset_account->id,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $close_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $equity_account->id,
            "credit_account_id" => $expense_account->id,
            "action" => CLOSE_MODIFIER_ACTION
        ])->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $recorded_normal_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $normal_record_modifier->id,
            "debit_amount" => "1000",
            "credit_amount" => "1000"
        ])->create();
        $recorded_expense_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $expense_record_modifier->id,
            "debit_amount" => "250",
            "credit_amount" => "250"
        ])->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $first_frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at" => Time::parse("-3 day")->toDateTimeString(),
            "finished_at" => Time::parse("-2 day")->toDateTimeString()
        ])->create();
        $summary_calculation_fabricator = new Fabricator(SummaryCalculationModel::class);
        $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id
        ]);
        $first_equity_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $equity_account->id,
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "5500",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "4000"
        ])->create();
        $first_asset_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $asset_account->id,
            "unadjusted_debit_amount" => "2500",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "2000",
            "closed_credit_amount" => "0"
        ])->create();
        $first_asset_b_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $asset_b_account->id,
            "unadjusted_debit_amount" => "3000",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "2000",
            "closed_credit_amount" => "0"
        ])->create();
        $first_expense_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $expense_account->id,
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "0"
        ])->create();
        $second_frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at" => Time::yesterday()->toDateTimeString(),
            "finished_at" => Time::now()->toDateTimeString()
        ])->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/frozen_periods/dry_run", [
                "frozen_period" => $second_frozen_period->toArray()
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "@meta" => [
                "statements" => [
                    [
                        "currency_id" => $currency->id,
                        "unadjusted_trial_balance" => [
                            "debit_total" => "5000",
                            "credit_total" => "5000"
                        ],
                        "income_statement" => [
                            "net_total" => "-250"
                        ],
                        "balance_sheet" => [
                            "total_assets" => "4750",
                            "total_liabilities" => "0",
                            "total_equities" => "4750"
                        ],
                        "adjusted_trial_balance" => [
                            "debit_total" => "5000",
                            "credit_total" => "5000"
                        ]
                    ]
                ]
            ],
            "frozen_period" => $second_frozen_period->toArray(),
            "summary_calculations" => [
                [
                    "account_id" => $asset_account->id,
                    "unadjusted_debit_amount" => "2750",
                    "unadjusted_credit_amount" => "0",
                    "closed_debit_amount" => "2750",
                    "closed_credit_amount" => "0"
                ],
                [
                    "account_id" => $equity_account->id,
                    "unadjusted_debit_amount" => "0",
                    "unadjusted_credit_amount" => "5000",
                    "closed_debit_amount" => "0",
                    "closed_credit_amount" => "5000"
                ],
                [
                    "account_id" => $expense_account->id,
                    "unadjusted_debit_amount" => "250",
                    "unadjusted_credit_amount" => "0",
                    "closed_debit_amount" => "250",
                    "closed_credit_amount" => "0"
                ],
                [
                    "account_id" => $asset_b_account->id,
                    "unadjusted_debit_amount" => "2000",
                    "unadjusted_credit_amount" => "0",
                    "closed_debit_amount" => "2000",
                    "closed_credit_amount" => "0"
                ]
            ],
            "accounts" => json_decode(json_encode([
                $equity_account,
                $asset_account,
                $asset_b_account,
                $expense_account
            ]), true),
            "currencies" => []
        ]);
        $this->seeNumRecords(1, "frozen_periods", []);
        $this->seeNumRecords(4, "summary_calculations", []);
    }

    public function testValidIncompleteExchangeChainOpenCheck()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_a = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $currency_b = $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ])->create();
        $account_fabricator = new Fabricator(AccountModel::class);
        $equity_account = $account_fabricator->setOverrides([
            "currency_id" => $currency_a->id,
            "kind" => EQUITY_ACCOUNT_KIND
        ])->create();
        $asset_account = $account_fabricator->setOverrides([
            "currency_id" => $currency_a->id,
            "kind" => ASSET_ACCOUNT_KIND
        ])->create();
        $foreign_asset_account = $account_fabricator->setOverrides([
            "currency_id" => $currency_b->id,
            "kind" => ASSET_ACCOUNT_KIND
        ])->create();
        $expense_account = $account_fabricator->setOverrides([
            "currency_id" => $currency_a->id,
            "kind" => EXPENSE_ACCOUNT_KIND
        ])->create();
        $modifier_fabricator = new Fabricator(ModifierModel::class);
        $normal_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $asset_account->id,
            "credit_account_id" => $equity_account->id,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $normal_exchange_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $foreign_asset_account->id,
            "credit_account_id" => $asset_account->id,
            "action" => EXCHANGE_MODIFIER_ACTION
        ])->create();
        $expense_record_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $expense_account->id,
            "credit_account_id" => $asset_account->id,
            "action" => RECORD_MODIFIER_ACTION
        ])->create();
        $close_modifier = $modifier_fabricator->setOverrides([
            "debit_account_id" => $equity_account->id,
            "credit_account_id" => $expense_account->id,
            "action" => CLOSE_MODIFIER_ACTION
        ])->create();
        $financial_entry_fabricator = new Fabricator(FinancialEntryModel::class);
        $recorded_normal_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $normal_record_modifier->id,
            "debit_amount" => "1000",
            "credit_amount" => "1000"
        ])->create();
        $recorded_expense_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $expense_record_modifier->id,
            "debit_amount" => "250",
            "credit_amount" => "250"
        ])->create();
        $exchange_normal_financial_entry = $financial_entry_fabricator->setOverrides([
            "modifier_id" => $normal_exchange_modifier->id,
            "debit_amount" => "3",
            "credit_amount" => "250"
        ])->create();
        $frozen_period_fabricator = new Fabricator(FrozenPeriodModel::class);
        $first_frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at" => Time::parse("-3 day")->toDateTimeString(),
            "finished_at" => Time::parse("-2 day")->toDateTimeString()
        ])->create();
        $summary_calculation_fabricator = new Fabricator(SummaryCalculationModel::class);
        $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id
        ]);
        $first_equity_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $equity_account->id,
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "2500",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "2000"
        ])->create();
        $first_asset_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $asset_account->id,
            "unadjusted_debit_amount" => "2500",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "2000",
            "closed_credit_amount" => "0"
        ])->create();
        $first_expense_summary_calculation = $summary_calculation_fabricator->setOverrides([
            "frozen_period_id" => $first_frozen_period->id,
            "account_id" => $expense_account->id,
            "unadjusted_debit_amount" => "0",
            "unadjusted_credit_amount" => "0",
            "closed_debit_amount" => "0",
            "closed_credit_amount" => "0"
        ])->create();
        $second_frozen_period = $frozen_period_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id,
            "started_at" => Time::yesterday()->toDateTimeString(),
            "finished_at" => Time::now()->toDateTimeString()
        ])->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/frozen_periods/dry_run", [
                "frozen_period" => $second_frozen_period->toArray()
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "@meta" => [
                "statements" => [
                    [
                        "currency_id" => $currency_a->id,
                        "unadjusted_trial_balance" => [
                            "debit_total" => "2750",
                            "credit_total" => "3000"
                        ],
                        "income_statement" => [
                            "net_total" => "-250"
                        ],
                        "balance_sheet" => [
                            "total_assets" => "2500",
                            "total_liabilities" => "0",
                            "total_equities" => "2750"
                        ],
                        "adjusted_trial_balance" => [
                            "debit_total" => "2750",
                            "credit_total" => "3000"
                        ]
                    ],
                    [
                        "currency_id" => $currency_b->id,
                        "unadjusted_trial_balance" => [
                            "debit_total" => "3",
                            "credit_total" => "0"
                        ],
                        "income_statement" => [
                            "net_total" => "0"
                        ],
                        "balance_sheet" => [
                            "total_assets" => "3",
                            "total_liabilities" => "0",
                            "total_equities" => "0"
                        ],
                        "adjusted_trial_balance" => [
                            "debit_total" => "3",
                            "credit_total" => "0"
                        ]
                    ]
                ],
                "exchange_rates" => [
                    [
                        "source" => [
                            "currency_id" => $currency_a->id,
                            "value" => "250"
                        ],
                        "destination" => [
                            "currency_id" => $currency_b->id,
                            "value" => "3"
                        ],
                        "updated_at" => $exchange_normal_financial_entry
                            ->updated_at
                            ->toDateTimeString()
                    ]
                ]
            ],
            "frozen_period" => $second_frozen_period->toArray(),
            "summary_calculations" => [
                [
                    "account_id" => $asset_account->id,
                    "unadjusted_debit_amount" => "2500",
                    "unadjusted_credit_amount" => "0",
                    "closed_debit_amount" => "2500",
                    "closed_credit_amount" => "0"
                ],
                [
                    "account_id" => $equity_account->id,
                    "unadjusted_debit_amount" => "0",
                    "unadjusted_credit_amount" => "3000",
                    "closed_debit_amount" => "0",
                    "closed_credit_amount" => "3000"
                ],
                [
                    "account_id" => $foreign_asset_account->id,
                    "unadjusted_debit_amount" => "3",
                    "unadjusted_credit_amount" => "0",
                    "closed_debit_amount" => "3",
                    "closed_credit_amount" => "0"
                ],
                [
                    "account_id" => $expense_account->id,
                    "unadjusted_debit_amount" => "250",
                    "unadjusted_credit_amount" => "0",
                    "closed_debit_amount" => "250",
                    "closed_credit_amount" => "0"
                ]
            ],
            "accounts" => json_decode(json_encode([
                $equity_account,
                $asset_account,
                $foreign_asset_account,
                $expense_account
            ]), true),
            "currencies" => []
        ]);
        $this->seeNumRecords(1, "frozen_periods", []);
        $this->seeNumRecords(3, "summary_calculations", []);
    }

    public function testInvalidUpdate()
    {
        // There is no update route for frozen period so this passes automatically.
        // This test method has been retained in case there a new fields that can be updated.
        $this->assertTrue(true);
    }

    public function testUnownedDelete()
    {
        // There is no soft delete route for frozen period so this passes automatically.
        // This test method has been retained in case the resource can be soft-deleted.
        $this->assertTrue(true);
    }

    public function testDoubleDelete()
    {
        // There is no soft delete route for frozen period so this passes automatically.
        // This test method has been retained in case the resource can be soft-deleted.
        $this->assertTrue(true);
    }

    public function testDoubleRestore()
    {
        // There is no restore route for frozen period so this passes automatically.
        // This test method has been retained in case the resource can be restored.
        $this->assertTrue(true);
    }

    public function testImmediateForceDelete()
    {
        // There is no immediate force route for frozen period so this passes automatically.
        // This test method has been retained in case the resource can be soft-deleted.
        $this->assertTrue(true);
    }

    public function testDoubleForceDelete()
    {
        // There is no double force route for frozen period so this passes automatically.
        // This test method has been retained in case the resource can be soft-deleted.
        $this->assertTrue(true);
    }
}
