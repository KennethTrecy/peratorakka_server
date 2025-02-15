<?php

namespace Tests\Feature\Resource;

use App\Exceptions\InvalidRequest;
use App\Exceptions\MissingResource;
use App\Exceptions\UnprocessableRequest;
use App\Libraries\Resource;
use App\Models\FrozenPeriodModel;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\I18n\Time;
use Tests\Feature\Helper\AuthenticatedContextualHTTPTestCase;
use Throwable;

class FrozenPeriodTest extends AuthenticatedContextualHTTPTestCase
{
    public function testDefaultIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [ $details ] = FrozenPeriodModel::createTestResource(
            $authenticated_info->getUser()->id,
            []
        );

        $result = $authenticated_info->getRequest()->get("/api/v2/frozen_periods");

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "overall_filtered_count" => 1
            ],
            "frozen_periods" => json_decode(json_encode([ $details ]))
        ]);
    }

    public function testDefaultShow()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $cash_flow_activities,
            $currencies,
            $accounts,
            $frozen_periods,
            $frozen_accounts,
            $real_adjusted_summaries,
            $real_unadjusted_summaries,
            $real_flows
        ] = FrozenPeriodModel::createTestPeriods(
            $authenticated_info->getUser(),
            [
                [
                    "started_at" => Time::now()->subDays(1),
                    "finished_at" => Time::now(),
                    "entries" => [
                        [
                            "modifier_index" => 0,
                            "atoms" => [
                                [ 0, "1000" ],
                                [ 1, "1000" ]
                            ]
                        ],
                        [
                            "modifier_index" => 1,
                            "atoms" => [
                                [ 2, "250" ],
                                [ 3, "250" ]
                            ]
                        ],
                        [
                            "modifier_index" => 2,
                            "atoms" => [
                                [ 4, "250" ],
                                [ 5, "250" ]
                            ]
                        ]
                    ]
                ]
            ],
            [
                "currency_count" => 1,
                "cash_flow_activity_count" => 1,
                "expected_modifier_actions" => [
                    RECORD_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION,
                    CLOSE_MODIFIER_ACTION
                ],
                "account_combinations" => [
                    [ 0, EQUITY_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ],
                    [ 0, GENERAL_EXPENSE_ACCOUNT_KIND ]
                ],
                "modifier_atom_combinations" => [
                    [ 0, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 0, 0, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 1, 2, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 1, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 2, 0, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 2, 2, REAL_CREDIT_MODIFIER_ATOM_KIND ]
                ],
                "modifier_atom_activity_combinations" => [
                    [ 1, 0 ],
                    [ 2, 0 ]
                ]
            ]
        );
        $cash_flow_activity = $cash_flow_activities[0];
        $currency = $currencies[0];
        $details = $frozen_periods[0];

        $result = $authenticated_info
            ->getRequest()
            ->get("/api/v2/frozen_periods/$details->id");

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "statements" => [
                    [
                        "currency_id" => $currency->id,
                        "unadjusted_trial_balance" => [
                            "debit_total" => "1000",
                            "credit_total" => "1000"
                        ],
                        "income_statement" => [
                            "net_total" => "-250"
                        ],
                        "balance_sheet" => [
                            "total_assets" => "750",
                            "total_liabilities" => "0",
                            "total_equities" => "750"
                        ],
                        "cash_flow_statement" => [
                            "opened_real_liquid_amount" => "0",
                            "closed_real_liquid_amount" => "750",
                            "real_liquid_amount_difference" => "750",
                            "subtotals" => [
                                [
                                    "cash_flow_activity_id" => $cash_flow_activity->id,
                                    "net_income" => "-250",
                                    "subtotal" => "750"
                                ]
                            ]
                        ],
                        "adjusted_trial_balance" => [
                            "debit_total" => "750",
                            "credit_total" => "750"
                        ]
                    ]
                ],
                "exchange_rates" => []
            ],
            "accounts" => json_decode(json_encode($accounts)),
            "cash_flow_activities" => json_decode(json_encode([ $cash_flow_activity ])),
            "currencies" => json_decode(json_encode([ $currency ])),
            "frozen_accounts" => json_decode(json_encode($frozen_accounts)),
            "frozen_period" => json_decode(json_encode($details)),
            "precision_formats" => json_decode(json_encode($precision_formats), true),
            "real_adjusted_summary_calculations" => json_decode(json_encode(
                $real_adjusted_summaries
            )),
            "real_unadjusted_summary_calculations" => json_decode(json_encode(
                $real_unadjusted_summaries
            )),
            "real_flow_calculations" => json_decode(json_encode($real_flows))
        ]);
    }

    public function testDefaultCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $cash_flow_activities,
            $currencies,
            $accounts,
            $frozen_periods,
            $frozen_accounts,
            $real_adjusted_summaries,
            $real_unadjusted_summaries,
            $real_flows
        ] = FrozenPeriodModel::createTestPeriods(
            $authenticated_info->getUser(),
            [
                [
                    "started_at" => Time::now()->subDays(1),
                    "entries" => [
                        [
                            "modifier_index" => 0,
                            "atoms" => [
                                [ 0, "1000" ],
                                [ 1, "1000" ]
                            ]
                        ],
                        [
                            "modifier_index" => 1,
                            "atoms" => [
                                [ 2, "250" ],
                                [ 3, "250" ]
                            ]
                        ],
                        [
                            "modifier_index" => 2,
                            "atoms" => [
                                [ 4, "250" ],
                                [ 5, "250" ]
                            ]
                        ]
                    ]
                ]
            ],
            [
                "currency_count" => 1,
                "cash_flow_activity_count" => 1,
                "expected_modifier_actions" => [
                    RECORD_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION,
                    CLOSE_MODIFIER_ACTION
                ],
                "account_combinations" => [
                    [ 0, EQUITY_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ],
                    [ 0, GENERAL_EXPENSE_ACCOUNT_KIND ]
                ],
                "modifier_atom_combinations" => [
                    [ 0, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 0, 0, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 1, 2, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 1, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 2, 0, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 2, 2, REAL_CREDIT_MODIFIER_ATOM_KIND ]
                ],
                "modifier_atom_activity_combinations" => [
                    [ 1, 0 ],
                    [ 2, 0 ]
                ]
            ]
        );
        $cash_flow_activity = $cash_flow_activities[0];
        $currency = $currencies[0];
        $details = $frozen_periods[0];

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/frozen_periods", [
                "frozen_period" => [
                    "started_at" => $details->started_at->toDateTimeString(),
                    "finished_at" => Time::now()->toDateTimeString()
                ]
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "frozen_period" => $details->toArray()
        ]);
        $this->seeNumRecords(1, "frozen_periods", []);
        $this->seeNumRecords(3, "frozen_Accounts", []);
        $this->seeNumRecords(3, "real_unadjusted_summary_calculations", []);
        $this->seeNumRecords(2, "real_adjusted_summary_calculations", []);
        $this->seeNumRecords(2, "real_flow_calculations", []);
    }

    public function testDefaultUpdate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details,
            $new_details
        ] = FrozenPeriodModel::createAndMakeTestResources(
            $authenticated_info->getUser()->id,
            []
        );

        try {
            $result = $authenticated_info
                ->getRequest()
                ->withBodyFormat("json")
                ->put("/api/v2/frozen_periods/$details->id", [
                    "frozen_period" => $new_details->toArray()
                ]);
            $this->assertTrue(false);
        } catch (PageNotFoundException $error) {
            $this->assertTrue(true);
        }
    }

    public function testDefaultDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details
        ] = FrozenPeriodModel::createTestResource(
            $authenticated_info->getUser()->id,
            []
        );

        try {
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v2/frozen_periods/$details->id");
            $this->assertTrue(false);
        } catch (PageNotFoundException $error) {
            $this->assertTrue(true);
        }
    }

    public function testDefaultRestore()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details
        ] = FrozenPeriodModel::createTestResource(
            $authenticated_info->getUser()->id,
            []
        );
        model(FrozenPeriodModel::class)->delete($details->id);

        try {
            $result = $authenticated_info
                ->getRequest()
                ->patch("/api/v2/frozen_periods/$details->id");
            $this->assertTrue(false);
        } catch (PageNotFoundException $error) {
            $this->assertTrue(true);
        }
    }

    public function testDefaultForceDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $cash_flow_activities,
            $currencies,
            $accounts,
            $frozen_periods,
            $frozen_accounts,
            $real_adjusted_summaries,
            $real_unadjusted_summaries,
            $real_flows
        ] = FrozenPeriodModel::createTestPeriods(
            $authenticated_info->getUser(),
            [
                [
                    "started_at" => Time::now()->subDays(1),
                    "finished_at" => Time::now(),
                    "entries" => [
                        [
                            "modifier_index" => 0,
                            "atoms" => [
                                [ 0, "1000" ],
                                [ 1, "1000" ]
                            ]
                        ],
                        [
                            "modifier_index" => 1,
                            "atoms" => [
                                [ 2, "250" ],
                                [ 3, "250" ]
                            ]
                        ],
                        [
                            "modifier_index" => 2,
                            "atoms" => [
                                [ 4, "250" ],
                                [ 5, "250" ]
                            ]
                        ]
                    ]
                ]
            ],
            [
                "currency_count" => 1,
                "cash_flow_activity_count" => 1,
                "expected_modifier_actions" => [
                    RECORD_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION,
                    CLOSE_MODIFIER_ACTION
                ],
                "account_combinations" => [
                    [ 0, EQUITY_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ],
                    [ 0, GENERAL_EXPENSE_ACCOUNT_KIND ]
                ],
                "modifier_atom_combinations" => [
                    [ 0, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 0, 0, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 1, 2, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 1, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 2, 0, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 2, 2, REAL_CREDIT_MODIFIER_ATOM_KIND ]
                ],
                "modifier_atom_activity_combinations" => [
                    [ 1, 0 ],
                    [ 2, 0 ]
                ]
            ]
        );
        $details = $frozen_periods[0];

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v2/frozen_periods/$details->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "frozen_periods", []);
    }

    public function testDefaultCheck()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $cash_flow_activities,
            $currencies,
            $accounts,
            $frozen_periods,
            $frozen_accounts,
            $real_adjusted_summaries,
            $real_unadjusted_summaries,
            $real_flows
        ] = FrozenPeriodModel::createTestPeriods(
            $authenticated_info->getUser(),
            [
                [
                    "started_at" => Time::now()->subDays(1),
                    "entries" => [
                        [
                            "modifier_index" => 0,
                            "atoms" => [
                                [ 0, "1000" ],
                                [ 1, "1000" ]
                            ]
                        ],
                        [
                            "modifier_index" => 1,
                            "atoms" => [
                                [ 2, "250" ],
                                [ 3, "250" ]
                            ]
                        ],
                        [
                            "modifier_index" => 2,
                            "atoms" => [
                                [ 4, "250" ],
                                [ 5, "250" ]
                            ]
                        ]
                    ]
                ]
            ],
            [
                "currency_count" => 1,
                "cash_flow_activity_count" => 1,
                "expected_modifier_actions" => [
                    RECORD_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION,
                    CLOSE_MODIFIER_ACTION
                ],
                "account_combinations" => [
                    [ 0, EQUITY_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ],
                    [ 0, GENERAL_EXPENSE_ACCOUNT_KIND ]
                ],
                "modifier_atom_combinations" => [
                    [ 0, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 0, 0, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 1, 2, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 1, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 2, 0, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 2, 2, REAL_CREDIT_MODIFIER_ATOM_KIND ]
                ],
                "modifier_atom_activity_combinations" => [
                    [ 1, 0 ],
                    [ 2, 0 ]
                ]
            ]
        );
        [ $equity_account, $asset_account, $expense_account ] = $accounts;
        $frozen_account_hashes = Resource::key(
            $frozen_accounts,
            fn ($info) => $info->account_id
        );
        $cash_flow_activity = $cash_flow_activities[0];
        $currency = $currencies[0];
        $details = [
            "started_at" => $frozen_periods[0]->started_at->toDateTimeString(),
            "finished_at" => Time::now()->toDateTimeString()
        ];

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/frozen_periods/dry_run", [
                "frozen_period" => $details
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "@meta" => [
                "statements" => [
                    [
                        "currency_id" => $currency->id,
                        "unadjusted_trial_balance" => [
                            "debit_total" => "1000",
                            "credit_total" => "1000"
                        ],
                        "income_statement" => [
                            "net_total" => "-250"
                        ],
                        "balance_sheet" => [
                            "total_assets" => "750",
                            "total_liabilities" => "0",
                            "total_equities" => "750"
                        ],
                        "cash_flow_statement" => [
                            "opened_real_liquid_amount" => "0",
                            "closed_real_liquid_amount" => "750",
                            "real_liquid_amount_difference" => "750",
                            "subtotals" => [
                                [
                                    "cash_flow_activity_id" => $cash_flow_activity->id,
                                    "net_income" => "-250",
                                    "subtotal" => "750"
                                ]
                            ]
                        ],
                        "adjusted_trial_balance" => [
                            "debit_total" => "750",
                            "credit_total" => "750"
                        ]
                    ]
                ]
            ],
            "frozen_period" => $details,
            "frozen_accounts" => json_decode(json_encode($frozen_accounts), true),
            "real_unadjusted_summary_calculations" => [
                [
                    "frozen_account_hash" => $frozen_account_hashes[$equity_account->id]->hash,
                    "debit_amount" => "0",
                    "credit_amount" => "1000"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[$asset_account->id]->hash,
                    "debit_amount" => "1000",
                    "credit_amount" => "250"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[$expense_account->id]->hash,
                    "debit_amount" => "250",
                    "credit_amount" => "0"
                ]
            ],
            "real_adjusted_summary_calculations" => [
                [
                    "frozen_account_hash" => $frozen_account_hashes[$equity_account->id]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "750"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[$asset_account->id]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "750"
                ]
            ],
            "real_flow_calculations" => [
                [
                    "cash_flow_activity_id" => $cash_flow_activity->id,
                    "frozen_account_hash" => $frozen_account_hashes[$equity_account->id]->hash,
                    "net_amount" => "1000"
                ],
                [
                    "cash_flow_activity_id" => $cash_flow_activity->id,
                    "frozen_account_hash" => $frozen_account_hashes[$expense_account->id]->hash,
                    "net_amount" => "-250"
                ]
            ],
            "accounts" => json_decode(json_encode($accounts), true),
            "currencies" => json_decode(json_encode($currencies), true),
            "precision_formats" => json_decode(json_encode($precision_formats), true)
        ]);
        $this->seeNumRecords(0, "frozen_periods", []);
        $this->seeNumRecords(0, "real_unadjusted_summary_calculations", []);
        $this->seeNumRecords(0, "real_adjusted_summary_calculations", []);
        $this->seeNumRecords(0, "real_flow_calculations", []);
    }

    public function testDefaultRecalculate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $cash_flow_activities,
            $currencies,
            $accounts,
            $frozen_periods,
            $frozen_accounts,
            $real_adjusted_summaries,
            $real_unadjusted_summaries,
            $real_flows
        ] = FrozenPeriodModel::createTestPeriods(
            $authenticated_info->getUser(),
            [
                [
                    "started_at" => Time::now()->subDays(1),
                    "entries" => [
                        [
                            "modifier_index" => 0,
                            "atoms" => [
                                [ 0, "1000" ],
                                [ 1, "1000" ]
                            ]
                        ],
                        [
                            "modifier_index" => 1,
                            "atoms" => [
                                [ 2, "250" ],
                                [ 3, "250" ]
                            ]
                        ],
                        [
                            "modifier_index" => 2,
                            "atoms" => [
                                [ 4, "250" ],
                                [ 5, "250" ]
                            ]
                        ]
                    ]
                ]
            ],
            [
                "currency_count" => 1,
                "cash_flow_activity_count" => 1,
                "expected_modifier_actions" => [
                    RECORD_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION,
                    CLOSE_MODIFIER_ACTION
                ],
                "account_combinations" => [
                    [ 0, EQUITY_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ],
                    [ 0, GENERAL_EXPENSE_ACCOUNT_KIND ]
                ],
                "modifier_atom_combinations" => [
                    [ 0, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 0, 0, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 1, 2, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 1, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 2, 0, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 2, 2, REAL_CREDIT_MODIFIER_ATOM_KIND ]
                ],
                "modifier_atom_activity_combinations" => [
                    [ 1, 0 ],
                    [ 2, 0 ]
                ]
            ]
        );
        [ $equity_account, $asset_account, $expense_account ] = $accounts;
        $frozen_account_hashes = Resource::key(
            $frozen_accounts,
            fn ($info) => $info->account_id
        );
        $cash_flow_activity = $cash_flow_activities[0];
        $currency = $currencies[0];
        $details = [
            "started_at" => $frozen_periods[0]->started_at->toDateTimeString(),
            "finished_at" => Time::now()->toDateTimeString()
        ];

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/frozen_periods/recalculate", [
                "frozen_period" => [
                    ...$details,
                    "source_currency_id" => null,
                    "target_currency_id" => $currency->id
                ]
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "@meta" => [
                "statement" => [
                    "currency_id" => null,
                    "unadjusted_trial_balance" => [
                        "debit_total" => "1000",
                        "credit_total" => "1000"
                    ],
                    "income_statement" => [
                        "net_total" => "-250"
                    ],
                    "balance_sheet" => [
                        "total_assets" => "750",
                        "total_liabilities" => "0",
                        "total_equities" => "750"
                    ],
                    "cash_flow_statement" => [
                        "opened_real_liquid_amount" => "0",
                        "closed_real_liquid_amount" => "750",
                        "real_liquid_amount_difference" => "750",
                        "subtotals" => [
                            [
                                "cash_flow_activity_id" => $cash_flow_activity->id,
                                "net_income" => "-250",
                                "subtotal" => "750"
                            ]
                        ]
                    ],
                    "adjusted_trial_balance" => [
                        "debit_total" => "750",
                        "credit_total" => "750"
                    ]
                ]
            ]
        ]);
        $this->seeNumRecords(0, "frozen_periods", []);
        $this->seeNumRecords(0, "real_unadjusted_summary_calculations", []);
        $this->seeNumRecords(0, "real_adjusted_summary_calculations", []);
        $this->seeNumRecords(0, "real_flow_calculations", []);
    }

    public function testEmptyIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $result = $authenticated_info->getRequest()->get("/api/v2/frozen_periods");

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "overall_filtered_count" => 0
            ],
            "frozen_periods" => json_decode(json_encode([])),
        ]);
    }

    public function testQueriedIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details
        ] = FrozenPeriodModel::createTestResource(
            $authenticated_info->getUser()->id,
            []
        );

        $result = $authenticated_info->getRequest()->get("/api/v2/frozen_periods", [
            "page" => [
                "offset" => 0,
                "limit" => 5
            ]
        ]);

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "overall_filtered_count" => 1
            ],
            "frozen_periods" => [
                $details
            ]
        ]);
    }

    public function testMissingShow()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details
        ] = FrozenPeriodModel::createTestResource(
            $authenticated_info->getUser()->id,
            []
        );
        $details->id = $details->id + 1;

        $this->expectException(MissingResource::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info
            ->getRequest()
            ->get("/api/v2/frozen_periods/$details->id");
    }

    public function testValidChainCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $cash_flow_activities,
            $currencies,
            $accounts,
            $frozen_periods,
            $frozen_accounts,
            $real_adjusted_summaries,
            $real_unadjusted_summaries,
            $real_flows
        ] = FrozenPeriodModel::createTestPeriods(
            $authenticated_info->getUser(),
            [
                [
                    "started_at" => Time::now()->subDays(3),
                    "finished_at" => Time::now()->subDays(2),
                    "entries" => [
                        [
                            "modifier_index" => 0,
                            "atoms" => [
                                [ 0, "1000" ],
                                [ 1, "1000" ]
                            ]
                        ],
                        [
                            "modifier_index" => 1,
                            "atoms" => [
                                [ 2, "250" ],
                                [ 3, "250" ]
                            ]
                        ],
                        [
                            "modifier_index" => 2,
                            "atoms" => [
                                [ 4, "250" ],
                                [ 5, "250" ]
                            ]
                        ]
                    ]
                ],
                [
                    "started_at" => Time::now()->subDays(1),
                    "entries" => [
                        [
                            "modifier_index" => 0,
                            "atoms" => [
                                [ 0, "1500" ],
                                [ 1, "1500" ]
                            ]
                        ],
                        [
                            "modifier_index" => 1,
                            "atoms" => [
                                [ 2, "125" ],
                                [ 3, "125" ]
                            ]
                        ],
                        [
                            "modifier_index" => 2,
                            "atoms" => [
                                [ 4, "125" ],
                                [ 5, "125" ]
                            ]
                        ]
                    ]
                ]
            ],
            [
                "currency_count" => 1,
                "cash_flow_activity_count" => 1,
                "expected_modifier_actions" => [
                    RECORD_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION,
                    CLOSE_MODIFIER_ACTION
                ],
                "account_combinations" => [
                    [ 0, EQUITY_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ],
                    [ 0, GENERAL_EXPENSE_ACCOUNT_KIND ]
                ],
                "modifier_atom_combinations" => [
                    [ 0, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 0, 0, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 1, 2, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 1, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 2, 0, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 2, 2, REAL_CREDIT_MODIFIER_ATOM_KIND ]
                ],
                "modifier_atom_activity_combinations" => [
                    [ 1, 0 ],
                    [ 2, 0 ]
                ]
            ]
        );
        [ $equity_account, $asset_account, $expense_account ] = $accounts;
        $frozen_account_hashes = Resource::key(
            array_filter(
                $frozen_accounts,
                fn ($info) => $info->frozen_period_id !== $frozen_periods[0]->id
            ),
            fn ($info) => $info->account_id
        );
        $cash_flow_activity = $cash_flow_activities[0];
        $currency = $currencies[0];
        $details = [
            "started_at" => $frozen_periods[1]->started_at->toDateTimeString(),
            "finished_at" => Time::now()->toDateTimeString()
        ];

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/frozen_periods", [
                "frozen_period" => $details
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "frozen_period" => $details
        ]);
        $this->seeNumRecords(2, "frozen_periods", []);
        $this->seeNumRecords(6, "real_unadjusted_summary_calculations", []);
        $this->seeInDatabase("real_unadjusted_summary_calculations", [
            "frozen_account_hash" => $frozen_account_hashes[$equity_account->id]->hash,
            "debit_amount" => "0",
            "credit_amount" => "2250"
        ]);
        $this->seeInDatabase("real_unadjusted_summary_calculations", [
            "frozen_account_hash" => $frozen_account_hashes[$asset_account->id]->hash,
            "debit_amount" => "2250",
            "credit_amount" => "125"
        ]);
        $this->seeInDatabase("real_unadjusted_summary_calculations", [
            "frozen_account_hash" => $frozen_account_hashes[$expense_account->id]->hash,
            "debit_amount" => "125",
            "credit_amount" => "0"
        ]);
        $this->seeNumRecords(4, "real_adjusted_summary_calculations", []);
        $this->seeInDatabase("real_adjusted_summary_calculations", [
            "frozen_account_hash" => $frozen_account_hashes[$equity_account->id]->hash,
            "opened_amount" => "750",
            "closed_amount" => "2125"
        ]);
        $this->seeInDatabase("real_adjusted_summary_calculations", [
            "frozen_account_hash" => $frozen_account_hashes[$asset_account->id]->hash,
            "opened_amount" => "750",
            "closed_amount" => "2125"
        ]);
        $this->seeNumRecords(4, "real_flow_calculations", []);
        $this->seeInDatabase("real_flow_calculations", [
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "frozen_account_hash" => $frozen_account_hashes[$equity_account->id]->hash,
            "net_amount" => "1500"
        ]);
        $this->seeInDatabase("real_flow_calculations", [
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "frozen_account_hash" => $frozen_account_hashes[$expense_account->id]->hash,
            "net_amount" => "-125"
        ]);
    }

    public function testValidIncompleteChainCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $cash_flow_activities,
            $currencies,
            $accounts,
            $frozen_periods,
            $frozen_accounts,
            $real_adjusted_summaries,
            $real_unadjusted_summaries,
            $real_flows
        ] = FrozenPeriodModel::createTestPeriods(
            $authenticated_info->getUser(),
            [
                [
                    "started_at" => Time::now()->subDays(3),
                    "finished_at" => Time::now()->subDays(2),
                    "entries" => [
                        [
                            "modifier_index" => 0,
                            "atoms" => [
                                [ 0, "1000" ],
                                [ 1, "1000" ]
                            ]
                        ],
                        [
                            "modifier_index" => 1,
                            "atoms" => [
                                [ 2, "250" ],
                                [ 3, "250" ]
                            ]
                        ],
                        [
                            "modifier_index" => 3,
                            "atoms" => [
                                [ 6, "200" ],
                                [ 7, "200" ]
                            ]
                        ],
                        [
                            "modifier_index" => 2,
                            "atoms" => [
                                [ 4, "250" ],
                                [ 5, "250" ]
                            ]
                        ]
                    ]
                ],
                [
                    "started_at" => Time::now()->subDays(1),
                    "entries" => [
                        [
                            "modifier_index" => 0,
                            "atoms" => [
                                [ 0, "1500" ],
                                [ 1, "1500" ]
                            ]
                        ],
                        [
                            "modifier_index" => 1,
                            "atoms" => [
                                [ 2, "125" ],
                                [ 3, "125" ]
                            ]
                        ],
                        [
                            "modifier_index" => 2,
                            "atoms" => [
                                [ 4, "125" ],
                                [ 5, "125" ]
                            ]
                        ]
                    ]
                ]
            ],
            [
                "currency_count" => 1,
                "cash_flow_activity_count" => 1,
                "expected_modifier_actions" => [
                    RECORD_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION,
                    CLOSE_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION
                ],
                "account_combinations" => [
                    [ 0, EQUITY_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ],
                    [ 0, GENERAL_EXPENSE_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ]
                ],
                "modifier_atom_combinations" => [
                    [ 0, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 0, 0, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 1, 2, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 1, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 2, 0, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 2, 2, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 3, 3, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 3, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ]
                ],
                "modifier_atom_activity_combinations" => [
                    [ 1, 0 ],
                    [ 2, 0 ]
                ]
            ]
        );
        [ $equity_account, $asset_a_account, $expense_account, $asset_b_account ] = $accounts;
        $frozen_account_hashes = Resource::key(
            array_filter(
                $frozen_accounts,
                fn ($info) => $info->frozen_period_id !== $frozen_periods[0]->id
            ),
            fn ($info) => $info->account_id
        );
        $cash_flow_activity = $cash_flow_activities[0];
        $currency = $currencies[0];
        $details = [
            "started_at" => $frozen_periods[1]->started_at->toDateTimeString(),
            "finished_at" => Time::now()->toDateTimeString()
        ];

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/frozen_periods", [
                "frozen_period" => $details
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "frozen_period" => $details
        ]);
        $this->seeNumRecords(2, "frozen_periods", []);
        $this->seeNumRecords(8, "real_unadjusted_summary_calculations", []);
        $this->seeInDatabase("real_unadjusted_summary_calculations", [
            "frozen_account_hash" => $frozen_account_hashes[$equity_account->id]->hash,
            "debit_amount" => "0",
            "credit_amount" => "2250"
        ]);
        $this->seeInDatabase("real_unadjusted_summary_calculations", [
            "frozen_account_hash" => $frozen_account_hashes[$asset_a_account->id]->hash,
            "debit_amount" => "2050",
            "credit_amount" => "125"
        ]);
        $this->seeInDatabase("real_unadjusted_summary_calculations", [
            "frozen_account_hash" => $frozen_account_hashes[$asset_b_account->id]->hash,
            "debit_amount" => "200",
            "credit_amount" => "0"
        ]);
        $this->seeInDatabase("real_unadjusted_summary_calculations", [
            "frozen_account_hash" => $frozen_account_hashes[$expense_account->id]->hash,
            "debit_amount" => "125",
            "credit_amount" => "0"
        ]);
        $this->seeNumRecords(6, "real_adjusted_summary_calculations", []);
        $this->seeInDatabase("real_adjusted_summary_calculations", [
            "frozen_account_hash" => $frozen_account_hashes[$equity_account->id]->hash,
            "opened_amount" => "750",
            "closed_amount" => "2125"
        ]);
        $this->seeInDatabase("real_adjusted_summary_calculations", [
            "frozen_account_hash" => $frozen_account_hashes[$asset_a_account->id]->hash,
            "opened_amount" => "550",
            "closed_amount" => "1925"
        ]);
        $this->seeInDatabase("real_adjusted_summary_calculations", [
            "frozen_account_hash" => $frozen_account_hashes[$asset_b_account->id]->hash,
            "opened_amount" => "200",
            "closed_amount" => "200"
        ]);
        $this->seeNumRecords(4, "real_flow_calculations", []);
        $this->seeInDatabase("real_flow_calculations", [
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "frozen_account_hash" => $frozen_account_hashes[$equity_account->id]->hash,
            "net_amount" => "1500"
        ]);
        $this->seeInDatabase("real_flow_calculations", [
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "frozen_account_hash" => $frozen_account_hashes[$expense_account->id]->hash,
            "net_amount" => "-125"
        ]);
    }

    public function testInvalidCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $cash_flow_activities,
            $currencies,
            $accounts,
            $frozen_periods,
            $frozen_accounts,
            $real_adjusted_summaries,
            $real_unadjusted_summaries,
            $real_flows
        ] = FrozenPeriodModel::createTestPeriods(
            $authenticated_info->getUser(),
            [
                [
                    "started_at" => Time::now()->subDays(3),
                    "finished_at" => Time::now()->subDays(2),
                    "entries" => [
                        [
                            "modifier_index" => 0,
                            "atoms" => [
                                [ 0, "1000" ],
                                [ 1, "1000" ]
                            ]
                        ],
                        [
                            "modifier_index" => 1,
                            "atoms" => [
                                [ 2, "250" ],
                                [ 3, "250" ]
                            ]
                        ],
                        [
                            "modifier_index" => 2,
                            "atoms" => [
                                [ 4, "250" ],
                                [ 5, "250" ]
                            ]
                        ]
                    ]
                ],
                [
                    "started_at" => Time::now()->subDays(1),
                    "entries" => [
                        [
                            "modifier_index" => 0,
                            "atoms" => [
                                [ 0, "1500" ],
                                [ 1, "1500" ]
                            ]
                        ],
                        [
                            "modifier_index" => 1,
                            "atoms" => [
                                [ 2, "125" ],
                                [ 3, "125" ]
                            ]
                        ],
                        [
                            "modifier_index" => 2,
                            "atoms" => [
                                [ 4, "125" ],
                                [ 5, "125" ]
                            ]
                        ]
                    ]
                ]
            ],
            [
                "currency_count" => 1,
                "cash_flow_activity_count" => 1,
                "expected_modifier_actions" => [
                    RECORD_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION,
                    CLOSE_MODIFIER_ACTION
                ],
                "account_combinations" => [
                    [ 0, EQUITY_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ],
                    [ 0, GENERAL_EXPENSE_ACCOUNT_KIND ]
                ],
                "modifier_atom_combinations" => [
                    [ 0, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 0, 0, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 1, 2, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 1, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 2, 0, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 2, 2, REAL_CREDIT_MODIFIER_ATOM_KIND ]
                ],
                "modifier_atom_activity_combinations" => [
                    [ 1, 0 ],
                    [ 2, 0 ]
                ]
            ]
        );
        $details = [
            "started_at" => Time::tomorrow()->toDateTimeString(),
            "finished_at" => Time::now()->toDateTimeString()
        ];

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/frozen_periods", [
                "frozen_period" => $details
            ]);
    }

    public function testOpenCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $cash_flow_activities,
            $currencies,
            $accounts,
            $frozen_periods,
            $frozen_accounts,
            $real_adjusted_summaries,
            $real_unadjusted_summaries,
            $real_flows
        ] = FrozenPeriodModel::createTestPeriods(
            $authenticated_info->getUser(),
            [
                [
                    "started_at" => Time::now()->subDays(1),
                    "entries" => [
                        [
                            "modifier_index" => 0,
                            "atoms" => [
                                [ 0, "1500" ],
                                [ 1, "1500" ]
                            ]
                        ],
                        [
                            "modifier_index" => 1,
                            "atoms" => [
                                [ 2, "125" ],
                                [ 3, "125" ]
                            ]
                        ]
                    ]
                ]
            ],
            [
                "currency_count" => 1,
                "cash_flow_activity_count" => 1,
                "expected_modifier_actions" => [
                    RECORD_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION
                ],
                "account_combinations" => [
                    [ 0, EQUITY_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ],
                    [ 0, GENERAL_EXPENSE_ACCOUNT_KIND ]
                ],
                "modifier_atom_combinations" => [
                    [ 0, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 0, 0, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 1, 2, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 1, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ]
                ],
                "modifier_atom_activity_combinations" => [
                    [ 1, 0 ],
                    [ 2, 0 ]
                ]
            ]
        );
        $details = [
            "started_at" => $frozen_periods[0]->started_at->toDateTimeString(),
            "finished_at" => Time::now()->toDateTimeString()
        ];

        try {
            $this->expectException(UnprocessableRequest::class);
            $this->expectExceptionCode(422);
            $result = $authenticated_info
                ->getRequest()
                ->withBodyFormat("json")
                ->post("/api/v2/frozen_periods", [
                    "frozen_period" => $details
                ]);
            $this->assertTrue(false);
        } catch (UnprocessableRequest $error) {
            $this->seeNumRecords(0, "frozen_periods", []);
            $this->seeNumRecords(0, "real_unadjusted_summary_calculations", []);
            $this->seeNumRecords(0, "real_adjusted_summary_calculations", []);
            $this->seeNumRecords(0, "real_flow_calculations", []);

            throw $error;
        } catch (Throwable $exception) {
            $this->assertTrue(true);
        }
    }

    public function testValidOpenCheck()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $cash_flow_activities,
            $currencies,
            $accounts,
            $frozen_periods,
            $frozen_accounts,
            $real_adjusted_summaries,
            $real_unadjusted_summaries,
            $real_flows
        ] = FrozenPeriodModel::createTestPeriods(
            $authenticated_info->getUser(),
            [
                [
                    "started_at" => Time::now()->subDays(3),
                    "finished_at" => Time::now()->subDays(2),
                    "entries" => [
                        [
                            "modifier_index" => 0,
                            "atoms" => [
                                [ 0, "1000" ],
                                [ 1, "1000" ]
                            ]
                        ],
                        [
                            "modifier_index" => 1,
                            "atoms" => [
                                [ 2, "250" ],
                                [ 3, "250" ]
                            ]
                        ],
                        [
                            "modifier_index" => 2,
                            "atoms" => [
                                [ 4, "250" ],
                                [ 5, "250" ]
                            ]
                        ]
                    ]
                ],
                [
                    "started_at" => Time::now()->subDays(1),
                    "entries" => [
                        [
                            "modifier_index" => 0,
                            "atoms" => [
                                [ 0, "1500" ],
                                [ 1, "1500" ]
                            ]
                        ],
                        [
                            "modifier_index" => 1,
                            "atoms" => [
                                [ 2, "125" ],
                                [ 3, "125" ]
                            ]
                        ]
                    ]
                ]
            ],
            [
                "currency_count" => 1,
                "cash_flow_activity_count" => 1,
                "expected_modifier_actions" => [
                    RECORD_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION,
                    CLOSE_MODIFIER_ACTION
                ],
                "account_combinations" => [
                    [ 0, EQUITY_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ],
                    [ 0, GENERAL_EXPENSE_ACCOUNT_KIND ]
                ],
                "modifier_atom_combinations" => [
                    [ 0, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 0, 0, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 1, 2, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 1, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 2, 0, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 2, 2, REAL_CREDIT_MODIFIER_ATOM_KIND ]
                ],
                "modifier_atom_activity_combinations" => [
                    [ 1, 0 ],
                    [ 2, 0 ]
                ]
            ]
        );
        [ $equity_account, $asset_account, $expense_account ] = $accounts;
        $uncommitted_frozen_accounts = array_values(array_filter(
            $frozen_accounts,
            fn ($info) => $info->frozen_period_id !== $frozen_periods[0]->id
        ));
        $frozen_account_hashes = Resource::key(
            $uncommitted_frozen_accounts,
            fn ($info) => $info->account_id
        );
        $cash_flow_activity = $cash_flow_activities[0];
        $currency = $currencies[0];
        $details = [
            "started_at" => $frozen_periods[1]->started_at->toDateTimeString(),
            "finished_at" => Time::now()->toDateTimeString()
        ];

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/frozen_periods/dry_run", [
                "frozen_period" => $details
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "@meta" => [
                "statements" => [
                    [
                        "currency_id" => $currency->id,
                        "unadjusted_trial_balance" => [
                            "debit_total" => "2250",
                            "credit_total" => "2250"
                        ],
                        "income_statement" => [
                            "net_total" => "-125"
                        ],
                        "balance_sheet" => [
                            "total_assets" => "2125",
                            "total_liabilities" => "0",
                            "total_equities" => "2125"
                        ],
                        "cash_flow_statement" => [
                            "opened_real_liquid_amount" => "750",
                            "closed_real_liquid_amount" => "2125",
                            "real_liquid_amount_difference" => "1375",
                            "subtotals" => [
                                [
                                    "cash_flow_activity_id" => $cash_flow_activity->id,
                                    "net_income" => "-125",
                                    "subtotal" => "1375"
                                ]
                            ]
                        ],
                        "adjusted_trial_balance" => [
                            "debit_total" => "2125",
                            "credit_total" => "2250"
                        ]
                    ]
                ]
            ],
            "frozen_period" => $details,
            "frozen_accounts" => json_decode(json_encode($uncommitted_frozen_accounts), true),
            "real_unadjusted_summary_calculations" => [
                [
                    "frozen_account_hash" => $frozen_account_hashes[$equity_account->id]->hash,
                    "debit_amount" => "0",
                    "credit_amount" => "2250"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[$asset_account->id]->hash,
                    "debit_amount" => "2250",
                    "credit_amount" => "125"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[$expense_account->id]->hash,
                    "debit_amount" => "125",
                    "credit_amount" => "0"
                ]
            ],
            "real_adjusted_summary_calculations" => [
                [
                    "frozen_account_hash" => $frozen_account_hashes[$equity_account->id]->hash,
                    "opened_amount" => "750",
                    "closed_amount" => "2250"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[$asset_account->id]->hash,
                    "opened_amount" => "750",
                    "closed_amount" => "2125"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[$expense_account->id]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "125"
                ]
            ],
            "real_flow_calculations" => [
                [
                    "cash_flow_activity_id" => $cash_flow_activity->id,
                    "frozen_account_hash" => $frozen_account_hashes[$equity_account->id]->hash,
                    "net_amount" => "1500"
                ],
                [
                    "cash_flow_activity_id" => $cash_flow_activity->id,
                    "frozen_account_hash" => $frozen_account_hashes[$expense_account->id]->hash,
                    "net_amount" => "-125"
                ]
            ],
            "accounts" => json_decode(json_encode($accounts), true),
            "currencies" => json_decode(json_encode($currencies), true),
            "precision_formats" => json_decode(json_encode($precision_formats), true)
        ]);
        $this->seeNumRecords(1, "frozen_periods", []);
        $this->seeNumRecords(3, "real_unadjusted_summary_calculations", []);
        $this->seeNumRecords(2, "real_adjusted_summary_calculations", []);
        $this->seeNumRecords(2, "real_flow_calculations", []);
    }

    public function testValidIncompleteChainOpenCheck()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $cash_flow_activities,
            $currencies,
            $accounts,
            $frozen_periods,
            $frozen_accounts,
            $real_adjusted_summaries,
            $real_unadjusted_summaries,
            $real_flows
        ] = FrozenPeriodModel::createTestPeriods(
            $authenticated_info->getUser(),
            [
                [
                    "started_at" => Time::now()->subDays(3),
                    "finished_at" => Time::now()->subDays(2),
                    "entries" => [
                        [
                            "modifier_index" => 0,
                            "atoms" => [
                                [ 0, "1000" ],
                                [ 1, "1000" ]
                            ]
                        ],
                        [
                            "modifier_index" => 1,
                            "atoms" => [
                                [ 2, "250" ],
                                [ 3, "250" ]
                            ]
                        ],
                        [
                            "modifier_index" => 3,
                            "atoms" => [
                                [ 6, "200" ],
                                [ 7, "200" ]
                            ]
                        ],
                        [
                            "modifier_index" => 2,
                            "atoms" => [
                                [ 4, "250" ],
                                [ 5, "250" ]
                            ]
                        ]
                    ]
                ],
                [
                    "started_at" => Time::now()->subDays(1),
                    "entries" => [
                        [
                            "modifier_index" => 0,
                            "atoms" => [
                                [ 0, "1500" ],
                                [ 1, "1500" ]
                            ]
                        ],
                        [
                            "modifier_index" => 1,
                            "atoms" => [
                                [ 2, "125" ],
                                [ 3, "125" ]
                            ]
                        ],
                        [
                            "modifier_index" => 2,
                            "atoms" => [
                                [ 4, "125" ],
                                [ 5, "125" ]
                            ]
                        ]
                    ]
                ]
            ],
            [
                "currency_count" => 1,
                "cash_flow_activity_count" => 1,
                "expected_modifier_actions" => [
                    RECORD_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION,
                    CLOSE_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION
                ],
                "account_combinations" => [
                    [ 0, EQUITY_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ],
                    [ 0, GENERAL_EXPENSE_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ]
                ],
                "modifier_atom_combinations" => [
                    [ 0, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 0, 0, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 1, 2, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 1, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 2, 0, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 2, 2, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 3, 3, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 3, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ]
                ],
                "modifier_atom_activity_combinations" => [
                    [ 1, 0 ],
                    [ 2, 0 ]
                ]
            ]
        );
        [ $equity_account, $asset_a_account, $expense_account, $asset_b_account ] = $accounts;
        $frozen_account_hashes = Resource::key(
            array_filter(
                $frozen_accounts,
                fn ($info) => $info->frozen_period_id !== $frozen_periods[0]->id
            ),
            fn ($info) => $info->account_id
        );
        $cash_flow_activity = $cash_flow_activities[0];
        $currency = $currencies[0];
        $details = [
            "started_at" => $frozen_periods[1]->started_at->toDateTimeString(),
            "finished_at" => Time::now()->toDateTimeString()
        ];

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/frozen_periods/dry_run", [
                "frozen_period" => $details
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "frozen_period" => $details
        ]);
        $this->seeNumRecords(1, "frozen_periods", []);
        $this->seeNumRecords(4, "real_unadjusted_summary_calculations", []);
        $this->seeNumRecords(3, "real_adjusted_summary_calculations", []);
        $this->seeNumRecords(2, "real_flow_calculations", []);
    }

    public function testValidIncompleteExchangeChainOpenCheck()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        [
            $precision_formats,
            $cash_flow_activities,
            $currencies,
            $accounts,
            $frozen_periods,
            $frozen_accounts,
            $real_adjusted_summaries,
            $real_unadjusted_summaries,
            $real_flows
        ] = FrozenPeriodModel::createTestPeriods(
            $authenticated_info->getUser(),
            [
                [
                    "started_at" => Time::now()->subDays(3),
                    "finished_at" => Time::now()->subDays(2),
                    "entries" => [
                        [
                            "modifier_index" => 0,
                            "atoms" => [
                                [ 0, "1000" ],
                                [ 1, "1000" ]
                            ]
                        ],
                        [
                            "modifier_index" => 1,
                            "atoms" => [
                                [ 2, "250" ],
                                [ 3, "250" ]
                            ]
                        ],
                        [
                            "modifier_index" => 3,
                            "atoms" => [
                                [ 6, "4" ],
                                [ 7, "200" ]
                            ]
                        ],
                        [
                            "modifier_index" => 2,
                            "atoms" => [
                                [ 4, "250" ],
                                [ 5, "250" ]
                            ]
                        ]
                    ]
                ],
                [
                    "started_at" => Time::now()->subDays(1),
                    "entries" => [
                        [
                            "modifier_index" => 0,
                            "atoms" => [
                                [ 0, "1500" ],
                                [ 1, "1500" ]
                            ]
                        ],
                        [
                            "modifier_index" => 1,
                            "atoms" => [
                                [ 2, "125" ],
                                [ 3, "125" ]
                            ]
                        ]
                    ]
                ]
            ],
            [
                "currency_count" => 2,
                "cash_flow_activity_count" => 1,
                "expected_modifier_actions" => [
                    RECORD_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION,
                    CLOSE_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION
                ],
                "account_combinations" => [
                    [ 0, EQUITY_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ],
                    [ 0, GENERAL_EXPENSE_ACCOUNT_KIND ],
                    [ 1, LIQUID_ASSET_ACCOUNT_KIND ]
                ],
                "modifier_atom_combinations" => [
                    [ 0, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 0, 0, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 1, 2, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 1, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 2, 0, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 2, 2, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 3, 3, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 3, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ]
                ],
                "modifier_atom_activity_combinations" => [
                    [ 1, 0 ],
                    [ 2, 0 ]
                ]
            ]
        );
        [ $equity_account, $asset_a_account, $expense_account, $asset_b_account ] = $accounts;
        $uncommitted_frozen_accounts = array_values(array_filter(
            $frozen_accounts,
            fn ($info) => $info->frozen_period_id !== $frozen_periods[0]->id
        ));
        $frozen_account_hashes = Resource::key(
            $uncommitted_frozen_accounts,
            fn ($info) => $info->account_id
        );
        $cash_flow_activity = $cash_flow_activities[0];
        [ $currency, $other_currency ] = $currencies;
        $details = [
            "started_at" => $frozen_periods[1]->started_at->toDateTimeString(),
            "finished_at" => Time::now()->toDateTimeString()
        ];

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/frozen_periods/dry_run", [
                "frozen_period" => $details
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "@meta" => [
                "statements" => [
                    [
                        "currency_id" => $currency->id,
                        "unadjusted_trial_balance" => [
                            "debit_total" => "2050",
                            "credit_total" => "2250"
                        ],
                        "income_statement" => [
                            "net_total" => "-125"
                        ],
                        "balance_sheet" => [
                            "total_assets" => "1925",
                            "total_liabilities" => "0",
                            "total_equities" => "2125"
                        ],
                        "cash_flow_statement" => [
                            "opened_real_liquid_amount" => "550",
                            "closed_real_liquid_amount" => "1925",
                            "real_liquid_amount_difference" => "1375",
                            "subtotals" => [
                                [
                                    "cash_flow_activity_id" => $cash_flow_activity->id,
                                    "net_income" => "-125",
                                    "subtotal" => "1375"
                                ]
                            ]
                        ],
                        "adjusted_trial_balance" => [
                            "debit_total" => "1925",
                            "credit_total" => "2250"
                        ]
                    ],
                    [
                        "currency_id" => $other_currency->id,
                        "unadjusted_trial_balance" => [
                            "debit_total" => "4",
                            "credit_total" => "0"
                        ],
                        "income_statement" => [
                            "net_total" => "0"
                        ],
                        "balance_sheet" => [
                            "total_assets" => "4",
                            "total_liabilities" => "0",
                            "total_equities" => "0"
                        ],
                        "cash_flow_statement" => [
                            "opened_real_liquid_amount" => "4",
                            "closed_real_liquid_amount" => "4",
                            "real_liquid_amount_difference" => "0",
                            "subtotals" => []
                        ],
                        "adjusted_trial_balance" => [
                            "debit_total" => "4",
                            "credit_total" => "0"
                        ]
                    ]
                ]
            ],
            "frozen_period" => $details,
            "frozen_accounts" => json_decode(json_encode($uncommitted_frozen_accounts), true),
            "real_unadjusted_summary_calculations" => [
                [
                    "frozen_account_hash" => $frozen_account_hashes[$equity_account->id]->hash,
                    "debit_amount" => "0",
                    "credit_amount" => "2250"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[$asset_a_account->id]->hash,
                    "debit_amount" => "2050",
                    "credit_amount" => "125"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[$expense_account->id]->hash,
                    "debit_amount" => "125",
                    "credit_amount" => "0"
                ]
            ],
            "real_adjusted_summary_calculations" => [
                [
                    "frozen_account_hash" => $frozen_account_hashes[$equity_account->id]->hash,
                    "opened_amount" => "750",
                    "closed_amount" => "2250"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[$asset_a_account->id]->hash,
                    "opened_amount" => "550",
                    "closed_amount" => "1925"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[$expense_account->id]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "125"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[$asset_b_account->id]->hash,
                    "opened_amount" => "4",
                    "closed_amount" => "4"
                ]
            ],
            "real_flow_calculations" => [
                [
                    "cash_flow_activity_id" => $cash_flow_activity->id,
                    "frozen_account_hash" => $frozen_account_hashes[$equity_account->id]->hash,
                    "net_amount" => "1500"
                ],
                [
                    "cash_flow_activity_id" => $cash_flow_activity->id,
                    "frozen_account_hash" => $frozen_account_hashes[$expense_account->id]->hash,
                    "net_amount" => "-125"
                ]
            ],
            "accounts" => json_decode(json_encode($accounts), true),
            "currencies" => json_decode(json_encode($currencies), true),
            "precision_formats" => json_decode(json_encode($precision_formats), true)
        ]);
        $this->seeNumRecords(1, "frozen_periods", []);
        $this->seeNumRecords(4, "real_unadjusted_summary_calculations", []);
        $this->seeNumRecords(3, "real_adjusted_summary_calculations", []);
        $this->seeNumRecords(2, "real_flow_calculations", []);
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
