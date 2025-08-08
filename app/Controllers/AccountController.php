<?php

namespace App\Controllers;

use App\Contracts\OwnedResource;
use App\Models\AccountModel;
use App\Models\CurrencyModel;
use App\Models\ItemConfigurationModel;
use App\Models\PrecisionFormatModel;
use App\Entities\ItemConfiguration;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Validation\Validation;

class AccountController extends BaseOwnedResourceController
{
    protected static function getIndividualName(): string
    {
        return "account";
    }

    protected static function getCollectiveName(): string
    {
        return "accounts";
    }

    protected static function getModelName(): string
    {
        return AccountModel::class;
    }

    protected static function makeCreateValidation(User $owner): Validation
    {
        $validation = static::makeValidation();
        $individual_name = static::getIndividualName();
        $table_name = static::getCollectiveName();
        $item_configuration_key = "$individual_name.@relationship.item_configuration";

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
        $validation->setRule("$individual_name.kind", "kind", [
            "required",
            "min_length[3]",
            "max_length[255]",
            "in_list[".implode(",", ACCEPTABLE_ACCOUNT_KINDS)."]",
            "must_have_compound_data_key_if_document_value_matches[".implode(",", [
                ITEMIZED_ASSET_ACCOUNT_KIND,
                $item_configuration_key
            ])."]",
            "has_valid_item_configuration_if_present[$item_configuration_key]"
        ]);

        return $validation;
    }

    protected static function makeUpdateValidation(User $owner, int $resource_id): Validation
    {
        $validation = static::makeValidation();
        $individual_name = static::getIndividualName();
        $table_name = static::getCollectiveName();
        $item_configuration_key = "$individual_name.@relationship.item_configuration";

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
        // TODO: Make validation to allow this field if the entity was not yet updated
        $validation->setRule("$individual_name.kind", "kind", [
            "required",
            "min_length[3]",
            "max_length[255]",
            "in_list[".implode(",", ACCEPTABLE_ACCOUNT_KINDS)."]",
            "must_have_compound_data_key_if_document_value_matches[".implode(",", [
                ITEMIZED_ASSET_ACCOUNT_KIND,
                $item_configuration_key
            ])."]",
            "has_valid_item_configuration_if_present[$item_configuration_key]"
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
        $must_include_item_configuration = $must_include_all || in_array(
            "item_configurations",
            $relationships
        );

        $linked_accounts = array_unique(array_column($main_documents, "id"));
        $has_accounts = !empty($linked_accounts);

        $currencies = [];
        if ($has_accounts && ($must_include_precision_format || $must_include_currency)) {
            $currencies = model(CurrencyModel::class, false)->whereIn(
                "id", array_unique(array_column($main_documents, "currency_id"))
            )->findAll();
        }

        if ($must_include_currency) {
            $enriched_document["currencies"] = $currencies;
        }

        $item_configurations = [];
        if ($has_accounts && $must_include_item_configuration) {
            $item_configurations = model(ItemConfigurationModel::class, false)->whereIn(
                "account_id", $linked_accounts
            )->findAll();
        }

        if ($must_include_item_configuration) {
            $enriched_document["item_configurations"] = $item_configurations;
        }

        $precision_formats = [];
        if ($has_accounts && $must_include_precision_format && (
            count($currencies) > 0 || count($item_configurations) > 0
        )) {
            $linked_precision_formats = array_unique(array_column(
                array_merge($currencies, $item_configurations),
                "precision_format_id"
            ));

            if (count($linked_precision_formats) > 0) {
                $precision_formats = model(PrecisionFormatModel::class, false)->whereIn(
                    "id", $linked_precision_formats
                )->findAll();
            }
        }

        if ($must_include_precision_format) {
            $enriched_document["precision_formats"] = $precision_formats;
        }

        return $enriched_document;
    }

    protected static function processCreatedDocument(array $created_document, array $input): array
    {
        $main_document = $created_document[static::getIndividualName()];
        $main_document_id = $main_document["id"];
        unset($created_document["account"]["@relationship"]);

        if (isset($input["@relationship"]["item_configuration"])) {
            $item_configuration_model = model(ItemConfigurationModel::class, false);
            $raw_item_configuration = $input["@relationship"]["item_configuration"];

            if (isset($main_document["@relationship"])) unset($main_document["@relationship"]);

            $item_configuration_entity = new ItemConfiguration();
            $item_configuration_entity->fill([
                "account_id" => $main_document_id,
                "item_detail_id" => $raw_item_configuration["item_detail_id"],
                "valuation_method" => $raw_item_configuration["valuation_method"]
            ]);
            $item_configuration_model->insert($item_configuration_entity);
            $item_configuration_entity->id = $item_configuration_model->getInsertID();

            $created_document["item_configuration"] = $item_configuration_entity;
        }

        return $created_document;
    }

    protected static function processUpdatedDocument(int $id, array $input): void
    {
        $item_configuration_model = model(ItemConfigurationModel::class, false);
        if (isset($input["@relationship"]["item_configuration"])) {
            $data = $input["@relationship"]["item_configuration"];
            $data["account_id"] = $id;
            $item_configuration = new ItemConfiguration();
            $item_configuration->fill($data);
            $item_configuration_model->update($item_configuration->account_id, $item_configuration);
        }
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

        return $validation;
    }
}
