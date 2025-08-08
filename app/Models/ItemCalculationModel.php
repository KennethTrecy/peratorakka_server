<?php

namespace App\Models;

use App\Entities\ItemCalculation;
use CodeIgniter\Shield\Entities\User;
use Faker\Generator;

class ItemCalculationModel extends BaseResourceModel
{
    protected $table = "item_calculations";
    protected $returnType = ItemCalculation::class;
    protected $allowedFields = [
        "frozen_account_hash",
        "financial_entry_id",
        "unit_price",
        "remaining_quantity"
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
                "financial_entry_id",
                model(FinancialEntryModel::class, false)
                    ->whereIn(
                        "modifier_id",
                        model(ModifierModel::class, false)
                            ->builder()
                            ->select("id")
                            ->where("user_id", $user->id)
                    )
            );
    }

    public static function extractLinkedFinancialEntries(array $item_calculations): array
    {
        return array_map(
            fn ($item_calculation) => $item_calculation->financial_entry_id,
            $item_calculations
        );
    }
}
