<?php

namespace App\Libraries\Context;

use App\Libraries\Context\ContextKeys;
use App\Models\AccountModel;

/**
 * Cache containing accounts.
 *
 * Some methods were intended to return null even if not specified in return type to catch errors
 * early.
 */
class AccountCache extends ResourceCache
{
    protected static function contextKey(): ContextKeys
    {
        return ContextKeys::ACCOUNT_CACHE;
    }

    protected static function getModel(): AccountModel
    {
        return model(AccountModel::class, false);
    }

    public function determineCurrencyID(int $account_id): int
    {
        return isset($this->resources[$account_id])
            ? $this->resources[$account_id]->currency_id
            : null;
    }

    public function determineAccountName(int $account_id): string
    {
        return isset($this->resources[$account_id])
            ? $this->resources[$account_id]->name
            : null;
    }

    public function determineAccountKind(int $account_id): string
    {
        return isset($this->resources[$account_id])
            ? $this->resources[$account_id]->kind
            : null;
    }

    public function isDebitedNormally(int $account_id): bool
    {
        if (!isset($this->resources[$account_id])) {
            return null;
        }

        $kind = $this->resources[$account_id]->kind;

        return in_array($kind, NORMAL_DEBIT_ACCOUNT_KINDS);
    }

    public function isNormallyTemporary(int $account_id): bool
    {
        if (!isset($this->resources[$account_id])) {
            return null;
        }

        $kind = $this->resources[$account_id]->kind;

        return in_array($kind, TEMPORARY_ACCOUNT_KINDS);
    }

    public function isItemizedNormally(int $account_id): bool
    {
        if (!isset($this->resources[$account_id])) {
            return null;
        }

        $kind = $this->resources[$account_id]->kind;

        return $kind === ITEMIZED_ASSET_ACCOUNT_KIND;
    }
}
