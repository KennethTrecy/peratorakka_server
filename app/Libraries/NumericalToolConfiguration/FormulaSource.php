<?php

namespace App\Libraries\NumericalToolConfiguration;

use App\Casts\RationalNumber;
use App\Contracts\NumericalToolSource;
use App\Entities\Formula;
use App\Exceptions\NumericalToolConfigurationException;
use App\Libraries\Constellation;
use App\Libraries\Constellation\Star;
use App\Libraries\Context;
use App\Libraries\Context\ContextKeys;
use App\Libraries\Context\TimeGroupManager;
use App\Libraries\MathExpression;
use App\Models\FormulaModel;

class FormulaSource implements NumericalToolSource
{
    public static function sourceType(): string
    {
        return "formula";
    }

    public static function parseConfiguration(array $configuration): ?FormulaSource {
        if (isset($configuration["formula_id"])) {
            return new FormulaSource($configuration["formula_id"]);
        }

        return null;
    }

    private static array $formulae = [];

    private readonly Formula $formula;

    public readonly int $formula_id;

    private function __construct(int $formula_id) {
        $this->formula_info_id = $formula_id;

        if (!isset(self::$formulae[$formula_id])) {
            $formula = model(FormulaModel::class, false)->find($formula_id);
            if (is_null($formula)) {
                throw new NumericalToolConfigurationException("Formula $formula_id not found.");
            }

            self::$formulae[$formula_id] = $formula;
            $this->formula_info = $formula;
        }
    }

    public function outputFormatCode(): string {
        if ($this->formula_info->output_format === CURRENCY_FORMULA_OUTPUT_FORMAT) {
            return CURRENCY_FORMULA_OUTPUT_FORMAT."#$this->formula_info->currency_id";
        }

        return $this->formula_info->output_format;
    }

    public function calculate(Context $context): array
    {
        $context->setVariable(ContextKeys::DESTINATION_CURRENCY_ID, $this->currency_id);
        $context->setVariable(
            ContextKeys::EXCHANGE_RATE_BASIS,
            $this->formula_info->exchange_rate_basis
        );

        /**
         * @var TimeGroupManager
         */
        $time_group_manager = $context->getVariable(ContextKeys::TIME_GROUP_MANAGER);

        $math_expression = new MathExpression($time_group_manager);

        $formula_original_output_format = $this->formula_info->output_format;
        $totals = $math_expression->evaluate($this->formula_info->formula);

        /**
         * @var Constellation[]
         */
        $constellations = [
            new Constellation(
                $this->formula_info->name,
                array_map(function ($result) use ($formula_presentational_precision) {
                    $display_value = $result->toScale(
                        $formula_presentational_precision,
                        RoundingMode::HALF_EVEN
                    );
                    return new Star($display_value, $result);
                }, $totals)
            )
        ];

        return $constellations;
    }


    public function toArray(): array {
        return [
            "type" => static::sourceType(),
            "formula_id" => $this->formula_info_id
        ];
    }
}
