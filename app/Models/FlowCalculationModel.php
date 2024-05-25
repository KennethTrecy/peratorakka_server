<?php

namespace App\Models;

use DateTimeInterface;

use CodeIgniter\Shield\Entities\User;
use Faker\Generator;

use App\Entities\FlowCalculation;

class FlowCalculationModel extends BaseResourceModel
{
    protected $table = "flow_calculations";
    protected $returnType = FlowCalculation::class;
    protected $allowedFields = [
        "frozen_period_id",
        "cash_flow_activity_id",
        "account_id",
        "net_amount"
    ];
    protected $useTimestamps = false;
    protected $useSoftDeletes = false;

    public function fake(Generator &$faker)
    {
        $amount = $faker->regexify("\d{5}\.\d{3}");
        return [
            "net_amount"  => $amount
        ];
    }

    public function limitSearchToUser(BaseResourceModel $query_builder, User $user) {
        return $query_builder
            ->whereIn(
                "cash_flow_activity_id",
                model(CashFlowActivityModel::class, false)
                    ->builder()
                    ->select("id")
                    ->where("user_id", $user->id)
            )
            ->whereIn(
                "frozen_period_id",
                model(FrozenPeriodModel::class, false)
                    ->builder()
                    ->select("id")
                    ->where("user_id", $user->id)
            )
            ->whereIn(
                "account_id",
                model(AccountModel::class, false)
                    ->builder()
                    ->select("id")
                    ->whereIn(
                        "currency_id",
                        model(CurrencyModel::class, false)
                            ->builder()
                            ->select("id")
                            ->where("user_id", $user->id)
                    )
            );
    }
}
