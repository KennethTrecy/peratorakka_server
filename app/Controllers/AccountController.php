<?php

namespace App\Controllers;

use CodeIgniter\Validation\Validation;

use App\Contracts\OwnedResource;
use App\Models\AccountModel;
use App\Models\CurrencyModel;

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

    protected static function enrichResponseDocument(array $initial_document): array {
        $enriched_document = array_merge([], $initial_document);
        $main_documents = isset($initial_document[static::getIndividualName()])
            ? [ $initial_document[static::getIndividualName()] ]
            : ($initial_document[static::getCollectiveName()] ?? [] );

        $linked_currencies = [];
        foreach ($main_documents as $document) {
            $currency_id = $document->currency_id;
            array_push($linked_currencies, $currency_id);
        }

        $currencies = model(CurrencyModel::class)
            ->whereIn("id", array_unique($linked_currencies))
            ->findAll();
        $enriched_document["currencies"] = $currencies;

        return $enriched_document;
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
        $validation->setRule("$individual_name.kind", "description", [
            "required",
            "min_length[3]",
            "max_length[255]",
            "in_list[".implode(",", ACCEPTABLE_ACCOUNT_KINDS)."]"
        ]);

        return $validation;
    }
}
