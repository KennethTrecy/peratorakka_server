<?php

namespace App\Models;

use App\Entities\NumericalTool;
use App\Libraries\Context;
use App\Libraries\Context\ContextKeys;
use App\Libraries\Context\FrozenAccountCache;
use App\Libraries\NumericalToolConfiguration\CollectionSource;
use App\Libraries\Resource;
use App\Libraries\TimeGroup\PeriodicTimeGroup;
use App\Libraries\TimeGroup\UnfrozenTimeGroup;
use App\Libraries\TimeGroup\YearlyTimeGroup;
use App\Libraries\TimeGroupManager;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use Faker\Generator;

class NumericalToolModel extends BaseResourceModel
{
    protected $table = "numerical_tools_v2";
    protected $returnType = NumericalTool::class;
    protected $allowedFields = [
        "currency_id",
        "exchange_rate_basis",
        "name",
        "kind",
        "recurrence",
        "recency",
        "order",
        "notes",
        "configuration",
        "created_at",
        "deleted_at"
    ];

    protected $sortable_fields = [
        "name",
        "order",
        "created_at",
        "updated_at",
        "deleted_at"
    ];

    public function fake(Generator &$faker)
    {
        return [
            "name"  => $faker->unique()->firstName(),
            "kind"  => $faker->randomElement(ACCEPTABLE_NUMERICAL_TOOL_KINDS),
            "exchange_rate_basis" => PERIODIC_EXCHANGE_RATE_BASIS,
            "recurrence"  => $faker->randomElement(ACCEPTABLE_NUMERICAL_TOOL_RECURRENCE_PERIODS),
            "recency"  => $faker->numberBetween(-100, 100),
            "order"  => $faker->numberBetween(0, 100),
            "notes"  => $faker->paragraph(),
            "configuration"  => json_encode([
                "sources" => [
                    [
                        "type" => CollectionSource::sourceType(),
                        "collection_id" => 1,
                        "stage_basis" => UNADJUSTED_AMOUNT_STAGE_BASIS,
                        "side_basis" => NET_DEBIT_AMOUNT_SIDE_BASIS,
                        "must_show_individual_amounts" => true,
                        "must_show_collective_sum" => true,
                        "must_show_collective_average" => true
                    ]
                ]
            ])
        ];
    }

    public function limitSearchToUser(BaseResourceModel $query_builder, User $user)
    {
        return $query_builder
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
    }

    public static function showConstellations(Time $reference_time, NumericalTool $tool): array
    {
        $context = new Context();
        $context->setVariable(ContextKeys::DESTINATION_CURRENCY_ID, $tool->currency_id);
        $context->setVariable(ContextKeys::EXCHANGE_RATE_BASIS, $tool->exchange_rate_basis);
        $raw_time_groups = static::makeTimeGroups(
            $context,
            $reference_time,
            $tool->recurrence,
            $tool->recency
        );
        $time_groups = new TimeGroupManager($context, $raw_time_groups);
        $constellations = $tool->configuration->calculate($context);

        return [ $time_groups->timeTags(), $constellations ];
    }

    protected static function createAncestorResources(int $user_id, array $options): array
    {
        [
            $precision_formats,
            $currency
        ] = $options["parents"] ?? CurrencyModel::createTestResource(
            $user_id,
            $options["currency_options"] ?? []
        );

        $parent_links = static::permutateParentLinks([
            "currency_id" => [ $currency->id ]
        ], $options);

        return [
            [ $precision_formats, [ $currency ] ],
            $parent_links
        ];
    }

    protected static function identifyAncestors(): array
    {
        return [
            CurrencyModel::class => [ "currency_id" ]
        ];
    }

