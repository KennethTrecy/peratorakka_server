<?php

namespace App\Models;

use App\Entities\AccountCollection;
use App\Models\BaseResourceModel;
use CodeIgniter\Shield\Entities\User;
use DateTimeInterface;
use Faker\Generator;

class DeprecatedAccountCollectionModel extends BaseResourceModel
{
    protected $table = "account_collections";
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
                model(DeprecatedAccountModel::class, false)
                    ->builder()
                    ->select("id")
                    ->whereIn(
                        "currency_id",
                        model(DeprecatedCurrencyModel::class, false)
                            ->builder()
                            ->select("id")
                            ->where("user_id", $user->id)
                    )
            );
    }
}
