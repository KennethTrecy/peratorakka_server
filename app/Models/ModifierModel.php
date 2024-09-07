<?php

namespace App\Models;

use App\Entities\Modifier;
use CodeIgniter\Shield\Entities\User;
use Faker\Generator;

class ModifierModel extends BaseResourceModel
{
    protected $table = "modifiers";
    protected $returnType = Modifier::class;
    protected $allowedFields = [
        "debit_account_id",
        "credit_account_id",
        "debit_cash_flow_activity_id",
        "credit_cash_flow_activity_id",
        "name",
        "description",
        "action",
        "kind",
        "deleted_at"
    ];

    protected $sortable_fields = [
        "name",
        "created_at",
        "updated_at",
        "deleted_at"
    ];

    public function fake(Generator &$faker)
    {
        return [
            "name"  => $faker->unique()->firstName(),
            "description"  => $faker->paragraph(),
            "action"  => $faker->randomElement([
                RECORD_MODIFIER_ACTION,
                CLOSE_MODIFIER_ACTION,
            ]),
            "kind"  => $faker->randomElement(ACCEPTABLE_MODIFIER_KINDS),
        ];
    }

    public function limitSearchToUser(BaseResourceModel $query_builder, User $user)
    {
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
            ->whereIn("debit_account_id", $account_subquery)
            ->whereIn("credit_account_id", $account_subquery)
            ->groupStart()
                ->whereIn("debit_cash_flow_activity_id", $cash_flow_activity_subquery)
                ->orWhere("debit_cash_flow_activity_id IS NULL")
            ->groupEnd()
            ->groupStart()
                ->whereIn("credit_cash_flow_activity_id", $cash_flow_activity_subquery)
                ->orWhere("credit_cash_flow_activity_id IS NULL")
            ->groupEnd();
    }

    protected static function identifyAncestors(): array
    {
        return [
            AccountModel::class => [ "debit_account_id", "credit_account_id" ],
            CashFlowActivityModel::class => [
                "debit_cash_flow_activity_id",
                "credit_cash_flow_activity_id"
            ]
        ];
    }
}
