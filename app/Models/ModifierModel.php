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
        "debit_account_id",
        "credit_account_id",
        "name",
        "description",
        "action",
        "kind",
        "deleted_at"
    ];

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
            "action"  => $faker->randomElement(ACCEPTABLE_MODIFIER_ACTIONS),
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
            ->whereIn("debit_account_id", $account_subquery)
            ->whereIn("credit_account_id", $account_subquery);
    }
}
