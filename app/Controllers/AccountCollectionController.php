<?php

namespace App\Controllers;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Validation\Validation;

use App\Contracts\OwnedResource;
use App\Models\AccountModel;
use App\Models\CollectionModel;
use App\Models\AccountCollectionModel;

class AccountCollectionController extends BaseOwnedResourceController
{
    protected static function getIndividualName(): string {
        return "financial_entry";
    }

    protected static function getCollectiveName(): string {
        return "financial_entries";
    }

    protected static function getModelName(): string {
        return AccountCollectionModel::class;
    }

    protected static function makeCreateValidation(User $owner): Validation {
        $validation = static::makeValidation();
        $individual_name = static::getIndividualName();

        $validation->setRule("$individual_name.collection_id", "collection", [
            "required",
            "is_natural_no_zero",
            "ensure_ownership[".implode(",", [
                collectionModel::class,
                SEARCH_NORMALLY
            ])."]",
            "has_column_value_in_list[".implode(",", [
                collectionModel::class,
                "kind",
                MANUAL_collection_KIND
            ])."]"
        ]);
        $validation->setRule("$individual_name.debit_amount", "debit amount", [
            "required",
            "string",
            "min_length[1]",
            "max_length[255]",
            "is_valid_collection_amount",
            "must_be_same_for_collection[$individual_name.collection_id,$individual_name.credit_amount]"
        ]);

        return $validation;
    }

    protected static function makeUpdateValidation(User $owner, int $resource_id): Validation {
        $validation = static::makeValidation();
        $individual_name = static::getIndividualName();

        $validation->setRule("$individual_name.debit_amount", "debit amount", [
            "required",
            "string",
            "min_length[1]",
            "max_length[255]",
            "is_valid_collection_amount",
            "must_be_same_for_financial_entry[$resource_id,$individual_name.credit_amount]"
        ]);

        return $validation;
    }

    protected static function enrichResponseDocument(array $initial_document): array {
        $enriched_document = array_merge([], $initial_document);
        $is_single_main_document = isset($initial_document[static::getIndividualName()]);
        $main_documents = $is_single_main_document
            ? [ $initial_document[static::getIndividualName()] ]
            : ($initial_document[static::getCollectiveName()] ?? [] );

        $linked_accounts = [];
        foreach ($main_documents as $document) {
            $account_id = $document->account_id;
            array_push($linked_accounts, $account_id);
        }

        $accounts = [];
        if (count($linked_accounts) > 0) {
            $accounts = model(AccountModel::class)
                ->whereIn("id", array_unique($linked_accounts))
                ->withDeleted()
                ->findAll();
        }
        $enriched_document["accounts"] = $accounts;

        $linked_collections = [];
        foreach ($accounts as $document) {
            $collection_id = $document->collection_id;
            array_push($linked_collections, $collection_id);
        }

        $collections = [];
        if (count($linked_collections) > 0) {
            $collections = model(CollectionModel::class)
                ->whereIn("id", array_unique($linked_collections))
                ->withDeleted()
                ->findAll();
        }
        $enriched_document["collections"] = $collections;

        return $enriched_document;
    }

    private static function makeValidation(): Validation {
        $validation = single_service("validation");
        $individual_name = static::getIndividualName();

        $validation->setRule($individual_name, "account collection info", [
            "required"
        ]);

        return $validation;
    }
}
