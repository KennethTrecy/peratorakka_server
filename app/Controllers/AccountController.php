<?php

namespace App\Controllers;

use CodeIgniter\Validation\Validation;

use App\Contracts\OwnedResource;
use App\Models\AccountModel;

class AccountController extends BaseOwnedResourceController
{
    protected static function getIndividualName(): string {
        return "account";
    }

    protected static function getCollectiveName(): string {
        return "accounts";
    }

    protected static function getModelName(): string {
        return AccountModel::class;
    }

    protected static function makeCreateValidation(): Validation {
        $validation = static::makeValidation();
        $individual_name = static::getIndividualName();
        $table_name = static::getCollectiveName();
        $currency_table_name = CurrencyController::getInfo()->getCollectiveName();

        $validation->setRule("$individual_name.currency_id", "currency", [
            "required",
            "is_natural_no_zero",
            "is_unique[$currency_table_name.id]"
        ]);
        $validation->setRule("$individual_name.name", "name", [
            "required",
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
            "alpha_numeric_punct"
        ]);
        $validation->setRule("$individual_name.kind", "description", [
            "required",
            "alpha",
            "in_list[".implode(",", ACCEPTABLE_ACCOUNT_KINDS)."]"
        ]);

        return $validation;
    }
}
