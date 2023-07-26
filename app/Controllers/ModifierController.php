<?php

namespace App\Controllers;

use CodeIgniter\Validation\Validation;

use App\Contracts\OwnedResource;
use App\Models\AccountModel;
use App\Models\CurrencyModel;
use App\Models\ModifierModel;

class ModifierController extends BaseOwnedResourceController
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

        $validation->setRule("$individual_name.account_id", "account", [
            "required",
            "is_natural_no_zero",
            "ensure_ownership[".implode(",", [
                AccountModel::class,
                SEARCH_NORMALLY
            ])."]"
        ]);
        $validation->setRule("$individual_name.opposite_account_id", "opposite account", [
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

    protected static function enrichResponseDocument(array $initial_document): array {
        $enriched_document = array_merge([], $initial_document);
        $main_documents = isset($initial_document[static::getIndividualName()])
            ? [ $initial_document[static::getIndividualName()] ]
            : ($initial_document[static::getCollectiveName()] ?? [] );

        $linked_accounts = [];
        foreach ($main_documents as $document) {
            $account_id = $document->account_id;
            $opposite_account_id = $document->opposite_account_id;
            array_push($linked_accounts, $account_id, $opposite_account_id);
        }

        $accounts = model(AccountModel::class)
            ->whereIn("id", array_unique($linked_accounts))
            ->findAll();
        $enriched_document["accounts"] = $accounts;

        $linked_currencies = [];
        foreach ($accounts as $document) {
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

        $validation->setRule($individual_name, "modifier info", [
            "required"
        ]);
        $validation->setRule("$individual_name.description", "description", [
            "permit_empty",
            "max_length[500]",
            "alpha_numeric_punct"
        ]);
        $validation->setRule("$individual_name.result_side", "result side", [
            "required",
            "min_length[3]",
            "max_length[10]",
            "in_list[".implode(",", RESULT_SIDES)."]"
        ]);
        $validation->setRule("$individual_name.kind", "kind", [
            "required",
            "min_length[3]",
            "max_length[255]",
            "in_list[".implode(",", ACCEPTABLE_MODIFIER_KINDS)."]"
        ]);

        return $validation;
    }
}
