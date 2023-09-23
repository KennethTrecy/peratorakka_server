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

        $current_user = auth()->user();
        $user_id = $current_user->id;

        $validation->setRule("$individual_name.code", "code", [
            "required",
            "min_length[2]",
            "max_length[255]",
            "alpha_numeric",
            "is_unique_compositely[".implode(",", [
                implode("|", [
                    static::getModelName().":"."code",
                    "name->$individual_name.name",
                    "user_id=$user_id"
                ])
            ])."]"
        ]);
        $validation->setRule("$individual_name.name", "name", [
            "required",
            "min_length[2]",
            "max_length[255]",
            "alpha_numeric_space",
            "is_unique_compositely[".implode(",", [
                implode("|", [
                    static::getModelName().":"."name",
                    "code->$individual_name.code",
                    "user_id=$user_id"
                ])
            ])."]"
        ]);

        return $validation;
    }

    protected static function makeUpdateValidation(int $id): Validation {
        $validation = static::makeValidation();
        $individual_name = static::getIndividualName();
        $table_name = static::getCollectiveName();

        $current_user = auth()->user();
        $user_id = $current_user->id;

        $validation->setRule("$individual_name.code", "code", [
            "required",
            "min_length[2]",
            "max_length[255]",
            "alpha_numeric",
            "is_unique_compositely[".implode(",", [
                implode("|", [
                    static::getModelName().":"."code",
                    "name->$individual_name.name",
                    "user_id=$user_id"
                ]),
                "id=$id"
            ])."]"
        ]);
        $validation->setRule("$individual_name.name", "name", [
            "required",
            "min_length[2]",
            "max_length[255]",
            "alpha_numeric_space",
            "is_unique_compositely[".implode(",", [
                implode("|", [
                    static::getModelName().":"."name",
                    "code->$individual_name.code",
                    "user_id=$user_id"
                ]),
                "id=$id"
            ])."]"
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
