<?php

namespace App\Libraries\MathExpression;

use Exception;
use App\Casts\RationalNumber;
use App\Exceptions\ExpressionException;
use Brick\Math\BigRational;
use Xylemical\Expressions\MathInterface;

class PeratorakkaMath implements MathInterface
{
    private readonly int $scale;

    public function __construct(int $scale = 0)
    {
        $this->scale = $scale;
    }

    public function add($rawAddend, $rawAdder, $overridenScale = 0)
    {
        $resolvedOperators = $this->resolveOperators($rawAddend, $rawAdder, $overridenScale);
        return json_encode(array_map(function ($operators) {
            [ $addend, $adder ] = $operators;

            if ($addend instanceof BigRational && $adder instanceof BigRational) {
                return $addend->plus($adder);
            } elseif (is_array($addend) && $adder instanceof BigRational) {
                $subelement_count = count($addend);
                $subadder = $adder->dividedBy($subelement_count);
                return array_map(
                    function ($subaddend) use ($subadder) {
                        return $subaddend->plus($subadder);
                    },
                    $addend
                );
            } elseif ($addend instanceof BigRational && is_array($adder)) {
                $subelement_count = count($adder);
                $subaddend = $addend->dividedBy($subelement_count);
                return array_map(
                    function ($subadder) use ($subaddend) {
                        return $subaddend->plus($subadder);
                    },
                    $adder
                );
            } elseif (is_array($addend) && is_array($adder)) {
                return array_map(
                    function ($subaddend, $subadder) {
                        return $subaddend->plus($subadder);
                    },
                    $addend,
                    $adder
                );
            }

            return $addend ?? $adder ?? RationalNumber::zero();
        }, $resolvedOperators));
    }

    public function multiply($rawMultiplicand, $rawMultipier, $overridenScale = 0)
    {
        $resolvedOperators = $this->resolveOperators(
            $rawMultiplicand,
            $rawMultipier,
            $overridenScale
        );

        return json_encode(array_map(function ($operators) {
            [ $multiplicand, $multiplier ] = $operators;

            if ($multiplicand instanceof BigRational && $multiplier instanceof BigRational) {
                return $multiplicand->multipliedBy($multiplier)->simplified();
            } elseif (is_array($multiplicand) && $multiplier instanceof BigRational) {
                return array_map(
                    function ($submultiplicand) use ($multiplier) {
                        return $submultiplicand->multipliedBy($multiplier)->simplified();
                    },
                    $multiplicand
                );
            } elseif ($multiplicand instanceof BigRational && is_array($multiplier)) {
                return array_map(
                    function ($submultiplier) use ($multiplicand) {
                        return $multiplicand->multipliedBy($submultiplier)->simplified();
                    },
                    $multiplier
                );
            } elseif (is_array($multiplicand) && is_array($multiplier)) {
                return array_map(
                    function ($submultiplicand, $submultiplier) {
                        return $submultiplicand->multipliedBy($submultiplier)->simplified();
                    },
                    $multiplicand,
                    $multiplier
                );
            }

            return null;
        }, $resolvedOperators));
    }

    public function subtract($rawSubtrahend, $rawMinuend, $overridenScale = 0)
    {
        $resolvedOperators = $this->resolveOperators($rawSubtrahend, $rawMinuend, $overridenScale);
        return json_encode(array_map(function ($operators) {
            [ $subtrahend, $minuend ] = $operators;

            if ($subtrahend instanceof BigRational && $minuend instanceof BigRational) {
                return $subtrahend->minus($minuend);
            } elseif (is_array($subtrahend) && $minuend instanceof BigRational) {
                $subelement_count = count($subtrahend);
                $subminuend = $minuend->dividedBy($subelement_count);
                return array_map(
                    function ($subsubtrahend) use ($subminuend) {
                        return $subsubtrahend->minus($subminuend);
                    },
                    $subtrahend
                );
            } elseif ($subtrahend instanceof BigRational && is_array($minuend)) {
                $subelement_count = count($minuend);
                $subsubtrahend = $subtrahend->dividedBy($subelement_count);
                return array_map(
                    function ($subminuend) use ($subsubtrahend) {
                        return $subsubtrahend->minus($subminuend);
                    },
                    $minuend
                );
            } elseif (is_array($subtrahend) && is_array($minuend)) {
                return array_map(
                    function ($subsubtrahend, $subminuend) {
                        return $subsubtrahend->minus($subminuend);
                    },
                    $subtrahend, $minuend
                );
            }

            return $subtrahend ?? $minuend->negated() ?? RationalNumber::zero();
        }, $resolvedOperators));
    }

