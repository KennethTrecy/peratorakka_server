<?php

namespace App\Controllers;

use App\Models\AccountCollectionModel;
use App\Models\AccountModel;
use App\Models\CollectionModel;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Validation\Validation;

class AccountCollectionController extends BaseOwnedResourceController
{
    protected static function getIndividualName(): string
    {
        return "account_collection";
    }

    protected static function getCollectiveName(): string
    {
        return "account_collections";
    }

    protected static function getModelName(): string
    {
        return AccountCollectionModel::class;
    }

    protected static function makeCreateValidation(User $owner): Validation
    {
        $validation = static::makeValidation();
        $individual_name = static::getIndividualName();

        $validation->setRule("$individual_name.collection_id", "collection", [
            "required",
            "is_natural_no_zero",
            "ensure_ownership[".implode(",", [
                CollectionModel::class,
                SEARCH_NORMALLY
            ])."]"
        ]);
        $validation->setRule("$individual_name.account_id", "account", [
            "required",
            "is_natural_no_zero",
            "ensure_ownership[".implode(",", [
                AccountModel::class,
                SEARCH_WITH_DELETED
            ])."]"
        ]);

        return $validation;
    }

    protected static function makeUpdateValidation(User $owner, int $resource_id): Validation
    {
        // There is no update validation because it is not possible to update an account collection.
        $validation = static::makeValidation();

        return $validation;
    }

    protected static function enrichResponseDocument(
        array $initial_document,
        array $relationships
    ): array {
        $enriched_document = array_merge([], $initial_document);
        $is_single_main_document = isset($initial_document[static::getIndividualName()]);
        $main_documents = $is_single_main_document
            ? [ $initial_document[static::getIndividualName()] ]
            : ($initial_document[static::getCollectiveName()] ?? []);

        $must_include_all = in_array("*", $relationships);
        $must_include_account = $must_include_all || in_array("accounts", $relationships);
        $must_include_collection = $must_include_all || in_array("collections", $relationships);

        if ($must_include_account) {
            $linked_accounts = [];
            foreach ($main_documents as $document) {
                $account_id = $document->account_id;
                array_push($linked_accounts, $account_id);
            }
            $linked_accounts = array_unique($linked_accounts);

            $accounts = [];
            if (count($linked_accounts) > 0) {
                $accounts = model(AccountModel::class)
                    ->whereIn("id", $linked_accounts)
                    ->withDeleted()
                    ->findAll();
            }
            $enriched_document["accounts"] = $accounts;
        }

        if ($must_include_collection) {
            $linked_collections = [];
            foreach ($main_documents as $document) {
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
        }

        return $enriched_document;
    }

    private static function makeValidation(): Validation
    {
        $validation = single_service("validation");
        $individual_name = static::getIndividualName();

        $validation->setRule($individual_name, "account collection info", [
            "required"
        ]);

        return $validation;
    }
}
