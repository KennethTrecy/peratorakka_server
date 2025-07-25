<?php

namespace App\Models;

use App\Entities\Modifier;
use CodeIgniter\Shield\Entities\User;
use Faker\Generator;

class ModifierModel extends BaseResourceModel
{
    protected $table = "modifiers_v2";
    protected $returnType = Modifier::class;
    protected $allowedFields = [
        "user_id",
        "name",
        "description",
        "action",
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
            "action"  => $faker->randomElement([
                RECORD_MODIFIER_ACTION,
                CLOSE_MODIFIER_ACTION
            ]),
            "kind"  => $faker->randomElement(ACCEPTABLE_MODIFIER_KINDS),
        ];
    }

    public function limitSearchToUser(BaseResourceModel $query_builder, User $user)
    {
        return $query_builder->where("user_id", $user->id);
    }

    protected static function createAncestorResources(int $user_id, array $options): array
    {
        $ancestor_resources = [];
        $parent_links = static::permutateParentLinks([
            "user_id" => [ $user_id ],
            "action" => $options["expected_actions"] ?? []
        ], $options);

        return [
            $ancestor_resources,
            $parent_links
        ];
    }
}
