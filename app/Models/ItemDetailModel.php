<?php

namespace App\Models;

use App\Entities\ItemDetail;
use CodeIgniter\Shield\Entities\User;
use Faker\Generator;

class ItemDetailModel extends BaseResourceModel
{
    protected $table = "item_details";
    protected $returnType = ItemDetail::class;
    protected $allowedFields = [
        "precision_format_id",
        "name",
        "unit",
        "description",
        "created_at",
        "deleted_at"
    ];

    protected $sortable_fields = [
        "name",
        "unit",
        "created_at",
        "updated_at",
        "deleted_at"
    ];

    public function fake(Generator &$faker)
    {
        return [
            "name"  => $faker->unique()->firstName(),
            "unit"  => $faker->unique()->slug(1, false),
            "description"  => $faker->paragraph()
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
