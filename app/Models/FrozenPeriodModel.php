<?php

namespace App\Models;

use DateTimeInterface;

use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use Faker\Generator;

use App\Entities\FrozenPeriod;

class FrozenPeriodModel extends BaseResourceModel
{
    protected $table = "frozen_periods";
    protected $returnType = FrozenPeriod::class;
    protected $allowedFields = [
        "user_id",
        "started_at",
        "finished_at",
        "deleted_at"
    ];

    public function fake(Generator &$faker)
    {
        return [
            "started_at"  => Time::now()->toDateTimeString(),
            "finished_at"  => Time::now()->toDateTimeString()
        ];
    }

    public function limitSearchToUser(BaseResourceModel $query_builder, User $user) {
        return $query_builder->where("user_id", $user->id);
    }
}
