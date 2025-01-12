<?php

namespace App\Models;

use App\Entities\CashFlowActivity;
use CodeIgniter\Shield\Entities\User;
use Faker\Generator;

class CashFlowActivityModel extends BaseResourceModel
{
    protected $table = "cash_flow_activities";
    protected $returnType = CashFlowActivity::class;
    protected $allowedFields = [
        "user_id",
        "name",
        "description",
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
        ];
    }

    public function limitSearchToUser(BaseResourceModel $query_builder, User $user)
    {
        return $query_builder->where("user_id", $user->id);
    }
}
