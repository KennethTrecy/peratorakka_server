<?php

namespace App\Models;

use App\Entities\ModifierAtom;
use App\Libraries\Resource;
use CodeIgniter\Shield\Entities\User;
use Faker\Generator;

class ModifierAtomModel extends BaseResourceModel
{
    protected $table = "modifier_atoms";
    protected $returnType = ModifierAtom::class;
    protected $allowedFields = [
        "modifier_id",
        "account_id",
        "kind"
    ];
    protected $useTimestamps = false;
    protected $useSoftDeletes = false;

    protected $sortable_fields = [];

    public function fake(Generator &$faker)
    {
        return [
            "kind" => $faker->randomElement(ACCEPTABLE_MODIFIER_ATOM_KINDS)
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

        return $query_builder
            ->whereIn("account_id", $account_subquery)
            ->whereIn("modifier_id", $modifier_subquery);
    }

    protected static function createAncestorResources(int $user_id, array $options): array
    {
        $combination_options = $options["combinations"] ?? [];
        $specific_modifier_actions = array_map(
            fn ($combination) => $combination[0],
            $combination_options
        );
        $specific_account_kinds = array_unique(
            array_reduce(
                $combination_options,
                fn ($previous_combinations, $combination) => [
                    ...$previous_combinations,
                    ...$combination[2]
                ],
                []
            )
        );

        $modifier_options = $options["modifier_options"] ?? [ "expected_actions" => [] ];
        $modifier_options["expected_actions"] = array_merge(
            $modifier_options["expected_actions"],
            $specific_modifier_actions
        );
        $account_options = $options["account_options"] ?? [ "expected_kinds" => [] ];
        $account_options["expected_kinds"] = array_unique(
            array_merge($account_options["expected_kinds"], $specific_account_kinds)
        );

        [
            $precision_formats,
            $currencies,
            $accounts
        ] = isset($options["ancestor_accounts"])
            ? $options["ancestor_accounts"]
            : AccountModel::createTestResources($user_id, 1, $account_options);
        [
            $modifiers
        ] = isset($options["parent_modifiers"])
            ? [ $options["parent_modifiers"] ]
            : ModifierModel::createTestResources($user_id, 1, $modifier_options);

        $filtered_parent_links = [];
        if (isset($options["combinations"])) {
            $keyed_accounts = Resource::key($accounts, fn ($account) => $account->kind);

            $filtered_parent_links = array_reduce(
                array_map(null, array_keys($options["combinations"]), $options["combinations"]),
                fn ($parent_links, $combination_info) => [
                    ...$parent_links,
                    ...array_map(
                        fn ($kind_combination, $account_combination) => [
                            "modifier_id" => $modifiers[$combination_info[0]]->id,
                            "account_id" => $keyed_accounts[$account_combination]->id,
                            "kind" => $kind_combination
                        ],
                        $combination_info[1][1],
                        $combination_info[1][2]
                    )
                ],
                []
            );
        } else {
            $filtered_parent_links = static::permutateParentLinks([
                "account_id" => array_map(fn ($account) => $account->id, $accounts),
                "modifier_id" => array_map(fn ($modifier) => $modifier->id, $modifiers)
            ], $options);
        }

        return [
            [ $precision_formats, $currencies, $accounts, $modifiers ],
            $filtered_parent_links
        ];
    }

    protected static function identifyAncestors(): array
    {
        return [
            AccountModel::class => [ "account_id" ],
            ModifierModel::class => [ "modifier_id" ]
        ];
    }
}
