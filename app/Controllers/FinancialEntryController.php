<?php

namespace App\Controllers;

use App\Contracts\OwnedResource;
use App\Models\FinancialEntryModel;
use App\Models\ModifierModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Validation\Validation;

class FinancialEntryController extends BaseOwnedResourceController
{
    protected static function getIndividualName(): string
    {
        return "financial_entry";
    }

    protected static function getCollectiveName(): string
    {
        return "financial_entries";
    }

    protected static function getModelName(): string
    {
        return FinancialEntryModel::class;
    }

    protected static function makeCreateValidation(User $owner): Validation
    {
        $validation = static::makeValidation();
        $individual_name = static::getIndividualName();

        $validation->setRule("$individual_name.modifier_id", "modifier", [
            "required",
            "is_natural_no_zero",
            "ensure_ownership[".implode(",", [
                ModifierModel::class,
                SEARCH_NORMALLY
            ])."]",
            "has_column_value_in_list[".implode(",", [
                ModifierModel::class,
                "kind",
                MANUAL_MODIFIER_KIND
            ])."]"
        ]);
        $validation->setRule("$individual_name.debit_amount", "debit amount", [
            "required",
            "string",
            "min_length[1]",
            "max_length[255]",
            "is_valid_currency_amount",
            "must_be_same_for_modifier[$individual_name.modifier_id,$individual_name.credit_amount]"
        ]);

        return $validation;
    }

    protected static function makeUpdateValidation(User $owner, int $resource_id): Validation
    {
        $validation = static::makeValidation();
        $individual_name = static::getIndividualName();

        $validation->setRule("$individual_name.debit_amount", "debit amount", [
            "required",
            "string",
            "min_length[1]",
            "max_length[255]",
            "is_valid_currency_amount",
            "must_be_same_for_financial_entry[$resource_id,$individual_name.credit_amount]"
        ]);

        return $validation;
    }

    protected static function enrichResponseDocument(array $initial_document): array
    {
        $enriched_document = array_merge([], $initial_document);
        $is_single_main_document = isset($initial_document[static::getIndividualName()]);
        $main_documents = $is_single_main_document
            ? [ $initial_document[static::getIndividualName()] ]
            : ($initial_document[static::getCollectiveName()] ?? []);

        [
            $modifiers,
            $accounts,
            $cash_flow_activities,
            $currencies,
        ] = FinancialEntryModel::selectAncestorsWithResolvedResources($main_documents);

        if ($is_single_main_document) {
            $enriched_document["modifier"] = $modifiers[0] ?? null;
        } else {
            $enriched_document["modifiers"] = $modifiers;
        }

        $enriched_document["accounts"] = $accounts;
        $enriched_document["cash_flow_activities"] = $cash_flow_activities;
        $enriched_document["currencies"] = $currencies;

        return $enriched_document;
    }

    private static function makeValidation(): Validation
    {
        $validation = single_service("validation");
        $individual_name = static::getIndividualName();

        $validation->setRule($individual_name, "financial entry info", [
            "required"
        ]);
        $validation->setRule("$individual_name.credit_amount", "credit amount", [
            "required",
            "string",
            "min_length[1]",
            "max_length[255]",
            "is_valid_currency_amount"
        ]);
        $validation->setRule("$individual_name.transacted_at", "transacted date", [
            "required",
            "valid_date[".DATE_TIME_STRING_FORMAT."]",
            "must_be_thawed"
        ]);
        $validation->setRule("$individual_name.remarks", "remarks", [
            "permit_empty",
            "max_length[500]",
            "string"
        ]);

        return $validation;
    }
}
