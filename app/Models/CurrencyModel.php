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
        "presentational_precision",
        "deleted_at"
    ];

    protected $sortable_fields = [
        "code",
        "name",
        "presentational_precision",
        "created_at",
        "updated_at",
        "deleted_at"
    ];

    public function fake(Generator &$faker)
    {
        return [
            "code"  => $faker->unique()->currencyCode(),
            "name"  => $faker->unique()->firstName(),
            "presentational_precision"  => $faker->randomElement([ 0, 1, 2, 3, 4, 12 ])
        ];
    }

    public function limitSearchToUser(BaseResourceModel $query_builder, User $user) {
        return $query_builder->where("user_id", $user->id);
    }
}
