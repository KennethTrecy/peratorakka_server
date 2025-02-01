<?php

namespace App\Controllers;

use App\Contracts\OwnedResource;
use App\Models\CurrencyModel;
use App\Models\PrecisionFormatModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Validation\Validation;

class CurrencyController extends BaseOwnedResourceController
{
    protected static function getIndividualName(): string
    {
        return "currency";
    }

    protected static function getCollectiveName(): string
    {
        return "currencies";
    }

    protected static function getModelName(): string
    {
        return CurrencyModel::class;
    }

    protected static function makeCreateValidation(User $owner): Validation
    {
        $validation = static::makeValidation();
        $individual_name = static::getIndividualName();
        $table_name = static::getCollectiveName();

        $validation->setRule("$individual_name.code", "code", [
            "required",
            "min_length[2]",
            "max_length[255]",
            "alpha_numeric",
            "is_unique_compositely[".implode(",", [
                implode("|", [
                    static::getModelName().":"."code",
                    "name->$individual_name.name",
                    "precision_format_id->$individual_name.precision_format_id"
                ])
            ])."]"
        ]);
        $validation->setRule("$individual_name.name", "name", [
            "required",
            "min_length[2]",
            "max_length[255]",
            "alpha_numeric_punct",
            "is_unique_compositely[".implode(",", [
                implode("|", [
                    static::getModelName().":"."name",
                    "code->$individual_name.code",
                    "precision_format_id->$individual_name.precision_format_id"
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

        $validation->setRule("$individual_name.code", "code", [
            "required",
            "min_length[2]",
            "max_length[255]",
            "alpha_numeric",
            "is_unique_compositely[".implode(",", [
                implode("|", [
                    static::getModelName().":"."code",
                    "name->$individual_name.name",
                    "precision_format_id->$individual_name.precision_format_id"
                ]),
                "id=$resource_id"
            ])."]"
        ]);
        $validation->setRule("$individual_name.name", "name", [
            "required",
            "min_length[2]",
            "max_length[255]",
            "alpha_numeric_punct",
            "is_unique_compositely[".implode(",", [
                implode("|", [
                    static::getModelName().":"."name",
                    "code->$individual_name.code",
                    "precision_format_id->$individual_name.precision_format_id"
                ]),
                "id=$resource_id"
            ])."]"
        ]);

        return $validation;
    }

    protected static function enrichResponseDocument(array $initial_document): array
    {
        $enriched_document = array_merge([], $initial_document);
        $main_documents = isset($initial_document[static::getIndividualName()])
            ? [ $initial_document[static::getIndividualName()] ]
            : ($initial_document[static::getCollectiveName()] ?? []);

        [
            $precision_formats
        ] = CurrencyModel::selectAncestorsWithResolvedResources($main_documents);
        $enriched_document["precision_formats"] = $precision_formats;

        return $enriched_document;
    }

    private static function makeValidation(): Validation
    {
        $validation = single_service("validation");
        $individual_name = static::getIndividualName();

        $validation->setRule($individual_name, "currency info", [
            "required"
        ]);
        $validation->setRule("$individual_name.precision_format_id", "precision format", [
            "required",
            "is_natural_no_zero",
            "ensure_ownership[".implode(",", [
                PrecisionFormatModel::class,
                SEARCH_NORMALLY
            ])."]"
        ]);

        return $validation;
    }
}
