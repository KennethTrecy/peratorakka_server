<?php

namespace Tests\Feature\Libraries;

use App\Libraries\Context;
use App\Libraries\Context\AccountCache;
use App\Libraries\Context\ModifierCache;
use App\Libraries\Context\ModifierAtomCache;
use App\Libraries\FinancialEntryAtomInputExaminer;
use App\Models\ModifierAtomActivityModel;
use App\Models\FinancialEntryModel;
use Tests\Feature\Helper\AuthenticatedContextualHTTPTestCase;

class FinancialEntryAtomInputExaminerTest extends AuthenticatedContextualHTTPTestCase
{
    public function testValidateCurrencyValuesForCorrectRecord()
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
        [
            $debited_general_asset_atom,
            $credited_liquid_asset_atom
        ] = $modifier_atoms;
        $key = "atoms";
        $modifier_id = $modifiers[0]->id;
        $data = [
            $key => [
                [
                    "modifier_atom_id" => $debited_general_asset_atom->id,
                    "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
                    "numerical_value" => "100"
                ],
                [
                    "modifier_atom_id" => $credited_liquid_asset_atom->id,
                    "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
                    "numerical_value" => "100"
                ]
            ]
        ];

        $context = Context::make();
        $financial_entry_atom_input_examiner = FinancialEntryAtomInputExaminer::make($key, $data);
        $is_valid = $financial_entry_atom_input_examiner->validateCurrencyValues($modifier_id);

