<?php

namespace Tests\Feature\Libraries;

use App\Libraries\Context;
use App\Libraries\Context\AccountCache;
use App\Libraries\ModifierAtomInputExaminer;
use App\Models\AccountModel;
use Tests\Feature\Helper\AuthenticatedContextualHTTPTestCase;

class ModifierAtomInputExaminerTest extends AuthenticatedContextualHTTPTestCase
{
    public function testValidateActionForCorrectRecord()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            [ $liquid_asset_account, $general_asset_account]
        ] = AccountModel::createTestResource($authenticated_info->getUser()->id, [
            "expected_kinds" => [ LIQUID_ASSET_ACCOUNT_KIND, GENERAL_ASSET_ACCOUNT_KIND ]
        ]);
        $key = "atoms";
        $data = [
            $key => [
                [
                    "kind" => REAL_DEBIT_MODIFIER_ATOM_KIND,
                    "account_id" => $liquid_asset_account->id
                ],
                [
                    "kind" => REAL_CREDIT_MODIFIER_ATOM_KIND,
                    "account_id" => $general_asset_account->id
                ]
            ]
        ];

        $context = Context::make();
        AccountCache::make($context)->addPreloadedResources([
            $liquid_asset_account,
            $general_asset_account
        ]);
        $modifier_atom_input_examiner = ModifierAtomInputExaminer::make($key, $data);
        $is_valid = $modifier_atom_input_examiner->validateAction(RECORD_MODIFIER_ACTION);

        $this->assertTrue($is_valid);
    }

    public function testValidateActionForCorrectBid()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            [ $itemized_asset_account, $general_asset_account ]
        ] = AccountModel::createTestResource($authenticated_info->getUser()->id, [
            "expected_kinds" => [ ITEMIZED_ASSET_ACCOUNT_KIND, GENERAL_ASSET_ACCOUNT_KIND ]
        ]);
        $key = "atoms";
        $data = [
            $key => [
                [
                    "kind" => REAL_DEBIT_MODIFIER_ATOM_KIND,
                    "account_id" => $itemized_asset_account->id
                ],
                [
                    "kind" => REAL_CREDIT_MODIFIER_ATOM_KIND,
                    "account_id" => $general_asset_account->id
                ]
            ]
        ];

        $context = Context::make();
        AccountCache::make($context)->addPreloadedResources([
            $itemized_asset_account,
            $general_asset_account
        ]);
        $modifier_atom_input_examiner = ModifierAtomInputExaminer::make($key, $data);
        $is_valid = $modifier_atom_input_examiner->validateAction(BID_MODIFIER_ACTION);

        $this->assertTrue($is_valid);
    }

    public function testValidateActionForCorrectAsk()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            [ $general_asset_account, $itemized_asset_account, $nominal_return_account ]
        ] = AccountModel::createTestResource($authenticated_info->getUser()->id, [
            "expected_kinds" => [
                GENERAL_ASSET_ACCOUNT_KIND,
                ITEMIZED_ASSET_ACCOUNT_KIND,
                NOMINAL_RETURN_ACCOUNT_KIND
            ]
        ]);
        $key = "atoms";
        $data = [
            $key => [
                [
                    "kind" => REAL_DEBIT_MODIFIER_ATOM_KIND,
                    "account_id" => $general_asset_account->id
                ],
                [
                    "kind" => REAL_CREDIT_MODIFIER_ATOM_KIND,
                    "account_id" => $itemized_asset_account->id
                ],
                [
                    "kind" => REAL_EMERGENT_MODIFIER_ATOM_KIND,
                    "account_id" => $nominal_return_account->id
                ]
            ]
        ];

        $context = Context::make();
        AccountCache::make($context)->addPreloadedResources([
            $itemized_asset_account,
            $general_asset_account,
            $nominal_return_account
        ]);
        $modifier_atom_input_examiner = ModifierAtomInputExaminer::make($key, $data);
        $is_valid = $modifier_atom_input_examiner->validateAction(ASK_MODIFIER_ACTION);

        $this->assertTrue($is_valid);
    }

    public function testValidateActionForCorrectDilute()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $itemized_asset_account
        ] = AccountModel::createTestResource($authenticated_info->getUser()->id, [
            "expected_kinds" => [
                ITEMIZED_ASSET_ACCOUNT_KIND
            ]
        ]);
        $key = "atoms";
        $data = [
            $key => [
                [
                    "kind" => REAL_DEBITEM_MODIFIER_ATOM_KIND,
                    "account_id" => $itemized_asset_account->id
                ]
            ]
        ];

        $context = Context::make();
        AccountCache::make($context)->addPreloadedResources([
            $itemized_asset_account
        ]);
        $modifier_atom_input_examiner = ModifierAtomInputExaminer::make($key, $data);
        $is_valid = $modifier_atom_input_examiner->validateAction(DILUTE_MODIFIER_ACTION);

        $this->assertTrue($is_valid);
    }

    public function testValidateActionForCorrectCondense()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $itemized_asset_account
        ] = AccountModel::createTestResource($authenticated_info->getUser()->id, [
            "expected_kinds" => [
                ITEMIZED_ASSET_ACCOUNT_KIND
            ]
        ]);
        $key = "atoms";
        $data = [
            $key => [
                [
                    "kind" => REAL_CREDITEM_MODIFIER_ATOM_KIND,
                    "account_id" => $itemized_asset_account->id
                ]
            ]
        ];

        $context = Context::make();
        AccountCache::make($context)->addPreloadedResources([
            $itemized_asset_account
        ]);
        $modifier_atom_input_examiner = ModifierAtomInputExaminer::make($key, $data);
        $is_valid = $modifier_atom_input_examiner->validateAction(CONDENSE_MODIFIER_ACTION);

        $this->assertTrue($is_valid);
    }

    public function testValidateCashFlowActivityAssociationsForCorrectRecord()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            [ $liquid_asset_account, $general_asset_account]
        ] = AccountModel::createTestResource($authenticated_info->getUser()->id, [
            "expected_kinds" => [ LIQUID_ASSET_ACCOUNT_KIND, GENERAL_ASSET_ACCOUNT_KIND ]
        ]);
        $key = "atoms";
        $data = [
            $key => [
                [
                    "kind" => REAL_DEBIT_MODIFIER_ATOM_KIND,
                    "account_id" => $liquid_asset_account->id
                ],
                [
                    "kind" => REAL_CREDIT_MODIFIER_ATOM_KIND,
                    "account_id" => $general_asset_account->id,
                    "cash_flow_activity_id" => 1
                ]
            ]
        ];

        $context = Context::make();
        AccountCache::make($context)->addPreloadedResources([
            $liquid_asset_account,
            $general_asset_account
        ]);
        $modifier_atom_input_examiner = ModifierAtomInputExaminer::make($key, $data);
        $is_valid = $modifier_atom_input_examiner->validateCashFlowActivityAssociations(
            RECORD_MODIFIER_ACTION
        );

        $this->assertTrue($is_valid);
    }

    public function testValidateCashFlowActivityAssociationsForIncorrectRecord()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            [ $liquid_asset_account, $general_asset_account]
        ] = AccountModel::createTestResource($authenticated_info->getUser()->id, [
            "expected_kinds" => [ LIQUID_ASSET_ACCOUNT_KIND, GENERAL_ASSET_ACCOUNT_KIND ]
        ]);
        $key = "atoms";
        $data = [
            $key => [
                [
                    "kind" => REAL_DEBIT_MODIFIER_ATOM_KIND,
                    "account_id" => $liquid_asset_account->id
                ],
                [
                    "kind" => REAL_CREDIT_MODIFIER_ATOM_KIND,
                    "account_id" => $general_asset_account->id
                ]
            ]
        ];

        $context = Context::make();
        AccountCache::make($context)->addPreloadedResources([
            $liquid_asset_account,
            $general_asset_account
        ]);
        $modifier_atom_input_examiner = ModifierAtomInputExaminer::make($key, $data);
        $is_valid = $modifier_atom_input_examiner->validateCashFlowActivityAssociations(
            RECORD_MODIFIER_ACTION
        );

        $this->assertFalse($is_valid);
    }

    public function testValidateCashFlowActivityAssociationsForCorrectBid()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            [ $itemized_asset_account, $general_asset_account ]
        ] = AccountModel::createTestResource($authenticated_info->getUser()->id, [
            "expected_kinds" => [ ITEMIZED_ASSET_ACCOUNT_KIND, GENERAL_ASSET_ACCOUNT_KIND ]
        ]);
        $key = "atoms";
        $data = [
            $key => [
                [
                    "kind" => REAL_DEBIT_MODIFIER_ATOM_KIND,
                    "account_id" => $itemized_asset_account->id,
                    "cash_flow_activity_id" => 1
                ],
                [
                    "kind" => REAL_CREDIT_MODIFIER_ATOM_KIND,
                    "account_id" => $general_asset_account->id,
                    "cash_flow_activity_id" => 1
                ]
            ]
        ];

        $context = Context::make();
        AccountCache::make($context)->addPreloadedResources([
            $itemized_asset_account,
            $general_asset_account
        ]);
        $modifier_atom_input_examiner = ModifierAtomInputExaminer::make($key, $data);
        $is_valid = $modifier_atom_input_examiner->validateCashFlowActivityAssociations(
            BID_MODIFIER_ACTION
        );

        $this->assertTrue($is_valid);
    }

    public function testValidateCashFlowActivityAssociationsForIncorrectBid()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            [ $itemized_asset_account, $general_asset_account ]
        ] = AccountModel::createTestResource($authenticated_info->getUser()->id, [
            "expected_kinds" => [ ITEMIZED_ASSET_ACCOUNT_KIND, GENERAL_ASSET_ACCOUNT_KIND ]
        ]);
        $key = "atoms";
        $data = [
            $key => [
                [
                    "kind" => REAL_DEBIT_MODIFIER_ATOM_KIND,
                    "account_id" => $itemized_asset_account->id
                ],
                [
                    "kind" => REAL_CREDIT_MODIFIER_ATOM_KIND,
                    "account_id" => $general_asset_account->id,
                    "cash_flow_activity_id" => 1
                ]
            ]
        ];

        $context = Context::make();
        AccountCache::make($context)->addPreloadedResources([
            $itemized_asset_account,
            $general_asset_account
        ]);
        $modifier_atom_input_examiner = ModifierAtomInputExaminer::make($key, $data);
        $is_valid = $modifier_atom_input_examiner->validateCashFlowActivityAssociations(
            BID_MODIFIER_ACTION
        );

        $this->assertFalse($is_valid);
    }

    public function testValidateCashFlowActivityAssociationsForCorrectAsk()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            [ $general_asset_account, $itemized_asset_account, $nominal_return_account ]
        ] = AccountModel::createTestResource($authenticated_info->getUser()->id, [
            "expected_kinds" => [
                GENERAL_ASSET_ACCOUNT_KIND,
                ITEMIZED_ASSET_ACCOUNT_KIND,
                NOMINAL_RETURN_ACCOUNT_KIND
            ]
        ]);
        $key = "atoms";
        $data = [
            $key => [
                [
                    "kind" => REAL_DEBIT_MODIFIER_ATOM_KIND,
                    "account_id" => $general_asset_account->id,
                    "cash_flow_activity_id" => 1
                ],
                [
                    "kind" => REAL_CREDIT_MODIFIER_ATOM_KIND,
                    "account_id" => $itemized_asset_account->id,
                    "cash_flow_activity_id" => 1
                ],
                [
                    "kind" => REAL_EMERGENT_MODIFIER_ATOM_KIND,
                    "account_id" => $nominal_return_account->id,
                    "cash_flow_activity_id" => 1
                ]
            ]
        ];

        $context = Context::make();
        AccountCache::make($context)->addPreloadedResources([
            $itemized_asset_account,
            $general_asset_account,
            $nominal_return_account
        ]);
        $modifier_atom_input_examiner = ModifierAtomInputExaminer::make($key, $data);
        $is_valid = $modifier_atom_input_examiner->validateCashFlowActivityAssociations(
            ASK_MODIFIER_ACTION
        );

        $this->assertTrue($is_valid);
    }

    public function testValidateCashFlowActivityAssociationsForIncorrectAsk()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            [ $general_asset_account, $itemized_asset_account, $nominal_return_account ]
        ] = AccountModel::createTestResource($authenticated_info->getUser()->id, [
            "expected_kinds" => [
                GENERAL_ASSET_ACCOUNT_KIND,
                ITEMIZED_ASSET_ACCOUNT_KIND,
                NOMINAL_RETURN_ACCOUNT_KIND
            ]
        ]);
        $key = "atoms";
        $data = [
            $key => [
                [
                    "kind" => REAL_DEBIT_MODIFIER_ATOM_KIND,
                    "account_id" => $general_asset_account->id,
                    "cash_flow_activity_id" => 1
                ],
                [
                    "kind" => REAL_CREDIT_MODIFIER_ATOM_KIND,
                    "account_id" => $itemized_asset_account->id,
                    "cash_flow_activity_id" => 1
                ],
                [
                    "kind" => REAL_EMERGENT_MODIFIER_ATOM_KIND,
                    "account_id" => $nominal_return_account->id
                ]
            ]
        ];

        $context = Context::make();
        AccountCache::make($context)->addPreloadedResources([
            $itemized_asset_account,
            $general_asset_account,
            $nominal_return_account
        ]);
        $modifier_atom_input_examiner = ModifierAtomInputExaminer::make($key, $data);
        $is_valid = $modifier_atom_input_examiner->validateCashFlowActivityAssociations(
            ASK_MODIFIER_ACTION
        );

        $this->assertFalse($is_valid);
    }

    public function testValidateCashFlowActivityAssociationsForCorrectDilute()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $itemized_asset_account
        ] = AccountModel::createTestResource($authenticated_info->getUser()->id, [
            "expected_kinds" => [
                ITEMIZED_ASSET_ACCOUNT_KIND
            ]
        ]);
        $key = "atoms";
        $data = [
            $key => [
                [
                    "kind" => REAL_DEBITEM_MODIFIER_ATOM_KIND,
                    "account_id" => $itemized_asset_account->id
                ]
            ]
        ];

        $context = Context::make();
        AccountCache::make($context)->addPreloadedResources([
            $itemized_asset_account
        ]);
        $modifier_atom_input_examiner = ModifierAtomInputExaminer::make($key, $data);
        $is_valid = $modifier_atom_input_examiner->validateCashFlowActivityAssociations(
            DILUTE_MODIFIER_ACTION
        );

        $this->assertTrue($is_valid);
    }

    public function testValidateCashFlowActivityAssociationsForIncorrectDilute()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $itemized_asset_account
        ] = AccountModel::createTestResource($authenticated_info->getUser()->id, [
            "expected_kinds" => [
                ITEMIZED_ASSET_ACCOUNT_KIND
            ]
        ]);
        $key = "atoms";
        $data = [
            $key => [
                [
                    "kind" => REAL_DEBITEM_MODIFIER_ATOM_KIND,
                    "account_id" => $itemized_asset_account->id,
                    "cash_flow_activity_id" => 1
                ]
            ]
        ];

        $context = Context::make();
        AccountCache::make($context)->addPreloadedResources([
            $itemized_asset_account
        ]);
        $modifier_atom_input_examiner = ModifierAtomInputExaminer::make($key, $data);
        $is_valid = $modifier_atom_input_examiner->validateCashFlowActivityAssociations(
            DILUTE_MODIFIER_ACTION
        );

        $this->assertFalse($is_valid);
    }

    public function testValidateCashFlowActivityAssociationsForCorrectCondense()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $itemized_asset_account
        ] = AccountModel::createTestResource($authenticated_info->getUser()->id, [
            "expected_kinds" => [
                ITEMIZED_ASSET_ACCOUNT_KIND
            ]
        ]);
        $key = "atoms";
        $data = [
            $key => [
                [
                    "kind" => REAL_CREDITEM_MODIFIER_ATOM_KIND,
                    "account_id" => $itemized_asset_account->id
                ]
            ]
        ];

        $context = Context::make();
        AccountCache::make($context)->addPreloadedResources([
            $itemized_asset_account
        ]);
        $modifier_atom_input_examiner = ModifierAtomInputExaminer::make($key, $data);
        $is_valid = $modifier_atom_input_examiner->validateCashFlowActivityAssociations(
            CONDENSE_MODIFIER_ACTION
        );

        $this->assertTrue($is_valid);
    }

    public function testValidateCashFlowActivityAssociationsForIncorrectCondense()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $precision_formats,
            $currencies,
            $itemized_asset_account
        ] = AccountModel::createTestResource($authenticated_info->getUser()->id, [
            "expected_kinds" => [
                ITEMIZED_ASSET_ACCOUNT_KIND
            ]
        ]);
        $key = "atoms";
        $data = [
            $key => [
                [
                    "kind" => REAL_CREDITEM_MODIFIER_ATOM_KIND,
                    "account_id" => $itemized_asset_account->id,
                    "cash_flow_activity_id" => 1
                ]
            ]
        ];

        $context = Context::make();
        AccountCache::make($context)->addPreloadedResources([
            $itemized_asset_account
        ]);
        $modifier_atom_input_examiner = ModifierAtomInputExaminer::make($key, $data);
        $is_valid = $modifier_atom_input_examiner->validateCashFlowActivityAssociations(
            CONDENSE_MODIFIER_ACTION
        );

        $this->assertFalse($is_valid);
    }
}
