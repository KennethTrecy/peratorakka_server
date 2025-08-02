<?php

namespace App\Models;

use App\Entities\ItemConfiguration;
use CodeIgniter\Shield\Entities\User;
use Faker\Generator;

class ItemConfigurationModel extends BaseResourceModel
{
    protected $table = "item_configurations";
    protected $returnType = ItemConfiguration::class;
    protected $allowedFields = [
        "account_id",
        "item_detail_id",
        "valuation_method"
    ];
    protected $useTimestamps = false;
    protected $useSoftDeletes = false;

    protected $sortable_fields = [
        "id"
    ];

    public function fake(Generator &$faker)
    {
        return [
            "valuation_method" => $faker->randomElement(ACCEPTABLE_VALUATION_METHODS)
        ];
    }

    public function limitSearchToUser(BaseResourceModel $query_builder, User $user)
    {
        return $query_builder
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
                            ->whereIn(
                                "precision_format_id",
                                model(PrecisionFormatModel::class, false)
                                    ->builder()
                                    ->select("id")
                                    ->where("user_id", $user->id)
                            )
                    )
            )
            ->whereIn(
                "item_detail_id",
                model(ItemDetailModel::class, false)
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
    }

    protected static function createAncestorResources(int $user_id, array $options): array
    {
        [
            $item_detail
        ] = $options["item_detail_parent"] ?? ItemDetailModel::createTestResource(
            $user_id,
            $options["item_detail_options"] ?? []
        );

        $account_options = $options["account_options"] ?? [ "expected_kinds" => [] ];

        [
            $precision_formats,
            $currencies,
            $accounts
        ] = isset($options["ancestor_accounts"])
            ? $options["ancestor_accounts"]
            : AccountModel::createTestResources($user_id, 1, $account_options);

        $parent_links = static::permutateParentLinks([
            "account_id" => array_map(fn ($account) => $account->id, $accounts),
            "item_detail_id" => [ $item_detail->id ]
        ], $options);

        return [
            [ $precision_formats, $currencies, $accounts, [ $item_detail ] ],
            $parent_links
        ];
    }
}
