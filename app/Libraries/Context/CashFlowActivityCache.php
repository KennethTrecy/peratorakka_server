<?php

namespace App\Libraries\Context;

use App\Libraries\Context\ContextKeys;
use App\Models\CashFlowActivityModel;
use Brick\Math\BigRational;

class CashFlowActivityCache extends ResourceCache
{
    protected static function contextKey(): ContextKeys
    {
        return ContextKeys::CASH_FLOW_ACTIVITY_CACHE;
    }

    protected static function getModel(): CashFlowActivityModel
    {
        return model(CashFlowActivityModel::class, false);
    }

    public function determineCashFlowActivityName(int $cash_flow_activity_id): ?string
    {
        return isset($this->resources[$cash_flow_activity_id])
            ? $this->resources[$cash_flow_activity_id]->name
            : null;
    }
}
