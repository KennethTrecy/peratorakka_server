<?php

namespace App\Libraries\Context\TimeGroupManager;

use App\Libraries\Context;
use App\Libraries\Context\ContextKeys;
use App\Libraries\Resource;
use App\Models\CurrencyModel;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;

class CurrencyCache
{
    public readonly Context $context;
    private array $currencies = [];

    public function __construct(Context $context)
    {
        $this->context = $context;

        $this->context->setVariable(ContextKeys::CURRENCY_CACHE, $this);
    }

    public function formatValue(int $currency_id, BigRational $value): ?string
    {
        return isset($this->currencies[$currency_id])
            ? $value->toScale(
                $this->currencies[$currency_id]->presentational_precision,
                RoundingMode::HALF_EVEN
            )
            : null;
    }

    public function loadCurrencies(array $target_currency_IDs): void
    {
        $missing_currency_IDs = array_diff($target_currency_IDs, array_keys($this->currencies));

        if (count($missing_currency_IDs) === 0) {
            return;
        }

        $new_currencies = model(CurrencyModel::class, false)
            ->whereIn("id", array_unique($target_currency_IDs))
            ->findAll();

        $this->currencies = array_replace(
            $this->currencies,
            Resource::key($new_currencies, function ($currency) {
                return $currency->id;
            })
        );
    }
}
