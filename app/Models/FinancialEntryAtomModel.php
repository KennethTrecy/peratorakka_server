<?php

namespace App\Models;

use App\Entities\FinancialEntryAtom;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use DateTimeInterface;
use Faker\Generator;

class FinancialEntryAtomModel extends BaseResourceModel
{
    protected $table = "financial_entry_stoms";
    protected $returnType = FinancialEntryAtom::class;
    protected $allowedFields = [
        "financial_entry_id",
        "modifier_atom_id",
        "numerical_value"
    ];
    protected $useTimestamps = false;
    protected $useSoftDeletes = false;

    protected $sortable_fields = [
        "financial_entry_id",
        "modifier_atom_id"
    ];

    public function fake(Generator &$faker)
    {
        $amount = $faker->regexify("\d{5}\.\d{3}");
        return [
            "numerical_value" => $amount
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
                    "modifier_id",
                    model(ModifierAtomModel::class, false)
                        ->builder()
                        ->select("id")
                        ->where(
                            "account_id",
                            $filter_account_id
                        )
                );
        }

        if (!is_null($filter_modifier_id)) {
            $query_builder = $query_builder
                ->where("modifier_id", $filter_modifier_id);
        }

        if (!is_null($begin_date)) {
            $query_builder = $query_builder
                ->whereIn(
                    "financial_entry_id",
                    model(FinancialEntryAtom::class, false)
                        ->builder()
                        ->select("id")
                        ->where(
                            "transacted_at >=",
                            $begin_date
                        )
                );
        }

        if (!is_null($end_date)) {
            $query_builder = $query_builder
                ->whereIn(
                    "financial_entry_id",
                    model(FinancialEntryAtom::class, false)
                        ->builder()
                        ->select("id")
                        ->where(
                            "transacted_at <=",
                            $begin_date
                        )
                );
        }

        return $query_builder;
    }

    public function limitSearchToUser(BaseResourceModel $query_builder, User $user)
    {
        $modifier_subquery = model(ModifierModel::class, false)
            ->builder()
            ->select("id")
            ->where("user_id", $user->id);

        return $query_builder
            ->whereIn(
                "modifier_atom_id",
                model(ModifierAtomModel::class, false)
                    ->builder()
                    ->select("id")
                    ->whereIn("modifier_id", $modifier_subquery)
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
                                    ->where(
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
                    ->builder()
                    ->select("id")
                    ->whereIn("modifier_id", $modifier_subquery)
            );
    }

    protected static function identifyAncestors(): array
    {
        return [
            FinancialEntryModel::class => [ "financial_entry_id" ],
            ModifierAtomModel::class => [ "modifier_atom_id" ]
        ];
    }
}
