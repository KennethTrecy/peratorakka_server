<?php

namespace App\Models;

use CodeIgniter\Shield\Entities\User;
use Faker\Generator;

use App\Entities\Account;

class AccountModel extends BaseResourceModel
{
    protected $table = "accounts";
    protected $returnType = Account::class;
    protected $allowedFields = [
        "currency_id",
        "name",
        "description",
        "kind",
        "deleted_at"
    ];

    public function fake(Generator &$faker)
    {
        return [
            "name"  => $faker->unique()->firstName(),
            "description"  => $faker->paragraph(),
            "kind"  => $faker->randomElement(ACCEPTABLE_ACCOUNT_KINDS),
        ];
    }

    public function limitSearchToUser(BaseResourceModel $query_builder, User $user) {
        return $query_builder
            ->whereIn(
                "currency_id",
                model(CurrencyModel::class)
                    ->builder()
                    ->select("id")
                    ->where("user_id", $user->id)
            );
    }
}
