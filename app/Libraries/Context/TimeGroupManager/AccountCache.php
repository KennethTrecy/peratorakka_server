<?php

namespace App\Libraries\Context\TimeGroupManager;

use App\Casts\ModifierAction;
use App\Libraries\Context\ContextKeys;
use App\Libraries\Context;
use App\Libraries\FinancialStatementGroup\ExchangeRateInfo;
use App\Libraries\FinancialStatementGroup\ExchangeRateDerivator;
use App\Libraries\Resource;
use App\Models\AccountModel;
use App\Models\CurrencyModel;
use App\Models\ModifierModel;
use App\Models\FinancialEntryModel;
use Brick\Math\BigRational;
use CodeIgniter\I18n\Time;

class AccountCache {
    public readonly Context $context;
    private array $accounts = [];

    public function __construct(Context $context)
    {
        $this->context = $context;

        $this->context->setVariable(ContextKeys::ACCOUNT_CACHE, $this);
    }

    public function determineCurrencyID(int $account_id): ?int {
        return isset($this->accounts[$account_id])
            ? $this->accounts[$account_id]->currency_id
            : null;
    }

    public function determineAccountName(int $account_id): ?string {
        return isset($this->accounts[$account_id])
            ? $this->accounts[$account_id]->name
            : null;
    }

    public function determineAccountKind(int $account_id): ?string {
        return isset($this->accounts[$account_id])
            ? $this->accounts[$account_id]->kind
            : null;
    }

    public function loadAccounts(array $missing_account_IDs): void {
        $new_accounts = model(AccountModel::class, false)
            ->whereIn("id", array_unique($missing_account_IDs))
            ->findAll();

        $this->accounts = array_replace(
            $this->accounts,
            Resource::key($new_accounts, function ($account) {
                return $account->id;
            })
        );
    }
}
