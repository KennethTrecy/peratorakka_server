<?php

namespace App\Models;

use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use Faker\Generator;

use App\Entities\FinancialEntry;

class FinancialEntryModel extends BaseResourceModel
{
    protected $table = "financial_entries";
    protected $returnType = FinancialEntry::class;
    protected $allowedFields = [
        "modifier_id",
        "transacted_at",
        "debit_amount",
        "credit_amount",
        "remarks",
        "deleted_at"
    ];

    public function fake(Generator &$faker)
    {
        $amount = $faker->regexify('\d{5}\.\d{3}');
        return [
            "transacted_at"  => Time::now(),
            "debit_amount"  => $amount,
            "credit_amount"  => $amount,
            "remarks"  => $faker->paragraph(),
        ];
    }

    public function limitSearchToUser(BaseResourceModel $query_builder, User $user) {
        $account_subquery = model(AccountModel::class, false)
            ->builder()
            ->select("id")
            ->whereIn(
                "currency_id",
                model(CurrencyModel::class, false)
                    ->builder()
                    ->select("id")
                    ->where("user_id", $user->id)
            );

        return $query_builder
            ->whereIn(
                "modifier_id",
                model(ModifierModel::class, false)
                    ->builder()
                    ->select("id")
                    ->whereIn("account_id", $account_subquery)
                    ->whereIn("opposite_account_id", $account_subquery)
            );
    }
}
