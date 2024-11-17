<?php

namespace App\Models;

use App\Entities\NumericalTool;
use App\Libraries\Context;
use App\Libraries\Context\TimeGroupManager;
use App\Libraries\NumericalToolConfiguration\CollectionSource;
use App\Libraries\Resource;
use App\Libraries\TimeGroup\PeriodicTimeGroup;
use App\Libraries\TimeGroup\UnfrozenTimeGroup;
use App\Libraries\TimeGroup\YearlyTimeGroup;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use Faker\Generator;

class NumericalToolModel extends BaseResourceModel
{
    protected $table = "numerical_tools";
    protected $returnType = NumericalTool::class;
    protected $allowedFields = [
        "user_id",
        "name",
        "kind",
        "recurrence",
        "recency",
        "order",
        "notes",
        "configuration",
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
            "recurrence"  => $faker->randomElement(ACCEPTABLE_NUMERICAL_TOOL_RECURRENCE_PERIODS),
            "recency"  => $faker->numberBetween(-100, 100),
            "order"  => $faker->numberBetween(0, 100),
            "notes"  => $faker->paragraph(),
            "configuration"  => json_encode([
                "sources" => [
                    [
                        "type" => CollectionSource::sourceType(),
                        "collection_id" => 1,
                        "currency_id" => 1,
                        "exchange_rate_basis" => PERIODIC_FORMULA_EXCHANGE_RATE_BASIS,
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
        return $query_builder->where("user_id", $user->id);
    }

    public static function showConstellations(NumericalTool $tool): array {
        $context = new Context();
        $time_groups = new TimeGroupManager(
            $context,
            static::makeTimeGroups($tool->recurrence, $tool->recency)
        );
        $constellations = $tool->configuration->calculate($context);

        return [ $time_groups->timeTags(), $constellations ];
    }

    private static function makeTimeGroups(string $recurrence, int $recency): array {
        $current_date = Time::today();
        $maxed_current_date = $current_date->setHour(23)->setMinute(59)->setSecond(59);
        $last_frozen_period = FrozenPeriodModel::findLatestPeriod(
            $maxed_current_date->toDateTimeString()
        );

        $frozen_time_group_limit = abs($recency);
        $time_groups = $frozen_time_group_limit > 0
            ? [ new PeriodicTimeGroup($last_frozen_period) ]
            : [];

        // Happens for new users and there is no frozen period yet
        if ($recency < 1 && is_null($last_frozen_period)) {
            $last_financial_entry = model(FinancialEntryModel::class)
                ->orderBy("transacted_at", "ASC")
                ->withDeleted()
                ->first();

            $possible_unfrozen_date = is_null($last_financial_entry)
                ? $current_date
                : $last_financial_entry->transacted_at;

            array_push($time_groups, UnfrozenTimeGroup::make(
                $possible_unfrozen_date,
                $maxed_current_date
            ));

            return $time_groups;
        }

        $possible_unfrozen_date = $last_frozen_period->finished_at
            ->addDays(1)
            ->setHour(0)->setMinute(0)->setSecond(0);
        $frozen_time_group_limit = abs($recency);

        if ($recency < 1 && $current_date->isAfter($possible_unfrozen_date)) {
            array_push($time_groups, UnfrozenTimeGroup::make(
                $possible_unfrozen_date,
                $maxed_current_date
            ));
        }

        switch ($recurrence) {
            case PERIODIC_NUMERICAL_TOOL_RECURRENCE_PERIOD:
                if ($frozen_time_group_limit < 2) break;

                $frozen_periods = model(FrozenPeriodModel::class, false)
                    ->where("finished_at <=", $last_frozen_period->started_at->toDateTimeString())
                    ->orderBy("finished_at", "DESC")
                    ->limit($frozen_time_group_limit - 1)
                    ->findAll();

                array_unshift($time_groups, ...array_map(function ($frozen_period) {
                    return new PeriodicTimeGroup($frozen_period);
                }, $frozen_periods));

                break;
            case YEARLY_NUMERICAL_TOOL_RECURRENCE_PERIOD:
                $last_frozen_period_year = $last_frozen_period->started_at->year;
                $earliest_year = $last_frozen_period_year - $frozen_time_group_limit;
                $earliest_date_of_earliest_year = Time::createFromDate($earliest_year, 1, 1);

                $frozen_periods = model(FrozenPeriodModel::class, false)
                    ->where("started_at <", $earliest_date_of_earliest_year->toDateTimeString())
                    ->where("finished_at <", $last_frozen_period->started_at->toDateTimeString())
                    ->orderBy("finished_at", "DESC")
                    ->findAll();

                array_unshift($time_groups, ...array_map(function ($frozen_period) {
                    return new PeriodicTimeGroup($frozen_period);
                }, $frozen_periods));

                $specific_time_groups = Resource::group($time_groups, function ($time_group) {
                    return $time_group->finishedAt()->year;
                });

                $time_groups = [];
                for ($year = $earliest_year; $year <= $last_frozen_period_year; $year++) {
                    $yearly_time_group = new YearlyTimeGroup($year, true);
                    if (isset($specific_time_groups[$year])) {
                        foreach ($specific_time_groups[$year] as $time_group) {
                            $yearly_time_group->addTimeGroup($time_group);
                        }
                    }
                    array_push($time_groups, $yearly_time_group);
                }

                break;
            default:
                return [];
        }

        return $time_groups;
    }
}
