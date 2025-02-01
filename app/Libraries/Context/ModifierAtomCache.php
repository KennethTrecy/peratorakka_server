<?php

namespace App\Libraries\Context;

use App\Libraries\Context\ContextKeys;
use App\Models\ModifierAtomModel;

class ModifierAtomCache extends ResourceCache
{
    protected static function contextKey(): ContextKeys
    {
        return ContextKeys::MODIFIER_ATOM_CACHE;
    }

    protected static function getModel(): ModifierAtomModel
    {
        return model(ModifierAtomModel::class, false);
    }

    public function determineModifierAtomKind(int $modifier_atom_id): ?string
    {
        return isset($this->resources[$modifier_atom_id])
            ? $this->resources[$modifier_atom_id]->kind
            : null;
    }
}
