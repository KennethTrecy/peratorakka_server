<?php

namespace App\Libraries\MathExpression;

use App\Exceptions\ExpressionException;
use Brick\Math\BigRational;
use Xylemical\Expressions\MathInterface;

class PeratorakkaMath implements MathInterface
{
    private readonly int $scale;

    public function __construct(int $scale = 0) {
        $this->scale = $scale;
    }

    public function add($rawAddend, $rawAdder, $overridenScale = 0) {
        $resolvedOperators = $this->resolveOperators($rawAddend, $rawAdder, $overridenScale);
        return json_encode(array_map(function ($operators) {
            [ $addend, $adder ] = $operators;

            if ($addend instanceof BigRational && $adder instanceof BigRational) {
                return $addend->plus($adder);
            }

            return $addend ?? $adder ?? BigRational::zero();
        }, $resolvedOperators));
    }

    public function multiply($rawMultiplicand, $rawMultipier, $overridenScale = 0) {
        $resolvedOperators = $this->resolveOperators(
            $rawMultiplicand,
            $rawMultipier,
            $overridenScale
        );

        return json_encode(array_map(function ($operators) {
            [ $multiplicand, $multipier ] = $operators;

            if ($multiplicand instanceof BigRational && $multipier instanceof BigRational) {
                return $multiplicand->multipliedBy($multipier);
            }

            return BigRational::zero();
        }, $resolvedOperators));
    }

    public function subtract($rawSubtrahend, $rawMinuend, $overridenScale = 0) {
        $resolvedOperators = $this->resolveOperators($rawSubtrahend, $rawMinuend, $overridenScale);
        return json_encode(array_map(function ($operators) {
            [ $subtrahend, $minuend ] = $operators;

            if ($subtrahend instanceof BigRational && $minuend instanceof BigRational) {
                return $subtrahend->minus($minuend);
            }

            return $subtrahend ?? $minuend->negated() ?? BigRational::zero();
        }, $resolvedOperators));
    }

    public function divide($rawDividend, $rawDivisor, $overridenScale = 0) {
        $resolvedOperators = $this->resolveOperators($rawDividend, $rawDivisor, $overridenScale);
        return json_encode(array_map(function ($operators) {
            [ $dividend, $divisor ] = $operators;

            if ($dividend instanceof BigRational && $divisor instanceof BigRational) {
                return $dividend->dividedBy($divisor);
            } else if ($divisor instanceof BigRational) {
                return BigRational::zero();
            }

            throw new ExpressionException("Division by zero");
        }, $resolvedOperators));
    }

    public function modulus($rawDividend, $rawDivisor, $overridenScale = 0) {
        $resolvedOperators = $this->resolveOperators($rawDividend, $rawDivisor, $overridenScale);
        return json_encode(array_map(function ($operators) {
            [ $dividend, $divisor ] = $operators;

            if ($dividend instanceof BigRational && $divisor instanceof BigRational) {
                return $dividend->modulo($divisor);
            } else if ($divisor instanceof BigRational) {
                return BigRational::zero();
            }

            throw new ExpressionException("Division by zero");
        }, $resolvedOperators));
    }

    public function compare($rawOperandA, $rawOperandB, $overridenScale = 0) {
        $resolvedOperators = $this->resolveOperators($rawOperandA, $rawOperandB, $overridenScale);
        return json_encode(array_map(function ($operators) {
            [ $operandA, $operandB ] = $operators;

            if ($operandA instanceof BigRational && $operandB instanceof BigRational) {
                return $operandA->compareTo($operandB);
            }

            return 0;
        }, $resolvedOperators));
    }

    public function native($value) {
        $rationalValue = BigRational::of($value);
        $numerator = $rationalValue->getNumerator();
        $denominator = $rationalValue->getDenominator();
        $remainder = $numerator->remainder($denominator);

        if ($remainder->isZero()) {
            return $rationalValue->toInt();
        }

        return $rationalValue->toFloat();
    }

    public function resolve(string $value, int $overridenScale = 0): mixed {
        $scale = $this->getScale($overridenScale);

        if (str_starts_with($value, "[") && str_ends_with($value, "]")) {
            return array_map(
                function ($element) use ($scale) {
                    return BigRational::of($element, $scale);
                },
                json_decode($value, true)
            );
        }

        return BigRational::of($value, $scale);
    }

    private function resolveOperators(
        string $rawLeftHand,
        string $rawRightHand,
        int $overridenScale
    ): array {
        $leftHand = $this->resolve($rawLeftHand, $overridenScale);
        $rightHand = $this->resolve($rawRightHand, $overridenScale);

        if ($leftHand instanceof BigRational && $rightHand instanceof BigRational) {
            return [ [ $leftHand, $rightHand ] ];
        } elseif ($leftHand instanceof BigRational && is_array($rightHand)) {
            return array_map(function ($rightHandElement) use ($leftHand) {
                return [ $leftHand, $rightHandElement ];
            }, $rightHand);
        } elseif (is_array($leftHand) && $rightHand instanceof BigRational) {
            return array_map(function ($leftHandElement) use ($rightHand) {
                return [ $leftHandElement, $rightHand ];
            }, $leftHand);
        } elseif (is_array($leftHand) && is_array($rightHand)) {
            return array_map(function ($leftHandElement, $rightHandElement) {
                return [ $leftHandElement, $rightHandElement ];
            }, $leftHand, $rightHand);
        } else {
            throw new ExpressionException("Cannot resolve operators");
        }
    }

    private function getScale(?int $defaultScale): int {
        return $defaultScale ?? $this->scale;
    }
}
