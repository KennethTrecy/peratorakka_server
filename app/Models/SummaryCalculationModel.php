<?php

namespace App\Models;

use DateTimeInterface;

use CodeIgniter\Shield\Entities\User;
use Faker\Generator;

use App\Entities\SummaryCalculation;

class SummaryCalculationModel extends BaseResourceModel
{
    protected $table = "summary_calculations";
    protected $returnType = SummaryCalculation::class;
    protected $allowedFields = [
        "frozen_period_id",
        "account_id",
        "unadjusted_debit_amount",
        "unadjusted_credit_amount",
        "closed_debit_amount",
        "closed_credit_amount"
    ];
    protected $useTimestamps = false;
    protected $useSoftDeletes = false;

    public function fake(Generator &$faker)
    {
        $amount = $faker->regexify("\d{5}\.\d{3}");
        return [
            "unadjusted_debit_amount"  => $amount,
            "unadjusted_credit_amount"  => $amount,
            "closed_debit_amount"  => $amount,
            "closed_credit_amount"  => $amount,
        ];
    }

    public function limitSearchToUser(BaseResourceModel $query_builder, User $user) {
        return $query_builder
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
