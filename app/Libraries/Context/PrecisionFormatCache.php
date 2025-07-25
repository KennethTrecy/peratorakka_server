<?php

namespace App\Libraries\Context;

use App\Libraries\Context;
use App\Libraries\Context\ContextKeys;
use App\Libraries\Resource;
use App\Models\PrecisionFormatModel;

class PrecisionFormatCache extends ResourceCache
{
    protected static function contextKey(): ContextKeys
    {
        return ContextKeys::PRECISION_FORMAT_CACHE;
    }

    protected static function getModel(): PrecisionFormatModel
    {
        return model(PrecisionFormatModel::class, false);
    }

    public function determineMaximumPresentationalPrecision(int $precision_format_id): int
    {
        return isset($this->resources[$precision_format_id])
            ? $this->resources[$precision_format_id]->maximum_presentational_precision
            : 12;
    }
}
