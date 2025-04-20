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
use App\Libraries\Context\FrozenAccountCache;
use App\Models\AccountCollectionModel;
use App\Models\CollectionModel;
use App\Models\FrozenPeriodModel;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\Fabricator;
use Tests\Feature\Helper\AuthenticatedContextualHTTPTestCase;

// public function testTotalUnadjustedDebitAmountForExpenseKind()
// public function testTotalOpenedDebitAmountForAssetCollection()
// public function testTotalOpenedCreditAmountForEquityCollection()
// public function testTotalUnadjustedDebitAmountForExpenseCollection()
// public function testTotalUnadjustedCreditAmountForEquityCollection()
// public function testTotalClosedDebitAmountForAssetCollection()
// public function testTotalClosedCreditAmountForEquityCollection()
// public function testFailurelDueToUnexpectedValueForTotalFunctions()
// public function testRobustnesslDueToUncollectedAssets()
// public function testLiteralRightHandAdditionToTotalClosedDebitAmountForAssetCollection()
// public function testLiteralLeftHandAdditionToTotalClosedDebitAmountForAssetCollection()
// public function testLiteralRightHandSubtractionToTotalClosedDebitAmountForAssetCollection()
// public function testLiteralLeftHandSubtractionToTotalClosedDebitAmountForAssetCollection()
// public function testLiteralRightHandMultiplicationToTotalClosedDebitAmountForAssetCollection()
// public function testLiteralLeftHandMultiplicationToTotalClosedDebitAmountForAssetCollection()
// public function testLiteralRightHandDivisionToTotalClosedDebitAmountForAssetCollection()
// public function testLiteralLeftHandDivisionToTotalClosedDebitAmountForAssetCollection()
// public function testCollectiveLeftHandDivisionToTotalClosedDebitAmountForAssetCollection()
// public function testExchangedTotalOpenedDebitAmountForAssetCollection()
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
        [
            $precision_formats,
            $currencies,
            $accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResource($authenticated_info->getUser()->id, []);
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
        $math_expression = new MathExpression($time_group_manager);

        $formula = "TOTAL_OPENED_DEBIT_AMOUNT(COLLECTION[$asset_collection->id])";
        $totals = $math_expression->evaluate($formula);

        $this->assertEquals($totals, [
            [ RationalNumber::get("0") ],
            [ RationalNumber::get("2000") ]
        ]);
    }
}