    public function divide($rawDividend, $rawDivisor, $overridenScale = 0)
    {
        $resolvedOperators = $this->resolveOperators($rawDividend, $rawDivisor, $overridenScale);
        return json_encode(array_map(function ($operators) {
            [ $dividend, $divisor ] = $operators;

            if ($dividend instanceof BigRational && $divisor instanceof BigRational) {
                return $dividend->dividedBy($divisor)->simplified();
            } elseif (is_array($dividend) && $divisor instanceof BigRational) {
                return array_map(
                    function ($subdividend) use ($divisor) {
                        return $subdividend->dividedBy($divisor)->simplified();
                    },
                    $dividend
                );
            } elseif ($dividend instanceof BigRational && is_array($divisor)) {
                return array_map(
                    function ($subdivisor) use ($dividend) {
                        return $dividend->dividedBy($subdivisor)->simplified();
                    },
                    $divisor
                );
            } elseif (is_array($dividend) && is_array($divisor)) {
                return array_map(
                    function ($subdividend, $subdivisor) {
                        return $subdividend->dividedBy($subdivisor)->simplified();
                    },
                    $dividend, $divisor
                );
            } elseif ($divisor instanceof BigRational) {
                return RationalNumber::zero();
            }

            return null;
        }, $resolvedOperators));
    }

    public function modulus($rawDividend, $rawDivisor, $overridenScale = 0)
    {
        $resolvedOperators = $this->resolveOperators($rawDividend, $rawDivisor, $overridenScale);
        return json_encode(array_map(function ($operators) {
            [ $dividend, $divisor ] = $operators;

            if ($dividend instanceof BigRational && $divisor instanceof BigRational) {
                return $dividend->modulo($divisor);
            } elseif ($divisor instanceof BigRational) {
                return RationalNumber::zero();
            }

            return null;
        }, $resolvedOperators));
    }

    public function compare($rawOperandA, $rawOperandB, $overridenScale = 0)
    {
        $resolvedOperators = $this->resolveOperators($rawOperandA, $rawOperandB, $overridenScale);
        return json_encode(array_map(function ($operators) {
            [ $operandA, $operandB ] = $operators;

            if ($operandA instanceof BigRational && $operandB instanceof BigRational) {
                return $operandA->compareTo($operandB);
            }

            return 0;
        }, $resolvedOperators));
    }

    public function native($value)
    {
        $rationalValue = RationalNumber::get($value);
        $numerator = $rationalValue->getNumerator();
        $denominator = $rationalValue->getDenominator();
        $remainder = $numerator->remainder($denominator);

        if ($remainder->isZero()) {
            return $rationalValue->toInt();
        }

        return $rationalValue->toFloat();
    }

    public function power($base, $exponent, $overridenScale = 0)
    {
        $resolvedOperators = $this->resolveOperators(
            $base,
            $exponent,
            $overridenScale
        );

        return json_encode(array_map(function ($operators) {
            [ $base, $exponent ] = $operators;

            if ($base instanceof BigRational && $exponent instanceof BigRational) {
                try {
                    return $base->power($exponent->toInt())->simplified();
                } catch (Exception $error) {
                    throw new ExpressionException("Ensure exponents are integer. Otherwise, the exponent is too big for the memory.");
                }
            } elseif (is_array($base) && $exponent instanceof BigRational) {
                return array_map(
                    function ($subbase) use ($subexponent) {
                        try {
                            return $subbase->power($subexponent->toInt())->simplified();
                        } catch (Exception $error) {
                            throw new ExpressionException("Ensure exponents are integer. Otherwise, the exponent is too big for the memory.");
                        }
                    },
                    $base
                );
            } elseif ($base instanceof BigRational && is_array($exponent)) {
                return array_map(
                    function ($subexponent) use ($subbase) {
                        try {
                            return $subbase->power($subexponent->toInt())->simplified();
                        } catch (Exception $error) {
                            throw new ExpressionException("Ensure exponents are integer. Otherwise, the exponent is too big for the memory.");
                        }
                    },
                    $exponent
                );
            } elseif (is_array($base) && is_array($exponent)) {
                return array_map(
                    function ($subbase, $subexponent) {
                        try {
                            return $subbase->power($subexponent->toInt())->simplified();
                        } catch (Exception $error) {
                            throw new ExpressionException("Ensure exponents are integer. Otherwise, the exponent is too big for the memory.");
                        }
                    },
                    $base, $exponent
                );
            }

            return null;
        }, $resolvedOperators));
    }

    public function resolve(string $value, int $overridenScale = 0): mixed
    {
        $scale = $this->getScale($overridenScale);

        if (str_starts_with($value, "[") && str_ends_with($value, "]")) {
            return array_map(
                function ($elements) use ($scale) {
                    return is_array($elements)
                        ? array_map(
                            function ($element) use ($scale) {
                                return BigRational::of($element, $scale);
                            },
                            $elements
                        )
                        : BigRational::of($elements, $scale);
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
            throw new ExpressionException("Cannot resolve operands: \"$rawLeftHand\" and \"$rawRightHand\"");
        }
    }

    private function getScale(?int $defaultScale): int
    {
        return $defaultScale ?? $this->scale;
    }
}
