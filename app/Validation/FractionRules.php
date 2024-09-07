<?php

namespace App\Validation;

class FractionRules
{
    public function is_valid_currency_amount(
        $value,
        ?string &$error = null
    ): bool {
        if ($this->isRationalNumber($value)) {
            return true;
        }

        if (strpos($value, "/") === false) {
            return false;
        }

        $parts = explode("/", $value);
        if (count($parts) != 2) {
            return false;
        }
        if (!$this->isInteger($parts[0]) || !$this->isInteger($parts[1])) {
            return false;
        }

        return true;
    }

    private function isRationalNumber(string $value): bool
    {
        return preg_match("/^\d+(\.\d+)?$/", $value) === 1;
    }

    private function isInteger(string $value): bool
    {
        return preg_match("/^\d+$/", $value) === 1;
    }
}
