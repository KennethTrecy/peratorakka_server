<?php

namespace App\Models\Deprecated;

use App\Entities\Deprecated\SummaryCalculation;
use App\Models\BaseResourceModel;
use App\Models\FrozenPeriodModel;
use CodeIgniter\Shield\Entities\User;
use DateTimeInterface;
use Faker\Generator;

class DeprecatedSummaryCalculationModel extends BaseResourceModel
{
    protected $table = "summary_calculations";
    protected $returnType = SummaryCalculation::class;
    protected $allowedFields = [
        "frozen_period_id",
        "account_id",
        "opened_debit_amount",
        "opened_credit_amount",
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
            "opened_debit_amount"  => $amount,
            "opened_credit_amount"  => $amount,
            "unadjusted_debit_amount"  => $amount,
            "unadjusted_credit_amount"  => $amount,
            "closed_debit_amount"  => $amount,
            "closed_credit_amount"  => $amount,
        ];
    }

    public function limitSearchToUser(BaseResourceModel $query_builder, User $user)
    {
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
                model(DeprecatedAccountModel::class, false)
                    ->builder()
                    ->select("id")
                    ->whereIn(
                        "currency_id",
                        model(DeprecatedCurrencyModel::class, false)
                            ->builder()
                            ->select("id")
                            ->where("user_id", $user->id)
                    )
            );
    }

    public static function extractLinkedAccounts(array $calculations): array
    {
        return array_map(
            function ($calculation) {
                return $calculation->account_id;
            },
            $calculations
        );
    }
}
