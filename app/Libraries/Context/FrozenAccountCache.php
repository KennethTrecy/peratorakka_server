<?php

namespace App\Libraries\Context;

use App\Libraries\Context\ContextKeys;
use App\Models\FrozenAccountModel;

/**
 * Cache containing frozen account information.
 */
class FrozenAccountCache extends ResourceCache
{
    protected static function contextKey(): ContextKeys
    {
        return ContextKeys::FROZEN_ACCOUNT_CACHE;
    }

    protected static function getModel(): FrozenAccountModel
    {
        return model(FrozenAccountModel::class, false);
    }

    public function selectAccountHashesByAccountID(array $selected_account_IDs): array {
        return array_filter(
            $this->resources,
            fn ($frozen_account_hash_info) => in_array(
                $frozen_account_hash_info->account_id,
                $selected_account_IDs
            )
        );
    }
}
