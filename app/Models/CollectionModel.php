<?php

namespace App\Models;

use App\Entities\Collection;
use CodeIgniter\Shield\Entities\User;
use DateTimeInterface;
use Faker\Generator;

class CollectionModel extends BaseResourceModel
{
    protected $table = "collections";
    protected $returnType = Collection::class;
    protected $allowedFields = [
        "user_id",
        "name",
        "description",
        "deleted_at"
    ];
    protected $useTimestamps = true;
    protected $useSoftDeletes = true;

    protected $sortable_fields = [
        "name",
        "created_at",
        "updated_at",
        "deleted_at"
    ];

    public function fake(Generator &$faker)
    {
        return [
            "name"  => $faker->unique()->firstName(),
            "description"  => $faker->paragraph(),
        ];
    }

    public function limitSearchToUser(BaseResourceModel $query_builder, User $user)
    {
        return $query_builder->where("user_id", $user->id);
    }
}
