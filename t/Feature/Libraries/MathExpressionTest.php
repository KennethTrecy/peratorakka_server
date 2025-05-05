<?php

namespace Tests\Feature\Libraries;

use App\Casts\RationalNumber;
use App\Exceptions\ExpressionException;
use App\Libraries\Context;
use App\Libraries\Context\ContextKeys;
use App\Libraries\MathExpression;
use App\Libraries\TimeGroup\PeriodicTimeGroup;
use App\Libraries\TimeGroup\YearlyTimeGroup;
use App\Libraries\TimeGroupManager;
use App\Libraries\Context\AccountCache;
use App\Libraries\Context\ExchangeRateCache;
use App\Libraries\Context\FrozenAccountCache;
use App\Models\AccountCollectionModel;
use App\Models\CollectionModel;
use App\Models\FrozenPeriodModel;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\Fabricator;
use Tests\Feature\Helper\AuthenticatedContextualHTTPTestCase;

// public function testPeriodicCycleDayCount()
// public function testYearlyCycleDayCount()
// public function testPeriodicDayPrecountPerYear()
// public function testPeriodicDayPostcountPerYear()
// public function testLiteralExponentiation()
// public function testConstantExponentiation()
// public function testShiftCycle()
// public function testSolve()
// public function testPeriodicSubcycleDayCount()
// public function testYearlySubcycleDayCount()
// public function testPeriodicSubcycleIndex()
// public function testYearlySubcycleIndex()
// public function testPeriodicSubcycleCount()
// public function testYearlySubcycleCount()
// public function testPeriodicSubcycleLiteral()
// public function testYearlySubcycleLiteral()
// public function testPeriodicCyclicProduct()
// public function testYearlyCyclicProduct()
// public function testPeriodicSelectCycleFirstValue()
// public function testPeriodicSelectCycleLastValueAndCycleCount()
// public function testYearlySelectCycleValue()
// public function testPeriodicAccountNetCashFlowAmount()
// public function testYearlyAccountNetCashFlowAmount()
// public function testPeriodicCollectionNetCashFlowAmount()
// public function testYearlyCollectionNetCashFlowAmount()

class MathExpressionTest extends AuthenticatedContextualHTTPTestCase
{
    public function testTotalUnadjustedDebitAmountForExpenseKind()
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
        [
            $precision_formats,
            $currencies,
            $asset_accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResource($authenticated_info->getUser()->id, [
            "ancestor_accounts" => [
                $precision_formats,
                $currencies,
                [ $asset_account ]
            ]
        ]);
        $asset_collection = $collections[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            new PeriodicTimeGroup($frozen_periods[1])
        ];
        $time_group_manager = new TimeGroupManager(Context::make(), $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));
        $math_expression = new MathExpression($time_group_manager);

