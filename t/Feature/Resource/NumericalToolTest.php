<?php

namespace Tests\Feature\Resource;

use App\Casts\NumericalToolKind;
use App\Casts\NumericalToolRecurrencePeriod;
use App\Casts\RationalNumber;
use App\Exceptions\InvalidRequest;
use App\Exceptions\MissingResource;
use App\Libraries\Constellation;
use App\Libraries\Constellation\AcceptableConstellationKind;
use App\Libraries\Constellation\Star;
use App\Libraries\NumericalToolConfiguration\CollectionSource;
use App\Libraries\NumericalToolConfiguration\FormulaSource;
use App\Models\AccountCollectionModel;
use App\Models\FormulaModel;
use App\Models\FrozenPeriodModel;
use App\Models\NumericalToolModel;
use CodeIgniter\I18n\Time;
use Tests\Feature\Helper\AuthenticatedContextualHTTPTestCase;
use Throwable;

class NumericalToolTest extends AuthenticatedContextualHTTPTestCase
{
    public function testDefaultIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $numerical_tools
        ] = NumericalToolModel::createTestResources(
            $authenticated_info->getUser()->id,
            10,
            []
        );

        $result = $authenticated_info->getRequest()->get("/api/v2/numerical_tools");

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "overall_filtered_count" => 10
            ],
            "numerical_tools" => json_decode(json_encode($numerical_tools))
        ]);
    }

    public function testDefaultShow()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $details
        ] = NumericalToolModel::createTestResource($authenticated_info->getUser()->id, []);

        $result = $authenticated_info->getRequest()
            ->get("/api/v2/numerical_tools/$details->id");

        $result->assertOk();
        $result->assertJSONExact([
            "numerical_tool" => json_decode(json_encode($details))
        ]);
    }

    public function testDefaultCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $details
        ] = NumericalToolModel::makeTestResource($authenticated_info->getUser()->id, []);

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/numerical_tools", [
                "numerical_tool" => $details->toArray()
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "numerical_tool" => $details->toArray()
        ]);
    }

    public function testDefaultUpdate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $details,
            $new_details
        ] = NumericalToolModel::createAndMakeTestResources(
            $authenticated_info->getUser()->id,
            []
        );

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v2/numerical_tools/$details->id", [
                "numerical_tool" => $new_details->toArray()
            ]);

        $result->assertStatus(204);
        $this->seeInDatabase("numerical_tools_v2", array_merge(
            [ "id" => $details->id ],
            array_merge($new_details->toRawArray(), [
                "kind" => NumericalToolKind::set($new_details->kind),
                "recurrence" => NumericalToolRecurrencePeriod::set($new_details->recurrence)
            ])
        ));
    }

    public function testDefaultDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $details
        ] = NumericalToolModel::createTestResource($authenticated_info->getUser()->id, []);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v2/numerical_tools/$details->id");

        $result->assertStatus(204);
        $this->seeInDatabase("numerical_tools_v2", array_merge(
            [ "id" => $details->id ]
        ));
        $this->dontSeeInDatabase("numerical_tools_v2", [
            "id" => $details->id,
            "deleted_at" => null
        ]);
    }

    public function testDefaultRestore()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $details
        ] = NumericalToolModel::createTestResource($authenticated_info->getUser()->id, []);
        model(NumericalToolModel::class)->delete($details->id);

        $result = $authenticated_info
            ->getRequest()
            ->patch("/api/v2/numerical_tools/$details->id");

        $result->assertStatus(204);
        $this->seeInDatabase("numerical_tools_v2", [
            "id" => $details->id,
            "deleted_at" => null
        ]);
    }

    public function testDefaultForceDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $details
        ] = NumericalToolModel::createTestResource($authenticated_info->getUser()->id, []);
        model(NumericalToolModel::class)->delete($details->id);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v2/numerical_tools/$details->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "numerical_tools_v2", []);
    }

    public function testEmptyIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $result = $authenticated_info->getRequest()->get("/api/v2/numerical_tools");

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "overall_filtered_count" => 0
            ],
            "numerical_tools" => []
        ]);
    }

    public function testQueriedIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $details
        ] = NumericalToolModel::createTestResources(
            $authenticated_info->getUser()->id,
            10,
            []
        );

        $result = $authenticated_info->getRequest()->get("/api/v2/numerical_tools", [
            "page" => [
                "limit" => 5
            ],
            "relationship" => [
                "precision_formats",
                "currencies"
            ]
        ]);

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "overall_filtered_count" => 10
            ],
            "numerical_tools" => json_decode(json_encode(array_slice($details, 0, 5))),
            "currencies" => json_decode(json_encode($currencies)),
            "precision_formats" => json_decode(json_encode($precision_formats))
        ]);
    }

    public function testCalculatedFrozenOnlyAppendedMultiCurrencyCollection()
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
        [
            $precision_formats,
            $currencies,
            $details
        ] = NumericalToolModel::createTestResource($authenticated_info->getUser()->id, [
            "parents" => [ $precision_formats, $local_currency ],
            "overrides" => [
                "recurrence" => PERIODIC_NUMERICAL_TOOL_RECURRENCE_PERIOD,
                "recency" => 2,
                "currency_id" => $local_currency->id,
                "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
                "configuration" => json_encode([
                    "sources" => [
                        [
                            "type" => CollectionSource::sourceType(),
                            "collection_id" => $asset_collection->id,
                            "stage_basis" => CLOSED_AMOUNT_STAGE_BASIS,
                            "side_basis" => DEBIT_AMOUNT_SIDE_BASIS,
                            "must_show_individual_amounts" => true,
                            "must_show_collective_sum" => true,
                            "must_show_collective_average" => true
                        ]
                    ]
                ])
            ]
        ]);

        $result = $authenticated_info->getRequest()->get(
            "/api/v2/numerical_tools/calculate/{$details->id}"
        );

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "time_tags" => array_fill(0, 2, Time::now()->toLocalizedString("MMMM yyyy")),
                "constellations" => array_map(function ($constellation) {
                    return $constellation->toArray();
                }, [
                    new Constellation(
                        $local_asset_account->name,
                        AcceptableConstellationKind::Account,
                        [
                            new Star(
                                "750.00",
                                RationalNumber::get("750")
                            ),
                            new Star(
                                "1250.00",
                                RationalNumber::get("1250")
                            )
                        ]
                    ),
                    new Constellation(
                        $foreign_asset_account->name,
                        AcceptableConstellationKind::Account,
                        [
                            new Star(
                                "0.00",
                                RationalNumber::get("0")
                            ),
                            new Star(
                                "250.00",
                                RationalNumber::get("250")
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
                                "375.00",
                                RationalNumber::get("375")
                            ),
                            new Star(
                                "750.00",
                                RationalNumber::get("750")
                            )
                        ]
                    )
                ])
            ],
            "numerical_tool" => $details
        ]);
    }

    public function testCalculatedFrozenOnlyTemporaryMultiCurrencyCollection()
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
        [
            $precision_formats,
            $currencies,
            $details
        ] = NumericalToolModel::createTestResource($authenticated_info->getUser()->id, [
            "parents" => [ $precision_formats, $local_currency ],
            "overrides" => [
                "recurrence" => PERIODIC_NUMERICAL_TOOL_RECURRENCE_PERIOD,
                "recency" => 2,
                "currency_id" => $local_currency->id,
                "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
                "configuration" => json_encode([
                    "sources" => [
                        [
                            "type" => CollectionSource::sourceType(),
                            "collection_id" => $asset_collection->id,
                            "stage_basis" => CLOSED_AMOUNT_STAGE_BASIS,
                            "side_basis" => DEBIT_AMOUNT_SIDE_BASIS,
                            "must_show_individual_amounts" => true,
                            "must_show_collective_sum" => true,
                            "must_show_collective_average" => true
                        ]
                    ]
                ])
            ]
        ]);

        $result = $authenticated_info->getRequest()->get(
            "/api/v2/numerical_tools/calculate/{$details->id}"
        );

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "time_tags" => array_fill(0, 2, Time::now()->toLocalizedString("MMMM yyyy")),
                "constellations" => array_map(function ($constellation) {
                    return $constellation->toArray();
                }, [
                    new Constellation(
                        $local_asset_account->name,
                        AcceptableConstellationKind::Account,
                        [
                            new Star(
                                "500.00",
                                RationalNumber::get("500")
                            ),
                            new Star(
                                "1250.00",
                                RationalNumber::get("1250")
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
                                "250.00",
                                RationalNumber::get("250")
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
                                "375.00",
                                RationalNumber::get("375")
                            ),
                            new Star(
                                "750.00",
                                RationalNumber::get("750")
                            )
                        ]
                    )
                ])
            ],
            "numerical_tool" => $details
        ]);
    }

    public function testCalculatedFrozenOnlyPeristentMultiCurrencyCollection()
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
        [
            $precision_formats,
            $currencies,
            $details
        ] = NumericalToolModel::createTestResource($authenticated_info->getUser()->id, [
            "parents" => [ $precision_formats, $local_currency ],
            "overrides" => [
                "recurrence" => PERIODIC_NUMERICAL_TOOL_RECURRENCE_PERIOD,
                "recency" => 2,
                "currency_id" => $local_currency->id,
                "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
                "configuration" => json_encode([
                    "sources" => [
                        [
                            "type" => CollectionSource::sourceType(),
                            "collection_id" => $asset_collection->id,
                            "stage_basis" => CLOSED_AMOUNT_STAGE_BASIS,
                            "side_basis" => DEBIT_AMOUNT_SIDE_BASIS,
                            "must_show_individual_amounts" => true,
                            "must_show_collective_sum" => true,
                            "must_show_collective_average" => true
                        ]
                    ]
                ])
            ]
        ]);

        $result = $authenticated_info->getRequest()->get(
            "/api/v2/numerical_tools/calculate/{$details->id}"
        );

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "time_tags" => array_fill(0, 2, Time::now()->toLocalizedString("MMMM yyyy")),
                "constellations" => array_map(function ($constellation) {
                    return $constellation->toArray();
                }, [
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
                                "625.00",
                                RationalNumber::get("625")
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
                                "1625.00",
                                RationalNumber::get("1625")
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
                                "812.50",
                                RationalNumber::get("812.50")
                            )
                        ]
                    )
                ])
            ],
            "numerical_tool" => $details
        ]);
    }

    public function testCalculatedFrozenOnlyParallelMultiCurrencyCollection()
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
        [
            $precision_formats,
            $currencies,
            $details
        ] = NumericalToolModel::createTestResource($authenticated_info->getUser()->id, [
            "parents" => [ $precision_formats, $local_currency ],
            "overrides" => [
                "recurrence" => PERIODIC_NUMERICAL_TOOL_RECURRENCE_PERIOD,
                "recency" => 2,
                "currency_id" => $local_currency->id,
                "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
                "configuration" => json_encode([
                    "sources" => [
                        [
                            "type" => CollectionSource::sourceType(),
                            "collection_id" => $asset_collection->id,
                            "stage_basis" => CLOSED_AMOUNT_STAGE_BASIS,
                            "side_basis" => DEBIT_AMOUNT_SIDE_BASIS,
                            "must_show_individual_amounts" => true,
                            "must_show_collective_sum" => true,
                            "must_show_collective_average" => true
                        ]
                    ]
                ])
            ]
        ]);

        $result = $authenticated_info->getRequest()->get(
            "/api/v2/numerical_tools/calculate/{$details->id}"
        );

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "time_tags" => array_fill(0, 2, Time::now()->toLocalizedString("MMMM yyyy")),
                "constellations" => array_map(function ($constellation) {
                    return $constellation->toArray();
                }, [
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
                ])
            ],
            "numerical_tool" => $details
        ]);
    }

    public function testCalculatedFrozenOnlyParallelMultiCurrencyCollectionAndFormula()
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
        [
            $precision_formats,
            $formula_details
        ] = FormulaModel::createTestResource(
            $authenticated_info->getUser()->id,
            [
                "overrides" => [
                    "expression" => "TOTAL_UNADJUSTED_DEBIT_AMOUNT(EXPENSE_ACCOUNTS)",
                    "output_format" => CURRENCY_FORMULA_OUTPUT_FORMAT
                ],
                "precision_format_parent" => $precision_formats
            ]
        );
        [
            $precision_formats,
            $currencies,
            $details
        ] = NumericalToolModel::createTestResource($authenticated_info->getUser()->id, [
            "parents" => [ $precision_formats, $local_currency ],
            "overrides" => [
                "recurrence" => PERIODIC_NUMERICAL_TOOL_RECURRENCE_PERIOD,
                "recency" => 2,
                "currency_id" => $local_currency->id,
                "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
                "configuration" => json_encode([
                    "sources" => [
                        [
                            "type" => CollectionSource::sourceType(),
                            "collection_id" => $asset_collection->id,
                            "stage_basis" => CLOSED_AMOUNT_STAGE_BASIS,
                            "side_basis" => DEBIT_AMOUNT_SIDE_BASIS,
                            "must_show_individual_amounts" => true,
                            "must_show_collective_sum" => true,
                            "must_show_collective_average" => true
                        ],
                        [
                            "type" => FormulaSource::sourceType(),
                            "formula_id" => $formula_details->id
                        ]
                    ]
                ])
            ]
        ]);

        $result = $authenticated_info->getRequest()->get(
            "/api/v2/numerical_tools/calculate/{$details->id}"
        );

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "time_tags" => array_fill(0, 2, Time::now()->toLocalizedString("MMMM yyyy")),
                "constellations" => array_map(function ($constellation) {
                    return $constellation->toArray();
                }, [
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
                    ),
                    new Constellation(
                        $formula_details->name,
                        AcceptableConstellationKind::Formula,
                        [
                            new Star(
                                "250.00",
                                RationalNumber::get("250")
                            ),
                            new Star(
                                "250.00",
                                RationalNumber::get("250")
                            )
                        ]
                    )
                ])
            ],
            "numerical_tool" => $details
        ]);
    }

    public function testMissingShow()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $details
        ] = NumericalToolModel::createTestResource($authenticated_info->getUser()->id, []);
        $details->id = $details->id + 1;

        $this->expectException(MissingResource::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info->getRequest()->get("/api/v2/numerical_tools/$details->id");
    }

    public function testInvalidCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $details
        ] = NumericalToolModel::makeTestResource($authenticated_info->getUser()->id, [
            "overrides" => [
                "name" => "@only alphanumeric characters only"
            ]
        ]);

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/numerical_tools", [
                "numerical_tool" => $details->toArray()
            ]);
    }

    public function testInvalidUpdate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $details,
            $new_details
        ] = NumericalToolModel::createAndMakeTestResources(
            $authenticated_info->getUser()->id,
            [
                "make_overrides" => [
                    "name" => "@only alphanumeric characters only"
                ]
            ]
        );

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v2/numerical_tools/$details->id", [
                "numerical_tool" => $new_details->toArray()
            ]);
    }

    public function testUnownedDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        [
            $precision_formats,
            $currencies,
            $details
        ] = NumericalToolModel::createTestResource($another_user->id, []);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v2/numerical_tools/$details->id");
            $this->assertTrue(false);
        } catch (MissingResource $error) {
            $this->seeInDatabase("numerical_tools_v2", array_merge(
                [ "id" => $details->id ]
            ));
            $this->seeInDatabase("numerical_tools_v2", [
                "id" => $details->id,
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

        [
            $precision_formats,
            $currencies,
            $details
        ] = NumericalToolModel::createTestResource($another_user->id, []);
        model(NumericalToolModel::class)->delete($details->id);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v2/numerical_tools/$details->id");
        } catch (MissingResource $error) {
            $this->seeInDatabase("numerical_tools_v2", array_merge(
                [ "id" => $details->id ]
            ));
            $this->dontSeeInDatabase("numerical_tools_v2", [
                "id" => $details->id,
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

        [
            $precision_formats,
            $currencies,
            $details
        ] = NumericalToolModel::createTestResource($another_user->id, []);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->patch("/api/v2/numerical_tools/$details->id");
        } catch (MissingResource $error) {
            $this->seeInDatabase("numerical_tools_v2", [
                "id" => $details->id,
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

        [
            $precision_formats,
            $currencies,
            $details
        ] = NumericalToolModel::createTestResource($authenticated_info->getUser()->id, []);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v2/numerical_tools/$details->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "numerical_tools_v2", []);
    }

    public function testDoubleForceDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        [
            $precision_formats,
            $currencies,
            $details
        ] = NumericalToolModel::createTestResource($another_user->id, []);
        model(NumericalToolModel::class)->delete($details->id, true);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v2/numerical_tools/$details->id/force");
        } catch (MissingResource $error) {
            $this->seeNumRecords(0, "numerical_tools_v2", []);
            throw $error;
        } catch (Throwable $error) {
            $this->assertTrue(false);
        }
    }
}
