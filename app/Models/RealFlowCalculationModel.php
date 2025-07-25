<?php

namespace App\Models;

use App\Entities\RealFlowCalculation;
use CodeIgniter\Shield\Entities\User;
use Faker\Generator;

class RealFlowCalculationModel extends BaseResourceModel
{
    protected $table = "real_flow_calculations";
    protected $returnType = RealFlowCalculation::class;
    protected $allowedFields = [
        "frozen_account_hash",
        "cash_flow_activity_id",
        "net_amount"
    ];
    protected $useTimestamps = false;
    protected $useSoftDeletes = false;

    protected $sortable_fields = [];

    public function fake(Generator &$faker)
    {
        return [];
    }

    public function limitSearchToUser(BaseResourceModel $query_builder, User $user)
    {
        return $query_builder
            ->whereIn(
                "frozen_account_hash",
                model(FrozenAccountModel::class, false)
                    ->builder()
                    ->select("hash")
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
                                    ->whereIn(
                                        "precision_format_id",
                                        model(PrecisionFormatModel::class, false)
                                            ->builder()
                                            ->select("id")
                                            ->where("user_id", $user->id)
                                    )
                            )
                    )
            )->whereIn(
                "cash_flow_activity_id",
                model(CashFlowActivityModel::class, false)
                    ->builder()
                    ->select("id")
                    ->where("user_id", $user->id)
            );
    }

    public static function extractLinkedCashFlowActivities(array $flow_calculations): array
    {
        return array_map(
            fn ($flow_calculation) => $flow_calculation->cash_flow_activity_id,
            $flow_calculations
        );
    }
}
