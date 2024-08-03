<?php

namespace App\Validation;

class FractionRules {
    public function is_fractional_or_numeric_number(
        $value,
        string $parameters,
        array $data,
        ?string &$error = null
    ): bool {
        if ($this->isRationalNumber($value)) return true;

        if (strpos($value, "/") === false) return false;

        $parts = explode("/", $value);
        if (count($parts) != 2) return false;
        if (!is_integer(+$parts[0]) || !is_integer(+$parts[1])) return false;

        return true;
    }

    private function isRationalNumber(string $value): bool {
        return preg_match("/^[0-9.]+$/", $value) === 1;
    }
}
