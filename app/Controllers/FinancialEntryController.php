<?php

namespace App\Controllers;

use App\Casts\FinancialEntryAtomKind;
use App\Contracts\OwnedResource;
use App\Entities\FinancialEntryAtom;
use App\Libraries\Context;
use App\Libraries\Context\Memoizer;
use App\Libraries\Resource;
use App\Models\FinancialEntryAtomModel;
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

        $atom_key = "$individual_name.@relationship.financial_entry_atoms";
        $validation->setRule("$atom_key", "entry value", [ "required" ]);
        $validation->setRule("$atom_key.*.numerical_value", "numerical value", [
            "required",
            "is_valid_currency_amount"
        ]);
        $validation->setRule("$individual_name.modifier_id", "modifier", [
            "required",
            "is_natural_no_zero",
            "ensure_ownership[".implode(",", [
                ModifierModel::class,
                SEARCH_NORMALLY
            ])."]",
            "must_have_compound_data_key[$atom_key]",
            "has_valid_financial_entry_atom_group_info[$atom_key]",
            "does_own_resources_declared_in_financial_entry_atom_group_info[$atom_key]",
            "has_valid_financial_entry_atom_group_values[$atom_key]"
        ]);

        return $validation;
    }

    protected static function makeUpdateValidation(User $owner, int $resource_id): Validation
    {
        $validation = static::makeValidation();
        $individual_name = static::getIndividualName();

        $atom_key = "$individual_name.@relationship.financial_entry_atoms";
        $validation->setRule("$atom_key", "entry value", [ "required" ]);
        $validation->setRule("$atom_key.*.numerical_value", "numerical value", [
            "required",
            "is_valid_currency_amount"
        ]);
        $validation->setRule("$individual_name.modifier_id", "modifier", [
            "required",
            "is_natural_no_zero",
            "ensure_ownership[".implode(",", [
                ModifierModel::class,
                SEARCH_NORMALLY
            ])."]",
            "must_have_compound_data_key[$atom_key]",
            "must_have_compound_data_key[$atom_key.*.id]",
            "has_valid_financial_entry_atom_group_info[$atom_key]",
            "does_own_resources_declared_in_financial_entry_atom_group_info[$atom_key]",
            "has_valid_financial_entry_atom_group_values[$atom_key]"
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

        $must_include_all = in_array("*", $relationships);
        $must_include_precision_format = $must_include_all
            || in_array("precision_formats", $relationships);
        $must_include_currency = $must_include_all || in_array("currencies", $relationships);
        $must_include_account = $must_include_all || in_array("accounts", $relationships);
        $must_include_cash_flow_activity = $must_include_all || in_array(
            "cash_flow_activities",
            $relationships
        );
        $must_include_modifier_atom = $must_include_all || in_array(
            "modifier_atoms",
            $relationships
        );
        $must_include_modifier_atom_activity = $must_include_all || in_array(
            "modifier_atom_activities",
            $relationships
        );
        $must_include_financial_entry_atom = $must_include_all || in_array(
            "financial_entry_atoms",
            $relationships
        );

        if ($must_include_financial_entry_atom) {
            $enriched_document["financial_entry_atoms"] = model(
                FinancialEntryAtomModel::class,
                false
            )->whereIn("financial_entry_id", array_column($main_documents, "id"))
            ->findAll();
        }

        return $enriched_document;
    }

    protected static function processCreatedDocument(array $created_document, array $input): array
    {
        $main_document = $created_document[static::getIndividualName()];
        $main_document_id = $main_document["id"];
        $modifier_id = $main_document["modifier_id"];

        $atom_model = model(FinancialEntryAtomModel::class, false);

        $context = Context::make();
        $memoizer = Memoizer::make($context);
        $financial_entry_atoms = $memoizer->read("#$modifier_id", []);
        $financial_entry_atoms = array_map(function ($atom) use ($main_document_id, $atom_model) {
            $atom->financial_entry_id = $main_document_id;
            $atom_model->insert($atom);
            $atom->id = $atom_model->getInsertID();

            return $atom;
        }, $financial_entry_atoms);

        $created_document["financial_entry_atoms"] = model(FinancialEntryAtomModel::class, false)
            ->where("financial_entry_id", $main_document_id)
            ->findAll();

        return $created_document;
    }

    protected static function processUpdatedDocument(int $id, array $input): void
    {
        $financial_entry_atom_model = model(FinancialEntryAtomModel::class, false);
        foreach ($input["@relationship"]["financial_entry_atoms"] as $atom) {
            $atom["financial_entry_id"] = $id;
            $financial_entry_atom = new FinancialEntryAtom();
            $financial_entry_atom->fill($atom);
            $financial_entry_atom_model->update($financial_entry_atom->id, $financial_entry_atom);
        }
    }

    private static function makeValidation(): Validation
    {
        $validation = single_service("validation");
        $individual_name = static::getIndividualName();

        $validation->setRule($individual_name, "financial entry info", [
            "required"
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
