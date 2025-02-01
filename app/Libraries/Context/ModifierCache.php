<?php

namespace App\Libraries\Context;

use App\Libraries\Context\ContextKeys;
use App\Models\ModifierModel;

class ModifierCache extends ResourceCache
{
    protected static function contextKey(): ContextKeys
    {
        return ContextKeys::MODIFIER_CACHE;
    }

    protected static function getModel(): ModifierModel
    {
        return model(ModifierModel::class, false);
    }

    public function determineModifierName(int $modifier_id): ?string
    {
        return isset($this->resources[$modifier_id])
            ? $this->resources[$modifier_id]->name
            : null;
    }

    public function determineModifierAction(int $modifier_id): ?string
    {
        return isset($this->resources[$modifier_id])
            ? $this->resources[$modifier_id]->action
            : null;
    }
}
