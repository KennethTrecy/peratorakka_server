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
use App\Models\FrozenPeriodModel;
use App\Models\ModifierModel;
use App\Models\ModifierAtomModel;
use App\Models\ModifierAtomActivityModel;
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

    protected static function mustTransactForCreation(): bool
    {
        return true;
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
        $must_include_modifier = $must_include_all || in_array(
            "modifiers",
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

        $modifiers = [];
        if (
            $must_include_modifier
            || $must_include_modifier_atom
            || $must_include_modifier_atom_activity
            || $must_include_financial_entry_atom
        ) {
            $modifiers = model(ModifierModel::class, false)
                ->whereIn("id", array_column($main_documents, "modifier_id"))
                ->findAll();
        }

        if ($must_include_modifier) {
            $enriched_document["modifiers"] = $modifiers;
        }

        $modifier_atoms = [];
        if (
            $must_include_modifier_atom
            || $must_include_modifier_atom_activity
            || $must_include_financial_entry_atom
        ) {
            $modifier_atoms = model(ModifierAtomModel::class, false)
                ->whereIn("modifier_id", array_column($modifiers, "id"))
                ->findAll();
        }

        if ($must_include_modifier_atom) {
            $enriched_document["modifier_atoms"] = $modifier_atoms;
        }

        if ($must_include_modifier_atom_activity) {
            $modifier_atom_activities = model(ModifierAtomActivityModel::class, false)
                ->whereIn("modifier_atom_id", array_column($modifier_atoms, "id"))
                ->findAll();
            $enriched_document["modifier_atom_activities"] = $modifier_atom_activities;
        }

        if ($must_include_financial_entry_atom) {
            $financial_entry_atoms = model(FinancialEntryAtomModel::class, false)
                ->whereIn("financial_entry_id", array_column($main_documents, "id"))
                ->findAll();

            $ask_modifiers = array_filter($modifiers, function ($modifier) {
                return $modifier->action === ASK_MODIFIER_ACTION;
            });
            if (count($ask_modifiers) > 0) {
                $ask_modifier_IDs = array_column($ask_modifiers, "id");

                [
                    // Used to determine earliest known unfrozen date
                    $earliest_transacted_time,
                    // Used to determine latest known unfrozen date
                    $latest_transacted_time
                ] = FrozenPeriodModel::minMaxTransactedTimes($main_documents);

                $latest_frozen_period = FrozenPeriodModel::findLatestPeriod(
                    $latest_transacted_time
                );

                $derived_earliest_unfrozen_date = $earliest_transacted_time;
                if ($latest_frozen_period !== null) {
                    $derived_earliest_unfrozen_date = $latest_frozen_period->finished_at;
                }
                $derived_earliest_unfrozen_date = $derived_earliest_unfrozen_date->addDays(1);

                $base_financial_entries = [];
                if ($derived_earliest_unfrozen_date->isBefore($earliest_transacted_time)) {
                    $financial_entry_model = model(FinancialEntryModel::class, false);
                    $financial_entries = $financial_entry_model
                        ->limitSearchToUser($financial_entry_model, $user)
                        ->where(
                            "transacted_at >=",
                            $derived_earliest_unfrozen_date->toDateTimeString()
                        )
                        ->where("transacted_at <", $earliest_transacted_time->toDateTimeString())
                        ->findAll();
                }

                $user = auth()->user();
                $context = Context::make();
                [
                    $frozen_accounts,
                    $real_unadjusted_summary_calculations,
                    $real_adjusted_summary_calculations,
                    $real_flow_calculations,
                    $item_calculations,
                    [ $emergent_financial_entry_atoms, $keyed_customized_financial_entry_atoms ]
                ] = FrozenPeriodModel::makeRawCalculationsFromFinancialEntries($user, $context, [
                    ...$base_financial_entries,
                    ...$main_documents
                ]);

                $financial_entry_atoms = Resource::key(
                    $financial_entry_atoms,
                    fn ($atom) => $atom->id
                );
                foreach ($keyed_customized_financial_entry_atoms as $atom_id => $atom_value) {
                    if (isset($financial_entry_atoms[$atom_id])) {
                        $meta_key = "@meta";
                        $financial_entry_atoms[$atom_id]->$meta_key = [
                            "displayed_numerical_value" => $atom_value->simplified()
                        ];
                    }
                }

                $financial_entry_atoms = array_values(array_merge(
                    $financial_entry_atoms,
                    $emergent_financial_entry_atoms
                ));
            }

            $enriched_document["financial_entry_atoms"] = $financial_entry_atoms;
        }

        return $enriched_document;
    }

    protected static function processCreatedDocument(array $created_document, array $input): array
    {
        $main_document = $created_document[static::getIndividualName()];
        $main_document_id = $main_document["id"];
        $modifier_id = $main_document["modifier_id"];
        unset($created_document["financial_entry"]["@relationship"]);

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

        $created_document["financial_entry_atoms"] = $financial_entry_atoms;

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
