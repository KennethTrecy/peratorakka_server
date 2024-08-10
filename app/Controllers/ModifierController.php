<?php

namespace App\Controllers;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Validation\Validation;

use App\Contracts\OwnedResource;
use App\Models\AccountModel;
use App\Models\CashFlowActivityModel;
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

    protected static function makeCreateValidation(User $owner): Validation {
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
                    "debit_account_id->$individual_name.debit_account_id",
                    "credit_account_id->$individual_name.credit_account_id"
                ])
            ])."]"
        ]);
        $validation->setRule("$individual_name.kind", "kind", [
            "required",
            "min_length[3]",
            "max_length[255]",
            "in_list[".implode(",", ACCEPTABLE_MODIFIER_KINDS)."]"
        ]);
        $validation->setRule("$individual_name.action", "action", [
            "required",
            "min_length[3]",
            "max_length[255]",
            "in_list[".implode(",", ACCEPTABLE_MODIFIER_ACTIONS)."]",
            "may_allow_exchange_action[".implode(",", [
                "$individual_name.debit_account_id",
                "$individual_name.credit_account_id"
            ])."]",
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
            "alpha_numeric_punct",
            "is_unique_compositely[".implode(",", [
                implode("|", [
                    static::getModelName().":"."name",
                    "debit_account_id->$individual_name.debit_account_id",
                    "credit_account_id->$individual_name.credit_account_id"
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

        $linked_accounts = [];
        foreach ($main_documents as $document) {
            $debit_account_id = $document->debit_account_id;
            $credit_account_id = $document->credit_account_id;
            array_push($linked_accounts, $debit_account_id, $credit_account_id);
        }

        $accounts = [];
        if (count($linked_accounts) > 0) {
            $accounts = model(AccountModel::class)
                ->whereIn("id", array_unique($linked_accounts))
                ->findAll();
        }
        $enriched_document["accounts"] = $accounts;

        $linked_cash_flow_activities = [];
        foreach ($main_documents as $document) {
            $debit_cash_flow_activity_id = $document->debit_cash_flow_activity_id;
            $credit_cash_flow_activity_id = $document->credit_cash_flow_activity_id;

            if ($debit_cash_flow_activity_id !== null) {
                array_push($linked_cash_flow_activities, $debit_cash_flow_activity_id);
            }

            if ($credit_cash_flow_activity_id !== null) {
                array_push($linked_cash_flow_activities, $credit_cash_flow_activity_id);
            }
        }

        $cash_flow_activities = [];
        if (count($linked_cash_flow_activities) > 0) {
            $cash_flow_activities = model(CashFlowActivityModel::class)
                ->whereIn("id", array_unique($linked_cash_flow_activities))
                ->findAll();
        }
        $enriched_document["cash_flow_activities"] = $cash_flow_activities;

        $linked_currencies = [];
        foreach ($accounts as $document) {
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

        $validation->setRule($individual_name, "modifier info", [
            "required"
        ]);
        $validation->setRule("$individual_name.debit_account_id", "debit account", [
            "required",
            "is_natural_no_zero",
            "ensure_ownership[".implode(",", [
                AccountModel::class,
                SEARCH_NORMALLY
            ])."]"
        ]);
        $validation->setRule("$individual_name.credit_account_id", "credit account", [
            "required",
            "is_natural_no_zero",
            "ensure_ownership[".implode(",", [
                AccountModel::class,
                SEARCH_NORMALLY
            ])."]"
        ]);
        $validation->setRule(
            "$individual_name.debit_cash_flow_activity_id",
            "debit cash flow activity", [
                "permit_empty_if_column_value_matches[".implode(",", [
                    AccountModel::class,
                    "$individual_name.debit_account_id",
                    "kind",
                    LIQUID_ASSET_ACCOUNT_KIND
                ])."]",
                "ensure_ownership[".implode(",", [
                    CashFlowActivityModel::class,
                    SEARCH_NORMALLY
                ])."]"
            ]
        );
        $validation->setRule(
            "$individual_name.credit_cash_flow_activity_id",
            "credit cash flow activity", [
                "permit_empty_if_column_value_matches[".implode(",", [
                    AccountModel::class,
                    "$individual_name.credit_account_id",
                    "kind",
                    LIQUID_ASSET_ACCOUNT_KIND
                ])."]",
                "ensure_ownership[".implode(",", [
                    CashFlowActivityModel::class,
                    SEARCH_NORMALLY
                ])."]"
            ]
        );
        $validation->setRule("$individual_name.description", "description", [
            "permit_empty",
            "max_length[500]",
            "string"
        ]);

        return $validation;
    }
}
