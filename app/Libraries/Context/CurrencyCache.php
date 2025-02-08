<?php

namespace App\Libraries\Context;

use App\Libraries\Context;
use App\Libraries\Context\ContextKeys;
use App\Libraries\Resource;
use App\Models\CurrencyModel;
use Brick\Math\BigRational;
use Brick\Math\RoundingMode;

class CurrencyCache extends ResourceCache
{
    protected static function contextKey(): ContextKeys
    {
        return ContextKeys::CURRENCY_CACHE;
    }

    protected static function getModel(): CurrencyModel
    {
        return model(CurrencyModel::class, false);
    }

    public function formatValue(int $currency_id, BigRational $value): ?string
    {
        if (isset($this->resources[$currency_id])) {
            $resource = $this->resources[$currency_id];
            $precision_format_cache = PrecisionFormatCache::make($this->context);
            $precision_format_cache->loadResources([ $resource->precision_format_id ]);

            return $value->toScale(
                $precision_format_cache->determineMaximumPresentationalPrecision(
                    $resource->precision_format_id
                ),
                RoundingMode::HALF_EVEN
            );
        } else {
            return null;
        }
    }
}
