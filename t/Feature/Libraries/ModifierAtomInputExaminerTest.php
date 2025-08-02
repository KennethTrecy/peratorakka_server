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
}
