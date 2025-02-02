<?php

namespace App\Libraries;

use App\Casts\RationalNumber;
use App\Libraries\Context;
use App\Libraries\Context\AccountCache;
use App\Libraries\Context\ContextKeys;
use App\Libraries\Context\ModifierAtomCache;
use App\Libraries\Context\ModifierCache;
use App\Models\AccountModel;

class FinancialEntryAtomInputExaminer
{
    /**
     * @type FinancialEntryAtomInputExaminer[]
     */
    private static array $instances = [];

    private readonly array $input;
    private readonly Context $context;

    public static function make(string $key, array $data): FinancialEntryAtomInputExaminer
    {
        helper("array");

        if (!isset(self::$instances[$key])) {
            self::$instances[$key] = new self(dot_array_search($key, $data) ?? []);
        }

        return self::$instances[$key];
    }

    private function __construct(array $input)
    {
        $this->context = Context::make();
        $this->input = $input;

        ModifierCache::make($this->context);
        ModifierAtomCache::make($this->context);
        AccountCache::make($this->context);
    }

    public function validateSchema(): bool
    {
        return is_array($this->input) && array_reduce(
            $this->input,
            fn ($previous_result, $input_element) => (
                $previous_result
                && isset($input_element["modifier_atom_id"])
                && isset($input_element["numerical_value"])
                && is_int($input_element["modifier_atom_id"])
                && is_string($input_element["numerical_value"])
            ),
            true
        );
    }

    public function validateOwnership(): bool
    {
        $modifier_atom_IDs = $this->extractModifierAtomIDs();

        $modifier_atom_cache = $this->context->getVariable(ContextKeys::MODIFIER_ATOM_CACHE);
        $modifier_atom_cache->loadResources($modifier_atom_IDs);
        $modifier_atom_count = $modifier_atom_cache->countLoadedResources();

        return $modifier_atom_count === count($modifier_atom_IDs);
    }

