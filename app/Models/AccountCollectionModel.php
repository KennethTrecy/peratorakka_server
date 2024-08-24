<?php

namespace App\Models;

use DateTimeInterface;

use CodeIgniter\Shield\Entities\User;
use Faker\Generator;

use App\Entities\AccountCollection;

class AccountCollectionModel extends BaseResourceModel
{
    protected $table = "account_collections";
    protected $returnType = AccountCollection::class;
    protected $allowedFields = [
        "user_id",
        "collection_id",
        "account_id"
    ];
    protected $useTimestamps = false;
    protected $useSoftDeletes = false;

    protected $sortable_fields = [];

    public function fake(Generator &$faker)
    {
        return [];
    }

    public function limitSearchToUser(BaseResourceModel $query_builder, User $user) {
        return $query_builder
            ->whereIn(
                "collection_id",
                model(CollectionModel::class, false)
                    ->builder()
                    ->select("id")
                    ->where("user_id", $user->id)
            );
    }
}
