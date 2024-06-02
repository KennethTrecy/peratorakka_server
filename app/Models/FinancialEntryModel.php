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
            "transacted_at"  => Time::today()->toDateTimeString(),
            "debit_amount"  => $amount,
            "credit_amount"  => $amount,
            "remarks"  => $faker->paragraph(),
        ];
    }

    public function filterList(BaseResourceModel $query_builder, array $options) {
        $query_builder = parent::filterList($query_builder, $options);

        $filter_account_id = $options["account_id"] ?? null;
        $begin_date = $options["begin_date"] ?? null;
        $end_date = $options["end_date"] ?? null;

        if (!is_null($filter_account_id)) {
            $query_builder = $query_builder
                ->whereIn(
                    "modifier_id",
                    model(ModifierModel::class, false)
                        ->builder()
                        ->select("id")
                        ->where("debit_account_id", $filter_account_id)
                        ->orWhere("credit_account_id", $filter_account_id)
                );
        }

        if (!is_null($begin_date)) {
            $query_builder = $query_builder
                ->where(
                    "transacted_at >=",
                    $begin_date
                );
        }

        if (!is_null($end_date)) {
            $query_builder = $query_builder
                ->where(
                    "transacted_at <=",
                    $end_date
                );
        }

        return $query_builder;
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
        $cash_flow_activity_subquery = model(CashFlowActivityModel::class, false)
            ->builder()
            ->select("id")
            ->where("user_id", $user->id);

        return $query_builder
            ->whereIn(
                "modifier_id",
                model(ModifierModel::class, false)
                    ->builder()
                    ->select("id")
                    ->whereIn("debit_account_id", $account_subquery)
                    ->whereIn("credit_account_id", $account_subquery)
                    ->groupStart()
                        ->whereIn("debit_cash_flow_activity_id", $cash_flow_activity_subquery)
                        ->orWhere("debit_cash_flow_activity_id IS NULL")
                    ->groupEnd()
                    ->groupStart()
                        ->whereIn("credit_cash_flow_activity_id", $cash_flow_activity_subquery)
                        ->orWhere("credit_cash_flow_activity_id IS NULL")
                    ->groupEnd()
            );
    }
}
