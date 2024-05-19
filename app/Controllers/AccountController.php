<?php

namespace App\Controllers;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Validation\Validation;

use App\Contracts\OwnedResource;
use App\Models\AccountModel;
use App\Models\CurrencyModel;
use App\Models\CashFlowCategoryModel;

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

    protected static function makeCreateValidation(User $owner): Validation {
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
            "is_unique_compositely[".implode(",", [
                implode("|", [
                    static::getModelName().":"."name",
                    "currency_id->$individual_name.currency_id"
                ])
            ])."]"
        ]);
        $validation->setRule("$individual_name.kind", "kind", [
            "required",
            "min_length[3]",
            "max_length[255]",
            "in_list[".implode(",", ACCEPTABLE_ACCOUNT_KINDS)."]"
        ]);

        return $validation;
    }

    protected static function makeUpdateValidation(User $owner, int $resource_id): Validation {
        $validation = static::makeValidation();
        $individual_name = static::getIndividualName();
        $table_name = static::getCollectiveName();

        $validation->setRule("$individual_name.name", "name", [
            "required",
            "min_length[3]",
            "max_length[255]",
            "alpha_numeric_space",
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

        $currencies = [];
        if (count($linked_currencies) > 0) {
            $currencies = model(CurrencyModel::class)
                ->whereIn("id", array_unique($linked_currencies))
                ->findAll();
        }
        $enriched_document["currencies"] = $currencies;

        return $enriched_document;
    }

    private static function makeValidation(): Validation {
        $validation = single_service("validation");
        $individual_name = static::getIndividualName();

        $validation->setRule($individual_name, "account info", [
            "required"
        ]);
        $validation->setRule(
            "$individual_name.increase_cash_flow_category_id",
            "increase cash flow category", [
                "permit_empty",
                "is_natural_no_zero",
                "ensure_ownership[".implode(",", [
                    CashFlowCategoryModel::class,
                    SEARCH_NORMALLY
                ])."]"
            ]
        );
        $validation->setRule(
            "$individual_name.decrease_cash_flow_category_id",
            "decrease cash flow category", [
                "permit_empty",
                "is_natural_no_zero",
                "ensure_ownership[".implode(",", [
                    CashFlowCategoryModel::class,
                    SEARCH_NORMALLY
                ])."]"
            ]
        );
        $validation->setRule("$individual_name.description", "description", [
            "permit_empty",
            "max_length[500]",
            "alpha_numeric_punct"
        ]);

        return $validation;
    }
}
