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
            ->get("/api/v2/frozen_periods/$details->id", [
                "relationship" => [
                    "accounts",
                    "cash_flow_activities",
                    "item_details",
                    "item_configurations",
                    "currencies",
                    "frozen_accounts",
                    "frozen_period",
                    "precision_formats",
                    "real_adjusted_summary_calculations",
                    "real_unadjusted_summary_calculations",
                    "real_flow_calculations"
                ]
            ]);

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
            "real_flow_calculations" => json_decode(json_encode($real_flows)),
            "item_calculations" => [],
            "item_configurations" => [],
            "item_details" => []
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
        $this->seeNumRecords(3, "frozen_accounts", []);
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
            "precision_formats" => json_decode(json_encode($precision_formats), true),
            "cash_flow_activities" => json_decode(json_encode($cash_flow_activities), true)
        ]);
        $this->seeNumRecords(1, "frozen_periods", []);
        $this->seeNumRecords(3, "real_unadjusted_summary_calculations", []);
        $this->seeNumRecords(2, "real_adjusted_summary_calculations", []);
        $this->seeNumRecords(2, "real_flow_calculations", []);
    }

    public function testValidClosedCheckWithPermanentAccountAsFinalClose()
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
                ],
                "account_combinations" => [
                    [ 0, EQUITY_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ],
                    [ 0, GENERAL_EXPENSE_ACCOUNT_KIND ],
                    [ 0, EQUITY_ACCOUNT_KIND ],
                ],
                "modifier_atom_combinations" => [
                    [ 0, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 0, 0, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 1, 2, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 1, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 2, 3, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 2, 2, REAL_CREDIT_MODIFIER_ATOM_KIND ]
                ],
                "modifier_atom_activity_combinations" => [
                    [ 1, 0 ],
                    [ 2, 0 ]
                ]
            ]
        );
        [ $equity_account, $asset_account, $expense_account, $second_equity_account ] = $accounts;
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
                            "debit_total" => "1500",
                            "credit_total" => "1500"
                        ],
                        "income_statement" => [
                            "net_total" => "-125"
                        ],
                        "balance_sheet" => [
                            "total_assets" => "1375",
                            "total_liabilities" => "0",
                            "total_equities" => "1375"
                        ],
                        "cash_flow_statement" => [
                            "opened_real_liquid_amount" => "0",
                            "closed_real_liquid_amount" => "1375",
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
                            "debit_total" => "1375",
                            "credit_total" => "1375"
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
                    "credit_amount" => "1500"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[$asset_account->id]->hash,
                    "debit_amount" => "1500",
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
                    "opened_amount" => "0",
                    "closed_amount" => "1500"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[$asset_account->id]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "1375"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $second_equity_account->id
                    ]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "-125"
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
            "precision_formats" => json_decode(json_encode($precision_formats), true),
            "cash_flow_activities" => json_decode(json_encode($cash_flow_activities), true)
        ]);
        $this->seeNumRecords(0, "frozen_periods", []);
        $this->seeNumRecords(0, "real_unadjusted_summary_calculations", []);
        $this->seeNumRecords(0, "real_adjusted_summary_calculations", []);
        $this->seeNumRecords(0, "real_flow_calculations", []);
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
                    EXCHANGE_MODIFIER_ACTION
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
                ],
                "exchange_rates" => [
                    [
                        "source" => [
                            "currency_id" => $currency->id,
                            "value" => "50"
                        ],
                        "destination" => [
                            "currency_id" => $other_currency->id,
                            "value" => "1"
                        ]
                    ],
                    [
                        "source" => [
                            "currency_id" => $other_currency->id,
                            "value" => "1"
                        ],
                        "destination" => [
                            "currency_id" => $currency->id,
                            "value" => "50"
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

    public function testValidCompleteOpenCheckWithUnchanged()
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
                            "modifier_index" => 4,
                            "atoms" => [
                                [ 8, "300" ],
                                [ 9, "300" ]
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
                "cash_flow_activity_count" => 2,
                "expected_modifier_actions" => [
                    RECORD_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION,
                    CLOSE_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION
                ],
                "account_combinations" => [
                    [ 0, EQUITY_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ],
                    [ 0, GENERAL_EXPENSE_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ],
                    [ 0, GENERAL_ASSET_ACCOUNT_KIND ]
                ],
                "modifier_atom_combinations" => [
                    [ 0, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 0, 0, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 1, 2, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 1, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 2, 0, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 2, 2, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 3, 3, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 3, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 4, 4, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 4, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ]
                ],
                "modifier_atom_activity_combinations" => [
                    [ 1, 0 ],
                    [ 2, 0 ],
                    [ 8, 1 ]
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
        $first_cash_flow_activity = $cash_flow_activities[0];
        $second_cash_flow_activity = $cash_flow_activities[1];
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
            "frozen_period" => $details,
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
                            "closed_real_liquid_amount" => "450",
                            "real_liquid_amount_difference" => "450",
                            "subtotals" => [
                                [
                                    "cash_flow_activity_id" => $first_cash_flow_activity->id,
                                    "net_income" => "-250",
                                    "subtotal" => "750"
                                ],
                                [
                                    "cash_flow_activity_id" => $second_cash_flow_activity->id,
                                    "net_income" => "0",
                                    "subtotal" => "-300"
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
            ]
        ]);
        $this->seeNumRecords(0, "frozen_periods", []);
        $this->seeNumRecords(0, "real_unadjusted_summary_calculations", []);
        $this->seeNumRecords(0, "real_adjusted_summary_calculations", []);
        $this->seeNumRecords(0, "real_flow_calculations", []);
    }

    public function testValidCompleteOpenCheckWithUnchangedAndPaidLiability()
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
                    "entries" => [
                        [
                            "modifier_index" => 0,
                            "atoms" => [
                                [ 0, "1000" ],
                                [ 1, "1000" ]
                            ]
                        ],
                        [
                            "modifier_index" => 5,
                            "atoms" => [
                                [ 10, "100" ],
                                [ 11, "100" ]
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
                            "modifier_index" => 4,
                            "atoms" => [
                                [ 8, "300" ],
                                [ 9, "300" ]
                            ]
                        ],
                        [
                            "modifier_index" => 6,
                            "atoms" => [
                                [ 12, "50" ],
                                [ 13, "50" ]
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
                "cash_flow_activity_count" => 3,
                "expected_modifier_actions" => [
                    RECORD_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION,
                    CLOSE_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION
                ],
                "account_combinations" => [
                    [ 0, EQUITY_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ],
                    [ 0, GENERAL_EXPENSE_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ],
                    [ 0, GENERAL_ASSET_ACCOUNT_KIND ],
                    [ 0, LIABILITY_ACCOUNT_KIND ]
                ],
                "modifier_atom_combinations" => [
                    [ 0, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 0, 0, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 1, 2, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 1, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 2, 0, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 2, 2, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 3, 3, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 3, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 4, 4, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 4, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 5, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 5, 5, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 6, 5, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 6, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ]
                ],
                "modifier_atom_activity_combinations" => [
                    [ 1, 0 ],
                    [ 2, 0 ],
                    [ 8, 1 ],
                    [ 11, 2 ],
                    [ 12, 2 ]
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
        $first_cash_flow_activity = $cash_flow_activities[0];
        $second_cash_flow_activity = $cash_flow_activities[1];
        $third_cash_flow_activity = $cash_flow_activities[2];
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
            "frozen_period" => $details,
            "@meta" => [
                "statements" => [
                    [
                        "currency_id" => $currency->id,
                        "unadjusted_trial_balance" => [
                            "debit_total" => "1050",
                            "credit_total" => "1050"
                        ],
                        "income_statement" => [
                            "net_total" => "-250"
                        ],
                        "balance_sheet" => [
                            "total_assets" => "800",
                            "total_liabilities" => "50",
                            "total_equities" => "750"
                        ],
                        "cash_flow_statement" => [
                            "opened_real_liquid_amount" => "0",
                            "closed_real_liquid_amount" => "500",
                            "real_liquid_amount_difference" => "500",
                            "subtotals" => [
                                [
                                    "cash_flow_activity_id" => $first_cash_flow_activity->id,
                                    "net_income" => "-250",
                                    "subtotal" => "750"
                                ],
                                [
                                    "cash_flow_activity_id" => $second_cash_flow_activity->id,
                                    "net_income" => "0",
                                    "subtotal" => "-300"
                                ],
                                [
                                    "cash_flow_activity_id" => $third_cash_flow_activity->id,
                                    "net_income" => "0",
                                    "subtotal" => "50"
                                ]
                            ]
                        ],
                        "adjusted_trial_balance" => [
                            "debit_total" => "800",
                            "credit_total" => "800"
                        ]
                    ]
                ],
                "exchange_rates" => []
            ]
        ]);
        $this->seeNumRecords(0, "frozen_periods", []);
        $this->seeNumRecords(0, "real_unadjusted_summary_calculations", []);
        $this->seeNumRecords(0, "real_adjusted_summary_calculations", []);
        $this->seeNumRecords(0, "real_flow_calculations", []);
    }

    public function testValidCompleteOpenCheckWithUnchangedAndOverpaidLiability()
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
                    "entries" => [
                        [
                            "modifier_index" => 0,
                            "atoms" => [
                                [ 0, "1000" ],
                                [ 1, "1000" ]
                            ]
                        ],
                        [
                            "modifier_index" => 5,
                            "atoms" => [
                                [ 10, "100" ],
                                [ 11, "100" ]
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
                            "modifier_index" => 4,
                            "atoms" => [
                                [ 8, "300" ],
                                [ 9, "300" ]
                            ]
                        ],
                        [
                            "modifier_index" => 6,
                            "atoms" => [
                                [ 12, "120" ],
                                [ 13, "120" ]
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
                "cash_flow_activity_count" => 3,
                "expected_modifier_actions" => [
                    RECORD_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION,
                    CLOSE_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION
                ],
                "account_combinations" => [
                    [ 0, EQUITY_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ],
                    [ 0, GENERAL_EXPENSE_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ],
                    [ 0, GENERAL_ASSET_ACCOUNT_KIND ],
                    [ 0, LIABILITY_ACCOUNT_KIND ]
                ],
                "modifier_atom_combinations" => [
                    [ 0, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 0, 0, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 1, 2, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 1, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 2, 0, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 2, 2, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 3, 3, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 3, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 4, 4, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 4, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 5, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 5, 5, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 6, 5, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 6, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ]
                ],
                "modifier_atom_activity_combinations" => [
                    [ 1, 0 ],
                    [ 2, 0 ],
                    [ 8, 1 ],
                    [ 11, 2 ],
                    [ 12, 2 ]
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
        $first_cash_flow_activity = $cash_flow_activities[0];
        $second_cash_flow_activity = $cash_flow_activities[1];
        $third_cash_flow_activity = $cash_flow_activities[2];
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
            "frozen_period" => $details,
            "@meta" => [
                "statements" => [
                    [
                        "currency_id" => $currency->id,
                        "unadjusted_trial_balance" => [
                            "debit_total" => "980",
                            "credit_total" => "980"
                        ],
                        "income_statement" => [
                            "net_total" => "-250"
                        ],
                        "balance_sheet" => [
                            "total_assets" => "730",
                            "total_liabilities" => "-20",
                            "total_equities" => "750"
                        ],
                        "cash_flow_statement" => [
                            "opened_real_liquid_amount" => "0",
                            "closed_real_liquid_amount" => "430",
                            "real_liquid_amount_difference" => "430",
                            "subtotals" => [
                                [
                                    "cash_flow_activity_id" => $first_cash_flow_activity->id,
                                    "net_income" => "-250",
                                    "subtotal" => "750"
                                ],
                                [
                                    "cash_flow_activity_id" => $second_cash_flow_activity->id,
                                    "net_income" => "0",
                                    "subtotal" => "-300"
                                ],
                                [
                                    "cash_flow_activity_id" => $third_cash_flow_activity->id,
                                    "net_income" => "0",
                                    "subtotal" => "-20"
                                ]
                            ]
                        ],
                        "adjusted_trial_balance" => [
                            "debit_total" => "730",
                            "credit_total" => "730"
                        ]
                    ]
                ],
                "exchange_rates" => []
            ]
        ]);
        $this->seeNumRecords(0, "frozen_periods", []);
        $this->seeNumRecords(0, "real_unadjusted_summary_calculations", []);
        $this->seeNumRecords(0, "real_adjusted_summary_calculations", []);
        $this->seeNumRecords(0, "real_flow_calculations", []);
    }

    public function testValidIncompleteChainOpenCheckWithUnchanged()
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
                            "modifier_index" => 4,
                            "atoms" => [
                                [ 8, "300" ],
                                [ 9, "300" ]
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
                "cash_flow_activity_count" => 2,
                "expected_modifier_actions" => [
                    RECORD_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION,
                    CLOSE_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION
                ],
                "account_combinations" => [
                    [ 0, EQUITY_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ],
                    [ 0, GENERAL_EXPENSE_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ],
                    [ 0, GENERAL_ASSET_ACCOUNT_KIND ]
                ],
                "modifier_atom_combinations" => [
                    [ 0, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 0, 0, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 1, 2, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 1, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 2, 0, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 2, 2, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 3, 3, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 3, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 4, 4, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 4, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ]
                ],
                "modifier_atom_activity_combinations" => [
                    [ 1, 0 ],
                    [ 2, 0 ],
                    [ 8, 1 ]
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
            "frozen_period" => $details,
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
                            "opened_real_liquid_amount" => "450",
                            "closed_real_liquid_amount" => "1825",
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
                            "credit_total" => "2125"
                        ]
                    ]
                ],
                "exchange_rates" => []
            ]
        ]);
        $this->seeNumRecords(1, "frozen_periods", []);
        $this->seeNumRecords(5, "real_unadjusted_summary_calculations", []);
        $this->seeNumRecords(4, "real_adjusted_summary_calculations", []);
        $this->seeNumRecords(3, "real_flow_calculations", []);
    }

    public function testValidFirstPartialCheckWithPQBid()
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
                                [ 2, PRICE_FINANCIAL_ENTRY_ATOM_KIND, "3" ],
                                [ 2, QUANTITY_FINANCIAL_ENTRY_ATOM_KIND, "5" ],
                                [ 3, "15" ]
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
                    BID_MODIFIER_ACTION
                ],
                "account_combinations" => [
                    [ 0, EQUITY_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ],
                    [ 0, ITEMIZED_ASSET_ACCOUNT_KIND ]
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
        [
            $equity_account,
            $liquid_asset_account,
            $itemized_asset_account
        ] = $accounts;
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
                            "debit_total" => "1500",
                            "credit_total" => "1500"
                        ],
                        "income_statement" => [
                            "net_total" => "0"
                        ],
                        "balance_sheet" => [
                            "total_assets" => "1500",
                            "total_liabilities" => "0",
                            "total_equities" => "1500"
                        ],
                        "cash_flow_statement" => [
                            "opened_real_liquid_amount" => "0",
                            "closed_real_liquid_amount" => "1485",
                            "real_liquid_amount_difference" => "1485",
                            "subtotals" => [
                                [
                                    "cash_flow_activity_id" => $cash_flow_activity->id,
                                    "subtotal" => "1485"
                                ]
                            ]
                        ],
                        "adjusted_trial_balance" => [
                            "debit_total" => "1500",
                            "credit_total" => "1500"
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
                    "credit_amount" => "1500"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $liquid_asset_account->id
                    ]->hash,
                    "debit_amount" => "1500",
                    "credit_amount" => "15"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "debit_amount" => "15",
                    "credit_amount" => "0"
                ]
            ],
            "real_adjusted_summary_calculations" => [
                [
                    "frozen_account_hash" => $frozen_account_hashes[$equity_account->id]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "1500"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $liquid_asset_account->id
                    ]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "1485"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "15"
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
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "net_amount" => "-15"
                ]
            ],
            "item_calculations" => [
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "remaining_cost" => "15",
                    "remaining_quantity" => "5"
                ]
            ],
            "accounts" => json_decode(json_encode($accounts), true),
            "currencies" => json_decode(json_encode($currencies), true),
            "precision_formats" => json_decode(json_encode($precision_formats), true),
            "cash_flow_activities" => json_decode(json_encode($cash_flow_activities), true)
        ]);
        $this->seeNumRecords(0, "frozen_periods", []);
        $this->seeNumRecords(0, "real_unadjusted_summary_calculations", []);
        $this->seeNumRecords(0, "real_adjusted_summary_calculations", []);
        $this->seeNumRecords(0, "real_flow_calculations", []);
    }

    public function testValidFirstPartialCheckWithPTBid()
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
                                [ 2, PRICE_FINANCIAL_ENTRY_ATOM_KIND, "3" ],
                                [ 2, TOTAL_FINANCIAL_ENTRY_ATOM_KIND, "15" ],
                                [ 3, "15" ]
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
                    BID_MODIFIER_ACTION
                ],
                "account_combinations" => [
                    [ 0, EQUITY_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ],
                    [ 0, ITEMIZED_ASSET_ACCOUNT_KIND ]
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
        [
            $equity_account,
            $liquid_asset_account,
            $itemized_asset_account
        ] = $accounts;
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
                            "debit_total" => "1500",
                            "credit_total" => "1500"
                        ],
                        "income_statement" => [
                            "net_total" => "0"
                        ],
                        "balance_sheet" => [
                            "total_assets" => "1500",
                            "total_liabilities" => "0",
                            "total_equities" => "1500"
                        ],
                        "cash_flow_statement" => [
                            "opened_real_liquid_amount" => "0",
                            "closed_real_liquid_amount" => "1485",
                            "real_liquid_amount_difference" => "1485",
                            "subtotals" => [
                                [
                                    "cash_flow_activity_id" => $cash_flow_activity->id,
                                    "subtotal" => "1485"
                                ]
                            ]
                        ],
                        "adjusted_trial_balance" => [
                            "debit_total" => "1500",
                            "credit_total" => "1500"
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
                    "credit_amount" => "1500"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $liquid_asset_account->id
                    ]->hash,
                    "debit_amount" => "1500",
                    "credit_amount" => "15"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "debit_amount" => "15",
                    "credit_amount" => "0"
                ]
            ],
            "real_adjusted_summary_calculations" => [
                [
                    "frozen_account_hash" => $frozen_account_hashes[$equity_account->id]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "1500"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $liquid_asset_account->id
                    ]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "1485"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "15"
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
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "net_amount" => "-15"
                ]
            ],
            "item_calculations" => [
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "remaining_cost" => "15",
                    "remaining_quantity" => "5"
                ]
            ],
            "accounts" => json_decode(json_encode($accounts), true),
            "currencies" => json_decode(json_encode($currencies), true),
            "precision_formats" => json_decode(json_encode($precision_formats), true),
            "cash_flow_activities" => json_decode(json_encode($cash_flow_activities), true)
        ]);
        $this->seeNumRecords(0, "frozen_periods", []);
        $this->seeNumRecords(0, "real_unadjusted_summary_calculations", []);
        $this->seeNumRecords(0, "real_adjusted_summary_calculations", []);
        $this->seeNumRecords(0, "real_flow_calculations", []);
    }

    public function testValidFirstPartialCheckWithQTBid()
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
                                [ 2, QUANTITY_FINANCIAL_ENTRY_ATOM_KIND, "5" ],
                                [ 2, TOTAL_FINANCIAL_ENTRY_ATOM_KIND, "15" ],
                                [ 3, "15" ]
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
                    BID_MODIFIER_ACTION
                ],
                "account_combinations" => [
                    [ 0, EQUITY_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ],
                    [ 0, ITEMIZED_ASSET_ACCOUNT_KIND ]
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
        [
            $equity_account,
            $liquid_asset_account,
            $itemized_asset_account
        ] = $accounts;
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
                            "debit_total" => "1500",
                            "credit_total" => "1500"
                        ],
                        "income_statement" => [
                            "net_total" => "0"
                        ],
                        "balance_sheet" => [
                            "total_assets" => "1500",
                            "total_liabilities" => "0",
                            "total_equities" => "1500"
                        ],
                        "cash_flow_statement" => [
                            "opened_real_liquid_amount" => "0",
                            "closed_real_liquid_amount" => "1485",
                            "real_liquid_amount_difference" => "1485",
                            "subtotals" => [
                                [
                                    "cash_flow_activity_id" => $cash_flow_activity->id,
                                    "subtotal" => "1485"
                                ]
                            ]
                        ],
                        "adjusted_trial_balance" => [
                            "debit_total" => "1500",
                            "credit_total" => "1500"
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
                    "credit_amount" => "1500"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $liquid_asset_account->id
                    ]->hash,
                    "debit_amount" => "1500",
                    "credit_amount" => "15"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "debit_amount" => "15",
                    "credit_amount" => "0"
                ]
            ],
            "real_adjusted_summary_calculations" => [
                [
                    "frozen_account_hash" => $frozen_account_hashes[$equity_account->id]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "1500"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $liquid_asset_account->id
                    ]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "1485"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "15"
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
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "net_amount" => "-15"
                ]
            ],
            "item_calculations" => [
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "remaining_cost" => "15",
                    "remaining_quantity" => "5"
                ]
            ],
            "accounts" => json_decode(json_encode($accounts), true),
            "currencies" => json_decode(json_encode($currencies), true),
            "precision_formats" => json_decode(json_encode($precision_formats), true),
            "cash_flow_activities" => json_decode(json_encode($cash_flow_activities), true)
        ]);
        $this->seeNumRecords(0, "frozen_periods", []);
        $this->seeNumRecords(0, "real_unadjusted_summary_calculations", []);
        $this->seeNumRecords(0, "real_adjusted_summary_calculations", []);
        $this->seeNumRecords(0, "real_flow_calculations", []);
    }

    public function testValidFirstPartialCheckWithQTBidAndPartialQTAsk()
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
                                [ 2, QUANTITY_FINANCIAL_ENTRY_ATOM_KIND, "10" ],
                                [ 2, TOTAL_FINANCIAL_ENTRY_ATOM_KIND, "30" ],
                                [ 3, "30" ]
                            ]
                        ],
                        [
                            "modifier_index" => 2,
                            "atoms" => [
                                [ 4, "50" ],
                                [ 5, QUANTITY_FINANCIAL_ENTRY_ATOM_KIND, "5" ],
                                [ 5, TOTAL_FINANCIAL_ENTRY_ATOM_KIND, "50" ],
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
                    BID_MODIFIER_ACTION,
                    ASK_MODIFIER_ACTION
                ],
                "account_combinations" => [
                    [ 0, EQUITY_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ],
                    [ 0, ITEMIZED_ASSET_ACCOUNT_KIND, WEIGHTED_AVERAGE_VALUATION_METHOD ],
                    [ 0, NOMINAL_RETURN_ACCOUNT_KIND ]
                ],
                "modifier_atom_combinations" => [
                    [ 0, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 0, 0, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 1, 2, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 1, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 2, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 2, 2, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 2, 3, REAL_EMERGENT_MODIFIER_ATOM_KIND ]
                ],
                "modifier_atom_activity_combinations" => [
                    [ 1, 0 ],
                    [ 2, 0 ],
                    [ 5, 0 ],
                    [ 6, 0 ]
                ]
            ]
        );
        [
            $equity_account,
            $liquid_asset_account,
            $itemized_asset_account,
            $nominal_return_account
        ] = $accounts;
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
                            "debit_total" => "1535",
                            "credit_total" => "1535"
                        ],
                        "income_statement" => [
                            "net_total" => "35"
                        ],
                        "balance_sheet" => [
                            "total_assets" => "1535",
                            "total_liabilities" => "0",
                            "total_equities" => "1535"
                        ],
                        "cash_flow_statement" => [
                            "opened_real_liquid_amount" => "0",
                            "closed_real_liquid_amount" => "1520",
                            "real_liquid_amount_difference" => "1520",
                            "subtotals" => [
                                [
                                    "cash_flow_activity_id" => $cash_flow_activity->id,
                                    "subtotal" => "1520"
                                ]
                            ]
                        ],
                        "adjusted_trial_balance" => [
                            "debit_total" => "1535",
                            "credit_total" => "1500"
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
                    "credit_amount" => "1500"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $liquid_asset_account->id
                    ]->hash,
                    "debit_amount" => "1550",
                    "credit_amount" => "30"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "debit_amount" => "30",
                    "credit_amount" => "15"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $nominal_return_account->id
                    ]->hash,
                    "debit_amount" => "0",
                    "credit_amount" => "35"
                ]
            ],
            "real_adjusted_summary_calculations" => [
                [
                    "frozen_account_hash" => $frozen_account_hashes[$equity_account->id]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "1500"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $liquid_asset_account->id
                    ]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "1520"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "15"
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
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "net_amount" => "-15"
                ],
                [
                    "cash_flow_activity_id" => $cash_flow_activity->id,
                    "frozen_account_hash" => $frozen_account_hashes[
                        $nominal_return_account->id
                    ]->hash,
                    "net_amount" => "35"
                ]
            ],
            "item_calculations" => [
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "remaining_cost" => "15",
                    "remaining_quantity" => "5"
                ]
            ],
            "accounts" => json_decode(json_encode($accounts), true),
            "currencies" => json_decode(json_encode($currencies), true),
            "precision_formats" => json_decode(json_encode($precision_formats), true),
            "cash_flow_activities" => json_decode(json_encode($cash_flow_activities), true)
        ]);
        $this->seeNumRecords(0, "frozen_periods", []);
        $this->seeNumRecords(0, "real_unadjusted_summary_calculations", []);
        $this->seeNumRecords(0, "real_adjusted_summary_calculations", []);
        $this->seeNumRecords(0, "real_flow_calculations", []);
    }

    public function testValidFirstPartialCheckWithQTBidAndPartialQTAskAndDilute()
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
                                [ 2, QUANTITY_FINANCIAL_ENTRY_ATOM_KIND, "10" ],
                                [ 2, TOTAL_FINANCIAL_ENTRY_ATOM_KIND, "30" ],
                                [ 3, "30" ]
                            ]
                        ],
                        [
                            "modifier_index" => 3,
                            "atoms" => [
                                [ 7, QUANTITY_FINANCIAL_ENTRY_ATOM_KIND, "40" ]
                            ]
                        ],
                        [
                            "modifier_index" => 2,
                            "atoms" => [
                                [ 4, "50" ],
                                [ 5, QUANTITY_FINANCIAL_ENTRY_ATOM_KIND, "5" ],
                                [ 5, TOTAL_FINANCIAL_ENTRY_ATOM_KIND, "50" ],
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
                    BID_MODIFIER_ACTION,
                    ASK_MODIFIER_ACTION,
                    DILUTE_MODIFIER_ACTION
                ],
                "account_combinations" => [
                    [ 0, EQUITY_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ],
                    [ 0, ITEMIZED_ASSET_ACCOUNT_KIND, WEIGHTED_AVERAGE_VALUATION_METHOD ],
                    [ 0, NOMINAL_RETURN_ACCOUNT_KIND ]
                ],
                "modifier_atom_combinations" => [
                    [ 0, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 0, 0, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 1, 2, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 1, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 2, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 2, 2, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 2, 3, REAL_EMERGENT_MODIFIER_ATOM_KIND ],
                    [ 3, 2, REAL_DEBITEM_MODIFIER_ATOM_KIND ]
                ],
                "modifier_atom_activity_combinations" => [
                    [ 1, 0 ],
                    [ 2, 0 ],
                    [ 5, 0 ],
                    [ 6, 0 ]
                ]
            ]
        );
        [
            $equity_account,
            $liquid_asset_account,
            $itemized_asset_account,
            $nominal_return_account
        ] = $accounts;
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
                            "debit_total" => "1547",
                            "credit_total" => "1547"
                        ],
                        "income_statement" => [
                            "net_total" => "47"
                        ],
                        "balance_sheet" => [
                            "total_assets" => "1547",
                            "total_liabilities" => "0",
                            "total_equities" => "1547"
                        ],
                        "cash_flow_statement" => [
                            "opened_real_liquid_amount" => "0",
                            "closed_real_liquid_amount" => "1520",
                            "real_liquid_amount_difference" => "1520",
                            "subtotals" => [
                                [
                                    "cash_flow_activity_id" => $cash_flow_activity->id,
                                    "subtotal" => "1520"
                                ]
                            ]
                        ],
                        "adjusted_trial_balance" => [
                            "debit_total" => "1547",
                            "credit_total" => "1500"
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
                    "credit_amount" => "1500"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $liquid_asset_account->id
                    ]->hash,
                    "debit_amount" => "1550",
                    "credit_amount" => "30"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "debit_amount" => "30",
                    "credit_amount" => "3"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $nominal_return_account->id
                    ]->hash,
                    "debit_amount" => "0",
                    "credit_amount" => "47"
                ]
            ],
            "real_adjusted_summary_calculations" => [
                [
                    "frozen_account_hash" => $frozen_account_hashes[$equity_account->id]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "1500"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $liquid_asset_account->id
                    ]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "1520"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "27"
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
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "net_amount" => "-27"
                ],
                [
                    "cash_flow_activity_id" => $cash_flow_activity->id,
                    "frozen_account_hash" => $frozen_account_hashes[
                        $nominal_return_account->id
                    ]->hash,
                    "net_amount" => "47"
                ]
            ],
            "item_calculations" => [
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "remaining_cost" => "27",
                    "remaining_quantity" => "9"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "remaining_cost" => "0",
                    "remaining_quantity" => "36"
                ]
            ],
            "accounts" => json_decode(json_encode($accounts), true),
            "currencies" => json_decode(json_encode($currencies), true),
            "precision_formats" => json_decode(json_encode($precision_formats), true),
            "cash_flow_activities" => json_decode(json_encode($cash_flow_activities), true)
        ]);
        $this->seeNumRecords(0, "frozen_periods", []);
        $this->seeNumRecords(0, "real_unadjusted_summary_calculations", []);
        $this->seeNumRecords(0, "real_adjusted_summary_calculations", []);
        $this->seeNumRecords(0, "real_flow_calculations", []);
    }

    public function testValidFirstPartialCheckWithQTBidAndPartialQTAskAndCondense()
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
                                [ 2, QUANTITY_FINANCIAL_ENTRY_ATOM_KIND, "40" ],
                                [ 2, TOTAL_FINANCIAL_ENTRY_ATOM_KIND, "30" ],
                                [ 3, "30" ]
                            ]
                        ],
                        [
                            "modifier_index" => 3,
                            "atoms" => [
                                [ 7, QUANTITY_FINANCIAL_ENTRY_ATOM_KIND, "30" ]
                            ]
                        ],
                        [
                            "modifier_index" => 2,
                            "atoms" => [
                                [ 4, "50" ],
                                [ 5, QUANTITY_FINANCIAL_ENTRY_ATOM_KIND, "5" ],
                                [ 5, TOTAL_FINANCIAL_ENTRY_ATOM_KIND, "50" ],
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
                    BID_MODIFIER_ACTION,
                    ASK_MODIFIER_ACTION,
                    CONDENSE_MODIFIER_ACTION
                ],
                "account_combinations" => [
                    [ 0, EQUITY_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ],
                    [ 0, ITEMIZED_ASSET_ACCOUNT_KIND, WEIGHTED_AVERAGE_VALUATION_METHOD ],
                    [ 0, NOMINAL_RETURN_ACCOUNT_KIND ]
                ],
                "modifier_atom_combinations" => [
                    [ 0, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 0, 0, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 1, 2, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 1, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 2, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 2, 2, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 2, 3, REAL_EMERGENT_MODIFIER_ATOM_KIND ],
                    [ 3, 2, REAL_CREDITEM_MODIFIER_ATOM_KIND ]
                ],
                "modifier_atom_activity_combinations" => [
                    [ 1, 0 ],
                    [ 2, 0 ],
                    [ 5, 0 ],
                    [ 6, 0 ]
                ]
            ]
        );
        [
            $equity_account,
            $liquid_asset_account,
            $itemized_asset_account,
            $nominal_return_account
        ] = $accounts;
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
                            "debit_total" => "1535",
                            "credit_total" => "1535"
                        ],
                        "income_statement" => [
                            "net_total" => "35"
                        ],
                        "balance_sheet" => [
                            "total_assets" => "1535",
                            "total_liabilities" => "0",
                            "total_equities" => "1535"
                        ],
                        "cash_flow_statement" => [
                            "opened_real_liquid_amount" => "0",
                            "closed_real_liquid_amount" => "1520",
                            "real_liquid_amount_difference" => "1520",
                            "subtotals" => [
                                [
                                    "cash_flow_activity_id" => $cash_flow_activity->id,
                                    "subtotal" => "1520"
                                ]
                            ]
                        ],
                        "adjusted_trial_balance" => [
                            "debit_total" => "1535",
                            "credit_total" => "1500"
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
                    "credit_amount" => "1500"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $liquid_asset_account->id
                    ]->hash,
                    "debit_amount" => "1550",
                    "credit_amount" => "30"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "debit_amount" => "30",
                    "credit_amount" => "15"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $nominal_return_account->id
                    ]->hash,
                    "debit_amount" => "0",
                    "credit_amount" => "35"
                ]
            ],
            "real_adjusted_summary_calculations" => [
                [
                    "frozen_account_hash" => $frozen_account_hashes[$equity_account->id]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "1500"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $liquid_asset_account->id
                    ]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "1520"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "15"
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
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "net_amount" => "-15"
                ],
                [
                    "cash_flow_activity_id" => $cash_flow_activity->id,
                    "frozen_account_hash" => $frozen_account_hashes[
                        $nominal_return_account->id
                    ]->hash,
                    "net_amount" => "35"
                ]
            ],
            "item_calculations" => [
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "remaining_cost" => "15",
                    "remaining_quantity" => "20"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "remaining_cost" => "0",
                    "remaining_quantity" => "-15"
                ]
            ],
            "accounts" => json_decode(json_encode($accounts), true),
            "currencies" => json_decode(json_encode($currencies), true),
            "precision_formats" => json_decode(json_encode($precision_formats), true),
            "cash_flow_activities" => json_decode(json_encode($cash_flow_activities), true)
        ]);
        $this->seeNumRecords(0, "frozen_periods", []);
        $this->seeNumRecords(0, "real_unadjusted_summary_calculations", []);
        $this->seeNumRecords(0, "real_adjusted_summary_calculations", []);
        $this->seeNumRecords(0, "real_flow_calculations", []);
    }

    public function testValidFirstPartialCheckWithQTBidAndPartialQTAskAndDiluteAndCondense()
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
                                [ 2, QUANTITY_FINANCIAL_ENTRY_ATOM_KIND, "80" ],
                                [ 2, TOTAL_FINANCIAL_ENTRY_ATOM_KIND, "30" ],
                                [ 3, "30" ]
                            ]
                        ],
                        [
                            "modifier_index" => 3,
                            "atoms" => [
                                [ 7, QUANTITY_FINANCIAL_ENTRY_ATOM_KIND, "90" ]
                            ]
                        ],
                        [
                            "modifier_index" => 4,
                            "atoms" => [
                                [ 8, QUANTITY_FINANCIAL_ENTRY_ATOM_KIND, "120" ]
                            ]
                        ],
                        [
                            "modifier_index" => 2,
                            "atoms" => [
                                [ 4, "50" ],
                                [ 5, QUANTITY_FINANCIAL_ENTRY_ATOM_KIND, "5" ],
                                [ 5, TOTAL_FINANCIAL_ENTRY_ATOM_KIND, "50" ],
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
                    BID_MODIFIER_ACTION,
                    ASK_MODIFIER_ACTION,
                    DILUTE_MODIFIER_ACTION,
                    CONDENSE_MODIFIER_ACTION
                ],
                "account_combinations" => [
                    [ 0, EQUITY_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ],
                    [ 0, ITEMIZED_ASSET_ACCOUNT_KIND, WEIGHTED_AVERAGE_VALUATION_METHOD ],
                    [ 0, NOMINAL_RETURN_ACCOUNT_KIND ]
                ],
                "modifier_atom_combinations" => [
                    [ 0, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 0, 0, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 1, 2, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 1, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 2, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 2, 2, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 2, 3, REAL_EMERGENT_MODIFIER_ATOM_KIND ],
                    [ 3, 2, REAL_DEBITEM_MODIFIER_ATOM_KIND ],
                    [ 4, 2, REAL_CREDITEM_MODIFIER_ATOM_KIND ]
                ],
                "modifier_atom_activity_combinations" => [
                    [ 1, 0 ],
                    [ 2, 0 ],
                    [ 5, 0 ],
                    [ 6, 0 ]
                ]
            ]
        );
        [
            $equity_account,
            $liquid_asset_account,
            $itemized_asset_account,
            $nominal_return_account
        ] = $accounts;
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
                            "debit_total" => "1547",
                            "credit_total" => "1547"
                        ],
                        "income_statement" => [
                            "net_total" => "47"
                        ],
                        "balance_sheet" => [
                            "total_assets" => "1547",
                            "total_liabilities" => "0",
                            "total_equities" => "1547"
                        ],
                        "cash_flow_statement" => [
                            "opened_real_liquid_amount" => "0",
                            "closed_real_liquid_amount" => "1520",
                            "real_liquid_amount_difference" => "1520",
                            "subtotals" => [
                                [
                                    "cash_flow_activity_id" => $cash_flow_activity->id,
                                    "subtotal" => "1520"
                                ]
                            ]
                        ],
                        "adjusted_trial_balance" => [
                            "debit_total" => "1547",
                            "credit_total" => "1500"
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
                    "credit_amount" => "1500"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $liquid_asset_account->id
                    ]->hash,
                    "debit_amount" => "1550",
                    "credit_amount" => "30"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "debit_amount" => "30",
                    "credit_amount" => "3"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $nominal_return_account->id
                    ]->hash,
                    "debit_amount" => "0",
                    "credit_amount" => "47"
                ]
            ],
            "real_adjusted_summary_calculations" => [
                [
                    "frozen_account_hash" => $frozen_account_hashes[$equity_account->id]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "1500"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $liquid_asset_account->id
                    ]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "1520"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "27"
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
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "net_amount" => "-27"
                ],
                [
                    "cash_flow_activity_id" => $cash_flow_activity->id,
                    "frozen_account_hash" => $frozen_account_hashes[
                        $nominal_return_account->id
                    ]->hash,
                    "net_amount" => "47"
                ]
            ],
            "item_calculations" => [
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "remaining_cost" => "27",
                    "remaining_quantity" => "72"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "remaining_cost" => "0",
                    "remaining_quantity" => "81"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "remaining_cost" => "0",
                    "remaining_quantity" => "-108"
                ]
            ],
            "accounts" => json_decode(json_encode($accounts), true),
            "currencies" => json_decode(json_encode($currencies), true),
            "precision_formats" => json_decode(json_encode($precision_formats), true),
            "cash_flow_activities" => json_decode(json_encode($cash_flow_activities), true)
        ]);
        $this->seeNumRecords(0, "frozen_periods", []);
        $this->seeNumRecords(0, "real_unadjusted_summary_calculations", []);
        $this->seeNumRecords(0, "real_adjusted_summary_calculations", []);
        $this->seeNumRecords(0, "real_flow_calculations", []);
    }

    public function testValidFirstCompleteCheckWithQTBidAndPartialQTAsk()
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
                                [ 2, QUANTITY_FINANCIAL_ENTRY_ATOM_KIND, "10" ],
                                [ 2, TOTAL_FINANCIAL_ENTRY_ATOM_KIND, "30" ],
                                [ 3, "30" ]
                            ]
                        ],
                        [
                            "modifier_index" => 2,
                            "atoms" => [
                                [ 4, "50" ],
                                [ 5, QUANTITY_FINANCIAL_ENTRY_ATOM_KIND, "5" ],
                                [ 5, TOTAL_FINANCIAL_ENTRY_ATOM_KIND, "50" ],
                            ]
                        ],
                        [
                            "modifier_index" => 3,
                            "atoms" => [
                                [ 7, "35" ],
                                [ 8, "35" ]
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
                    BID_MODIFIER_ACTION,
                    ASK_MODIFIER_ACTION,
                    CLOSE_MODIFIER_ACTION
                ],
                "account_combinations" => [
                    [ 0, EQUITY_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ],
                    [ 0, ITEMIZED_ASSET_ACCOUNT_KIND, WEIGHTED_AVERAGE_VALUATION_METHOD ],
                    [ 0, NOMINAL_RETURN_ACCOUNT_KIND ]
                ],
                "modifier_atom_combinations" => [
                    [ 0, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 0, 0, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 1, 2, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 1, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 2, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 2, 2, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 2, 3, REAL_EMERGENT_MODIFIER_ATOM_KIND ],
                    [ 3, 3, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 3, 0, REAL_CREDIT_MODIFIER_ATOM_KIND ]
                ],
                "modifier_atom_activity_combinations" => [
                    [ 1, 0 ],
                    [ 2, 0 ],
                    [ 5, 0 ],
                    [ 6, 0 ]
                ]
            ]
        );
        [
            $equity_account,
            $liquid_asset_account,
            $itemized_asset_account,
            $nominal_return_account
        ] = $accounts;
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
                            "debit_total" => "1535",
                            "credit_total" => "1535"
                        ],
                        "income_statement" => [
                            "net_total" => "35"
                        ],
                        "balance_sheet" => [
                            "total_assets" => "1535",
                            "total_liabilities" => "0",
                            "total_equities" => "1535"
                        ],
                        "cash_flow_statement" => [
                            "opened_real_liquid_amount" => "0",
                            "closed_real_liquid_amount" => "1520",
                            "real_liquid_amount_difference" => "1520",
                            "subtotals" => [
                                [
                                    "cash_flow_activity_id" => $cash_flow_activity->id,
                                    "subtotal" => "1520"
                                ]
                            ]
                        ],
                        "adjusted_trial_balance" => [
                            "debit_total" => "1535",
                            "credit_total" => "1535"
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
                    "credit_amount" => "1500"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $liquid_asset_account->id
                    ]->hash,
                    "debit_amount" => "1550",
                    "credit_amount" => "30"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "debit_amount" => "30",
                    "credit_amount" => "15"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $nominal_return_account->id
                    ]->hash,
                    "debit_amount" => "0",
                    "credit_amount" => "35"
                ]
            ],
            "real_adjusted_summary_calculations" => [
                [
                    "frozen_account_hash" => $frozen_account_hashes[$equity_account->id]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "1535"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $liquid_asset_account->id
                    ]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "1520"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "15"
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
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "net_amount" => "-15"
                ],
                [
                    "cash_flow_activity_id" => $cash_flow_activity->id,
                    "frozen_account_hash" => $frozen_account_hashes[
                        $nominal_return_account->id
                    ]->hash,
                    "net_amount" => "35"
                ]
            ],
            "item_calculations" => [
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "remaining_cost" => "15",
                    "remaining_quantity" => "5"
                ]
            ],
            "accounts" => json_decode(json_encode($accounts), true),
            "currencies" => json_decode(json_encode($currencies), true),
            "precision_formats" => json_decode(json_encode($precision_formats), true),
            "cash_flow_activities" => json_decode(json_encode($cash_flow_activities), true)
        ]);
        $this->seeNumRecords(0, "frozen_periods", []);
        $this->seeNumRecords(0, "real_unadjusted_summary_calculations", []);
        $this->seeNumRecords(0, "real_adjusted_summary_calculations", []);
        $this->seeNumRecords(0, "real_flow_calculations", []);
    }

    public function testValidFirstCompleteCheckWithQTMultipleBidAndSinglePartialAsk()
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
                                [ 2, QUANTITY_FINANCIAL_ENTRY_ATOM_KIND, "10" ],
                                [ 2, TOTAL_FINANCIAL_ENTRY_ATOM_KIND, "30" ],
                                [ 3, "30" ]
                            ]
                        ],
                        [
                            "modifier_index" => 1,
                            "atoms" => [
                                [ 2, QUANTITY_FINANCIAL_ENTRY_ATOM_KIND, "40" ],
                                [ 2, TOTAL_FINANCIAL_ENTRY_ATOM_KIND, "40" ],
                                [ 3, "40" ]
                            ]
                        ],
                        [
                            "modifier_index" => 2,
                            "atoms" => [
                                [ 4, "50" ],
                                [ 5, QUANTITY_FINANCIAL_ENTRY_ATOM_KIND, "5" ],
                                [ 5, TOTAL_FINANCIAL_ENTRY_ATOM_KIND, "50" ],
                            ]
                        ],
                        [
                            "modifier_index" => 3,
                            "atoms" => [
                                [ 7, "43" ],
                                [ 8, "43" ]
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
                    BID_MODIFIER_ACTION,
                    ASK_MODIFIER_ACTION,
                    CLOSE_MODIFIER_ACTION
                ],
                "account_combinations" => [
                    [ 0, EQUITY_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ],
                    [ 0, ITEMIZED_ASSET_ACCOUNT_KIND, WEIGHTED_AVERAGE_VALUATION_METHOD ],
                    [ 0, NOMINAL_RETURN_ACCOUNT_KIND ]
                ],
                "modifier_atom_combinations" => [
                    [ 0, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 0, 0, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 1, 2, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 1, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 2, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 2, 2, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 2, 3, REAL_EMERGENT_MODIFIER_ATOM_KIND ],
                    [ 3, 3, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 3, 0, REAL_CREDIT_MODIFIER_ATOM_KIND ]
                ],
                "modifier_atom_activity_combinations" => [
                    [ 1, 0 ],
                    [ 2, 0 ],
                    [ 5, 0 ],
                    [ 6, 0 ]
                ]
            ]
        );
        [
            $equity_account,
            $liquid_asset_account,
            $itemized_asset_account,
            $nominal_return_account
        ] = $accounts;
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
                            "debit_total" => "1543",
                            "credit_total" => "1543"
                        ],
                        "income_statement" => [
                            "net_total" => "43"
                        ],
                        "balance_sheet" => [
                            "total_assets" => "1543",
                            "total_liabilities" => "0",
                            "total_equities" => "1543"
                        ],
                        "cash_flow_statement" => [
                            "opened_real_liquid_amount" => "0",
                            "closed_real_liquid_amount" => "1480",
                            "real_liquid_amount_difference" => "1480",
                            "subtotals" => [
                                [
                                    "cash_flow_activity_id" => $cash_flow_activity->id,
                                    "subtotal" => "1480"
                                ]
                            ]
                        ],
                        "adjusted_trial_balance" => [
                            "debit_total" => "1543",
                            "credit_total" => "1543"
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
                    "credit_amount" => "1500"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $liquid_asset_account->id
                    ]->hash,
                    "debit_amount" => "1550",
                    "credit_amount" => "70"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "debit_amount" => "70",
                    "credit_amount" => "7"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $nominal_return_account->id
                    ]->hash,
                    "debit_amount" => "0",
                    "credit_amount" => "43"
                ]
            ],
            "real_adjusted_summary_calculations" => [
                [
                    "frozen_account_hash" => $frozen_account_hashes[$equity_account->id]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "1543"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $liquid_asset_account->id
                    ]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "1480"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "opened_amount" => "0",
                    "closed_amount" => "63"
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
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "net_amount" => "-63"
                ],
                [
                    "cash_flow_activity_id" => $cash_flow_activity->id,
                    "frozen_account_hash" => $frozen_account_hashes[
                        $nominal_return_account->id
                    ]->hash,
                    "net_amount" => "43"
                ]
            ],
            "item_calculations" => [
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "remaining_cost" => "27",
                    "remaining_quantity" => "9"
                ],
                [
                    "frozen_account_hash" => $frozen_account_hashes[
                        $itemized_asset_account->id
                    ]->hash,
                    "remaining_cost" => "36",
                    "remaining_quantity" => "36"
                ]
            ],
            "accounts" => json_decode(json_encode($accounts), true),
            "currencies" => json_decode(json_encode($currencies), true),
            "precision_formats" => json_decode(json_encode($precision_formats), true),
            "cash_flow_activities" => json_decode(json_encode($cash_flow_activities), true)
        ]);
        $this->seeNumRecords(0, "frozen_periods", []);
        $this->seeNumRecords(0, "real_unadjusted_summary_calculations", []);
        $this->seeNumRecords(0, "real_adjusted_summary_calculations", []);
        $this->seeNumRecords(0, "real_flow_calculations", []);
    }

    public function testFirstCreateWithQTBidAndPartialQTAsk()
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
                                [ 2, QUANTITY_FINANCIAL_ENTRY_ATOM_KIND, "10" ],
                                [ 2, TOTAL_FINANCIAL_ENTRY_ATOM_KIND, "30" ],
                                [ 3, "30" ]
                            ]
                        ],
                        [
                            "modifier_index" => 2,
                            "atoms" => [
                                [ 4, "50" ],
                                [ 5, QUANTITY_FINANCIAL_ENTRY_ATOM_KIND, "5" ],
                                [ 5, TOTAL_FINANCIAL_ENTRY_ATOM_KIND, "50" ],
                            ]
                        ],
                        [
                            "modifier_index" => 3,
                            "atoms" => [
                                [ 7, "35" ],
                                [ 8, "35" ]
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
                    BID_MODIFIER_ACTION,
                    ASK_MODIFIER_ACTION,
                    CLOSE_MODIFIER_ACTION
                ],
                "account_combinations" => [
                    [ 0, EQUITY_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ],
                    [ 0, ITEMIZED_ASSET_ACCOUNT_KIND, WEIGHTED_AVERAGE_VALUATION_METHOD ],
                    [ 0, NOMINAL_RETURN_ACCOUNT_KIND ]
                ],
                "modifier_atom_combinations" => [
                    [ 0, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 0, 0, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 1, 2, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 1, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 2, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 2, 2, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 2, 3, REAL_EMERGENT_MODIFIER_ATOM_KIND ],
                    [ 3, 3, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 3, 0, REAL_CREDIT_MODIFIER_ATOM_KIND ]
                ],
                "modifier_atom_activity_combinations" => [
                    [ 1, 0 ],
                    [ 2, 0 ],
                    [ 5, 0 ],
                    [ 6, 0 ]
                ]
            ]
        );
        [
            $equity_account,
            $liquid_asset_account,
            $itemized_asset_account,
            $nominal_return_account
        ] = $accounts;
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
            ->post("/api/v2/frozen_periods", [
                "frozen_period" => $details
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "frozen_period" => $details
        ]);
        $this->seeNumRecords(1, "frozen_periods", []);
        $this->seeNumRecords(4, "frozen_accounts", []);
        $this->seeNumRecords(4, "real_unadjusted_summary_calculations", []);
        $this->seeInDatabase("real_unadjusted_summary_calculations", [
            "frozen_account_hash" => $frozen_account_hashes[$equity_account->id]->hash,
            "debit_amount" => "0",
            "credit_amount" => "1500"
        ]);
        $this->seeInDatabase("real_unadjusted_summary_calculations", [
            "frozen_account_hash" => $frozen_account_hashes[$liquid_asset_account->id]->hash,
            "debit_amount" => "1550",
            "credit_amount" => "30"
        ]);
        $this->seeInDatabase("real_unadjusted_summary_calculations", [
            "frozen_account_hash" => $frozen_account_hashes[$itemized_asset_account->id]->hash,
            "debit_amount" => "30",
            "credit_amount" => "15"
        ]);
        $this->seeInDatabase("real_unadjusted_summary_calculations", [
            "frozen_account_hash" => $frozen_account_hashes[$nominal_return_account->id]->hash,
            "debit_amount" => "0",
            "credit_amount" => "35"
        ]);
        $this->seeNumRecords(3, "real_adjusted_summary_calculations", []);
        $this->seeInDatabase("real_adjusted_summary_calculations", [
            "frozen_account_hash" => $frozen_account_hashes[$equity_account->id]->hash,
            "opened_amount" => "0",
            "closed_amount" => "1535"
        ]);
        $this->seeInDatabase("real_adjusted_summary_calculations", [
            "frozen_account_hash" => $frozen_account_hashes[
                $liquid_asset_account->id
            ]->hash,
            "opened_amount" => "0",
            "closed_amount" => "1520"
        ]);
        $this->seeInDatabase("real_adjusted_summary_calculations", [
            "frozen_account_hash" => $frozen_account_hashes[$itemized_asset_account->id]->hash,
            "opened_amount" => "0",
            "closed_amount" => "15"
        ]);
        $this->seeNumRecords(3, "real_flow_calculations", []);
        $this->seeInDatabase("real_flow_calculations", [
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "frozen_account_hash" => $frozen_account_hashes[$equity_account->id]->hash,
            "net_amount" => "1500"
        ]);
        $this->seeInDatabase("real_flow_calculations", [
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "frozen_account_hash" => $frozen_account_hashes[$itemized_asset_account->id]->hash,
            "net_amount" => "-15"
        ]);
        $this->seeInDatabase("real_flow_calculations", [
            "cash_flow_activity_id" => $cash_flow_activity->id,
            "frozen_account_hash" => $frozen_account_hashes[$nominal_return_account->id]->hash,
            "net_amount" => "35"
        ]);
        $this->seeNumRecords(1, "item_calculations", []);
        $this->seeInDatabase("item_calculations", [
            "frozen_account_hash" => $frozen_account_hashes[$itemized_asset_account->id]->hash,
            "remaining_cost" => "15",
            "remaining_quantity" => "5"
        ]);
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
