<?php

namespace App\Controllers;

use App\Contracts\OwnedResource;
use App\Models\ItemDetailModel;
use App\Models\PrecisionFormatModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Validation\Validation;

class ItemDetailController extends BaseOwnedResourceController
{
    protected static function getIndividualName(): string
    {
        return "item_detail";
    }

    protected static function getCollectiveName(): string
    {
        return "item_details";
    }

    protected static function getModelName(): string
    {
        return ItemDetailModel::class;
    }

    protected static function makeCreateValidation(User $owner): Validation
    {
        $validation = static::makeValidation();
        $individual_name = static::getIndividualName();
        $table_name = static::getCollectiveName();

        $validation->setRule("$individual_name.name", "name", [
            "required",
            "min_length[2]",
            "max_length[255]",
            "alpha_numeric_punct",
            "is_unique_compositely[".implode(",", [
                implode("|", [
                    static::getModelName().":"."name",
                    "precision_format_id->$individual_name.precision_format_id"
                ])
            ])."]"
        ]);
        $validation->setRule("$individual_name.unit", "unit", [
            "required",
            "min_length[1]",
            "max_length[255]",
            "alpha_numeric"
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
                    "precision_format_id->$individual_name.precision_format_id"
                ]),
                "id=$resource_id"
            ])."]"
        ]);
        $validation->setRule("$individual_name.unit", "unit", [
            "required",
            "min_length[1]",
            "max_length[255]",
            "alpha_numeric"
        ]);

        return $validation;
    }

    protected static function enrichResponseDocument(
        array $initial_document,
        array $relationships
    ): array {
        $enriched_document = array_merge([], $initial_document);
        $main_documents = isset($initial_document[static::getIndividualName()])
            ? [ $initial_document[static::getIndividualName()] ]
            : ($initial_document[static::getCollectiveName()] ?? []);

        if (in_array("*", $relationships) || in_array("precision_formats", $relationships)) {
            [
                $precision_formats
            ] = ItemDetailModel::selectAncestorsWithResolvedResources(
                $main_documents,
                $relationships
            );
            $enriched_document["precision_formats"] = $precision_formats;
        }

        return $enriched_document;
    }

    private static function makeValidation(): Validation
    {
        $validation = single_service("validation");
        $individual_name = static::getIndividualName();

        $validation->setRule($individual_name, "item detail info", [
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
        $validation->setRule("$individual_name.description", "description", [
            "permit_empty",
            "max_length[500]",
            "string"
        ]);

        return $validation;
    }
}
