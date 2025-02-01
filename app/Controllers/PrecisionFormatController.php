<?php

namespace App\Controllers;

use App\Models\PrecisionFormatModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Validation\Validation;

class PrecisionFormatController extends BaseOwnedResourceController
{
    protected static function getIndividualName(): string
    {
        return "precision_format";
    }

    protected static function getCollectiveName(): string
    {
        return "precision_formats";
    }

    protected static function getModelName(): string
    {
        return PrecisionFormatModel::class;
    }

    protected static function makeCreateValidation(User $owner): Validation
    {
        $validation = static::makeValidation();
        $individual_name = static::getIndividualName();
        $table_name = static::getCollectiveName();

        $user_id = $owner->id;

        $validation->setRule("$individual_name.name", "name", [
            "required",
            "min_length[2]",
            "max_length[255]",
            "alpha_numeric_punct",
            "is_unique_compositely[".implode(",", [
                implode("|", [
                    static::getModelName().":"."name",
                    "user_id=$user_id"
                ])
            ])."]"
        ]);

        return $validation;
    }

    protected static function makeUpdateValidation(User $owner, int $resource_id): Validation
    {
        $validation = static::makeValidation();
        $individual_name = static::getIndividualName();
        $table_name = static::getCollectiveName();

        $user_id = $owner->id;

        $validation->setRule("$individual_name.name", "name", [
            "required",
            "min_length[2]",
            "max_length[255]",
            "alpha_numeric_punct",
            "is_unique_compositely[".implode(",", [
                implode("|", [
                    static::getModelName().":"."name",
                    "user_id=$user_id"
                ]),
                "id=$resource_id"
            ])."]"
        ]);

        return $validation;
    }

    protected static function prepareRequestData(array $raw_request_data): array
    {
        $current_user = auth()->user();

        return array_merge(
            [ "user_id" => $current_user->id ],
            $raw_request_data
        );
    }

    private static function makeValidation(): Validation
    {
        $validation = single_service("validation");
        $individual_name = static::getIndividualName();

        $validation->setRule($individual_name, "precision format info", [
            "required"
        ]);
        $validation->setRule(
            "$individual_name.minimum_presentational_precision",
            "minimum presentational precision",
            [
                "required",
                "is_natural"
            ]
        );
        $validation->setRule(
            "$individual_name.maximum_presentational_precision",
            "maximum presentational precision",
            [
                "required",
                "is_natural"
            ]
        );

        return $validation;
    }
}
