<?php

namespace App\Models;

use App\Entities\Deprecated\Formula;
use CodeIgniter\Shield\Entities\User;
use Faker\Generator;

class FormulaModel extends BaseResourceModel
{
    protected $table = "formulae_v2";
    protected $returnType = Formula::class;
    protected $allowedFields = [
        "precision_format_id",
        "name",
        "description",
        "output_format",
        "expression",
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
            "expression"  => " 1 + 1"
        ];
    }

    public function limitSearchToUser(BaseResourceModel $query_builder, User $user)
    {
        return $query_builder
            ->whereIn(
                "precision_format_id",
                model(PrecisionFormatModel::class, false)
                    ->builder()
                    ->select("id")
                    ->where("user_id", $user->id)
            );
    }

    protected static function createAncestorResources(int $user_id, array $options): array
    {
        [
            $precision_format
        ] = $options["precision_format_parent"] ?? PrecisionFormatModel::createTestResource(
            $user_id,
            $options["precision_format_options"] ?? []
        );

        return [
            [ [ $precision_format ] ],
            [ [ "precision_format_id" => $precision_format->id ] ]
        ];
    }

    protected static function identifyAncestors(): array
    {
        return [
            PrecisionFormatModel::class => [ "precision_format_id" ]
        ];
    }
}
