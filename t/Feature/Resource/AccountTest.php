<?php

namespace Tests\Feature\Resource;

use App\Casts\AccountKind;
use App\Exceptions\InvalidRequest;
use App\Exceptions\MissingResource;
use App\Models\AccountModel;
use App\Models\CurrencyModel;
use App\Models\ItemDetailModel;
use App\Models\PrecisionFormatModel;
use App\Models\FrozenPeriodModel;
use CodeIgniter\I18n\Time;
use Tests\Feature\Helper\AuthenticatedContextualHTTPTestCase;
use Throwable;

class AccountTest extends AuthenticatedContextualHTTPTestCase
{
    public function testDefaultIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $accounts
        ] = AccountModel::createTestResources(
            $authenticated_info->getUser()->id,
            10,
            []
        );

        $result = $authenticated_info->getRequest()->get("/api/v2/accounts");

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "overall_filtered_count" => 10
            ],
            "accounts" => json_decode(json_encode($accounts))
        ]);
    }

    public function testDefaultShow()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $details
        ] = AccountModel::createTestResource($authenticated_info->getUser()->id, []);

        $result = $authenticated_info->getRequest()->get("/api/v2/accounts/$details->id");

        $result->assertOk();
        $result->assertJSONExact([
            "account" => json_decode(json_encode($details))
        ]);
    }

    public function testDefaultCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $details
        ] = AccountModel::makeTestResource($authenticated_info->getUser()->id, [
            "overrides" => [
                "kind" => GENERAL_ASSET_ACCOUNT_KIND
            ]
        ]);

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/accounts", [
                "account" => $details->toArray()
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "account" => $details->toArray()
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
        ] = AccountModel::createAndMakeTestResources(
            $authenticated_info->getUser()->id,
            [
                "overrides" => [
                    "kind" => GENERAL_ASSET_ACCOUNT_KIND
                ]
            ]
        );

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v2/accounts/$details->id", [
                "account" => array_merge($new_details->toArray(), [
                    "kind" => GENERAL_EXPENSE_ACCOUNT_KIND
                ])
            ]);

        $result->assertStatus(204);
        $this->seeInDatabase("accounts_v2", array_merge(
            [ "id" => $details->id ],
            $new_details->toRawArray(),
            [ "kind" => AccountKind::set(GENERAL_EXPENSE_ACCOUNT_KIND) ]
        ));
    }

    public function testDefaultDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $details
        ] = AccountModel::createTestResource($authenticated_info->getUser()->id, []);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v2/accounts/$details->id");

        $result->assertStatus(204);
        $this->seeInDatabase("accounts_v2", array_merge(
            [ "id" => $details->id ]
        ));
        $this->dontSeeInDatabase("accounts_v2", [
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
        ] = AccountModel::createTestResource($authenticated_info->getUser()->id, []);
        model(AccountModel::class)->delete($details->id);

        $result = $authenticated_info
            ->getRequest()
            ->patch("/api/v2/accounts/$details->id");

        $result->assertStatus(204);
        $this->seeInDatabase("accounts_v2", [
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
        ] = AccountModel::createTestResource($authenticated_info->getUser()->id, []);
        model(AccountModel::class)->delete($details->id);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v2/accounts/$details->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "accounts_v2", []);
    }

    public function testEmptyIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $result = $authenticated_info->getRequest()->get("/api/v2/accounts");

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "overall_filtered_count" => 0
            ],
            "accounts" => []
        ]);
    }

    public function testQueriedIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $details
        ] = AccountModel::createTestResources(
            $authenticated_info->getUser()->id,
            10,
            []
        );

        $result = $authenticated_info->getRequest()->get("/api/v2/accounts", [
            "page" => [
                "limit" => 5
            ],
            "relationship" => [
                "precision_formats",
                "currencies",
                "item_configurations"
            ]
        ]);

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "overall_filtered_count" => 10
            ],
            "accounts" => json_decode(json_encode(array_slice($details, 0, 5))),
            "currencies" => json_decode(json_encode($currencies)),
            "precision_formats" => json_decode(json_encode($precision_formats)),
            "item_configurations" => []
        ]);
    }

    public function testMissingShow()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $details
        ] = AccountModel::createTestResource($authenticated_info->getUser()->id, []);
        $details->id = $details->id + 1;

        $this->expectException(MissingResource::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info->getRequest()->get("/api/v2/accounts/$details->id");
    }

    public function testInvalidCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $details
        ] = AccountModel::makeTestResource($authenticated_info->getUser()->id, [
            "overrides" => [
                "name" => "@only alphanumeric characters only"
            ]
        ]);

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/accounts", [
                "account" => $details->toArray()
            ]);
    }

    public function testIncompleteCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $details
        ] = AccountModel::makeTestResource($authenticated_info->getUser()->id, [
            "overrides" => [
                "kind" => ITEMIZED_ASSET_ACCOUNT_KIND
            ]
        ]);

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/accounts", [
                "account" => $details->toArray()
            ]);
    }

    public function testMalformedCompositeCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $details
        ] = AccountModel::makeTestResource($authenticated_info->getUser()->id, [
            "overrides" => [
                "kind" => ITEMIZED_ASSET_ACCOUNT_KIND
            ]
        ]);

        [
            $precision_formats,
            $item_detail_info
        ] = ItemDetailModel::createTestResource($authenticated_info->getUser()->id, [
            "precision_format_parent" => $precision_formats
        ]);

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/accounts", [
                "account" => array_merge($details->toArray(), [
                    "@relationship" => [
                        "item_configuration" => [
                            "item_detail_id" => $item_detail_info->id,
                            "valuation_method" => UNKNOWN_VALUATION_METHOD
                        ]
                    ]
                ])
            ]);
    }

    public function testCorrectCompositeCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $details
        ] = AccountModel::makeTestResource($authenticated_info->getUser()->id, [
            "overrides" => [
                "kind" => ITEMIZED_ASSET_ACCOUNT_KIND
            ]
        ]);

        [
            $precision_formats,
            $item_detail_info
        ] = ItemDetailModel::createTestResource($authenticated_info->getUser()->id, [
            "precision_format_parent" => $precision_formats
        ]);

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/accounts", [
                "account" => array_merge($details->toArray(), [
                    "@relationship" => [
                        "item_configuration" => [
                            "item_detail_id" => $item_detail_info->id,
                            "valuation_method" => WEIGHTED_AVERAGE_VALUATION_METHOD
                        ]
                    ]
                ])
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "account" => $details->toArray(),
            "item_configuration" => [
                "item_detail_id" => $item_detail_info->id,
                "valuation_method" => WEIGHTED_AVERAGE_VALUATION_METHOD
            ]
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
        ] = AccountModel::createAndMakeTestResources(
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
            ->put("/api/v2/accounts/$details->id", [
                "account" => $new_details->toArray()
            ]);
    }

    public function testProhibitedUpdate()
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
        $equity_account = $accounts[0];
        [
            $precision_formats,
            $currencies,
            $new_details
        ] = AccountModel::makeTestResource($authenticated_info->getUser()->id, [
            "ancestor_currency" => [ $precision_formats, $currencies[0] ],
            "overrides" => [
                "kind" => GENERAL_ASSET_ACCOUNT_KIND
            ]
        ]);

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v2/accounts/$equity_account->id", [
                "account" => $new_details->toArray()
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
        ] = AccountModel::createTestResource($another_user->id, []);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v2/accounts/$details->id");
            $this->assertTrue(false);
        } catch (MissingResource $error) {
            $this->seeInDatabase("accounts_v2", array_merge(
                [ "id" => $details->id ]
            ));
            $this->seeInDatabase("accounts_v2", [
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
        ] = AccountModel::createTestResource($another_user->id, []);
        model(AccountModel::class)->delete($details->id);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v2/accounts/$details->id");
        } catch (MissingResource $error) {
            $this->seeInDatabase("accounts_v2", array_merge(
                [ "id" => $details->id ]
            ));
            $this->dontSeeInDatabase("accounts_v2", [
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
        ] = AccountModel::createTestResource($another_user->id, []);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->patch("/api/v2/accounts/$details->id");
        } catch (MissingResource $error) {
            $this->seeInDatabase("accounts_v2", [
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
        ] = AccountModel::createTestResource($authenticated_info->getUser()->id, []);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v2/accounts/$details->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "accounts_v2", []);
    }

    public function testDoubleForceDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        [
            $precision_formats,
            $currencies,
            $details
        ] = AccountModel::createTestResource($another_user->id, []);
        model(AccountModel::class)->delete($details->id, true);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v2/accounts/$details->id/force");
        } catch (MissingResource $error) {
            $this->seeNumRecords(0, "accounts_v2", []);
            throw $error;
        } catch (Throwable $error) {
            $this->assertTrue(false);
        }
    }
}