    public function validateCurrencyValues(int $modifier_id): bool
    {
        $account_cache = $this->context->getVariable(ContextKeys::ACCOUNT_CACHE);
        $modifier_cache = $this->context->getVariable(ContextKeys::MODIFIER_CACHE);
        $modifier_action = $modifier_cache->determineModifierAction($modifier_id);

        $modifier_atom_cache = $this->context->getVariable(ContextKeys::MODIFIER_ATOM_CACHE);

        foreach ($this->input as $input_element) {
            if ($input_element->isEqualTo(RationalNumber::zero())) {
                return false;
            }
        }

        switch ($modifier_action) {
            case RECORD_MODIFIER_ACTION: {
                $debit_total = RationalNumber::zero();
                $credit_total = RationalNumber::zero();

                foreach ($this->input as $input_element) {
                    $modifier_atom_id = $input_element["modifier_atom_id"];
                    $numerical_value = $input_element["numerical_value"];
                    $modifier_atom_kind = $modifier_atom_cache->determineModifierAtomKind(
                        $modifier_atom_id
                    );

                    if ($modifier_atom_kind === DEBIT_MODIFIER_ATOM_KIND) {
                        $debit_total = $debit_total->plus($numerical_value);
                    } elseif ($modifier_atom_kind === CREDIT_MODIFIER_ATOM_KIND) {
                        $credit_total = $credit_total->plus($numerical_value);
                    }
                }

                return $debit_total->isEqualTo($credit_total);
            }

            case CLOSE_MODIFIER_ACTION: {
                return count($this->input) === 0;
            }

            case EXCHANGE_MODIFIER_ACTION: {
                return count($this->input) === 2;
            }

            case BID_MODIFIER_ACTION: {
                $debit_total = RationalNumber::zero();
                $credit_total = RationalNumber::zero();

                $remaining_price_atoms = [];
                $remaining_item_count_atoms = [];
                $remaining_itemized_debit_atoms = [];

                foreach ($this->input as $input_element) {
                    $modifier_atom_id = $input_element["modifier_atom_id"];
                    $numerical_value = RationalNumber::get($input_element["numerical_value"]);
                    if ($numerical_value->isZero()) {
                        return false;
                    }

                    $modifier_atom_kind = $modifier_atom_cache->determineModifierAtomKind(
                        $modifier_atom_id
                    );

                    if ($modifier_atom_kind === ITEM_COUNT_MODIFIER_ATOM_KIND) {
                        if (isset($remaining_price_atoms[$modifier_atom_id])) {
                            $product = $remaining_price_atoms[$modifier_atom_id]
                                ->multipliedBy($numerical_value);
                            unset($remaining_price_atoms[$modifier_atom_id]);

                            $remaining_itemized_debit_atoms[$modifier_id] = $product;
                        } else {
                            $remaining_item_count_atoms[$modifier_id] = $numerical_value;
                        }
                    } elseif ($modifier_atom_kind === PRICE_MODIFIER_ATOM_KIND) {
                        if (isset($remaining_item_count_atoms[$modifier_atom_id])) {
                            $product = $remaining_item_count_atoms[$modifier_atom_id]
                                ->multipliedBy($numerical_value);
                            unset($remaining_item_count_atoms[$modifier_atom_id]);

                            $remaining_itemized_debit_atoms[$modifier_id] = $product;
                        } else {
                            $remaining_price_atoms[$modifier_id] = $numerical_value;
                        }
                    }

                    if (
                        isset($remaining_itemized_debit_atoms[$modifier_atom_id])
                        && !$remaining_itemized_debit_atoms[$modifier_atom_id]->isZero()
                    ) {
                        $debit_total = $debit_total->plus(
                            $remaining_itemized_debit_atoms[$modifier_atom_id]
                        );

                        unset($remaining_itemized_debit_atoms[$modifier_atom_id]);
                    }

                    if ($modifier_atom_kind === DEBIT_MODIFIER_ATOM_KIND) {
                        $account_id = $modifier_atom_cache
                            ->determineModifierAtomAccountID($modifier_atom_id);
                        $account_kind = $account_cache->determineAccountKind($account_id);

                        if ($account_kind === ITEMIZED_ASSET_ACCOUNT_KIND) {
                            // Itemized asset accounts should not have inputs.
                            // Currency value is derived from prices and quantities.
                            return false;
                        } else {
                            $debit_total = $debit_total->plus($numerical_value);
                        }
                    } else {
                        $credit_total = $credit_total->plus($numerical_value);
                    }
                }

                return $debit_total->isEqualTo($credit_total);
            }

            case ASK_MODIFIER_ACTION: {
                $debit_total = RationalNumber::zero();
                $credit_total = RationalNumber::zero();

                $remaining_price_atoms = [];
                $remaining_item_count_atoms = [];
                $remaining_itemized_credit_atoms = [];

                foreach ($this->input as $input_element) {
                    $modifier_atom_id = $input_element["modifier_atom_id"];
                    $numerical_value = RationalNumber::get($input_element["numerical_value"]);
                    if ($numerical_value->isZero()) {
                        return false;
                    }

                    $modifier_atom_kind = $modifier_atom_cache->determineModifierAtomKind(
                        $modifier_atom_id
                    );

                    if ($modifier_atom_kind === ITEM_COUNT_MODIFIER_ATOM_KIND) {
                        if (isset($remaining_price_atoms[$modifier_atom_id])) {
                            $product = $remaining_price_atoms[$modifier_atom_id]
                                ->multipliedBy($numerical_value);
                            unset($remaining_price_atoms[$modifier_atom_id]);

                            $remaining_itemized_credit_atoms[$modifier_id] = $product;
                        } else {
                            $remaining_item_count_atoms[$modifier_id] = $numerical_value;
                        }
                    } elseif ($modifier_atom_kind === PRICE_MODIFIER_ATOM_KIND) {
                        if (isset($remaining_item_count_atoms[$modifier_atom_id])) {
                            $product = $remaining_item_count_atoms[$modifier_atom_id]
                                ->multipliedBy($numerical_value);
                            unset($remaining_item_count_atoms[$modifier_atom_id]);

                            $remaining_itemized_credit_atoms[$modifier_id] = $product;
                        } else {
                            $remaining_price_atoms[$modifier_id] = $numerical_value;
                        }
                    }

                    if (
                        isset($remaining_itemized_credit_atoms[$modifier_atom_id])
                        && !$remaining_itemized_credit_atoms[$modifier_atom_id]->isZero()
                    ) {
                        $credit_total = $credit_total->plus(
                            $remaining_itemized_credit_atoms[$modifier_atom_id]
                        );

                        unset($remaining_itemized_credit_atoms[$modifier_atom_id]);
                    }

                    if ($modifier_atom_kind === CREDIT_MODIFIER_ATOM_KIND) {
                        $account_id = $modifier_atom_cache
                            ->determineModifierAtomAccountID($modifier_atom_id);
                        $account_kind = $account_cache->determineAccountKind($account_id);

                        if ($account_kind !== ITEMIZED_ASSET_ACCOUNT_KIND) {
                            $credit_total = $credit_total->plus($numerical_value);
                        }
                    } else {
                        $debit_total = $debit_total->plus($numerical_value);
                    }
                }

                return $debit_total->isEqualTo($credit_total);
            }
        }

        return false;
    }

    private function extractModifierAtomIDs(): array
    {
        return array_unique(array_map(
            fn ($input_element) => $input_element["modifier_atom_id"],
            $this->input
        ));
    }
}