        $this->assertTrue($is_valid);
    }

    public function testValidateCurrencyValuesForCorrectQuantifiedTotalBid()
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
                        null,
                        0
                    ]
                ]
            ],
            "modifier_atom_options" => [
                "parent_modifiers" => $modifiers
            ]
        ]);
        [
            $debited_itemized_asset_atom,
            $credited_liquid_asset_atom
        ] = $modifier_atoms;
        $key = "atoms";
        $modifier_id = $modifiers[0]->id;
        $data = [
            $key => [
                [
                    "modifier_atom_id" => $debited_itemized_asset_atom->id,
                    "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
                    "numerical_value" => "100"
                ],
                [
                    "modifier_atom_id" => $debited_itemized_asset_atom->id,
                    "kind" => QUANTITY_FINANCIAL_ENTRY_ATOM_KIND,
                    "numerical_value" => "10"
                ],
                [
                    "modifier_atom_id" => $credited_liquid_asset_atom->id,
                    "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
                    "numerical_value" => "100"
                ]
            ]
        ];

        $context = Context::make();
        $financial_entry_atom_input_examiner = FinancialEntryAtomInputExaminer::make($key, $data);
        $is_valid = $financial_entry_atom_input_examiner->validateCurrencyValues($modifier_id);

        $this->assertTrue($is_valid);
    }

    public function testValidateCurrencyValuesForCorrectPricedQuantityBid()
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
                        null,
                        0
                    ]
                ]
            ],
            "modifier_atom_options" => [
                "parent_modifiers" => $modifiers
            ]
        ]);
        [
            $debited_itemized_asset_atom,
            $credited_liquid_asset_atom
        ] = $modifier_atoms;
        $key = "atoms";
        $modifier_id = $modifiers[0]->id;
        $data = [
            $key => [
                [
                    "modifier_atom_id" => $debited_itemized_asset_atom->id,
                    "kind" => PRICE_FINANCIAL_ENTRY_ATOM_KIND,
                    "numerical_value" => "20"
                ],
                [
                    "modifier_atom_id" => $debited_itemized_asset_atom->id,
                    "kind" => QUANTITY_FINANCIAL_ENTRY_ATOM_KIND,
                    "numerical_value" => "5"
                ],
                [
                    "modifier_atom_id" => $credited_liquid_asset_atom->id,
                    "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
                    "numerical_value" => "100"
                ]
            ]
        ];

        $context = Context::make();
        $financial_entry_atom_input_examiner = FinancialEntryAtomInputExaminer::make($key, $data);
        $is_valid = $financial_entry_atom_input_examiner->validateCurrencyValues($modifier_id);

        $this->assertTrue($is_valid);
    }

    public function testValidateCurrencyValuesForCorrectPricedTotalBid()
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
                        null,
                        0
                    ]
                ]
            ],
            "modifier_atom_options" => [
                "parent_modifiers" => $modifiers
            ]
        ]);
        [
            $debited_itemized_asset_atom,
            $credited_liquid_asset_atom
        ] = $modifier_atoms;
        $key = "atoms";
        $modifier_id = $modifiers[0]->id;
        $data = [
            $key => [
                [
                    "modifier_atom_id" => $debited_itemized_asset_atom->id,
                    "kind" => PRICE_FINANCIAL_ENTRY_ATOM_KIND,
                    "numerical_value" => "20"
                ],
                [
                    "modifier_atom_id" => $debited_itemized_asset_atom->id,
                    "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
                    "numerical_value" => "100"
                ],
                [
                    "modifier_atom_id" => $credited_liquid_asset_atom->id,
                    "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
                    "numerical_value" => "100"
                ]
            ]
        ];

        $context = Context::make();
        $financial_entry_atom_input_examiner = FinancialEntryAtomInputExaminer::make($key, $data);
        $is_valid = $financial_entry_atom_input_examiner->validateCurrencyValues($modifier_id);

        $this->assertTrue($is_valid);
    }

    public function testValidateCurrencyValuesForCorrectQuantifiedTotalAsk()
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
                        REAL_EMERGENT_MODIFIER_ATOM_KIND
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
        [
            $debited_liquid_asset_atom,
            $credited_itemized_asset_atom
        ] = $modifier_atoms;
        $key = "atoms";
        $modifier_id = $modifiers[0]->id;
        $data = [
            $key => [
                [
                    "modifier_atom_id" => $debited_liquid_asset_atom->id,
                    "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
                    "numerical_value" => "100"
                ],
                [
                    "modifier_atom_id" => $credited_itemized_asset_atom->id,
                    "kind" => QUANTITY_FINANCIAL_ENTRY_ATOM_KIND,
                    "numerical_value" => "10"
                ],
                [
                    "modifier_atom_id" => $credited_itemized_asset_atom->id,
                    "kind" => TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
                    "numerical_value" => "100"
                ]
            ]
        ];

        $context = Context::make();
        $financial_entry_atom_input_examiner = FinancialEntryAtomInputExaminer::make($key, $data);
        $is_valid = $financial_entry_atom_input_examiner->validateCurrencyValues($modifier_id);

        $this->assertTrue($is_valid);
    }

    public function testValidateCurrencyValuesForCorrectDilute()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $modifiers,
            $details
        ] = FinancialEntryModel::makeTestResource($authenticated_info->getUser()->id, [
            "modifier_options" => [ "expected_actions" => [ DILUTE_MODIFIER_ACTION ] ]
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
                    DILUTE_MODIFIER_ACTION,
                    [
                        REAL_DEBITEM_MODIFIER_ATOM_KIND
                    ],
                    [
                        ITEMIZED_ASSET_ACCOUNT_KIND
                    ],
                    [
                        null
                    ]
                ]
            ],
            "modifier_atom_options" => [
                "parent_modifiers" => $modifiers
            ]
        ]);
        [
            $debited_itemized_asset_atom
        ] = $modifier_atoms;
        $key = "atoms";
        $modifier_id = $modifiers[0]->id;
        $data = [
            $key => [
                [
                    "modifier_atom_id" => $debited_itemized_asset_atom->id,
                    "kind" => QUANTITY_FINANCIAL_ENTRY_ATOM_KIND,
                    "numerical_value" => "10"
                ]
            ]
        ];

        $context = Context::make();
        $financial_entry_atom_input_examiner = FinancialEntryAtomInputExaminer::make($key, $data);
        $is_valid = $financial_entry_atom_input_examiner->validateCurrencyValues($modifier_id);

        $this->assertTrue($is_valid);
    }

    public function testValidateCurrencyValuesForCorrectCondense()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $modifiers,
            $details
        ] = FinancialEntryModel::makeTestResource($authenticated_info->getUser()->id, [
            "modifier_options" => [ "expected_actions" => [ CONDENSE_MODIFIER_ACTION ] ]
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
                    CONDENSE_MODIFIER_ACTION,
                    [
                        REAL_CREDITEM_MODIFIER_ATOM_KIND
                    ],
                    [
                        ITEMIZED_ASSET_ACCOUNT_KIND
                    ],
                    [
                        null
                    ]
                ]
            ],
            "modifier_atom_options" => [
                "parent_modifiers" => $modifiers
            ]
        ]);
        [
            $credited_itemized_asset_atom
        ] = $modifier_atoms;
        $key = "atoms";
        $modifier_id = $modifiers[0]->id;
        $data = [
            $key => [
                [
                    "modifier_atom_id" => $credited_itemized_asset_atom->id,
                    "kind" => QUANTITY_FINANCIAL_ENTRY_ATOM_KIND,
                    "numerical_value" => "10"
                ]
            ]
        ];

        $context = Context::make();
        $financial_entry_atom_input_examiner = FinancialEntryAtomInputExaminer::make($key, $data);
        $is_valid = $financial_entry_atom_input_examiner->validateCurrencyValues($modifier_id);

        $this->assertTrue($is_valid);
    }
}
