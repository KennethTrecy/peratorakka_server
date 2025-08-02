<?php

namespace App\Libraries;

use App\Entities\ItemConfiguration;
use App\Libraries\Context\ItemDetailCache;

class ItemConfigurationInputExaminer extends InputExaminer
{
    public function validateSchema(): bool
    {
        return is_array($this->input)
            && isset($this->input["item_detail_id"])
            && isset($this->input["valuation_method"])
            && is_int($this->input["item_detail_id"])
            && is_string($this->input["valuation_method"])
            && in_array($this->input["valuation_method"], ACCEPTABLE_VALUATION_METHODS);
    }

    public function validateOwnership(): bool
    {
        $item_detail_cache = ItemDetailCache::make($this->context);
        $item_detail_cache->loadResources([ $this->input["item_detail_id"] ]);
        $item_detail_count = $item_detail_cache->countLoadedResources();

        return $item_detail_count === 1;
    }
}
