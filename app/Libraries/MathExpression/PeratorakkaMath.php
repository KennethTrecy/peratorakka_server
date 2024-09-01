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
        $scale = $this->getScale($overridenScale);
        $addend = BigRational::of($rawAddend, $scale);
        $adder = BigRational::of($rawAdder, $scale);

        return $addend->plus($adder);
    }

    public function multiply($rawMultiplicand, $rawMultipier, $scale = 0) {
        $scale = $this->getScale($scale);
        $multiplicand = BigRational::of($rawMultiplicand, $scale);
        $multipier = BigRational::of($rawMultipier, $scale);

        return $multiplicand->multipliedBy($multipier);
    }

    public function subtract($rawSubtrahend, $rawMinuend, $scale = 0) {
        $scale = $this->getScale($scale);
        $subtrahend = BigRational::of($rawSubtrahend, $scale);
        $minuend = BigRational::of($rawMinuend, $scale);

        return $subtrahend->minus($minuend);
    }

    public function divide($rawDividend, $rawDivisor, $scale = 0) {
        $scale = $this->getScale($scale);
        $dividend = BigRational::of($rawDividend, $scale);
        $divisor = BigRational::of($rawDivisor, $scale);

        return $dividend->dividedBy($divisor);
    }

    public function modulus($rawDividend, $rawDivisor, $scale = 0) {
        $scale = $this->getScale($scale);
        $dividend = BigRational::of($rawDividend, $scale);
        $divisor = BigRational::of($rawDivisor, $scale);

        return $dividend->remainder($divisor);
    }

    public function compare($rawOperandA, $rawOperandB, $scale = 0) {
        $scale = $this->getScale($scale);
        $operandA = BigRational::of($rawOperandA, $scale);
        $operandB = BigRational::of($rawOperandB, $scale);

        return $operandA->compareTo($operandB);
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
                function ($element) {
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
            }, $rightHand);
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
