<?php

namespace App\Libraries\Context;

use App\Libraries\Context\ContextKeys;
use App\Models\ItemDetailModel;

class ItemDetailCache extends ResourceCache
{
    protected static function contextKey(): ContextKeys
    {
        return ContextKeys::ITEM_DETAIL_CACHE;
    }

    protected static function getModel(): ItemDetailModel
    {
        return model(ItemDetailModel::class, false);
    }

    public function determineModifierName(int $item_detail_id): ?string
    {
        return isset($this->resources[$item_detail_id])
            ? $this->resources[$item_detail_id]->name
            : null;
    }
}
