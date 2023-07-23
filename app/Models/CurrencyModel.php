<?php

namespace App\Models;

use CodeIgniter\Shield\Entities\User;
use Faker\Generator;

class CurrencyModel extends BaseResourceModel
{
    protected $table            = "currencies";
    protected $returnType       = "array";
    protected $allowedFields    = [
        "user_id",
        "code",
        "name",
        "deleted_at"
    ];

    public function fake(Generator &$faker)
    {
        return [
            "code"  => $faker->unique()->currencyCode(),
            "name"  => $faker->unique()->firstName(),
        ];
    }

    protected function limitSearchToUser($builder, User $user) {
        return $this->where("user_id", $user->id);
    }
}
