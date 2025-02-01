<?php

namespace App\Models;

use App\Entities\ModifierAtom;
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
        $specific_modifier_actions = array_unique(
            array_map(fn ($combination) => $combination[0], $combination_options)
        );
        $specific_account_kinds = array_unique(
            array_merge(
                array_map(fn ($combination) => $combination[1], $combination_options),
                array_map(fn ($combination) => $combination[2], $combination_options)
            )
        );

        $modifier_options = $options["modifier_options"] ?? [ "expected_actions" => [] ];
        $modifier_options["expected_actions"] = array_unique(
            array_merge($modifier_options["expected_actions"], $specific_modifier_actions)
        );
        $account_options = $options["account_options"] ?? [ "expected_kinds" => [] ];
        $account_options["expected_kinds"] = array_unique(
            array_merge($account_options["expected_kinds"], $specific_account_kinds)
        );

        [
            $precision_formats,
            $currencies,
            $accounts
        ] = AccountModel::createTestResources($user_id, 1, $account_options);
        [
            $modifiers
        ] = ModifierModel::createTestResources($user_id, 1, $modifier_options);

        $filtered_parent_links = [];
        if (isset($options["combinations"])) {
            $keyed_accounts = Resource::key($accounts, fn ($account) => $account->kind);
            $keyed_modifiers = Resource::key($modifiers, fn ($modifier) => $modifier->kind);

            $filtered_parent_links = array_reduce(
                $options["combinations"],
                fn ($parent_links, $combination) => [
                    ...$parent_links,
                    [
                        "modifier_id" => $keyed_accounts[$combination[0]],
                        "account_id" => $keyed_accounts[$combination[1]]
                    ],
                    [
                        "modifier_id" => $keyed_accounts[$combination[0]],
                        "account_id" => $keyed_accounts[$combination[2]]
                    ]
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
