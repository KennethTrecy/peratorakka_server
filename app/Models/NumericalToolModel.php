<?php

namespace App\Models;

use App\Entities\NumericalTool;
use CodeIgniter\Shield\Entities\User;
use Faker\Generator;

class NumericalToolModel extends BaseResourceModel
{
    protected $table = "numerical_tools";
    protected $returnType = NumericalTool::class;
    protected $allowedFields = [
        "user_id",
        "name",
        "kind",
        "recurrence",
        "recency",
        "order",
        "notes",
        "configuration",
        "deleted_at"
    ];

    protected $sortable_fields = [
        "name",
        "order",
        "created_at",
        "updated_at",
        "deleted_at"
    ];

    public function fake(Generator &$faker)
    {
        return [
            "name"  => $faker->unique()->firstName(),
            "kind"  => $faker->randomElement(ACCEPTABLE_NUMERICAL_TOOL_KINDS),
            "recurrence"  => $faker->randomElement(ACCEPTABLE_NUMERICAL_TOOL_RECURRENCE_PERIODS),
            "recency"  => $faker->numberBetween(-100, 100),
            "order"  => $faker->numberBetween(0, 100),
            "notes"  => $faker->paragraph(),
            "configuration"  => json_encode([
                "sources" => [
                    [
                        "collection_id" => 1,
                        "currency_id" => 1,
                        "exchange_rate_basis" => PERIODIC_FORMULA_EXCHANGE_RATE_BASIS,
                        "stage_basis" => UNADJUSTED_AMOUNT_STAGE_BASIS,
                        "side_basis" => NET_DEBIT_AMOUNT_SIDE_BASIS,
                        "must_show_individual_amounts" => true,
                        "must_show_collective_sum" => true,
                        "must_show_collective_average" => true
                    ]
                ]
            ])
        ];
    }

    public function limitSearchToUser(BaseResourceModel $query_builder, User $user)
    {
        return $query_builder->where("user_id", $user->id);
    }
}
