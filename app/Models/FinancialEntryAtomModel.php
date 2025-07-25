<?php

namespace App\Models;

use App\Entities\FinancialEntryAtom;
use App\Libraries\Resource;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use DateTimeInterface;
use Faker\Generator;

class FinancialEntryAtomModel extends BaseResourceModel
{
    protected $table = "financial_entry_atoms";
    protected $returnType = FinancialEntryAtom::class;
    protected $allowedFields = [
        "financial_entry_id",
        "modifier_atom_id",
        "kind",
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
            "kind" => $faker->randomElement(ACCEPTABLE_FINANCIAL_ENTRY_ATOM_KINDS),
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

    protected static function createAncestorResources(int $user_id, array $options): array
    {
        $combinations = $options["combinations"] ?? [];
        $entries = $options["entries"] ?? [];

        [
            $precision_formats,
            $currencies,
            $accounts,
            $modifiers,
            $modifier_atoms,
            $cash_flow_activities,
            $modifier_atom_activities
        ] = ModifierAtomActivityModel::createTestResources(
            $user_id,
            1,
            $options["modifier_atom_activity_options"] ?? []
        );

        [
            $modifiers,
            $financial_entries
        ] = FinancialEntryModel::createTestResources(
            $user_id,
            1,
            array_merge(
                $options["financial_entry_options"] ?? [],
                [
                    "parent_modifiers" => $modifiers
                ]
            )
        );

        $filtered_parent_links = [];
        if (isset($options["entries"])) {
            $keyed_accounts = Resource::key($accounts, fn ($account) => $account->id);
            $keyed_modifiers = Resource::key($modifiers, fn ($modifier) => $modifier->id);
            $keyed_modifier_atoms = Resource::key(
                $modifier_atoms,
                fn ($modifier_atom) => (
                    $keyed_modifiers[$modifier_atom->modifier_id]->action
                    ."_"
                    .$modifier_atom->kind
                    ."_"
                    .$keyed_accounts[$modifier_atom->account_id]->kind
                )
            );
            $keyed_financial_entries = Resource::key(
                $financial_entries,
                fn ($financial_entry) => (
                    $financial_entry->modifier_id
                )
            );

            $filtered_parent_links = array_map(
                fn ($entry) => [
                    "financial_entry_id" => $keyed_financial_entries[
                        $keyed_modifier_atoms[
                            $entry[0]
                            ."_"
                            .$entry[1]
                            ."_"
                            .$entry[2]
                        ]->modifier_id
                    ]->id,
                    "modifier_atom_id" => $keyed_modifier_atoms[
                        $entry[0]
                        ."_"
                        .$entry[1]
                        ."_"
                        .$entry[2]
                    ]->id,
                    "kind" => count($entry) > 4
                        ? $entry[3]
                        : TOTAL_FINANCIAL_ENTRY_ATOM_KIND,
                    "numerical_value" => count($entry) > 4
                        ? $entry[4]
                        : $entry[3]
                ],
                $entries
            );
        } else {
            $filtered_parent_links = static::permutateParentLinks([
                "financial_entry_id" => array_map(
                    fn ($financial_entry) => $financial_entry->id,
                    $financial_entries
                ),
                "modifier_atom_id" => array_map(
                    fn ($modifier_atom) => $modifier_atom->id,
                    $modifier_atoms
                )
            ], $options);
        }

        return [
            [
                $precision_formats,
                $currencies,
                $accounts,
                $modifiers,
                $modifier_atoms,
                $cash_flow_activities,
                $modifier_atom_activities,
                $financial_entries
            ],
            $filtered_parent_links
        ];
    }

    protected static function identifyAncestors(): array
    {
        return [
            FinancialEntryModel::class => [ "financial_entry_id" ],
            ModifierAtomModel::class => [ "modifier_atom_id" ]
        ];
    }
}
