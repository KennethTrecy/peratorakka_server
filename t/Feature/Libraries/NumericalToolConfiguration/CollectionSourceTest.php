<?php

namespace Tests\Feature\Libraries\NumericalToolConfiguration;

use App\Casts\RationalNumber;
use App\Libraries\Constellation;
use App\Libraries\Constellation\AcceptableConstellationKind;
use App\Libraries\Constellation\Star;
use App\Exceptions\ExpressionException;
use App\Libraries\Context;
use App\Libraries\Context\ContextKeys;
use App\Libraries\MathExpression;
use App\Libraries\TimeGroup\PeriodicTimeGroup;
use App\Libraries\TimeGroupManager;
use App\Libraries\Context\AccountCache;
use App\Libraries\Context\ExchangeRateCache;
use App\Libraries\Context\FrozenAccountCache;
use App\Libraries\Context\CollectionCache;
use App\Libraries\NumericalToolConfiguration\CollectionSource;
use App\Models\AccountCollectionModel;
use App\Models\CashFlowActivityModel;
use App\Models\CollectionModel;
use App\Models\FormulaModel;
use App\Models\FrozenPeriodModel;
use CodeIgniter\I18n\Time;
use CodeIgniter\Test\Fabricator;
use Tests\Feature\Helper\AuthenticatedContextualHTTPTestCase;

