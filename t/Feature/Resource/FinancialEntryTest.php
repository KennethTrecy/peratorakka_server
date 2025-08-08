<?php

namespace Tests\Feature\Resource;

use App\Exceptions\InvalidRequest;
use App\Exceptions\MissingResource;
use App\Models\AccountModel;
use App\Models\CashFlowActivityModel;
use App\Models\CurrencyModel;
use App\Models\FinancialEntryAtomModel;
use App\Models\FinancialEntryModel;
use App\Models\ModifierAtomActivityModel;
use App\Models\ModifierAtomModel;
use App\Models\ModifierModel;
use App\Models\PrecisionFormatModel;
use CodeIgniter\Test\Fabricator;
use Tests\Feature\Helper\AuthenticatedContextualHTTPTestCase;
use Throwable;

class FinancialEntryTest extends AuthenticatedContextualHTTPTestCase
{
    public function testDefaultIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $modifiers,
            $details
        ] = FinancialEntryModel::createTestResources($authenticated_info->getUser()->id, 10, []);

        $result = $authenticated_info->getRequest()->get("/api/v2/financial_entries");

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "overall_filtered_count" => 10
            ],
            "financial_entries" => json_decode(json_encode($details))
        ]);
    }

    public function testDefaultShow()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $modifiers,
            $details
        ] = FinancialEntryModel::createTestResource($authenticated_info->getUser()->id, []);

        $result = $authenticated_info->getRequest()->get("/api/v2/financial_entries/$details->id");

        $result->assertOk();
        $result->assertJSONExact([
            "financial_entry" => json_decode(json_encode($details))
        ]);
    }

    public function testDefaultCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $modifiers,
            $details
        ] = FinancialEntryModel::makeTestResource($authenticated_info->getUser()->id, [
            "modifier_options" => [ "expected_actions" => [ RECORD_MODIFIER_ACTION ] ]
        ]);
        [
            $precision_formats,
            $currencies,
            $accounts,
            $modifiers,
            $modifier_atoms,
            $cash_flow_activities,
            $modifier_atom_activities
        ] = ModifierAtomActivityModel::createTestResource($authenticated_info->getUser()->id, [
            "combinations" => [
                [
                    RECORD_MODIFIER_ACTION,
                    [
                        REAL_DEBIT_MODIFIER_ATOM_KIND,
                        REAL_CREDIT_MODIFIER_ATOM_KIND
                    ],
                    [
                        GENERAL_ASSET_ACCOUNT_KIND,
                        LIQUID_ASSET_ACCOUNT_KIND
                    ],
                    [
                        null,
                        0
                    ]
                ]
            ],
            "modifier_atom_options" => [
                "parent_modifiers" => $modifiers
            ]
        ]);

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/financial_entries", [
                "financial_entry" => array_merge(
                    $details->toArray(),
                    [
                        "@relationship" => [
                            "financial_entry_atoms" => array_map(fn ($atom) => [
                                "modifier_atom_id" => $atom->id,
                                "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
                                "numerical_value" => "100"
                            ], $modifier_atoms)
                        ]
                    ]
                )
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "financial_entry" => $details->toArray(),
            "financial_entry_atoms" => array_map(fn ($atom) => [
                "modifier_atom_id" => $atom->id,
                "numerical_value" => "100"
            ], $modifier_atoms)
        ]);
        $this->seeNumRecords(1, "financial_entries_v2", []);
        $this->seeNumRecords(1, "modifiers_v2", []);
        $this->seeNumRecords(2, "modifier_atoms", []);
        $this->seeNumRecords(2, "financial_entry_atoms", []);
        $this->seeNumRecords(1, "modifier_atom_activities", []);
    }

    public function testDefaultUpdate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $accounts,
            $modifiers,
            $modifier_atoms,
            $cash_flow_activities,
            $modifier_atom_activities,
            $financial_entries,
            $financial_entry_atoms
        ] = FinancialEntryAtomModel::createTestResource($authenticated_info->getUser()->id, [
            "modifier_atom_activity_options" => [
                "combinations" => [
                    [
                        RECORD_MODIFIER_ACTION,
                        [
                            REAL_DEBIT_MODIFIER_ATOM_KIND,
                            REAL_CREDIT_MODIFIER_ATOM_KIND
                        ],
                        [
                            GENERAL_ASSET_ACCOUNT_KIND,
                            LIQUID_ASSET_ACCOUNT_KIND
                        ],
                        [
                            null,
                            0
                        ]
                    ]
                ]
            ],
            "entries" => [
                [
                    RECORD_MODIFIER_ACTION,
                    REAL_DEBIT_MODIFIER_ATOM_KIND,
                    GENERAL_ASSET_ACCOUNT_KIND,
                    TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
                    "100"
                ],
                [
                    RECORD_MODIFIER_ACTION,
                    REAL_CREDIT_MODIFIER_ATOM_KIND,
                    LIQUID_ASSET_ACCOUNT_KIND,
                    TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
                    "100"
                ]
            ]
        ]);

        $details = $financial_entries[0];
        $new_details = $financial_entries[0];
        $new_subdetails = $financial_entry_atoms;

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v2/financial_entries/$details->id", [
                "financial_entry" => array_merge(
                    $new_details->toArray(),
                    [
                        "@relationship" => [
                            "financial_entry_atoms" => $new_subdetails
                        ]
                    ]
                )
            ]);

        $result->assertStatus(204);
        $this->seeInDatabase("financial_entries_v2", array_merge(
            [ "id" => $details->id ],
            $new_details->toRawArray()
        ));
    }

    public function testDefaultDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $modifiers,
            $details
        ] = FinancialEntryModel::createTestResource($authenticated_info->getUser()->id, []);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v2/financial_entries/$details->id");

        $result->assertStatus(204);
        $this->seeInDatabase("financial_entries_v2", array_merge(
            [ "id" => $details->id ]
        ));
        $this->dontSeeInDatabase("financial_entries_v2", [
            "id" => $details->id,
            "deleted_at" => null
        ]);
    }

    public function testDefaultRestore()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $modifiers,
            $details
        ] = FinancialEntryModel::createTestResource($authenticated_info->getUser()->id, []);
        model(FinancialEntryModel::class)->delete($details->id);

        $result = $authenticated_info
            ->getRequest()
            ->patch("/api/v2/financial_entries/$details->id");

        $result->assertStatus(204);
        $this->seeInDatabase("financial_entries_v2", [
            "id" => $details->id,
            "deleted_at" => null
        ]);
    }

    public function testDefaultForceDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $modifiers,
            $details
        ] = FinancialEntryModel::createTestResource($authenticated_info->getUser()->id, []);
        model(FinancialEntryModel::class)->delete($details->id);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v2/financial_entries/$details->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "financial_entries_v2", []);
    }

    public function testEmptyIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $result = $authenticated_info->getRequest()->get("/api/v2/financial_entries");

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "overall_filtered_count" => 0
            ],
            "financial_entries" => []
        ]);
    }

    public function testQueriedIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $modifiers,
            $details
        ] = FinancialEntryModel::createTestResources(
            $authenticated_info->getUser()->id,
            10,
            []
        );

        $result = $authenticated_info->getRequest()->get("/api/v2/financial_entries", [
            "page" => [
                "limit" => 5,
                "must_be_enriched" => true
            ]
        ]);

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "overall_filtered_count" => 10
            ],
            "financial_entries" => json_decode(json_encode(array_slice($details, 0, 5)))
        ]);
    }

    public function testMissingShow()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $modifiers,
            $details
        ] = FinancialEntryModel::createTestResource($authenticated_info->getUser()->id, []);
        $details->id = $details->id + 1;

        $this->expectException(MissingResource::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info->getRequest()->get("/api/v2/financial_entries/$details->id");
    }

    public function testInvalidCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $modifiers,
            $details
        ] = FinancialEntryModel::makeTestResource($authenticated_info->getUser()->id, [
            "overrides" => [
                "name" => "@only alphanumeric characters only"
            ]
        ]);

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/financial_entries", [
                "financial_entry" => $details->toArray()
            ]);
    }

    public function testDualCurrencyCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $modifiers,
            $details
        ] = FinancialEntryModel::makeTestResource($authenticated_info->getUser()->id, [
            "modifier_options" => [ "expected_actions" => [ EXCHANGE_MODIFIER_ACTION ] ]
        ]);
        [
            $precision_formats,
            $currencies,
            $general_asset_account
        ] = AccountModel::createTestResource($authenticated_info->getUser()->id, [
            "expected_kinds" => [ GENERAL_ASSET_ACCOUNT_KIND ]
        ]);
        [
            $precision_formats,
            $other_currencies,
            $liquid_asset_account
        ] = AccountModel::createTestResource($authenticated_info->getUser()->id, [
            "expected_kinds" => [ LIQUID_ASSET_ACCOUNT_KIND ],
            "currency_options" => [
                "precision_format_parent" => [ $precision_formats[0] ]
            ]
        ]);
        [
            $precision_formats,
            $currencies,
            $accounts,
            $modifiers,
            $modifier_atoms,
            $cash_flow_activities,
            $modifier_atom_activities
        ] = ModifierAtomActivityModel::createTestResource($authenticated_info->getUser()->id, [
            "combinations" => [
                [
                    EXCHANGE_MODIFIER_ACTION,
                    [
                        REAL_DEBIT_MODIFIER_ATOM_KIND,
                        REAL_CREDIT_MODIFIER_ATOM_KIND
                    ],
                    [
                        GENERAL_ASSET_ACCOUNT_KIND,
                        LIQUID_ASSET_ACCOUNT_KIND
                    ],
                    [
                        null,
                        0
                    ]
                ]
            ],
            "modifier_atom_options" => [
                "ancestor_accounts" => [
                    $precision_formats,
                    array_merge($currencies, $other_currencies),
                    [ $general_asset_account, $liquid_asset_account ]
                ],
                "parent_modifiers" => $modifiers
            ]
        ]);

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/financial_entries", [
                "financial_entry" => array_merge(
                    $details->toArray(),
                    [
                        "@relationship" => [
                            "financial_entry_atoms" => array_map(fn ($atom) => [
                                "modifier_atom_id" => $atom->id,
                                "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
                                "numerical_value" => "100"
                            ], $modifier_atoms)
                        ]
                    ]
                )
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "financial_entry" => $details->toArray(),
            "financial_entry_atoms" => array_map(fn ($atom) => [
                "modifier_atom_id" => $atom->id,
                "numerical_value" => "100"
            ], $modifier_atoms)
        ]);
        $this->seeNumRecords(1, "financial_entries_v2", []);
        $this->seeNumRecords(1, "modifiers_v2", []);
        $this->seeNumRecords(2, "modifier_atoms", []);
        $this->seeNumRecords(2, "financial_entry_atoms", []);
        $this->seeNumRecords(1, "modifier_atom_activities", []);
    }

    public function testPricedQuantityBidCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $modifiers,
            $details
        ] = FinancialEntryModel::makeTestResource($authenticated_info->getUser()->id, [
            "modifier_options" => [ "expected_actions" => [ BID_MODIFIER_ACTION ] ]
        ]);
        [
            $precision_formats,
            $currencies,
            $accounts,
            $modifiers,
            $modifier_atoms,
            $cash_flow_activities,
            $modifier_atom_activities
        ] = ModifierAtomActivityModel::createTestResource($authenticated_info->getUser()->id, [
            "combinations" => [
                [
                    BID_MODIFIER_ACTION,
                    [
                        REAL_DEBIT_MODIFIER_ATOM_KIND,
                        REAL_CREDIT_MODIFIER_ATOM_KIND
                    ],
                    [
                        ITEMIZED_ASSET_ACCOUNT_KIND,
                        LIQUID_ASSET_ACCOUNT_KIND
                    ],
                    [
                        0,
                        null
                    ]
                ]
            ],
            "modifier_atom_options" => [
                "parent_modifiers" => $modifiers
            ]
        ]);

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/financial_entries", [
                "financial_entry" => array_merge(
                    $details->toArray(),
                    [
                        "@relationship" => [
                            "financial_entry_atoms" => [
                                [
                                    "modifier_atom_id" => $modifier_atoms[0]->id,
                                    "kind" => PRICE_FINANCIAL_ENTRY_ATOM_KIND,
                                    "numerical_value" => "10"
                                ],
                                [
                                    "modifier_atom_id" => $modifier_atoms[0]->id,
                                    "kind" => QUANTITY_FINANCIAL_ENTRY_ATOM_KIND,
                                    "numerical_value" => "5"
                                ],
                                [
                                    "modifier_atom_id" => $modifier_atoms[1]->id,
                                    "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
                                    "numerical_value" => "50"
                                ]
                            ]
                        ]
                    ]
                )
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "financial_entry" => $details->toArray(),
            "financial_entry_atoms" => [
                [
                    "modifier_atom_id" => $modifier_atoms[0]->id,
                    "kind" => PRICE_FINANCIAL_ENTRY_ATOM_KIND,
                    "numerical_value" => "10"
                ],
                [
                    "modifier_atom_id" => $modifier_atoms[0]->id,
                    "kind" => QUANTITY_FINANCIAL_ENTRY_ATOM_KIND,
                    "numerical_value" => "5"
                ],
                [
                    "modifier_atom_id" => $modifier_atoms[1]->id,
                    "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
                    "numerical_value" => "50"
                ]
            ]
        ]);
        $this->seeNumRecords(1, "financial_entries_v2", []);
        $this->seeNumRecords(1, "modifiers_v2", []);
        $this->seeNumRecords(2, "modifier_atoms", []);
        $this->seeNumRecords(3, "financial_entry_atoms", []);
        $this->seeNumRecords(1, "modifier_atom_activities", []);
    }

    public function testPricedQuantityAskCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $modifiers,
            $details
        ] = FinancialEntryModel::makeTestResource($authenticated_info->getUser()->id, [
            "modifier_options" => [ "expected_actions" => [ ASK_MODIFIER_ACTION ] ]
        ]);
        [
            $precision_formats,
            $currencies,
            $accounts,
            $modifiers,
            $modifier_atoms,
            $cash_flow_activities,
            $modifier_atom_activities
        ] = ModifierAtomActivityModel::createTestResource($authenticated_info->getUser()->id, [
            "combinations" => [
                [
                    ASK_MODIFIER_ACTION,
                    [
                        REAL_DEBIT_MODIFIER_ATOM_KIND,
                        REAL_CREDIT_MODIFIER_ATOM_KIND,
                        REAL_CREDIT_MODIFIER_ATOM_KIND
                    ],
                    [
                        LIQUID_ASSET_ACCOUNT_KIND,
                        ITEMIZED_ASSET_ACCOUNT_KIND,
                        NOMINAL_RETURN_ACCOUNT_KIND
                    ],
                    [
                        null,
                        0,
                        0
                    ]
                ]
            ],
            "modifier_atom_options" => [
                "parent_modifiers" => $modifiers
            ]
        ]);

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/financial_entries", [
                "financial_entry" => array_merge(
                    $details->toArray(),
                    [
                        "@relationship" => [
                            "financial_entry_atoms" => [
                                [
                                    "modifier_atom_id" => $modifier_atoms[0]->id,
                                    "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
                                    "numerical_value" => "10"
                                ],
                                [
                                    "modifier_atom_id" => $modifier_atoms[1]->id,
                                    "kind" => QUANTITY_FINANCIAL_ENTRY_ATOM_KIND,
                                    "numerical_value" => "5"
                                ],
                                [
                                    "modifier_atom_id" => $modifier_atoms[1]->id,
                                    "kind" => PRICE_FINANCIAL_ENTRY_ATOM_KIND,
                                    "numerical_value" => "2"
                                ]
                            ]
                        ]
                    ]
                )
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "financial_entry" => $details->toArray(),
            "financial_entry_atoms" => [
                [
                    "modifier_atom_id" => $modifier_atoms[0]->id,
                    "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
                    "numerical_value" => "10"
                ],
                [
                    "modifier_atom_id" => $modifier_atoms[1]->id,
                    "kind" => QUANTITY_FINANCIAL_ENTRY_ATOM_KIND,
                    "numerical_value" => "5"
                ],
                [
                    "modifier_atom_id" => $modifier_atoms[1]->id,
                    "kind" => PRICE_FINANCIAL_ENTRY_ATOM_KIND,
                    "numerical_value" => "2"
                ]
            ]
        ]);
        $this->seeNumRecords(1, "financial_entries_v2", []);
        $this->seeNumRecords(1, "modifiers_v2", []);
        $this->seeNumRecords(3, "modifier_atoms", []);
        $this->seeNumRecords(3, "financial_entry_atoms", []);
        $this->seeNumRecords(2, "modifier_atom_activities", []);
    }

    // TODO: Make test to confirm error of updating entry within frozen period

    public function testInvalidUpdate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $modifiers,
            $details,
            $new_details
        ] = FinancialEntryModel::createAndMakeTestResources(
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
            ->put("/api/v2/financial_entries/$details->id", [
                "financial_entry" => $new_details->toArray()
            ]);
    }

    public function testUnownedDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        [
            $modifiers,
            $details
        ] = FinancialEntryModel::createTestResource($another_user->id, []);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v2/financial_entries/$details->id");
            $this->assertTrue(false);
        } catch (MissingResource $error) {
            $this->seeInDatabase("financial_entries_v2", array_merge(
                [ "id" => $details->id ]
            ));
            $this->seeInDatabase("financial_entries_v2", [
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
            $modifiers,
            $details
        ] = FinancialEntryModel::createTestResource($another_user->id, []);
        model(FinancialEntryModel::class)->delete($details->id);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v2/financial_entries/$details->id");
        } catch (MissingResource $error) {
            $this->seeInDatabase("financial_entries_v2", array_merge(
                [ "id" => $details->id ]
            ));
            $this->dontSeeInDatabase("financial_entries_v2", [
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
            $modifiers,
            $details
        ] = FinancialEntryModel::createTestResource($another_user->id, []);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->patch("/api/v2/financial_entries/$details->id");
        } catch (MissingResource $error) {
            $this->seeInDatabase("financial_entries_v2", [
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
            $modifiers,
            $details
        ] = FinancialEntryModel::createTestResource($authenticated_info->getUser()->id, []);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v2/financial_entries/$details->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "financial_entries_v2", []);
    }

    public function testDoubleForceDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        [
            $modifiers,
            $details
        ] = FinancialEntryModel::createTestResource($another_user->id, []);
        model(FinancialEntryModel::class)->delete($details->id, true);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v2/financial_entries/$details->id/force");
        } catch (MissingResource $error) {
            $this->seeNumRecords(0, "financial_entries_v2", []);
            throw $error;
        } catch (Throwable $error) {
            $this->assertTrue(false);
        }
    }
}
