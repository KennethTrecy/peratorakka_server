<?php

namespace App\Controllers;

use CodeIgniter\Validation\Validation;

use App\Contracts\OwnedResource;
use App\Models\AccountModel;
use App\Models\ModifierModel;

class AccountController extends BaseOwnedResourceController
{
    protected static function getIndividualName(): string {
        return "modifier";
    }

    protected static function getCollectiveName(): string {
        return "modifiers";
    }

    protected static function getModelName(): string {
        return ModifierModel::class;
    }

    protected static function makeCreateValidation(): Validation {
        $validation = static::makeValidation();
        $individual_name = static::getIndividualName();
        $table_name = static::getCollectiveName();

        $validation->setRule("$individual_name.account_id", "currency", [
            "required",
            "is_natural_no_zero",
            "ensure_ownership[".implode(",", [
                AccountModel::class,
                SEARCH_NORMALLY
            ])."]"
        ]);
        $validation->setRule("$individual_name.opposite_account_id", "currency", [
            "required",
            "is_natural_no_zero",
            "ensure_ownership[".implode(",", [
                AccountModel::class,
                SEARCH_NORMALLY
            ])."]"
        ]);
        $validation->setRule("$individual_name.name", "name", [
            "required",
            "min_length[3]",
            "max_length[255]",
            "alpha_numeric_space",
            "is_unique[$table_name.name]"
        ]);

        return $validation;
    }

    protected static function makeUpdateValidation(int $id): Validation {
        $validation = static::makeValidation();
        $individual_name = static::getIndividualName();
        $table_name = static::getCollectiveName();

        $validation->setRule("$individual_name.name", "name", [
            "required",
            "min_length[3]",
            "max_length[255]",
            "alpha_numeric_space",
            "is_unique[$table_name.name,id,$id]"
        ]);

        return $validation;
    }

    private static function makeValidation(): Validation {
        $validation = single_service("validation");
        $individual_name = static::getIndividualName();

        $validation->setRule($individual_name, "account info", [
            "required"
        ]);
        $validation->setRule("$individual_name.description", "description", [
            "permit_empty",
            "max_length[500]",
            "alpha_numeric_punct"
        ]);
        $validation->setRule("$individual_name.result_side", "description", [
            "required",
            "min_length[3]",
            "max_length[10]",
            "in_list[".implode(",", RESULT_SIDES)."]"
        ]);
        $validation->setRule("$individual_name.kind", "description", [
            "required",
            "min_length[3]",
            "max_length[255]",
            "in_list[".implode(",", ACCEPTABLE_MODIFIER_KINDS)."]"
        ]);

        return $validation;
    }
}
