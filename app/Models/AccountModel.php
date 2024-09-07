<?php

namespace App\Models;

use App\Entities\Account;
use CodeIgniter\Shield\Entities\User;
use Faker\Generator;

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
            "kind"  => $faker->randomElement(ACCEPTABLE_ACCOUNT_KINDS),
        ];
    }

    public function limitSearchToUser(BaseResourceModel $query_builder, User $user)
    {
        return $query_builder
            ->whereIn(
                "currency_id",
                model(CurrencyModel::class, false)
                    ->builder()
                    ->select("id")
                    ->where("user_id", $user->id)
            );
    }

    protected static function identifyAncestors(): array
    {
        return [
            CurrencyModel::class => [ "currency_id" ]
        ];
    }

    public static function extractLinkedCurrencies(array $accounts): array
    {
        return array_map(
            function ($account) {
                return $account->currency_id;
            },
            $accounts
        );
    }
}
