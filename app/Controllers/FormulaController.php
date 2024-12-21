<?php

namespace App\Controllers;

use App\Contracts\OwnedResource;
use App\Models\FormulaModel;
use App\Models\CurrencyModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Validation\Validation;

class FormulaController extends BaseOwnedResourceController
{
    protected static function getIndividualName(): string
    {
        return "formula";
    }

    protected static function getCollectiveName(): string
    {
        return "formulae";
    }

    protected static function getModelName(): string
    {
        return FormulaModel::class;
    }

    protected static function makeCreateValidation(User $owner): Validation
    {
        $validation = static::makeValidation();
        $individual_name = static::getIndividualName();
        $table_name = static::getCollectiveName();

        $validation->setRule("$individual_name.currency_id", "currency", [
            "required",
            "is_natural_no_zero",
            "ensure_ownership[".implode(",", [
                CurrencyModel::class,
                SEARCH_NORMALLY
            ])."]"
        ]);
        $validation->setRule("$individual_name.name", "name", [
            "required",
            "min_length[3]",
            "max_length[255]",
            "alpha_numeric_punct",
            "is_unique_compositely[".implode(",", [
                implode("|", [
                    static::getModelName().":"."name",
                    "currency_id->$individual_name.currency_id"
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

        $validation->setRule("$individual_name.name", "name", [
            "required",
            "min_length[3]",
            "max_length[255]",
            "alpha_numeric_punct",
            "is_unique_compositely[".implode(",", [
                implode("|", [
                    static::getModelName().":"."name",
                    "currency_id->$individual_name.currency_id"
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

        [ $currencies ] = FormulaModel::selectAncestorsWithResolvedResources($main_documents);
        $enriched_document["currencies"] = $currencies;

        return $enriched_document;
    }

    private static function makeValidation(): Validation
    {
        $validation = single_service("validation");
        $individual_name = static::getIndividualName();

        $validation->setRule($individual_name, "account info", [
            "required"
        ]);
        $validation->setRule("$individual_name.description", "description", [
            "permit_empty",
            "max_length[500]",
            "string"
        ]);
        $validation->setRule("$individual_name.output_format", "output format", [
            "required",
            "min_length[3]",
            "max_length[255]",
            "in_list[".implode(",", ACCEPTABLE_FORMULA_OUTPUT_FORMATS)."]"
        ]);
        $validation->setRule("$individual_name.exchange_rate_basis", "exchange rate basis", [
            "required",
            "min_length[3]",
            "max_length[255]",
            "in_list[".implode(",", ACCEPTABLE_EXCHANGE_RATE_BASES)."]"
        ]);
        $validation->setRule(
            "$individual_name.presentational_precision",
            "presentational precision", [
                "required",
                "is_natural"
            ]
        );
        $validation->setRule("$individual_name.formula", "formula", [
            "required",
            "max_length[500]",
            "string"
        ]);

        return $validation;
    }
}
