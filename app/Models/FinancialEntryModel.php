<?php

namespace App\Models;

use DateTimeInterface;

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

    protected $sortable_fields = [
        "transacted_at",
        "created_at",
        "updated_at",
        "deleted_at"
    ];

    public function fake(Generator &$faker)
    {
        $amount = $faker->regexify("\d{5}\.\d{3}");
        return [
            "transacted_at"  => Time::now()->toDateTimeString(),
            "debit_amount"  => $amount,
            "credit_amount"  => $amount,
            "remarks"  => $faker->paragraph(),
        ];
    }

    public function filterList(BaseResourceModel $query_builder, array $options) {
        $query_builder = parent::filterList($query_builder, $options);

        $filter_account_id = $options["account_id"] ?? null;

        if (is_null($filter_account_id)) {
            return $query_builder;
        } else {
            return $query_builder
                ->whereIn(
                    "modifier_id",
                    model(ModifierModel::class, false)
                        ->builder()
                        ->select("id")
                        ->whereIn("debit_account_id", $filter_account_id)
                        ->orWhereIn("credit_account_id", $filter_account_id)
                );
        }
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
                    ->whereIn("debit_account_id", $account_subquery)
                    ->whereIn("credit_account_id", $account_subquery)
            );
    }
}
