<?php

namespace App\Models;

use CodeIgniter\Shield\Entities\User;
use Faker\Generator;

use App\Entities\Currency;

class CurrencyModel extends BaseResourceModel
{
    protected $table = "currencies";
    protected $returnType = Currency::class;
    protected $allowedFields = [
        "user_id",
        "code",
        "name",
        "deleted_at"
    ];

    protected $sortable_fields = [
        "code",
        "name",
        "created_at",
        "updated_at",
        "deleted_at"
    ];

    public function fake(Generator &$faker)
    {
        return [
            "code"  => $faker->unique()->currencyCode(),
            "name"  => $faker->unique()->firstName(),
        ];
    }

    public function limitSearchToUser(BaseResourceModel $query_builder, User $user) {
        return $query_builder->where("user_id", $user->id);
    }
}
