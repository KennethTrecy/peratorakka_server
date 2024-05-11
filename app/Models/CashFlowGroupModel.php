<?php

namespace App\Models;

use CodeIgniter\Shield\Entities\User;
use Faker\Generator;

use App\Entities\CashFlowGroup;

class CashFlowGroupModel extends BaseResourceModel
{
    protected $table = "cash_flow_groups";
    protected $returnType = CashFlowGroup::class;
    protected $allowedFields = [
        "user_id",
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
            "kind"  => $faker->randomElement(ACCEPTABLE_CASH_FLOW_GROUP_KINDS),
        ];
    }

    public function limitSearchToUser(BaseResourceModel $query_builder, User $user) {
        return $query_builder->where("user_id", $user->id);
    }
}
