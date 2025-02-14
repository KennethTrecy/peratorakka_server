<?php

namespace App\Models;

use App\Entities\ModifierAtomActivity;
use App\Libraries\Resource;
use CodeIgniter\Shield\Entities\User;
use Faker\Generator;

class ModifierAtomActivityModel extends BaseResourceModel
{
    protected $primaryKey = "modifier_atom_id";
    protected $table = "modifier_atom_activities";
    protected $returnType = ModifierAtomActivity::class;
    protected $allowedFields = [
        "modifier_atom_id",
        "cash_flow_activity_id"
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
        $account_subquery = model(AccountModel::class, false)
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
            );
        $modifier_subquery = model(ModifierModel::class, false)
            ->builder()
            ->select("id")
            ->where("user_id", $user->id);
        $modifier_atom_subquery = model(ModifierAtomModel::class, false)
            ->builder()
            ->select("id")
            ->whereIn("account_id", $account_subquery)
            ->whereIn("modifier_id", $modifier_subquery);
        $cash_flow_activity_subquery = model(CashFlowActivityModel::class, false)
            ->builder()
            ->select("id")
            ->where("user_id", $user->id);

        return $query_builder
            ->where("modifier_atom_id", $modifier_atom_subquery)
            ->where("cash_flow_activity_id", $cash_flow_activity_subquery);
    }

    protected static function createAncestorResources(int $user_id, array $options): array
    {
        $combination_options = $options["combinations"] ?? [];
        $specific_cash_flow_activity_indexes = Resource::retainExistingElements(
            array_unique(
                array_reduce(
                    $combination_options,
                    fn ($previous_combinations, $combination) => [
                        ...$previous_combinations,
                        $combination[3]
                    ],
                    []
                )
            )
        );

        $modifier_atom_options = $options["modifier_atom_options"] ?? [ "combinations" => [] ];
        $modifier_atom_options["combinations"] = array_merge(
            $modifier_atom_options["combinations"] ?? [],
            array_map(fn ($combination) => array_slice($combination, 0, 3), $combination_options)
        );

        [
            $precision_formats,
            $currencies,
            $accounts,
            $modifiers,
            $modifier_atoms
        ] = ModifierAtomModel::createTestResources($user_id, 1, $modifier_atom_options);
        [
            $cash_flow_activities
        ] = CashFlowActivityModel::createTestResources(
            $user_id,
            count($specific_cash_flow_activity_indexes),
            $options["cash_flow_activity_options"] ?? []
        );

        $filtered_parent_links = [];
        if (isset($options["combinations"])) {
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

            $filtered_parent_links = Resource::retainExistingElements(array_reduce(
                $options["combinations"],
                fn ($parent_links, $combination) => [
                    ...$parent_links,
                    ...array_reduce(
                        $combination_options,
                        fn ($previous_combination, $combination) => [
                            ...$previous_combination,
                            ...array_map(
                                fn (
                                    $kind_combination,
                                    $account_combination,
                                    $activity_combination
                                ) => is_null($activity_combination)
                                    ? null
                                    : [
                                        "modifier_atom_id" => $keyed_modifier_atoms[
                                            $combination[0]
                                            ."_"
                                            .$kind_combination
                                            ."_"
                                            .$account_combination
                                        ]->id,
                                        "cash_flow_activity_id" => $cash_flow_activities[
                                            $activity_combination
                                        ]->id
                                    ],
                                $combination[1],
                                $combination[2],
                                $combination[3]
                            )
                        ],
                        []
                    )
                ],
                []
            ));
        } else {
            $filtered_parent_links = static::permutateParentLinks([
                "modifier_atom_id" => array_map(
                    fn ($modifier_atom) => $modifier_atom->id,
                    $modifier_atoms
                ),
                "cash_flow_activity_id" => array_map(
                    fn ($cash_flow_activity) => $cash_flow_activity->id,
                    $cash_flow_activities
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
                $cash_flow_activities
            ],
            $filtered_parent_links
        ];
    }

    protected static function identifyAncestors(): array
    {
        return [
            ModifierAtomModel::class => [ "modifier_atom_id" ],
            CashFlowActivityModel::class => [ "cash_flow_activity_id" ],
        ];
    }
}
