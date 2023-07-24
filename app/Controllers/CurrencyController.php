<?php

namespace App\Controllers;

use CodeIgniter\Validation\Validation;

use App\Contracts\OwnedResource;
use App\Models\CurrencyModel;

class CurrencyController extends BaseOwnedResourceController
{
    protected static function getIndividualName(): string {
        return "currency";
    }

    protected static function getCollectiveName(): string {
        return "currencies";
    }

    protected static function getModelName(): string {
        return CurrencyModel::class;
    }

    protected static function makeCreateValidation(): Validation {
        $validation = static::makeValidation();
        $individual_name = static::getIndividualName();
        $table_name = static::getCollectiveName();

        $validation->setRule("$individual_name.code", "code", [
            "required",
            "alpha_numeric_space",
            "is_unique[$table_name.code]"
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

        $validation->setRule("$individual_name.code", "code", [
            "required",
            "alpha_numeric_space",
            "is_unique[$table_name.code,id,$id]"
        ]);
        $validation->setRule("$individual_name.name", "name", [
            "required",
            "alpha_numeric_space",
            "is_unique[$table_name.name,id,$id]"
        ]);

        return $validation;
    }

    protected static function prepareRequestData(array $raw_request_data): array {
        $current_user = auth()->user();

        return array_merge(
            [ "user_id" => $current_user->id ],
            $raw_request_data
        );
    }

    private static function makeValidation(): Validation {
        $validation = single_service("validation");
        $individual_name = static::getIndividualName();

        $validation->setRule($individual_name, "currency info", [
            "required"
        ]);

        return $validation;
    }
}