    private static function makeTimeGroups(
        Context $context,
        Time $reference_time,
        string $recurrence,
        int $recency
    ): array {
        $maxed_time = $reference_time->setHour(23)->setMinute(59)->setSecond(59);
        $last_frozen_period = FrozenPeriodModel::findLatestPeriod(
            $maxed_time->toDateTimeString()
        );
        $latest_known_date = $last_frozen_period === null
            ? $maxed_time
            : $last_frozen_period->finished_at;

        $frozen_time_group_limit = abs($recency);
        $must_include_unfrozen_period = $recency < 1;
        $time_groups = $frozen_time_group_limit > 0
            ? [ new PeriodicTimeGroup($last_frozen_period) ]
            : [];

        // Happens for new users and there is no frozen period yet
        if ($must_include_unfrozen_period && is_null($last_frozen_period)) {
            $last_financial_entry = model(FinancialEntryModel::class)
                ->orderBy("transacted_at", "ASC")
                ->withDeleted()
                ->first();

            $possible_unfrozen_date = is_null($last_financial_entry)
                ? $reference_time
                : $last_financial_entry->transacted_at;

            array_push($time_groups, UnfrozenTimeGroup::make(
                $possible_unfrozen_date,
                $maxed_time
            ));

            $latest_known_date = $maxed_time;

            return $time_groups;
        }

        $possible_unfrozen_date = $last_frozen_period->finished_at
            ->addDays(1)
            ->setHour(0)->setMinute(0)->setSecond(0);
        $frozen_time_group_limit = abs($recency);

        if ($must_include_unfrozen_period) {
            if ($maxed_time->isAfter($possible_unfrozen_date)) {
                array_push($time_groups, UnfrozenTimeGroup::make(
                    $possible_unfrozen_date,
                    $maxed_time
                ));
            } elseif (count($time_groups) > 1) {
                // Sometimes, max current date is less than or equal to possible unfrozen date. This
                // situation happens when all possible time periods are frozen. Since last time
                // group was already frozen and included in time groups, adjust the frozen time
                // group limit.
                $frozen_time_group_limit += 1;
            }

            $latest_known_date = $maxed_time;
        }

        switch ($recurrence) {
            case PERIODIC_NUMERICAL_TOOL_RECURRENCE_PERIOD:
                if ($frozen_time_group_limit < 2) {
                    break;
                }

                $frozen_periods = model(FrozenPeriodModel::class, false)
                    ->where("finished_at <=", $last_frozen_period->started_at->toDateTimeString())
                    ->orderBy("finished_at", "DESC")
                    ->limit($frozen_time_group_limit - 1)
                    ->findAll();

                array_unshift($time_groups, ...array_reverse(array_map(function ($frozen_period) {
                    return new PeriodicTimeGroup($frozen_period);
                }, $frozen_periods)));

                break;
            case YEARLY_NUMERICAL_TOOL_RECURRENCE_PERIOD:
                $last_known_year = $must_include_unfrozen_period
                    ? $latest_known_date->year
                    : $last_frozen_period->started_at->year;
                $earliest_year = $last_known_year
                    - $frozen_time_group_limit
                    + ($must_include_unfrozen_period ? 0 : 1);
                $earliest_date_of_earliest_year = Time::createFromDate($earliest_year, 1, 1);
                $latest_date_of_latest_year = Time::createFromDate($last_known_year, 12, 31);

                $frozen_periods = model(FrozenPeriodModel::class, false)
                    ->where("started_at >=", $earliest_date_of_earliest_year->toDateTimeString())
                    ->where("finished_at <=", $latest_date_of_latest_year->toDateTimeString())
                    ->orderBy("finished_at", "DESC")
                    ->findAll();

                array_unshift($time_groups, ...array_map(function ($frozen_period) {
                    return new PeriodicTimeGroup($frozen_period);
                }, $frozen_periods));

                $specific_time_groups = Resource::group($time_groups, function ($time_group) {
                    return $time_group->startedAt()->year;
                });

                $frozen_account_cache = FrozenAccountCache::make($context);
                $time_groups = [];
                for ($year = $earliest_year; $year <= $last_known_year; $year++) {
                    $yearly_time_group = new YearlyTimeGroup($frozen_account_cache, $year, true);
                    if (isset($specific_time_groups[$year])) {
                        foreach ($specific_time_groups[$year] as $time_group) {
                            $yearly_time_group->addTimeGroup($time_group);
                        }
                    }
                    array_push($time_groups, $yearly_time_group);
                }

                break;
        }

        return $time_groups;
    }
}
