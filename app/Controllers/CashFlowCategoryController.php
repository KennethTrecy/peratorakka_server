<?php

namespace App\Controllers;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Validation\Validation;

use App\Contracts\OwnedResource;
use App\Models\CashFlowCategoryModel;

class CashFlowCategoryController extends BaseOwnedResourceController
{
    protected static function getIndividualName(): string {
        return "cash_flow_category";
    }

    protected static function getCollectiveName(): string {
        return "cash_flow_categories";
    }

    protected static function getModelName(): string {
        return CashFlowCategoryModel::class;
    }

    protected static function makeCreateValidation(User $owner): Validation {
        $validation = static::makeValidation();
        $individual_name = static::getIndividualName();
        $table_name = static::getCollectiveName();

        $user_id = $owner->id;

        $validation->setRule("$individual_name.name", "name", [
            "required",
            "min_length[2]",
            "max_length[255]",
            "alpha_numeric_space",
            "is_unique_compositely[".implode(",", [
                implode("|", [
                    static::getModelName().":"."name",
                    "user_id=$user_id"
                ])
            ])."]"
        ]);
        $validation->setRule("$individual_name.kind", "kind", [
            "required",
            "min_length[3]",
            "max_length[255]",
            "in_list[".implode(",", ACCEPTABLE_CASH_FLOW_CATEGORY_KINDS)."]"
        ]);

        return $validation;
    }

    protected static function makeUpdateValidation(User $owner, int $resource_id): Validation {
        $validation = static::makeValidation();
        $individual_name = static::getIndividualName();
        $table_name = static::getCollectiveName();

        $user_id = $owner->id;

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
                "id=$resource_id"
            ])."]"
        ]);
        $validation->setRule("$individual_name.kind", "kind", [
            "required",
            "min_length[3]",
            "max_length[255]",
            "in_list[".implode(",", ACCEPTABLE_CASH_FLOW_CATEGORY_KINDS)."]"
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

        $validation->setRule($individual_name, "cash flow category info", [
            "required"
        ]);
        $validation->setRule("$individual_name.description", "description", [
            "permit_empty",
            "max_length[500]",
            "alpha_numeric_punct"
        ]);

        return $validation;
    }
}
