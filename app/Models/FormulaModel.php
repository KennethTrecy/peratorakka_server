<?php

namespace App\Models;

use App\Entities\Formula;
use CodeIgniter\Shield\Entities\User;
use Faker\Generator;

class FormulaModel extends BaseResourceModel
{
    protected $table = "formulae";
    protected $returnType = Formula::class;
    protected $allowedFields = [
        "currency_id",
        "name",
        "description",
        "output_format",
        "exchange_rate_basis",
        "presentational_precision",
        "formula",
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
            "output_format"  => $faker->randomElement(ACCEPTABLE_FORMULA_OUTPUT_FORMATS),
            "exchange_rate_basis"  => $faker->randomElement(ACCEPTABLE_FORMULA_EXCHANGE_RATE_BASES),
            "presentational_precision"  => $faker->randomElement([ 0, 1, 2, 3, 4, 12 ]),
            "formula"  => " 1 + 1"
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

    public static function extractLinkedCurrencies(array $formulae): array
    {
        return array_map(
            function ($formula) {
                return $formula->currency_id;
            },
            $formulae
        );
    }
}
