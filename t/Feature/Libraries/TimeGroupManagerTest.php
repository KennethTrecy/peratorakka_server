<?php

namespace Tests\Feature\Libraries;

use App\Casts\RationalNumber;
use App\Libraries\Context;
use App\Libraries\TimeGroup\PeriodicTimeGroup;
use App\Libraries\TimeGroup\UnfrozenTimeGroup;
use App\Libraries\TimeGroup\YearlyTimeGroup;
use App\Libraries\TimeGroupManager;
use App\Libraries\Context\AccountCache;
use App\Libraries\Context\FrozenAccountCache;
use App\Models\FrozenPeriodModel;
use CodeIgniter\I18n\Time;
use Tests\Feature\Helper\AuthenticatedContextualHTTPTestCase;
use Throwable;

class TimeGroupManagerTest extends AuthenticatedContextualHTTPTestCase
{
    public function testTotalRealOpenedDebitAmount()
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
        $currency = $currencies[0];
        $asset_account = $accounts[1];
        $details = $frozen_periods[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            new PeriodicTimeGroup($frozen_periods[1])
        ];
        $time_group_manager = new TimeGroupManager(new Context(), $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));

        $totals = $time_group_manager->totalRealOpenedDebitAmount([
            $asset_account->id
        ]);

        $this->assertEquals($totals, [
            [ RationalNumber::get("0") ],
            [ RationalNumber::get("750") ]
        ]);
    }

    public function testTotalRealOpenedCreditAmount()
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
        $currency = $currencies[0];
        $equity_account = $accounts[0];
        $details = $frozen_periods[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            new PeriodicTimeGroup($frozen_periods[1])
        ];
        $time_group_manager = new TimeGroupManager(new Context(), $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));

        $totals = $time_group_manager->totalRealOpenedCreditAmount([
            $equity_account->id
        ]);

        $this->assertEquals($totals, [
            [ RationalNumber::get("0") ],
            [ RationalNumber::get("750") ]
        ]);
    }

    public function testTotalRealClosedDebitAmount()
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
        $currency = $currencies[0];
        $asset_account = $accounts[1];
        $details = $frozen_periods[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            new PeriodicTimeGroup($frozen_periods[1])
        ];
        $time_group_manager = new TimeGroupManager(new Context(), $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));

        $totals = $time_group_manager->totalRealClosedDebitAmount([
            $asset_account->id
        ]);

        $this->assertEquals($totals, [
            [ RationalNumber::get("750") ],
            [ RationalNumber::get("1500") ]
        ]);
    }

    public function testTotalRealClosedCreditAmount()
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
        $currency = $currencies[0];
        $equity_account = $accounts[0];
        $details = $frozen_periods[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            new PeriodicTimeGroup($frozen_periods[1])
        ];
        $time_group_manager = new TimeGroupManager(new Context(), $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));

        $totals = $time_group_manager->totalRealClosedCreditAmount([
            $equity_account->id
        ]);

        $this->assertEquals($totals, [
            [ RationalNumber::get("750") ],
            [ RationalNumber::get("1500") ]
        ]);
    }

    public function testTotalRealUnadjustedDebitAmount()
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
        $currency = $currencies[0];
        $asset_account = $accounts[1];
        $details = $frozen_periods[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            new PeriodicTimeGroup($frozen_periods[1])
        ];
        $time_group_manager = new TimeGroupManager(new Context(), $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));

        $totals = $time_group_manager->totalRealUnadjustedDebitAmount([
            $asset_account->id
        ]);

        $this->assertEquals($totals, [
            [ RationalNumber::get("1000") ],
            [ RationalNumber::get("1750") ]
        ]);
    }

    public function testTotalRealUnadjustedCreditAmount()
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
        $currency = $currencies[0];
        $equity_account = $accounts[0];
        $details = $frozen_periods[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            new PeriodicTimeGroup($frozen_periods[1])
        ];
        $time_group_manager = new TimeGroupManager(new Context(), $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));

        $totals = $time_group_manager->totalRealUnadjustedCreditAmount([
            $equity_account->id
        ]);

        $this->assertEquals($totals, [
            [ RationalNumber::get("1000") ],
            [ RationalNumber::get("1750") ]
        ]);
    }

    public function testTotalRealNetCashFlowAmount()
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
                    "finished_at" => Time::now(),
                    "entries" => [
                        [
                            "modifier_index" => 0,
                            "atoms" => [
                                [ 0, "1100" ],
                                [ 1, "1100" ]
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
        $equity_account = $accounts[0];
        $details = $frozen_periods[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            new PeriodicTimeGroup($frozen_periods[1])
        ];
        $time_group_manager = new TimeGroupManager(new Context(), $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));

        $totals = $time_group_manager->totalRealNetCashFlowAmount([ $cash_flow_activity->id ], [
            $equity_account->id
        ]);

        $this->assertEquals($totals, [
            [ RationalNumber::get("1000") ],
            [ RationalNumber::get("1100") ]
        ]);
    }

    public function testTotalRealOpenedDebitAmountWithUnfrozenPeriod()
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
        $currency = $currencies[0];
        $asset_account = $accounts[1];
        $details = $frozen_periods[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            UnfrozenTimeGroup::make($frozen_periods[1]->started_at, Time::now())
        ];
        $time_group_manager = new TimeGroupManager(new Context(), $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));

        $totals = $time_group_manager->totalRealOpenedDebitAmount([
            $asset_account->id
        ]);

        $this->assertEquals($totals, [
            [ RationalNumber::get("0") ],
            [ RationalNumber::get("750") ]
        ]);
    }

    public function testTotalRealOpenedCreditAmountWithUnfrozenPeriod()
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
        $currency = $currencies[0];
        $equity_account = $accounts[0];
        $details = $frozen_periods[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            UnfrozenTimeGroup::make($frozen_periods[1]->started_at, Time::now())
        ];
        $time_group_manager = new TimeGroupManager(new Context(), $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));

        $totals = $time_group_manager->totalRealOpenedCreditAmount([
            $equity_account->id
        ]);

        $this->assertEquals($totals, [
            [ RationalNumber::get("0") ],
            [ RationalNumber::get("750") ]
        ]);
    }

    public function testTotalRealClosedDebitAmountWithUnfrozenPeriod()
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
        $currency = $currencies[0];
        $asset_account = $accounts[1];
        $details = $frozen_periods[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            UnfrozenTimeGroup::make($frozen_periods[1]->started_at, Time::now())
        ];
        $time_group_manager = new TimeGroupManager(new Context(), $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));

        $totals = $time_group_manager->totalRealClosedDebitAmount([
            $asset_account->id
        ]);

        $this->assertEquals($totals, [
            [ RationalNumber::get("750") ],
            [ RationalNumber::get("1500") ]
        ]);
    }

    public function testTotalRealClosedCreditAmountWithUnfrozenPeriod()
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
        $currency = $currencies[0];
        $equity_account = $accounts[0];
        $details = $frozen_periods[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            UnfrozenTimeGroup::make($frozen_periods[1]->started_at, Time::now())
        ];
        $time_group_manager = new TimeGroupManager(new Context(), $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));

        $totals = $time_group_manager->totalRealClosedCreditAmount([
            $equity_account->id
        ]);

        $this->assertEquals($totals, [
            [ RationalNumber::get("750") ],
            [ RationalNumber::get("1500") ]
        ]);
    }

    public function testTotalRealUnadjustedDebitAmountWithUnfrozenPeriod()
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
        $currency = $currencies[0];
        $asset_account = $accounts[1];
        $details = $frozen_periods[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            UnfrozenTimeGroup::make($frozen_periods[1]->started_at, Time::now())
        ];
        $time_group_manager = new TimeGroupManager(new Context(), $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));

        $totals = $time_group_manager->totalRealUnadjustedDebitAmount([
            $asset_account->id
        ]);

        $this->assertEquals($totals, [
            [ RationalNumber::get("1000") ],
            [ RationalNumber::get("1750") ]
        ]);
    }

    public function testTotalRealUnadjustedCreditAmountWithUnfrozenPeriod()
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
        $currency = $currencies[0];
        $equity_account = $accounts[0];
        $details = $frozen_periods[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            UnfrozenTimeGroup::make($frozen_periods[1]->started_at, Time::now())
        ];
        $time_group_manager = new TimeGroupManager(new Context(), $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));

        $totals = $time_group_manager->totalRealUnadjustedCreditAmount([
            $equity_account->id
        ]);

        $this->assertEquals($totals, [
            [ RationalNumber::get("1000") ],
            [ RationalNumber::get("1750") ]
        ]);
    }

    public function testTotalRealNetCashFlowAmountWithUnfrozenPeriod()
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
                                [ 0, "1100" ],
                                [ 1, "1100" ]
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
        $equity_account = $accounts[0];
        $details = $frozen_periods[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            UnfrozenTimeGroup::make($frozen_periods[1]->started_at, Time::now())
        ];
        $time_group_manager = new TimeGroupManager(new Context(), $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));

        $totals = $time_group_manager->totalRealNetCashFlowAmount([ $cash_flow_activity->id ], [
            $equity_account->id
        ]);

        $this->assertEquals($totals, [
            [ RationalNumber::get("1000") ],
            [ RationalNumber::get("1100") ]
        ]);
    }

    public function testTotalRealClosedCreditAmountWithSemiUnfrozenYearlyPeriod()
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
        $currency = $currencies[0];
        $equity_account = $accounts[0];
        $details = $frozen_periods[0];

        $context = new Context();
        $frozen_account_cache = FrozenAccountCache::make($context);

        $first_year_time_subgroups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            UnfrozenTimeGroup::make($frozen_periods[1]->started_at, Time::now())
        ];
        $first_year_time_group = new YearlyTimeGroup(
            $frozen_account_cache,
            Time::now()->year,
            true
        );
        foreach ($first_year_time_subgroups as $time_subgroup) {
            $first_year_time_group->addTimeGroup($time_subgroup);
        }
        $time_groups = [ $first_year_time_group ];

        $time_group_manager = new TimeGroupManager($context, $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));

        $totals = $time_group_manager->totalRealClosedCreditAmount([
            $equity_account->id
        ]);

        $this->assertEquals($totals, [
            [ RationalNumber::get("750"), RationalNumber::get("1500") ]
        ]);
    }

    public function testTotalRealClosedCreditAmountWithFullyUnfrozenYearlyPeriod()
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
        $currency = $currencies[0];
        $equity_account = $accounts[0];
        $details = $frozen_periods[0];

        $context = new Context();
        $frozen_account_cache = FrozenAccountCache::make($context);

        $first_year_time_subgroups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            new PeriodicTimeGroup($frozen_periods[1])
        ];
        $first_year_time_group = new YearlyTimeGroup(
            $frozen_account_cache,
            Time::now()->year,
            true
        );
        foreach ($first_year_time_subgroups as $time_subgroup) {
            $first_year_time_group->addTimeGroup($time_subgroup);
        }
        $time_groups = [ $first_year_time_group ];

        $time_group_manager = new TimeGroupManager($context, $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));

        $totals = $time_group_manager->totalRealClosedCreditAmount([
            $equity_account->id
        ]);

        $this->assertEquals($totals, [
            [ RationalNumber::get("750"), RationalNumber::get("1500") ]
        ]);
    }

    public function testTotalRealNetCashFlowAmountWithSemiUnfrozenYearlyPeriod()
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
                                [ 0, "1100" ],
                                [ 1, "1100" ]
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
        $equity_account = $accounts[0];
        $details = $frozen_periods[0];

        $context = new Context();
        $frozen_account_cache = FrozenAccountCache::make($context);

        $first_year_time_subgroups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            UnfrozenTimeGroup::make($frozen_periods[1]->started_at, Time::now())
        ];
        $first_year_time_group = new YearlyTimeGroup(
            $frozen_account_cache,
            Time::now()->year,
            true
        );
        foreach ($first_year_time_subgroups as $time_subgroup) {
            $first_year_time_group->addTimeGroup($time_subgroup);
        }
        $time_groups = [ $first_year_time_group ];

        $time_group_manager = new TimeGroupManager($context, $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));

        $totals = $time_group_manager->totalRealNetCashFlowAmount([ $cash_flow_activity->id ], [
            $equity_account->id
        ]);

        $this->assertEquals($totals, [
            [ RationalNumber::get("1000"), RationalNumber::get("1100") ]
        ]);
    }

    public function testTotalRealNetCashFlowAmountWithFullyUnfrozenYearlyPeriod()
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
                    "finished_at" => Time::now(),
                    "entries" => [
                        [
                            "modifier_index" => 0,
                            "atoms" => [
                                [ 0, "1100" ],
                                [ 1, "1100" ]
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
        $equity_account = $accounts[0];
        $details = $frozen_periods[0];

        $context = new Context();
        $frozen_account_cache = FrozenAccountCache::make($context);

        $first_year_time_subgroups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            new PeriodicTimeGroup($frozen_periods[1])
        ];
        $first_year_time_group = new YearlyTimeGroup(
            $frozen_account_cache,
            Time::now()->year,
            true
        );
        foreach ($first_year_time_subgroups as $time_subgroup) {
            $first_year_time_group->addTimeGroup($time_subgroup);
        }
        $time_groups = [ $first_year_time_group ];

        $time_group_manager = new TimeGroupManager($context, $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));

        $totals = $time_group_manager->totalRealNetCashFlowAmount([ $cash_flow_activity->id ], [
            $equity_account->id
        ]);

        $this->assertEquals($totals, [
            [ RationalNumber::get("1000"), RationalNumber::get("1100") ]
        ]);
    }
}
