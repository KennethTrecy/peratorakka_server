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
        "cash_flow_category_id",
        "summary_calculation_id",
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
                "cash_flow_category_id",
                model(CashFlowCategoryModel::class, false)
                    ->builder()
                    ->select("id")
                    ->where("user_id", $user->id)
            )
            ->whereIn(
                "summary_calculation_id",
                model(SummaryCalculationModel::class, false)
                    ->builder()
                    ->select("id")
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
                    )
            );
    }
}
