<?php

namespace App\Models;

use App\Entities\AccountCollection;
use CodeIgniter\Shield\Entities\User;
use DateTimeInterface;
use Faker\Generator;

class AccountCollectionModel extends BaseResourceModel
{
    protected $table = "account_collections_v2";
    protected $returnType = AccountCollection::class;
    protected $allowedFields = [
        "collection_id",
        "account_id"
    ];
    protected $useTimestamps = false;
    protected $useSoftDeletes = false;

    protected $sortable_fields = [
        "id"
    ];

    public function fake(Generator &$faker)
    {
        return [];
    }

    public function limitSearchToUser(BaseResourceModel $query_builder, User $user)
    {
        return $query_builder
            ->whereIn(
                "collection_id",
                model(CollectionModel::class, false)
                    ->builder()
                    ->select("id")
                    ->where("user_id", $user->id)
            )
            ->whereIn(
                "account_id",
                model(AccountModel::class, false)
                    ->builder()
                    ->select("id")
                    ->whereIn(
                        "currency_id",
                        model(CurrencyModel::class, false)
                            ->builder()
                            ->select("id")
                            ->whereIn(
                                "precision_format_id",
                                model(PrecisionFormatModel::class, false)
                                    ->builder()
                                    ->select("id")
                                    ->where("user_id", $user->id)
                            )
                    )
            );
    }

    protected static function createAncestorResources(int $user_id, array $options): array
    {
        [
            $collection
        ] = $options["collection_parent"] ?? CollectionModel::createTestResource(
            $user_id,
            $options["collection_options"] ?? []
        );

        $account_options = $options["account_options"] ?? [ "expected_kinds" => [] ];

        [
            $precision_formats,
            $currencies,
            $accounts
        ] = isset($options["ancestor_accounts"])
            ? $options["ancestor_accounts"]
            : AccountModel::createTestResources($user_id, 1, $account_options);

        $parent_links = static::permutateParentLinks([
            "collection_id" => [ $collection->id ],
            "account_id" => array_map(fn ($account) => $account->id, $accounts)
        ], $options);

        return [
            [ $precision_formats, $currencies, $accounts, [ $collection ] ],
            $parent_links
        ];
    }
}
