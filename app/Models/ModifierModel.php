<?php

namespace App\Models;

use CodeIgniter\Shield\Entities\User;
use Faker\Generator;

use App\Entities\Modifier;

class ModifierModel extends BaseResourceModel
{
    protected $table = "modifiers";
    protected $returnType = Modifier::class;
    protected $allowedFields = [
        "account_id",
        "opposite_account_id",
        "name",
        "description",
        "result_side",
        "kind",
        "deleted_at"
    ];

    public function fake(Generator &$faker)
    {
        return [
            "name"  => $faker->unique()->firstName(),
            "description"  => $faker->paragraph(),
            "result_side"  => $faker->randomElement(RESULT_SIDES),
            "kind"  => $faker->randomElement(ACCEPTABLE_MODIFIER_KINDS),
        ];
    }

    public function limitSearchToUser(BaseResourceModel $query_builder, User $user) {
        $account_subquery = model(AccountModel::class, false)
            ->builder()
            ->select("id")
            ->whereIn(
                "currency_id",
                model(CurrencyModel::class, false)
                    ->builder()
                    ->select("id")
                    ->where("user_id", $user->id)
            );

        return $query_builder
            ->whereIn("account_id", $account_subquery)
            ->whereIn("opposite_account_id", $account_subquery);
    }
}
