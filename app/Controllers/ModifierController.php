<?php

namespace App\Controllers;

use App\Contracts\OwnedResource;
use App\Entities\ModifierAtom;
use App\Entities\ModifierAtomActivity;
use App\Libraries\Resource;
use App\Models\AccountModel;
use App\Models\CashFlowActivityModel;
use App\Models\ModifierAtomActivityModel;
use App\Models\ModifierAtomModel;
use App\Models\ModifierModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Validation\Validation;

class ModifierController extends BaseOwnedResourceController
{
    protected static function getIndividualName(): string
    {
        return "modifier";
    }

    protected static function getCollectiveName(): string
    {
        return "modifiers";
    }

    protected static function getModelName(): string
    {
        return ModifierModel::class;
    }

    protected static function makeCreateValidation(User $owner): Validation
    {
        $validation = static::makeValidation();
        $individual_name = static::getIndividualName();
        $table_name = static::getCollectiveName();

        $user_id = $owner->id;

        $atom_key = "$individual_name.@relationship.modifier_atoms";
        $validation->setRule("$atom_key", "group info", [
            "required"
        ]);
        $validation->setRule("$individual_name.name", "name", [
            "required",
            "min_length[3]",
            "max_length[255]",
            "alpha_numeric_punct",
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
            "in_list[".implode(",", ACCEPTABLE_MODIFIER_KINDS)."]"
        ]);
        $validation->setRule("$individual_name.action", "action", [
            "required",
            "min_length[3]",
            "max_length[255]",
            "in_list[".implode(",", ACCEPTABLE_MODIFIER_ACTIONS)."]",
            "must_have_compound_data_key[$atom_key]",
            "has_valid_modifier_atom_group_info[$atom_key]",
            "does_own_resources_declared_in_modifier_atom_group_info[$atom_key]",
            "has_valid_modifier_atom_group_cash_flow_activity[$atom_key]",
            "may_allow_modifier_action[$atom_key]"
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
            "min_length[3]",
            "max_length[255]",
            "alpha_numeric_punct",
            "is_unique_compositely[".implode(",", [
                implode("|", [
                    static::getModelName().":"."name",
                    "user_id=$user_id"
                ]),
                "id=$resource_id"
            ])."]"
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
        $must_include_modifier_atom = $must_include_all || in_array(
            "modifier_atoms",
            $relationships
        );
        $must_include_modifier_atom_activity = $must_include_all || in_array(
            "modifier_atom_activities",
            $relationships
        );
        $modifier_atoms = model(ModifierAtomModel::class, false)
            ->whereIn("modifier_id", array_column($main_documents, "id"))
            ->findAll();

        if ($must_include_modifier_atom) {
            $enriched_document["modifier_atoms"] = $modifier_atoms;
        }

        if ($must_include_modifier_atom_activity) {
            $modifier_atom_activities = model(ModifierAtomActivityModel::class, false)
                ->whereIn("modifier_atom_id", array_column($modifier_atoms, "id"))
                ->findAll();
            $enriched_document["modifier_atom_activities"] = $modifier_atom_activities;
        }

        // TODO: Add other related resources

        // [
        //     $accounts,
        //     $cash_flow_activities,
        //     $currencies
        // ] = ModifierModel::selectAncestorsWithResolvedResources($main_documents);
        // $enriched_document["accounts"] = $accounts;
        // $enriched_document["cash_flow_activities"] = $cash_flow_activities;
        // $enriched_document["currencies"] = $currencies;

        return $enriched_document;
    }

    protected static function processCreatedDocument(array $created_document, array $input): array
    {
        $main_document = $created_document[static::getIndividualName()];
        $main_document_id = $main_document["id"];
        unset($created_document["modifier"]["@relationship"]);

        $modifier_atoms = array_map(
            function ($raw_modifier_atom) use ($main_document_id) {
                $modifier_atom_entity = new ModifierAtom();
                $modifier_atom_entity->fill([
                    "modifier_id" => $main_document_id,
                    "account_id" => $raw_modifier_atom["account_id"],
                    "kind" => $raw_modifier_atom["kind"],
                ]);
                $model = model(ModifierAtomModel::class, false);
                $model->insert($modifier_atom_entity);
                $modifier_atom_entity->id = $model->getInsertID();

                return $modifier_atom_entity;
            },
            $input["@relationship"]["modifier_atoms"]
        );

        $created_document["modifier_atoms"] = $modifier_atoms;

        $keyed_modifier_atoms = Resource::key(
            $created_document["modifier_atoms"],
            fn ($modifier_atom) => $modifier_atom->account_id."_".$modifier_atom->kind
        );

        $modifier_atom_activities = array_map(
            function ($raw_modifier_atom) use ($keyed_modifier_atoms) {
                $modifier_atom_activity_entity = new ModifierAtomActivity();
                $key = $raw_modifier_atom["account_id"]."_".$raw_modifier_atom["kind"];
                $modifier_atom = $keyed_modifier_atoms[$key];
                $modifier_atom_activity_entity->fill([
                    "modifier_atom_id" => $modifier_atom->id,
                    "cash_flow_activity_id" => $raw_modifier_atom["cash_flow_activity_id"]
                ]);

                return $modifier_atom_activity_entity;
            },
            array_filter(
                $input["@relationship"]["modifier_atoms"],
                fn ($raw_modifier_atom) => (
                    isset($raw_modifier_atom["cash_flow_activity_id"])
                    && !is_null($raw_modifier_atom["cash_flow_activity_id"])
                )
            )
        );

        if (count($modifier_atom_activities) > 0) {
            model(ModifierAtomActivityModel::class, false)->insertBatch($modifier_atom_activities);

            $created_document["modifier_atom_activities"] = array_values($modifier_atom_activities);
        }

        return $created_document;
    }

    protected static function prepareRequestData(array $raw_request_data): array
    {
        $current_user = auth()->user();

        return array_merge(
            [ "user_id" => $current_user->id ],
            $raw_request_data
        );
    }

    private static function makeValidation(): Validation
    {
        $validation = single_service("validation");
        $individual_name = static::getIndividualName();

        $validation->setRule($individual_name, "modifier info", [
            "required"
        ]);
        $validation->setRule("$individual_name.description", "description", [
            "permit_empty",
            "max_length[500]",
            "string"
        ]);

        return $validation;
    }
}