class CollectionSourceTest extends AuthenticatedContextualHTTPTestCase
{
    public function testTotalUnadjustedDebitAmountForAssetKind()
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
                ],
                "currency_options" => [
                    "precision_format_options" => [
                        "overrides" => [
                            "minimum_presentational_precision" => 2,
                            "maximum_presentational_precision" => 2
                        ]
                    ]
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
        $context = Context::make();
        $context->setVariable(ContextKeys::EXCHANGE_RATE_BASIS, PERIODIC_EXCHANGE_RATE_BASIS);
        $context->setVariable(ContextKeys::DESTINATION_CURRENCY_ID, $currency->id);
        $time_group_manager = new TimeGroupManager($context, $time_groups);
        $account_cache = AccountCache::make($context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));
        $collection_cache = CollectionCache::make($context);
        $collection_cache->loadCollectedAccounts([ $asset_collection->id ]);

        $collection_source = CollectionSource::parseConfiguration([
            "collection_id" => $asset_collection->id,
            "stage_basis" => UNADJUSTED_AMOUNT_STAGE_BASIS,
            "side_basis" => DEBIT_AMOUNT_SIDE_BASIS,
            "must_show_individual_amounts" => true,
            "must_show_collective_sum" => true,
            "must_show_collective_average" => true
        ]);
        $constellations = $collection_source->calculate($context);

        $this->assertEquals($constellations, [
            new Constellation($asset_account->name, AcceptableConstellationKind::Account, [
                new Star(
                    "1000.00",
                    RationalNumber::get("1000")
                ),
                new Star(
                    "1750.00",
                    RationalNumber::get("1750")
                )
            ]),
            new Constellation(
                "Total of $asset_collection->name",
                AcceptableConstellationKind::Sum,
                [
                    new Star(
                        "1000.00",
                        RationalNumber::get("1000")
                    ),
                    new Star(
                        "1750.00",
                        RationalNumber::get("1750")
                    )
                ]
            ),
            new Constellation(
                "Average of $asset_collection->name",
                AcceptableConstellationKind::Average,
                [
                    new Star(
                        "1000.00",
                        RationalNumber::get("1000")
                    ),
                    new Star(
                        "1750.00",
                        RationalNumber::get("1750")
                    )
                ]
            )
        ]);
    }

    public function testTotalUnadjustedCreditAmountForEquityKind()
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
                ],
                "currency_options" => [
                    "precision_format_options" => [
                        "overrides" => [
                            "minimum_presentational_precision" => 2,
                            "maximum_presentational_precision" => 2
                        ]
                    ]
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
        $context = Context::make();
        $context->setVariable(ContextKeys::EXCHANGE_RATE_BASIS, PERIODIC_EXCHANGE_RATE_BASIS);
        $context->setVariable(ContextKeys::DESTINATION_CURRENCY_ID, $currency->id);
        $time_group_manager = new TimeGroupManager($context, $time_groups);
        $account_cache = AccountCache::make($context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));
        $collection_cache = CollectionCache::make($context);
        $collection_cache->loadCollectedAccounts([ $equity_collection->id ]);

        $collection_source = CollectionSource::parseConfiguration([
            "collection_id" => $equity_collection->id,
            "stage_basis" => UNADJUSTED_AMOUNT_STAGE_BASIS,
            "side_basis" => CREDIT_AMOUNT_SIDE_BASIS,
            "must_show_individual_amounts" => true,
            "must_show_collective_sum" => true,
            "must_show_collective_average" => true
        ]);
        $constellations = $collection_source->calculate($context);

        $this->assertEquals($constellations, [
            new Constellation($equity_account->name, AcceptableConstellationKind::Account, [
                new Star(
                    "1000.00",
                    RationalNumber::get("1000")
                ),
                new Star(
                    "1750.00",
                    RationalNumber::get("1750")
                )
            ]),
            new Constellation(
                "Total of $equity_collection->name",
                AcceptableConstellationKind::Sum,
                [
                    new Star(
                        "1000.00",
                        RationalNumber::get("1000")
                    ),
                    new Star(
                        "1750.00",
                        RationalNumber::get("1750")
                    )
                ]
            ),
            new Constellation(
                "Average of $equity_collection->name",
                AcceptableConstellationKind::Average,
                [
                    new Star(
                        "1000.00",
                        RationalNumber::get("1000")
                    ),
                    new Star(
                        "1750.00",
                        RationalNumber::get("1750")
                    )
                ]
            )
        ]);
    }

    public function testTotalOpenedDebitAmountForAssetKind()
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
                ],
                "currency_options" => [
                    "precision_format_options" => [
                        "overrides" => [
                            "minimum_presentational_precision" => 2,
                            "maximum_presentational_precision" => 2
                        ]
                    ]
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
        $context = Context::make();
        $context->setVariable(ContextKeys::EXCHANGE_RATE_BASIS, PERIODIC_EXCHANGE_RATE_BASIS);
        $context->setVariable(ContextKeys::DESTINATION_CURRENCY_ID, $currency->id);
        $time_group_manager = new TimeGroupManager($context, $time_groups);
        $account_cache = AccountCache::make($context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));
        $collection_cache = CollectionCache::make($context);
        $collection_cache->loadCollectedAccounts([ $asset_collection->id ]);

        $collection_source = CollectionSource::parseConfiguration([
            "collection_id" => $asset_collection->id,
            "stage_basis" => OPENED_AMOUNT_STAGE_BASIS,
            "side_basis" => DEBIT_AMOUNT_SIDE_BASIS,
            "must_show_individual_amounts" => true,
            "must_show_collective_sum" => true,
            "must_show_collective_average" => true
        ]);
        $constellations = $collection_source->calculate($context);

        $this->assertEquals($constellations, [
            new Constellation($asset_account->name, AcceptableConstellationKind::Account, [
                new Star(
                    "0.00",
                    RationalNumber::get("0")
                ),
                new Star(
                    "750.00",
                    RationalNumber::get("750")
                )
            ]),
            new Constellation(
                "Total of $asset_collection->name",
                AcceptableConstellationKind::Sum,
                [
                    new Star(
                        "0.00",
                        RationalNumber::get("0")
                    ),
                    new Star(
                        "750.00",
                        RationalNumber::get("750")
                    )
                ]
            ),
            new Constellation(
                "Average of $asset_collection->name",
                AcceptableConstellationKind::Average,
                [
                    new Star(
                        "0.00",
                        RationalNumber::get("0")
                    ),
                    new Star(
                        "750.00",
                        RationalNumber::get("750")
                    )
                ]
            )
        ]);
    }

    public function testTotalOpenedCreditAmountForEquityKind()
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
                ],
                "currency_options" => [
                    "precision_format_options" => [
                        "overrides" => [
                            "minimum_presentational_precision" => 2,
                            "maximum_presentational_precision" => 2
                        ]
                    ]
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
        $context = Context::make();
        $context->setVariable(ContextKeys::EXCHANGE_RATE_BASIS, PERIODIC_EXCHANGE_RATE_BASIS);
        $context->setVariable(ContextKeys::DESTINATION_CURRENCY_ID, $currency->id);
        $time_group_manager = new TimeGroupManager($context, $time_groups);
        $account_cache = AccountCache::make($context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));
        $collection_cache = CollectionCache::make($context);
        $collection_cache->loadCollectedAccounts([ $equity_collection->id ]);

        $collection_source = CollectionSource::parseConfiguration([
            "collection_id" => $equity_collection->id,
            "stage_basis" => OPENED_AMOUNT_STAGE_BASIS,
            "side_basis" => CREDIT_AMOUNT_SIDE_BASIS,
            "must_show_individual_amounts" => true,
            "must_show_collective_sum" => true,
            "must_show_collective_average" => true
        ]);
        $constellations = $collection_source->calculate($context);

        $this->assertEquals($constellations, [
            new Constellation($equity_account->name, AcceptableConstellationKind::Account, [
                new Star(
                    "0.00",
                    RationalNumber::get("0")
                ),
                new Star(
                    "750.00",
                    RationalNumber::get("750")
                )
            ]),
            new Constellation(
                "Total of $equity_collection->name",
                AcceptableConstellationKind::Sum,
                [
                    new Star(
                        "0.00",
                        RationalNumber::get("0")
                    ),
                    new Star(
                        "750.00",
                        RationalNumber::get("750")
                    )
                ]
            ),
            new Constellation(
                "Average of $equity_collection->name",
                AcceptableConstellationKind::Average,
                [
                    new Star(
                        "0.00",
                        RationalNumber::get("0")
                    ),
                    new Star(
                        "750.00",
                        RationalNumber::get("750")
                    )
                ]
            )
        ]);
    }

    public function testTotalClosedDebitAmountForAssetKind()
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
                ],
                "currency_options" => [
                    "precision_format_options" => [
                        "overrides" => [
                            "minimum_presentational_precision" => 2,
                            "maximum_presentational_precision" => 2
                        ]
                    ]
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
        $context = Context::make();
        $context->setVariable(ContextKeys::EXCHANGE_RATE_BASIS, PERIODIC_EXCHANGE_RATE_BASIS);
        $context->setVariable(ContextKeys::DESTINATION_CURRENCY_ID, $currency->id);
        $time_group_manager = new TimeGroupManager($context, $time_groups);
        $account_cache = AccountCache::make($context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));
        $collection_cache = CollectionCache::make($context);
        $collection_cache->loadCollectedAccounts([ $asset_collection->id ]);

        $collection_source = CollectionSource::parseConfiguration([
            "collection_id" => $asset_collection->id,
            "stage_basis" => CLOSED_AMOUNT_STAGE_BASIS,
            "side_basis" => DEBIT_AMOUNT_SIDE_BASIS,
            "must_show_individual_amounts" => true,
            "must_show_collective_sum" => true,
            "must_show_collective_average" => true
        ]);
        $constellations = $collection_source->calculate($context);

        $this->assertEquals($constellations, [
            new Constellation($asset_account->name, AcceptableConstellationKind::Account, [
                new Star(
                    "750.00",
                    RationalNumber::get("750")
                ),
                new Star(
                    "1500.00",
                    RationalNumber::get("1500")
                )
            ]),
            new Constellation(
                "Total of $asset_collection->name",
                AcceptableConstellationKind::Sum,
                [
                    new Star(
                        "750.00",
                        RationalNumber::get("750")
                    ),
                    new Star(
                        "1500.00",
                        RationalNumber::get("1500")
                    )
                ]
            ),
            new Constellation(
                "Average of $asset_collection->name",
                AcceptableConstellationKind::Average,
                [
                    new Star(
                        "750.00",
                        RationalNumber::get("750")
                    ),
                    new Star(
                        "1500.00",
                        RationalNumber::get("1500")
                    )
                ]
            )
        ]);
    }

    public function testTotalClosedCreditAmountForEquityKind()
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
                ],
                "currency_options" => [
                    "precision_format_options" => [
                        "overrides" => [
                            "minimum_presentational_precision" => 2,
                            "maximum_presentational_precision" => 2
                        ]
                    ]
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
        $context = Context::make();
        $context->setVariable(ContextKeys::EXCHANGE_RATE_BASIS, PERIODIC_EXCHANGE_RATE_BASIS);
        $context->setVariable(ContextKeys::DESTINATION_CURRENCY_ID, $currency->id);
        $time_group_manager = new TimeGroupManager($context, $time_groups);
        $account_cache = AccountCache::make($context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));
        $collection_cache = CollectionCache::make($context);
        $collection_cache->loadCollectedAccounts([ $equity_collection->id ]);

        $collection_source = CollectionSource::parseConfiguration([
            "collection_id" => $equity_collection->id,
            "stage_basis" => CLOSED_AMOUNT_STAGE_BASIS,
            "side_basis" => CREDIT_AMOUNT_SIDE_BASIS,
            "must_show_individual_amounts" => true,
            "must_show_collective_sum" => true,
            "must_show_collective_average" => true
        ]);
        $constellations = $collection_source->calculate($context);

        $this->assertEquals($constellations, [
            new Constellation($equity_account->name, AcceptableConstellationKind::Account, [
                new Star(
                    "750.00",
                    RationalNumber::get("750")
                ),
                new Star(
                    "1500.00",
                    RationalNumber::get("1500")
                )
            ]),
            new Constellation(
                "Total of $equity_collection->name",
                AcceptableConstellationKind::Sum,
                [
                    new Star(
                        "750.00",
                        RationalNumber::get("750")
                    ),
                    new Star(
                        "1500.00",
                        RationalNumber::get("1500")
                    )
                ]
            ),
            new Constellation(
                "Average of $equity_collection->name",
                AcceptableConstellationKind::Average,
                [
                    new Star(
                        "750.00",
                        RationalNumber::get("750")
                    ),
                    new Star(
                        "1500.00",
                        RationalNumber::get("1500")
                    )
                ]
            )
        ]);
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
                ],
                "currency_options" => [
                    "precision_format_options" => [
                        "overrides" => [
                            "minimum_presentational_precision" => 2,
                            "maximum_presentational_precision" => 2
                        ]
                    ]
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
        model(AccountCollectionModel::class, false)->where("id", $details->id)->delete();
        $asset_collection = $collections[0];

        $time_groups = [
            new PeriodicTimeGroup($frozen_periods[0]),
            new PeriodicTimeGroup($frozen_periods[1])
        ];
        $context = Context::make();
        $context->setVariable(ContextKeys::EXCHANGE_RATE_BASIS, PERIODIC_EXCHANGE_RATE_BASIS);
        $context->setVariable(ContextKeys::DESTINATION_CURRENCY_ID, $currency->id);
        $time_group_manager = new TimeGroupManager($context, $time_groups);
        $account_cache = AccountCache::make($context);
        $account_cache->loadResources(array_map(fn ($account) => $account->id, $accounts));
        $collection_cache = CollectionCache::make($context);
        $collection_cache->loadCollectedAccounts([ $asset_collection->id ]);

        $collection_source = CollectionSource::parseConfiguration([
            "collection_id" => $asset_collection->id,
            "stage_basis" => CLOSED_AMOUNT_STAGE_BASIS,
            "side_basis" => DEBIT_AMOUNT_SIDE_BASIS,
            "must_show_individual_amounts" => true,
            "must_show_collective_sum" => true,
            "must_show_collective_average" => true
        ]);
        $constellations = $collection_source->calculate($context);

        $this->assertEquals($constellations, []);
    }

    public function testExchangedTotalClosedDebitAmountForAssetCollection()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $current_time = Time::now();
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
                    "started_at" => $current_time->subDays(4),
                    "finished_at" => $current_time->subDays(3),
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
                    "started_at" => $current_time->subDays(1),
                    "finished_at" => $current_time,
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
                            "modifier_index" => 5,
                            "atoms" => [
                                [ 10, "3" ],
                                [ 11, "3" ]
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
                    EXCHANGE_MODIFIER_ACTION,
                    RECORD_MODIFIER_ACTION
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
                    [ 4, 1, REAL_CREDIT_MODIFIER_ATOM_KIND ],
                    [ 5, 4, REAL_DEBIT_MODIFIER_ATOM_KIND ],
                    [ 5, 3, REAL_CREDIT_MODIFIER_ATOM_KIND ]
                ],
                "modifier_atom_activity_combinations" => [
                    [ 1, 0 ],
                    [ 2, 0 ],
                    [ 6, 0 ],
                    [ 7, 0 ],
                    [ 11, 0 ]
                ],
                "currency_options" => [
                    "precision_format_options" => [
                        "overrides" => [
                            "minimum_presentational_precision" => 2,
                            "maximum_presentational_precision" => 2
                        ]
                    ]
                ]
            ]
        );
        $local_currency = $currencies[0];
        [
            $local_equity_account,
            $local_asset_account,
            $expense_account,
            $foreign_equity_account,
            $foreign_asset_account
        ] = $accounts;
        [
            $precision_formats,
            $currencies,
            $asset_accounts,
            $collections,
            $details
        ] = AccountCollectionModel::createTestResources($authenticated_info->getUser()->id, 1, [
            "ancestor_accounts" => [
                $precision_formats,
                $currencies,
                [ $local_asset_account, $foreign_asset_account ]
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
        $exchange_rate_cache->setLastExchangeRateTimeOnce($current_time->addDays(1));
        $time_group_manager = new TimeGroupManager($context, $time_groups);
        $collection_cache = CollectionCache::make($context);
        $collection_cache->loadCollectedAccounts([ $asset_collection->id ]);

        $collection_source = CollectionSource::parseConfiguration([
            "collection_id" => $asset_collection->id,
            "stage_basis" => CLOSED_AMOUNT_STAGE_BASIS,
            "side_basis" => DEBIT_AMOUNT_SIDE_BASIS,
            "must_show_individual_amounts" => true,
            "must_show_collective_sum" => true,
            "must_show_collective_average" => true
        ]);
        $constellations = $collection_source->calculate($context);

        $this->assertEquals($constellations, [
            new Constellation(
                $local_asset_account->name,
                AcceptableConstellationKind::Account,
                [
                    new Star(
                        "500.00",
                        RationalNumber::get("500")
                    ),
                    new Star(
                        "1000.00",
                        RationalNumber::get("1000")
                    )
                ]
            ),
            new Constellation(
                $foreign_asset_account->name,
                AcceptableConstellationKind::Account,
                [
                    new Star(
                        "250.00",
                        RationalNumber::get("250")
                    ),
                    new Star(
                        "1000.00",
                        RationalNumber::get("1000")
                    )
                ]
            ),
            new Constellation(
                "Total of $asset_collection->name",
                AcceptableConstellationKind::Sum,
                [
                    new Star(
                        "750.00",
                        RationalNumber::get("750")
                    ),
                    new Star(
                        "2000.00",
                        RationalNumber::get("2000")
                    )
                ]
            ),
            new Constellation(
                "Average of $asset_collection->name",
                AcceptableConstellationKind::Average,
                [
                    new Star(
                        "375.00",
                        RationalNumber::get("375")
                    ),
                    new Star(
                        "1000.00",
                        RationalNumber::get("1000")
                    )
                ]
            )
        ]);
    }
}
