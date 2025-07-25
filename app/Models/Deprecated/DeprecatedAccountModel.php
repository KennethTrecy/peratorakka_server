<?php

namespace App\Models\Deprecated;

use App\Entities\Account;
use App\Models\BaseResourceModel;
use CodeIgniter\Shield\Entities\User;
use Faker\Generator;

class DeprecatedAccountModel extends BaseResourceModel
{
    protected $table = "accounts";
    protected $returnType = Account::class;
    protected $allowedFields = [
        "currency_id",
        "name",
        "description",
        "kind",
        "created_at",
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
                model(DeprecatedCurrencyModel::class, false)
                    ->builder()
                    ->select("id")
                    ->where("user_id", $user->id)
            );
    }

    protected static function identifyAncestors(): array
    {
        return [
            DeprecatedCurrencyModel::class => [ "currency_id" ]
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
