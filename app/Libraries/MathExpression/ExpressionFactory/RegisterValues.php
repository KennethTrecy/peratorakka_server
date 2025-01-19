<?php

namespace App\Libraries\MathExpression\ExpressionFactory;

use App\Casts\AccountKind;
use App\Exceptions\ExpressionException;
use App\Libraries\Context\FlashCache;
use App\Libraries\Context;
use App\Libraries\Context\ContextKeys;
use App\Libraries\Resource;
use App\Models\AccountCollectionModel;
use App\Models\AccountModel;
use App\Models\CollectionModel;
use App\Models\FormulaModel;
use Closure;
use CodeIgniter\Database\BaseBuilder;
use Xylemical\Expressions\Token;
use Xylemical\Expressions\Value;

trait RegisterValues
{
    public function addValues()
    {
        $this->addValue("SUBCYCLE_DAY_COUNT", "evaluateSubcycleDayCount");
        $this->addValue("SUBCYCLE_INDEX", "evaluateSubcycleIndex");
        $this->addValue("SUBCYCLE_COUNT", "evaluateSubcycleCount");
        $this->addValue("CYCLE_COUNT", "evaluateCycleCount");
        $this->addValue("CYCLE_DAY_COUNT", "evaluateCycleDayCount");
        $this->addValue("CYCLE_DAY_PRECOUNT_PER_YEAR", "evaluateCycleDayPrecountPerYear");
        $this->addValue("CYCLE_DAY_POSTCOUNT_PER_YEAR", "evaluateCycleDayPostcountPerYear");
        $this->addValue("COLLECTION\[\d+\]", "evaluateCollection");
        $this->addValue("FORMULA\[\d+\]", "evaluateFormula");
        $this->addValue(
            "(".join("|", ACCEPTABLE_ACCOUNT_KINDS).")_ACCOUNTS",
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

    private function evaluateSubcycleDayCount(array $values, Context $context, Token $token): string
    {
        $time_group_manager = $context->getVariable(ContextKeys::TIME_GROUP_MANAGER);
        $subcycle_ranges = $time_group_manager->subcycleRanges();
        $day_counts = array_map(
            function ($ranges) {
                return array_map(
                    function ($range) {
                        [ $started_at, $finished_at ] = $range;
                        return Resource::duration($started_at, $finished_at);
                    },
                    $ranges
                );
            },
            $subcycle_ranges
        );

        return json_encode($day_counts);
    }

    private function evaluateSubcycleIndex(array $values, Context $context, Token $token): string
    {
        $time_group_manager = $context->getVariable(ContextKeys::TIME_GROUP_MANAGER);
        $subcycle_ranges = $time_group_manager->subcycleRanges();
        $indexes = array_map(
            function ($ranges) {
                return array_keys(array_values($ranges));
            },
            $subcycle_ranges
        );

        return json_encode($indexes);
    }

    private function evaluateSubcycleCount(array $values, Context $context, Token $token): string
    {
        $time_group_manager = $context->getVariable(ContextKeys::TIME_GROUP_MANAGER);
        $subcycle_ranges = $time_group_manager->subcycleRanges();
        $subcycle_counts = array_map(
            function ($ranges) {
                return count($ranges);
            },
            $subcycle_ranges
        );

        return json_encode($subcycle_counts);
    }

    private function evaluateCycleCount(array $values, Context $context, Token $token): string
    {
        $time_group_manager = $context->getVariable(ContextKeys::TIME_GROUP_MANAGER);
        $cycle_counts = count($time_group_manager->timeTags());

        return json_encode($cycle_counts);
    }

    private function evaluateCycleDayCount(array $values, Context $context, Token $token): string
    {
        $time_group_manager = $context->getVariable(ContextKeys::TIME_GROUP_MANAGER);
        $cycle_ranges = $time_group_manager->cycleRanges();
        $day_counts = array_map(
            function ($range) {
                [ $started_at, $finished_at ] = $range;
                return Resource::duration($started_at, $finished_at);
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
                $year_start = $started_at->setMonth(1)->setDay(1);
                $year_end = $year_start->setMonth(12)->setDay(31);
                return Resource::duration($year_start, $year_end);
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
                $year_start = $finished_at->setMonth(1)->setDay(1);
                $year_end = $year_start->setMonth(12)->setDay(31);
                return Resource::duration($year_start, $year_end);
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

    private function evaluateFormula(array $values, Context $context, Token $token): string
    {
        $value = $token->getValue();
        preg_match('/FORMULA\[(?P<formula_id>\d+)\]/', $value, $matches);
        $formula_id = $matches["formula_id"];

        $builder = model(FormulaModel::class, false)
            ->builder()
            ->where("id", $formula_id);

        $key = $this->cache->store($builder);

        return $key;
    }
}
