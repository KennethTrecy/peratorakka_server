<?php

namespace App\Libraries\FinancialStatementGroup;

use Brick\Math\BigRational;
use CodeIgniter\I18n\Time;

class ExchangeRateInfo
{
    public readonly int $source_currency_id;
    public readonly BigRational $source_value;
    public readonly int $destination_currency_id;
    public readonly BigRational $destination_value;
    public readonly Time $updated_at;

    public function __construct(
        int $source_currency_id,
        BigRational $source_value,
        int $destination_currency_id,
        BigRational $destination_value,
        Time $updated_at
    ) {
        $this->source_currency_id = $source_currency_id;
        $this->source_value = $source_value;
        $this->destination_currency_id = $destination_currency_id;
        $this->destination_value = $destination_value;
        $this->updated_at = $updated_at;
    }

    public function reverse(): ExchangeRateInfo
    {
        return new ExchangeRateInfo(
            $this->destination_currency_id,
            $this->destination_value,
            $this->source_currency_id,
            $this->source_value,
            $this->updated_at
        );
    }

    public function rawArray(): array {
        $source = BigRational::of($this->source_value);
        $destination = BigRational::of($this->destination_value);
        $rate = $destination->dividedBy($source)->simplified();

        return [
            "source" => [
                "currency_id" => $this->source_currency_id,
                "value" => $rate->getDenominator()
            ],
            "destination" => [
                "currency_id" => $this->destination_currency_id,
                "value" => $rate->getNumerator()
            ],
            "updated_at" => $financial_entry->updated_at->toDateTimeString()
        ];
    }
}
