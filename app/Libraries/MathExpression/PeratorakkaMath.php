<?php

namespace App\Libraries\MathExpression;

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

    private function getScale(?int $defaultScale): int {
        return $defaultScale ?? $this->scale;
    }
}
