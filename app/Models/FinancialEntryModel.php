<?php

namespace App\Models;

use App\Entities\FinancialEntry;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use DateTimeInterface;
use Faker\Generator;

class FinancialEntryModel extends BaseResourceModel
{
    protected $table = "financial_entries_v2";
    protected $returnType = FinancialEntry::class;
    protected $allowedFields = [
        "modifier_id",
        "transacted_at",
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
        return [
            "transacted_at"  => Time::today()->toDateTimeString(),
            "remarks"  => $faker->paragraph(),
        ];
    }

    public function filterList(BaseResourceModel $query_builder, array $options)
    {
        $query_builder = parent::filterList($query_builder, $options);

        $filter_account_id = $options["account_id"] ?? null;
        $filter_modifier_id = $options["modifier_id"] ?? null;
        $begin_date = $options["begin_date"] ?? null;
        $end_date = $options["end_date"] ?? null;

        if (!is_null($filter_account_id)) {
            $query_builder = $query_builder
                ->whereIn(
                    "id",
                    model(FinancialEntryAtomModel::class, false)
                        ->builder()
                        ->select("financial_entry_id")
                        ->where(
                            "modifier_atom_id",
                            model(ModifierAtomModel::class, false)
                                ->builder()
                                ->select("id")
                                ->where(
                                    "account_id",
                                    $filter_account_id
                                )
                        )
                );
        }

        if (!is_null($filter_modifier_id)) {
            $query_builder = $query_builder
                ->where("modifier_id", $filter_modifier_id);
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

    public function limitSearchToUser(BaseResourceModel $query_builder, User $user)
    {
        return $query_builder
            ->whereIn(
                "modifier_id",
                model(ModifierModel::class, false)
                    ->builder()
                    ->select("id")
                    ->where("user_id", $user->id)
            );
    }

    protected static function identifyAncestors(): array
    {
        return [
            ModifierModel::class => [ "modifier_id" ]
        ];
    }
}
