<?php

namespace Tests\Feature\Resource;

use App\Exceptions\InvalidRequest;
use App\Exceptions\MissingResource;
use App\Models\AccountModel;
use App\Models\CashFlowActivityModel;
use App\Models\CurrencyModel;
use App\Models\ModifierModel;
use App\Models\PrecisionFormatModel;
use CodeIgniter\Test\Fabricator;
use Tests\Feature\Helper\AuthenticatedContextualHTTPTestCase;
use Throwable;

class ModifierTest extends AuthenticatedContextualHTTPTestCase
{
    public function testDefaultIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $modifiers
        ] = ModifierModel::createTestResources(
            $authenticated_info->getUser()->id,
            10,
            []
        );

        $result = $authenticated_info->getRequest()->get("/api/v2/modifiers");

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "overall_filtered_count" => 10
            ],
            "modifiers" => json_decode(json_encode($modifiers))
        ]);
    }

    public function testDefaultShow()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details
        ] = ModifierModel::createTestResource($authenticated_info->getUser()->id, []);

        $result = $authenticated_info->getRequest()->get("/api/v2/modifiers/$details->id");

        $result->assertOk();
        $result->assertJSONExact([
            "modifier" => json_decode(json_encode($details))
        ]);
    }

    public function testDefaultCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            [ $asset_account, $equity_account ]
        ] = AccountModel::createTestResource($authenticated_info->getUser()->id, [
            "expected_kinds" => [ LIQUID_ASSET_ACCOUNT_KIND, EQUITY_ACCOUNT_KIND ]
        ]);
        [
            $cash_flow_activity
        ] = CashFlowActivityModel::createTestResource($authenticated_info->getUser()->id, []);
        [
            $details
        ] = ModifierModel::makeTestResource($authenticated_info->getUser()->id, []);

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/modifiers", [
                "modifier" => array_merge(
                    $details->toArray(),
                    [
                        "modifier_atoms" => [
                            [
                                "kind" => REAL_DEBIT_MODIFIER_ATOM_KIND,
                                "account_id" => $asset_account->id
                            ],
                            [
                                "kind" => REAL_CREDIT_MODIFIER_ATOM_KIND,
                                "account_id" => $equity_account->id,
                                "cash_flow_activity_id" => $cash_flow_activity->id
                            ]
                        ]
                    ]
                )
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "modifier" => $details->toArray(),
            "modifier_atoms" => [
                [
                    "account_id" => $asset_account->id,
                    "kind" => REAL_DEBIT_MODIFIER_ATOM_KIND
                ],
                [
                    "account_id" => $equity_account->id,
                    "kind" => REAL_CREDIT_MODIFIER_ATOM_KIND
                ]
            ],
            "modifier_atom_activities" => [
                [
                    "cash_flow_activity_id" => $cash_flow_activity->id
                ]
            ]
        ]);
        $this->seeNumRecords(1, "modifiers_v2", []);
        $this->seeNumRecords(2, "modifier_atoms", []);
        $this->seeNumRecords(1, "modifier_atom_activities", []);
    }

    public function testDefaultUpdate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details,
            $new_details
        ] = ModifierModel::createAndMakeTestResources(
            $authenticated_info->getUser()->id,
            []
        );

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v2/modifiers/$details->id", [
                "modifier" => $new_details->toArray()
            ]);

        $result->assertStatus(204);
        $this->seeInDatabase("modifiers_v2", array_merge(
            [ "id" => $details->id ],
            $new_details->toRawArray()
        ));
    }

    public function testDefaultDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details
        ] = ModifierModel::createTestResource($authenticated_info->getUser()->id, []);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v2/modifiers/$details->id");

        $result->assertStatus(204);
        $this->seeInDatabase("modifiers_v2", array_merge(
            [ "id" => $details->id ]
        ));
        $this->dontSeeInDatabase("modifiers_v2", [
            "id" => $details->id,
            "deleted_at" => null
        ]);
    }

    public function testDefaultRestore()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details
        ] = ModifierModel::createTestResource($authenticated_info->getUser()->id, []);
        model(ModifierModel::class)->delete($details->id);

        $result = $authenticated_info
            ->getRequest()
            ->patch("/api/v2/modifiers/$details->id");

        $result->assertStatus(204);
        $this->seeInDatabase("modifiers_v2", [
            "id" => $details->id,
            "deleted_at" => null
        ]);
    }

    public function testDefaultForceDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details
        ] = ModifierModel::createTestResource($authenticated_info->getUser()->id, []);
        model(ModifierModel::class)->delete($details->id);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v2/modifiers/$details->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "modifiers_v2", []);
    }

    public function testEmptyIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $result = $authenticated_info->getRequest()->get("/api/v2/modifiers");

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "overall_filtered_count" => 0
            ],
            "modifiers" => []
        ]);
    }

    public function testQueriedIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details
        ] = ModifierModel::createTestResources(
            $authenticated_info->getUser()->id,
            10,
            []
        );

        $result = $authenticated_info->getRequest()->get("/api/v2/modifiers", [
            "page" => [
                "limit" => 5
            ],
            "relationship" => [
                "precision_formats",
                "currencies",
                "accounts"
            ]
        ]);

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "overall_filtered_count" => 10
            ],
            "modifiers" => json_decode(json_encode(array_slice($details, 0, 5)))
        ]);
    }

    public function testMissingShow()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details
        ] = ModifierModel::createTestResource($authenticated_info->getUser()->id, []);
        $details->id = $details->id + 1;

        $this->expectException(MissingResource::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info->getRequest()->get("/api/v2/modifiers/$details->id");
    }

    public function testInvalidCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details
        ] = ModifierModel::makeTestResource($authenticated_info->getUser()->id, [
            "overrides" => [
                "name" => "@only alphanumeric characters only"
            ]
        ]);

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/modifiers", [
                "modifier" => $details->toArray()
            ]);
    }

    public function testDualCurrencyCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $asset_account
        ] = AccountModel::createTestResource($authenticated_info->getUser()->id, [
            "expected_kinds" => [ LIQUID_ASSET_ACCOUNT_KIND ]
        ]);

        [
            $precision_formats,
            $other_currencies,
            $equity_account
        ] = AccountModel::createTestResource($authenticated_info->getUser()->id, [
            "expected_kinds" => [ EQUITY_ACCOUNT_KIND ],
            "currency_options" => [
                "precision_format_parent" => [ $precision_formats[0] ]
            ]
        ]);

        [
            $cash_flow_activity
        ] = CashFlowActivityModel::createTestResource($authenticated_info->getUser()->id, []);
        [
            $details
        ] = ModifierModel::makeTestResource($authenticated_info->getUser()->id, []);

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/modifiers", [
                "modifier" => array_merge(
                    $details->toArray(),
                    [
                        "modifier_atoms" => [
                            [
                                "kind" => REAL_DEBIT_MODIFIER_ATOM_KIND,
                                "account_id" => $asset_account->id
                            ],
                            [
                                "kind" => REAL_CREDIT_MODIFIER_ATOM_KIND,
                                "account_id" => $equity_account->id,
                                "cash_flow_activity_id" => $cash_flow_activity->id
                            ]
                        ]
                    ]
                )
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "modifier" => $details->toArray(),
            "modifier_atoms" => [
                [
                    "account_id" => $asset_account->id,
                    "kind" => REAL_DEBIT_MODIFIER_ATOM_KIND
                ],
                [
                    "account_id" => $equity_account->id,
                    "kind" => REAL_CREDIT_MODIFIER_ATOM_KIND
                ]
            ],
            "modifier_atom_activities" => [
                [
                    "cash_flow_activity_id" => $cash_flow_activity->id
                ]
            ]
        ]);
        $this->seeNumRecords(1, "modifiers_v2", []);
        $this->seeNumRecords(2, "modifier_atoms", []);
        $this->seeNumRecords(1, "modifier_atom_activities", []);
    }

    public function testPartiallyUnownedCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        [
            $precision_formats,
            $currencies,
            $asset_account
        ] = AccountModel::createTestResource($authenticated_info->getUser()->id, [
            "expected_kinds" => [ LIQUID_ASSET_ACCOUNT_KIND ]
        ]);

        [
            $other_precision_formats,
            $other_currencies,
            $equity_account
        ] = AccountModel::createTestResource($another_user->id, [
            "expected_kinds" => [ EQUITY_ACCOUNT_KIND ]
        ]);

        [
            $cash_flow_activity
        ] = CashFlowActivityModel::createTestResource($authenticated_info->getUser()->id, []);
        [
            $details
        ] = ModifierModel::makeTestResource($authenticated_info->getUser()->id, []);

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/modifiers", [
                "modifier" => array_merge(
                    $details->toArray(),
                    [
                        "modifier_atoms" => [
                            [
                                "kind" => REAL_DEBIT_MODIFIER_ATOM_KIND,
                                "account_id" => $asset_account->id
                            ],
                            [
                                "kind" => REAL_CREDIT_MODIFIER_ATOM_KIND,
                                "account_id" => $equity_account->id,
                                "cash_flow_activity_id" => $cash_flow_activity->id
                            ]
                        ]
                    ]
                )
            ]);
    }

    // TODO: Make test to confirm error of updating entry within frozen period

    public function testInvalidUpdate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details,
            $new_details
        ] = ModifierModel::createAndMakeTestResources(
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
            ->put("/api/v2/modifiers/$details->id", [
                "modifier" => $new_details->toArray()
            ]);
    }

    public function testUnownedUpdate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        [
            $details
        ] = ModifierModel::createTestResource(
            $another_user->id,
            []
        );
        [
            $new_details
        ] = ModifierModel::makeTestResource(
            $authenticated_info->getUser()->id,
            []
        );

        $this->expectException(MissingResource::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v2/modifiers/$details->id", [
                "modifier" => $new_details->toArray()
            ]);
    }

    public function testUnownedDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        [
            $details
        ] = ModifierModel::createTestResource($another_user->id, []);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v2/modifiers/$details->id");
            $this->assertTrue(false);
        } catch (MissingResource $error) {
            $this->seeInDatabase("modifiers_v2", array_merge(
                [ "id" => $details->id ]
            ));
            $this->seeInDatabase("modifiers_v2", [
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
            $details
        ] = ModifierModel::createTestResource($another_user->id, []);
        model(ModifierModel::class)->delete($details->id);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v2/modifiers/$details->id");
        } catch (MissingResource $error) {
            $this->seeInDatabase("modifiers_v2", array_merge(
                [ "id" => $details->id ]
            ));
            $this->dontSeeInDatabase("modifiers_v2", [
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
            $details
        ] = ModifierModel::createTestResource($another_user->id, []);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->patch("/api/v2/modifiers/$details->id");
        } catch (MissingResource $error) {
            $this->seeInDatabase("modifiers_v2", [
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
            $details
        ] = ModifierModel::createTestResource($authenticated_info->getUser()->id, []);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v2/modifiers/$details->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "modifiers_v2", []);
    }

    public function testDoubleForceDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        [
            $details
        ] = ModifierModel::createTestResource($another_user->id, []);
        model(ModifierModel::class)->delete($details->id, true);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v2/modifiers/$details->id/force");
        } catch (MissingResource $error) {
            $this->seeNumRecords(0, "modifiers_v2", []);
            throw $error;
        } catch (Throwable $error) {
            $this->assertTrue(false);
        }
    }
}
