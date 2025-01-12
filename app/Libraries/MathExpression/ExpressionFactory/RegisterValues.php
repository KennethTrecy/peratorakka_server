<?php

namespace App\Libraries\MathExpression\ExpressionFactory;

use App\Casts\AccountKind;
use App\Exceptions\ExpressionException;
use App\Libraries\Context\FlashCache;
use App\Libraries\Context;
use App\Libraries\Context\ContextKeys;
use App\Models\AccountCollectionModel;
use App\Models\AccountModel;
use App\Models\CollectionModel;
use Closure;
use CodeIgniter\Database\BaseBuilder;
use Xylemical\Expressions\Token;
use Xylemical\Expressions\Value;

trait RegisterValues
{
    public function addValues()
    {
        $this->addValue('CYCLE_DAY_COUNT', "evaluateCycleDayCount");
        $this->addValue('CYCLE_DAY_PRECOUNT_PER_YEAR', "evaluateCycleDayPrecountPerYear");
        $this->addValue('CYCLE_DAY_POSTCOUNT_PER_YEAR', "evaluateCycleDayPostcountPerYear");
        $this->addValue('COLLECTION\[\d+\]', "evaluateCollection");
        $this->addValue(
            '('.join('|', ACCEPTABLE_ACCOUNT_KINDS).')_ACCOUNTS',
            "evaluateAccountKind"
        );
    }

    private function evaluateCollection(array $values, Context $context, Token $token): string
    {
        $value = $token->getValue();
        preg_match('/COLLECTION\[(?P<collection_id>\d+)\]/', $value, $matches);
        $collection_id = $matches["collection_id"];

        $builder = model(CollectionModel::class, false)
            ->builder()
            ->where("id", $collection_id);

        $key = $this->cache->store($builder);

        return $key;
    }

    private function evaluateAccountKind(array $values, Context $context, Token $token): string
    {
        $raw_kind = explode("_", $token->getValue());
        $kind = strtolower($raw_kind[0]);

        $builder = model(AccountModel::class, false)
            ->builder()
            ->where("kind", AccountKind::set($kind));

        $key = $this->cache->store($builder);

        return $key;
    }

    private function evaluateCycleDayCount(array $values, Context $context, Token $token): string
    {
        $time_group_manager = $context->getVariable(ContextKeys::TIME_GROUP_MANAGER);
        $cycle_ranges = $time_group_manager->cycleRanges();
        $day_counts = array_map(
            function ($range) {
                [ $started_at, $finished_at ] = $range;
                $difference = $started_at
                    ->setHour(0)->setMinute(0)->setSecond(0)
                    ->difference($finished_at->setHour(0)->setMinute(0)->setSecond(0));
                $day_difference = $difference->getDays();
                $duration = $day_difference + 1;
                return $duration;
            },
            $cycle_ranges
        );

        return json_encode($day_counts);
    }

    private function evaluateCycleDayPrecountPerYear(
        array $values,
        Context $context,
        Token $token
    ): string {
        $time_group_manager = $context->getVariable(ContextKeys::TIME_GROUP_MANAGER);
        $cycle_ranges = $time_group_manager->cycleRanges();
        $day_counts = array_map(
            function ($range) {
                [ $started_at, $finished_at ] = $range;
                $year_start = $started_at
                    ->setMonth(1)->setDay(1)
                    ->setHour(0)->setMinute(0)->setSecond(0);
                $year_end = $year_start->setMonth(12)->setDay(31);

                $difference = $year_start->difference($year_end);
                $day_difference = $difference->getDays();
                $duration = $day_difference + 1;

                return $duration;
            },
            $cycle_ranges
        );

        return json_encode($day_counts);
    }

    private function evaluateCycleDayPostcountPerYear(
        array $values,
        Context $context,
        Token $token
    ): string {
        $time_group_manager = $context->getVariable(ContextKeys::TIME_GROUP_MANAGER);
        $cycle_ranges = $time_group_manager->cycleRanges();
        $day_counts = array_map(
            function ($range) {
                [ $started_at, $finished_at ] = $range;
                $year_start = $finished_at
                    ->setMonth(1)->setDay(1)
                    ->setHour(0)->setMinute(0)->setSecond(0);
                $year_end = $year_start->setMonth(12)->setDay(31);

                $difference = $year_start->difference($year_end);
                $day_difference = $difference->getDays();
                $duration = $day_difference + 1;

                return $duration;
            },
            $cycle_ranges
        );

        return json_encode($day_counts);
    }

    private function addValue(string $name, string $function_name)
    {
        $callback = Closure::fromCallable([ $this, $function_name ]);
        $this->addOperator(new Value($name, $callback));
    }
}
