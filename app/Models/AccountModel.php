<?php

namespace App\Models;

use App\Entities\Account;
use CodeIgniter\Shield\Entities\User;
use Faker\Generator;

class AccountModel extends BaseResourceModel
{
    protected $table = "accounts_v2";
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
                model(CurrencyModel::class, false)
                    ->builder()
                    ->select("id")
                    ->whereIn(
                        "precision_format_id",
                        model(PrecisionFormatModel::class, false)
                            ->builder()
                            ->select("id")
                            ->where("user_id", $user->id)
                    )
            );
    }

    protected static function createAncestorResources(int $user_id, array $options): array
    {
        [
            $precision_formats,
            $currency
        ] = CurrencyModel::createTestResource(
            $user_id,
            $options["currency_options"] ?? []
        );

        $parent_links = static::permutateParentLinks([
            "currency_id" => [ $currency->id ],
            "kind" => $options["expected_kinds"] ?? []
        ], $options);

        return [
            [ $precision_formats, [ $currency ] ],
            $parent_links
        ];
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
