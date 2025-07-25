<?php

namespace App\Models;

use App\Entities\PrecisionFormat;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\Fabricator;
use Faker\Generator;

class PrecisionFormatModel extends BaseResourceModel
{
    protected $table = "precision_formats";
    protected $returnType = PrecisionFormat::class;
    protected $allowedFields = [
        "user_id",
        "name",
        "minimum_presentational_precision",
        "maximum_presentational_precision",
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
            "minimum_presentational_precision"  => $faker->randomElement([ 0, 1, 2 ]),
            "maximum_presentational_precision"  => $faker->randomElement([ 3, 4, 12 ])
        ];
    }

    public function limitSearchToUser(BaseResourceModel $query_builder, User $user)
    {
        return $query_builder->where("user_id", $user->id);
    }
}