        $formula = "TOTAL_UNADJUSTED_DEBIT_AMOUNT(EXPENSE_ACCOUNTS)";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            [ RationalNumber::get("250") ],
            [ RationalNumber::get("250") ]
        ]);
    }

    public function testTotalOpenedDebitAmountForAssetCollection()
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
        [
            $precision_formats,
            $currencies,
            $asset_accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResource($authenticated_info->getUser()->id, [
            "ancestor_accounts" => [
                $precision_formats,
                $currencies,
                [ $asset_account ]
            ]
        ]);
        $asset_collection = $collections[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            new PeriodicTimeGroup($frozen_periods[1])
        ];
        $time_group_manager = new TimeGroupManager(Context::make(), $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));
        $math_expression = new MathExpression($time_group_manager);

        $formula = "TOTAL_OPENED_DEBIT_AMOUNT(COLLECTION[$asset_collection->id])";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            [ RationalNumber::get("0") ],
            [ RationalNumber::get("750") ]
        ]);
    }

    public function testTotalOpenedCreditAmountForEquityCollection()
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
        [
            $precision_formats,
            $currencies,
            $asset_accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResource($authenticated_info->getUser()->id, [
            "ancestor_accounts" => [
                $precision_formats,
                $currencies,
                [ $equity_account ]
            ]
        ]);
        $equity_collection = $collections[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            new PeriodicTimeGroup($frozen_periods[1])
        ];
        $time_group_manager = new TimeGroupManager(Context::make(), $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));
        $math_expression = new MathExpression($time_group_manager);

        $formula = "TOTAL_OPENED_CREDIT_AMOUNT(COLLECTION[$equity_collection->id])";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            [ RationalNumber::get("0") ],
            [ RationalNumber::get("750") ]
        ]);
    }

    public function testTotalUnadjustedDebitAmountForExpenseCollection()
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
        $expense_account = $accounts[2];
        $details = $frozen_periods[0];
        [
            $precision_formats,
            $currencies,
            $asset_accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResource($authenticated_info->getUser()->id, [
            "ancestor_accounts" => [
                $precision_formats,
                $currencies,
                [ $expense_account ]
            ]
        ]);
        $expense_collection = $collections[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            new PeriodicTimeGroup($frozen_periods[1])
        ];
        $time_group_manager = new TimeGroupManager(Context::make(), $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));
        $math_expression = new MathExpression($time_group_manager);

        $formula = "TOTAL_UNADJUSTED_DEBIT_AMOUNT(COLLECTION[$expense_collection->id])";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            [ RationalNumber::get("250") ],
            [ RationalNumber::get("250") ]
        ]);
    }

    public function testTotalUnadjustedCreditAmountForEquityCollection()
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
        [
            $precision_formats,
            $currencies,
            $asset_accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResource($authenticated_info->getUser()->id, [
            "ancestor_accounts" => [
                $precision_formats,
                $currencies,
                [ $equity_account ]
            ]
        ]);
        $equity_collection = $collections[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            new PeriodicTimeGroup($frozen_periods[1])
        ];
        $time_group_manager = new TimeGroupManager(Context::make(), $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));
        $math_expression = new MathExpression($time_group_manager);

        $formula = "TOTAL_UNADJUSTED_CREDIT_AMOUNT(COLLECTION[$equity_collection->id])";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            [ RationalNumber::get("1000") ],
            [ RationalNumber::get("1750") ]
        ]);
    }

    public function testTotalClosedDebitAmountForAssetCollection()
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
        [
            $precision_formats,
            $currencies,
            $asset_accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResource($authenticated_info->getUser()->id, [
            "ancestor_accounts" => [
                $precision_formats,
                $currencies,
                [ $asset_account ]
            ]
        ]);
        $asset_collection = $collections[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            new PeriodicTimeGroup($frozen_periods[1])
        ];
        $time_group_manager = new TimeGroupManager(Context::make(), $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));
        $math_expression = new MathExpression($time_group_manager);

        $formula = "TOTAL_CLOSED_DEBIT_AMOUNT(COLLECTION[$asset_collection->id])";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            [ RationalNumber::get("750") ],
            [ RationalNumber::get("1500") ]
        ]);
    }

    public function testTotalClosedCreditAmountForEquityCollection()
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
        [
            $precision_formats,
            $currencies,
            $asset_accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResource($authenticated_info->getUser()->id, [
            "ancestor_accounts" => [
                $precision_formats,
                $currencies,
                [ $equity_account ]
            ]
        ]);
        $equity_collection = $collections[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            new PeriodicTimeGroup($frozen_periods[1])
        ];
        $time_group_manager = new TimeGroupManager(Context::make(), $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));
        $math_expression = new MathExpression($time_group_manager);

        $formula = "TOTAL_CLOSED_CREDIT_AMOUNT(COLLECTION[$equity_collection->id])";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            [ RationalNumber::get("750") ],
            [ RationalNumber::get("1500") ]
        ]);
    }

    public function testFailurelDueToUnexpectedValueForTotalFunctions()
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
        [
            $precision_formats,
            $currencies,
            $asset_accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResource($authenticated_info->getUser()->id, [
            "ancestor_accounts" => [
                $precision_formats,
                $currencies,
                [ $equity_account ]
            ]
        ]);
        $equity_collection = $collections[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            new PeriodicTimeGroup($frozen_periods[1])
        ];
        $time_group_manager = new TimeGroupManager(Context::make(), $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));
        $math_expression = new MathExpression($time_group_manager);

        $this->expectException(ExpressionException::class);
        $formula = "TOTAL_CLOSED_CREDIT_AMOUNT(#123)";
        $totals = $math_expression->evaluate($formula);
    }

    public function testRobustnesslDueToUncollectedAssets()
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
        [
            $precision_formats,
            $currencies,
            $asset_accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResource($authenticated_info->getUser()->id, [
            "ancestor_accounts" => [
                $precision_formats,
                $currencies,
                [ $asset_account ]
            ]
        ]);
        model(AccountCollectionModel::class)->delete($details->id);
        $asset_collection = $collections[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            new PeriodicTimeGroup($frozen_periods[1])
        ];
        $time_group_manager = new TimeGroupManager(Context::make(), $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));
        $math_expression = new MathExpression($time_group_manager);

        $formula = "TOTAL_CLOSED_DEBIT_AMOUNT(COLLECTION[$asset_collection->id])";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            [ RationalNumber::get("0") ],
            [ RationalNumber::get("0") ]
        ]);
    }

    public function testLiteralRightHandAdditionToTotalClosedDebitAmountForAssetCollection()
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
        [
            $precision_formats,
            $currencies,
            $asset_accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResource($authenticated_info->getUser()->id, [
            "ancestor_accounts" => [
                $precision_formats,
                $currencies,
                [ $asset_account ]
            ]
        ]);
        $asset_collection = $collections[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            new PeriodicTimeGroup($frozen_periods[1])
        ];
        $time_group_manager = new TimeGroupManager(Context::make(), $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));
        $math_expression = new MathExpression($time_group_manager);

        $formula = "TOTAL_CLOSED_DEBIT_AMOUNT(COLLECTION[$asset_collection->id]) + 1";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            [ RationalNumber::get("751") ],
            [ RationalNumber::get("1501") ]
        ]);
    }

    public function testLiteralLeftHandAdditionToTotalClosedDebitAmountForAssetCollection()
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
        [
            $precision_formats,
            $currencies,
            $asset_accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResource($authenticated_info->getUser()->id, [
            "ancestor_accounts" => [
                $precision_formats,
                $currencies,
                [ $asset_account ]
            ]
        ]);
        $asset_collection = $collections[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            new PeriodicTimeGroup($frozen_periods[1])
        ];
        $time_group_manager = new TimeGroupManager(Context::make(), $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));
        $math_expression = new MathExpression($time_group_manager);

        $formula = "1 + TOTAL_CLOSED_DEBIT_AMOUNT(COLLECTION[$asset_collection->id])";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            [ RationalNumber::get("751") ],
            [ RationalNumber::get("1501") ]
        ]);
    }

    public function testLiteralRightHandSubtractionToTotalClosedDebitAmountForAssetCollection()
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
        [
            $precision_formats,
            $currencies,
            $asset_accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResource($authenticated_info->getUser()->id, [
            "ancestor_accounts" => [
                $precision_formats,
                $currencies,
                [ $asset_account ]
            ]
        ]);
        $asset_collection = $collections[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            new PeriodicTimeGroup($frozen_periods[1])
        ];
        $time_group_manager = new TimeGroupManager(Context::make(), $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));
        $math_expression = new MathExpression($time_group_manager);

        $formula = "TOTAL_CLOSED_DEBIT_AMOUNT(COLLECTION[$asset_collection->id]) - 1";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            [ RationalNumber::get("749") ],
            [ RationalNumber::get("1499") ]
        ]);
    }

    public function testLiteralLeftHandSubtractionToTotalClosedDebitAmountForAssetCollection()
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
        [
            $precision_formats,
            $currencies,
            $asset_accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResource($authenticated_info->getUser()->id, [
            "ancestor_accounts" => [
                $precision_formats,
                $currencies,
                [ $asset_account ]
            ]
        ]);
        $asset_collection = $collections[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            new PeriodicTimeGroup($frozen_periods[1])
        ];
        $time_group_manager = new TimeGroupManager(Context::make(), $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));
        $math_expression = new MathExpression($time_group_manager);

        $formula = "1 - TOTAL_CLOSED_DEBIT_AMOUNT(COLLECTION[$asset_collection->id])";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            [ RationalNumber::get("-749") ],
            [ RationalNumber::get("-1499") ]
        ]);
    }

    public function testLiteralRightHandMultiplicationToTotalClosedDebitAmountForAssetCollection()
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
        [
            $precision_formats,
            $currencies,
            $asset_accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResource($authenticated_info->getUser()->id, [
            "ancestor_accounts" => [
                $precision_formats,
                $currencies,
                [ $asset_account ]
            ]
        ]);
        $asset_collection = $collections[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            new PeriodicTimeGroup($frozen_periods[1])
        ];
        $time_group_manager = new TimeGroupManager(Context::make(), $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));
        $math_expression = new MathExpression($time_group_manager);

        $formula = "TOTAL_CLOSED_DEBIT_AMOUNT(COLLECTION[$asset_collection->id]) * 0.5";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            [ RationalNumber::get("375") ],
            [ RationalNumber::get("750") ]
        ]);
    }

    public function testLiteralLeftHandMultiplicationToTotalClosedDebitAmountForAssetCollection()
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
        [
            $precision_formats,
            $currencies,
            $asset_accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResource($authenticated_info->getUser()->id, [
            "ancestor_accounts" => [
                $precision_formats,
                $currencies,
                [ $asset_account ]
            ]
        ]);
        $asset_collection = $collections[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            new PeriodicTimeGroup($frozen_periods[1])
        ];
        $time_group_manager = new TimeGroupManager(Context::make(), $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));
        $math_expression = new MathExpression($time_group_manager);

        $formula = "0.5 * TOTAL_CLOSED_DEBIT_AMOUNT(COLLECTION[$asset_collection->id])";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            [ RationalNumber::get("375") ],
            [ RationalNumber::get("750") ]
        ]);
    }

    public function testLiteralRightHandDivisionToTotalClosedDebitAmountForAssetCollection()
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
        [
            $precision_formats,
            $currencies,
            $asset_accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResource($authenticated_info->getUser()->id, [
            "ancestor_accounts" => [
                $precision_formats,
                $currencies,
                [ $asset_account ]
            ]
        ]);
        $asset_collection = $collections[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            new PeriodicTimeGroup($frozen_periods[1])
        ];
        $time_group_manager = new TimeGroupManager(Context::make(), $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));
        $math_expression = new MathExpression($time_group_manager);

        $formula = "TOTAL_CLOSED_DEBIT_AMOUNT(COLLECTION[$asset_collection->id]) / 0.5";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            [ RationalNumber::get("1500") ],
            [ RationalNumber::get("3000") ]
        ]);
    }

    public function testLiteralLeftHandDivisionToTotalClosedDebitAmountForAssetCollection()
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
        [
            $precision_formats,
            $currencies,
            $asset_accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResource($authenticated_info->getUser()->id, [
            "ancestor_accounts" => [
                $precision_formats,
                $currencies,
                [ $asset_account ]
            ]
        ]);
        $asset_collection = $collections[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            new PeriodicTimeGroup($frozen_periods[1])
        ];
        $time_group_manager = new TimeGroupManager(Context::make(), $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));
        $math_expression = new MathExpression($time_group_manager);

        $formula = "1500 / TOTAL_CLOSED_DEBIT_AMOUNT(COLLECTION[$asset_collection->id])";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            [ RationalNumber::get("2") ],
            [ RationalNumber::get("1") ]
        ]);
    }

    public function testCollectiveLeftHandDivisionToTotalClosedDebitAmountForAssetCollection()
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
        $asset_account = $accounts[1];
        $details = $frozen_periods[0];
        [
            $precision_formats,
            $currencies,
            $asset_accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResource($authenticated_info->getUser()->id, [
            "ancestor_accounts" => [
                $precision_formats,
                $currencies,
                [ $equity_account ]
            ]
        ]);
        $equity_collection = $collections[0];
        [
            $precision_formats,
            $currencies,
            $asset_accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResource($authenticated_info->getUser()->id, [
            "ancestor_accounts" => [
                $precision_formats,
                $currencies,
                [ $asset_account ]
            ]
        ]);
        $asset_collection = $collections[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            new PeriodicTimeGroup($frozen_periods[1])
        ];
        $time_group_manager = new TimeGroupManager(Context::make(), $time_groups);
        $account_cache = AccountCache::make($time_group_manager->context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));
        $math_expression = new MathExpression($time_group_manager);

        $formula = "TOTAL_CLOSED_CREDIT_AMOUNT(COLLECTION[$equity_collection->id]) / TOTAL_CLOSED_DEBIT_AMOUNT(COLLECTION[$asset_collection->id])";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            [ RationalNumber::get("1") ],
            [ RationalNumber::get("1") ]
        ]);
    }

    public function testExchangedTotalClosedDebitAmountForAssetCollection()
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
                                [ 6, "250" ],
                                [ 7, "3" ]
                            ]
                        ],
                        [
                            "modifier_index" => 4,
                            "atoms" => [
                                [ 8, "3" ],
                                [ 9, "250" ]
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
                            "modifier_index" => 3,
                            "atoms" => [
                                [ 6, "250" ],
                                [ 7, "2" ]
                            ]
                        ],
                        [
                            "modifier_index" => 4,
                            "atoms" => [
                                [ 8, "2" ],
                                [ 9, "250" ]
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
                "currency_count" => 2,
                "cash_flow_activity_count" => 1,
                "expected_modifier_actions" => [
                    RECORD_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION,
                    CLOSE_MODIFIER_ACTION,
                    EXCHANGE_MODIFIER_ACTION,
                    EXCHANGE_MODIFIER_ACTION
                ],
                "account_combinations" => [
                    [ 0, EQUITY_ACCOUNT_KIND ],
                    [ 0, LIQUID_ASSET_ACCOUNT_KIND ],
                    [ 0, GENERAL_EXPENSE_ACCOUNT_KIND ],
                    [ 1, EQUITY_ACCOUNT_KIND ],
                    [ 1, LIQUID_ASSET_ACCOUNT_KIND ]
                ],
                "modifier_atom_combinations" => [
                    [ 0, 1, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 0, 0, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 1, 2, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 1, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 2, 0, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 2, 2, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 3, 0, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 3, 3, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 4, 4, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 4, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ]
                ],
                "modifier_atom_activity_combinations" => [
                    [ 1, 0 ],
                    [ 2, 0 ],
                    [ 6, 0 ],
                    [ 7, 0 ]
                ]
            ]
        );
        $local_currency = $currencies[0];
        $foreign_currency = $currencies[1];
        $foreign_asset_account = $accounts[4];
        $details = $frozen_periods[0];
        [
            $precision_formats,
            $currencies,
            $foreign_asset_accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResource($authenticated_info->getUser()->id, [
            "ancestor_accounts" => [
                $precision_formats,
                [ $foreign_currency ],
                [ $foreign_asset_account ]
            ]
        ]);
        $asset_collection = $collections[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            new PeriodicTimeGroup($frozen_periods[1])
        ];
        $context = Context::make();
        $context->setVariable(ContextKeys::EXCHANGE_RATE_BASIS, PERIODIC_EXCHANGE_RATE_BASIS);
        $context->setVariable(ContextKeys::DESTINATION_CURRENCY_ID, $local_currency->id);
        $account_IDs = array_map(fn ($account) => $account->id, $accounts);
        $account_cache = AccountCache::make($context);
        $account_cache->loadResources($account_IDs);
        $exchange_rate_cache = ExchangeRateCache::make($context);
        $exchange_rate_cache->loadExchangeRatesForAccounts($account_IDs);
        $exchange_rate_cache->setLastExchangeRateTimeOnce(Time::now()->addDays(1));
        $time_group_manager = new TimeGroupManager($context, $time_groups);
        $math_expression = new MathExpression($time_group_manager);

        $formula = "TOTAL_CLOSED_DEBIT_AMOUNT(COLLECTION[$asset_collection->id])";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            [ RationalNumber::get("250") ],
            [ RationalNumber::get("625") ]
        ]);
    }
}
