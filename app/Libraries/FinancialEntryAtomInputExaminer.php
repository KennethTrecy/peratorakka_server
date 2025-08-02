<?php

namespace App\Libraries;

use App\Casts\RationalNumber;
use App\Entities\FinancialEntryAtom;
use App\Libraries\Context;
use App\Libraries\Context\Memoizer;
use App\Libraries\Context\ModifierAtomCache;
use App\Libraries\Context\ModifierCache;
use App\Libraries\Context\AccountCache;
use App\Libraries\Resource;

class FinancialEntryAtomInputExaminer extends InputExaminer
{
    public function validateSchema(): bool
    {
        return is_array($this->input) && array_reduce(
            $this->input,
            fn ($previous_result, $input_element) => (
                $previous_result
                && isset($input_element["modifier_atom_id"])
                && isset($input_element["kind"])
                && isset($input_element["numerical_value"])
                && is_int($input_element["modifier_atom_id"])
                && is_string($input_element["kind"])
                && is_string($input_element["numerical_value"])
            ),
            true
        );
    }

    public function validateOwnership(int $modifier_id): bool
    {
        $modifier_cache = ModifierCache::make($this->context);
        $modifier_cache->loadResources([ $modifier_id ]);
        $modifier_count = $modifier_cache->countLoadedResources();

        $modifier_atom_IDs = $this->extractModifierAtomIDs();

        $modifier_atom_cache = ModifierAtomCache::make($this->context);
        $modifier_atom_cache->loadResources($modifier_atom_IDs);
        $modifier_atom_count = $modifier_atom_cache->countLoadedResources();

        return $modifier_count === 1 && $modifier_atom_count === count($modifier_atom_IDs);
    }

    public function validateCurrencyValues(int $modifier_id): bool
    {
        $memoizer = Memoizer::make($this->context);
        $modifier_cache = ModifierCache::make($this->context);
        $modifier_cache->loadResources([ $modifier_id ]);
        $modifier_action = $modifier_cache->determineModifierAction($modifier_id);

        $modifier_atom_cache = ModifierAtomCache::make($this->context);

        switch ($modifier_action) {
            case RECORD_MODIFIER_ACTION: {
                $premade_financial_entry_atoms = $this->makeFinancialEntryAtoms($this->input);

                $is_balanced = $this->checkBalance($premade_financial_entry_atoms);

                if ($is_balanced) {
                    $memoizer->write("#$modifier_id", $premade_financial_entry_atoms);
                }

                return $is_balanced;
            }

            case CLOSE_MODIFIER_ACTION: {
                $is_correct = count($this->input) === 2;

                if ($is_correct) {
                    $memoizer->write("#$modifier_id", []);
                }

                return $is_correct;
            }

            case EXCHANGE_MODIFIER_ACTION: {
                $is_correct = count($this->input) === 2;

                if ($is_correct) {
                    $premade_financial_entry_atoms = $this->makeFinancialEntryAtoms($this->input);
                    $memoizer->write("#$modifier_id", $premade_financial_entry_atoms);
                }

                return $is_correct;
            }

            case BID_MODIFIER_ACTION: {
                $premade_financial_entry_atoms = $this->makeFinancialEntryAtoms($this->input);

                if (!$this->hasNecessaryItemizedInputs($premade_financial_entry_atoms)) {
                    return false;
                }

                $is_balanced = $this->checkBalance($premade_financial_entry_atoms);

                if ($is_balanced) {
                    $memoizer->write("#$modifier_id", $premade_financial_entry_atoms);
                }

                return $is_balanced;
            }

            case ASK_MODIFIER_ACTION: {
                $premade_financial_entry_atoms = $this->makeFinancialEntryAtoms($this->input);
                $filled_financial_entry_atoms = $this->fillSourceAndSinkAtomsAutomatically(
                    $premade_financial_entry_atoms,
                    REAL_DEBIT_MODIFIER_ATOM_KIND,
                    REAL_CREDIT_MODIFIER_ATOM_KIND
                );

                // TODO: Transfer capital gains/losses from source atom to direct sale

                $is_balanced = $this->checkBalance($filled_financial_entry_atoms);

                if ($is_balanced) {
                    $memoizer->write("#$modifier_id", $filled_financial_entry_atoms);
                }

                return $is_balanced;
            }

                // Make sure there is always an input to the accounts.
                // TODO: Create finer validation for the following once available.
            case TRANSFORM_MODIFIER_ACTION:
            case THROW_MODIFIER_ACTION:
            case CATCH_MODIFIER_ACTION:
            case CONDENSE_MODIFIER_ACTION:
            case DILUTE_MODIFIER_ACTION: {
                foreach ($this->input as $input_element) {
                    $numerical_value = RationalNumber::get($input_element["numerical_value"]);
                    if ($numerical_value->isZero()) {
                        return false;
                    }
                }

                $premade_financial_entry_atoms = $this->makeFinancialEntryAtoms($this->input);
                $memoizer->write("#$modifier_id", $premade_financial_entry_atoms);
                return true;
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

    private function makeFinancialEntryAtoms(array $input): array
    {
        return array_map(
            function ($raw_financial_entry_atom) {
                $financial_entry_atom_entity = new FinancialEntryAtom();
                $financial_entry_atom_entity->fill([
                    "modifier_atom_id" => $raw_financial_entry_atom["modifier_atom_id"],
                    "kind" => $raw_financial_entry_atom["kind"],
                    "numerical_value" => $raw_financial_entry_atom["numerical_value"]
                ]);
                return $financial_entry_atom_entity;
            },
            $input
        );
    }

    private function hasNecessaryItemizedInputs(array $premade_financial_entry_atoms): bool
    {
        $unresolved_itemized_entry_atoms = [];

        $modifier_atom_cache = ModifierAtomCache::make($this->context);
        $modifier_atom_IDs = $this->extractModifierAtomIDs();
        $modifier_atom_cache->loadResources($modifier_atom_IDs);
        $modifier_atoms = Resource::key(
            $modifier_atom_cache->getLoadedResources($modifier_atom_IDs),
            fn ($resource) => $resource->id
        );
        $account_cache = AccountCache::make($this->context);
        $account_cache->loadResources($modifier_atom_cache->extractAssociatedAccountIDs());

        foreach ($premade_financial_entry_atoms as $premade_financial_entry_atom) {
            $modifier_atom_id = $premade_financial_entry_atom->modifier_atom_id;
            $account_id = $modifier_atom_cache->determineModifierAtomAccountID(
                $modifier_atom_id
            );
            $account_kind = $account_cache->determineAccountKind($account_id);

            if ($account_kind === ITEMIZED_ASSET_ACCOUNT_KIND) {
                if (isset($unresolved_itemized_entry_atoms[$modifier_atom_id])) {
                    unset($unresolved_itemized_entry_atoms[$modifier_atom_id]);
                } else {
                    $unresolved_itemized_entry_atoms[$modifier_atom_id] = true;
                }
            }
        }

        return count($unresolved_itemized_entry_atoms) === 0;
    }

    private function fillSourceAndSinkAtomsAutomatically(
        array $premade_financial_entry_atoms,
        string $source_side,
        string $sink_side
    ): array {
        $keyed_financial_entry_atoms = Resource::key(
            $premade_financial_entry_atoms,
            fn ($atom) => $atom->modifier_atom_id
        );

        $expected_modifier_atoms = model(ModifierAtomModel::class, false)
            ->where("modifier_id", $modifier_id)
            ->findAll();

        $modifier_atom_cache = ModifierAtomCache::make($this->context);
        $modifier_atom_cache->addPreloadedResources($expected_modifier_atoms);

        $account_cache = $this->context->getVariable(ContextKeys::ACCOUNT_CACHE);
        $account_cache->loadResources(array_unique(array_map(
            fn ($atom) => $atom->account_id,
            $expected_modifier_atoms
        )));

        $used_modifier_atom_IDs = array_keys($keyed_financial_entry_atoms);
        $unused_modifier_atom_IDs = array_diff(
            array_map(fn ($atom) => $atom->id, $expected_modifier_atoms),
            $used_modifier_atom_IDs
        );
        $grouped_modifier_atoms = array_map(
            fn ($group) => Resource::group(
                $group,
                fn ($atom) => $account_cache->determineAccountKind($atom->account_id)
            ),
            Resource::group(
                $expected_modifier_atoms,
                fn ($atom) => $atom->kind
            )
        );

        $price_modifier_atoms = Resource::key(
            $grouped_modifier_atoms[PRICE_MODIFIER_ATOM_KIND][ITEMIZED_ASSET_ACCOUNT_KIND],
            fn ($atom) => $atom->account_id
        );
        $item_count_modifier_atoms = Resource::key(
            $grouped_modifier_atoms[ITEM_COUNT_MODIFIER_ATOM_KIND][ITEMIZED_ASSET_ACCOUNT_KIND],
            fn ($atom) => $atom->account_id
        );

        $real_source_modifier_atoms = $grouped_modifier_atoms[$source_side];
        $real_sink_modifier_atoms = $grouped_modifier_atoms[$sink_side];

        if (count($real_source_modifier_atoms) !== 1 || count($real_sink_modifier_atoms) === 0) {
            return [];
        }

        $source_modifier_atom = ($real_source_modifier_atoms[GENERAL_ASSET_ACCOUNT_KIND]
            ?? $real_source_modifier_atoms[LIQUID_ASSET_ACCOUNT_KIND]
            ?? $real_source_modifier_atoms[DEPRECIATIVE_ASSET_ACCOUNT_KIND])[0];
        $sink_modifier_atoms = $real_sink_modifier_atoms[ITEMIZED_ASSET_ACCOUNT_KIND];

        $sink_financial_entry_atoms = array_map(
            function ($sink_modifier_atom) use (
                $price_modifier_atoms,
                $item_count_modifier_atoms,
                $keyed_financial_entry_atoms
            ) {
                $financial_entry_atom_entity = new FinancialEntryAtom();
                $price_value = $keyed_financial_entry_atoms[
                    $price_modifier_atoms[$sink_modifier_atom->account_id]->id
                ];
                $quantity_value = $keyed_financial_entry_atoms[
                    $item_count_modifier_atoms[$sink_modifier_atom->account_id]->id
                ];
                $financial_entry_atom_entity->fill([
                    "modifier_atom_id" => $sink_modifier_atom->id,
                    "numerical_value" => $price_value->multipliedBy($quantity_value),
                ]);
                return $financial_entry_atom_entity;
            },
            $sink_modifier_atoms
        );

        $source_atom = new FinancialEntryAtom();
        $source_atom->modifier_atom_id = $source_modifier_atom->id;
        return [
            ...$premade_financial_entry_atoms,
            array_reduce(
                $sink_financial_entry_atoms,
                fn ($previous_atom, $sink_atom) => $previous_atom->fill([
                    "numerical_value" => $previous_atom->numerical_value->plus(
                        $sink_atom->numerical_value
                    )
                ]),
                $source_atom
            ),
            ...$sink_financial_entry_atoms
        ];
    }

    private function checkBalance(array $financial_entry_atoms): bool
    {
        $modifier_atom_cache = ModifierAtomCache::make($this->context);
        $unresolved_itemized_entry_atoms = [];

        $real_debit_total = RationalNumber::zero();
        $real_credit_total = RationalNumber::zero();

        foreach ($financial_entry_atoms as $financial_entry_atom) {
            $modifier_atom_id = $financial_entry_atom->modifier_atom_id;
            $numerical_value = $financial_entry_atom->numerical_value;
            $modifier_atom_kind = $modifier_atom_cache->determineModifierAtomKind(
                $modifier_atom_id
            );
            $account_id = $modifier_atom_cache->determineModifierAtomAccountID(
                $modifier_atom_id
            );
            $account_cache = AccountCache::make($this->context);
            $account_kind = $account_cache->determineAccountKind($account_id);

            if ($account_kind === ITEMIZED_ASSET_ACCOUNT_KIND) {
                if (isset($unresolved_itemized_entry_atoms[$modifier_atom_id])) {
                    $previous_financial_entry_atom = $unresolved_itemized_entry_atoms[
                        $modifier_atom_id
                    ];
                    unset($unresolved_itemized_entry_atoms[$modifier_atom_id]);

                    $entry_atoms = [
                        $previous_financial_entry_atom,
                        $financial_entry_atom
                    ];

                    $price_atom = array_values(array_filter(
                        $entry_atoms,
                        fn ($atom) => $atom->kind === PRICE_FINANCIAL_ENTRY_ATOM_KIND
                    ))[0] ?? null;

                    $quantity_atom = array_values(array_filter(
                        $entry_atoms,
                        fn ($atom) => $atom->kind === QUANTITY_FINANCIAL_ENTRY_ATOM_KIND
                    ))[0] ?? null;

                    $total_atom = array_values(array_filter(
                        $entry_atoms,
                        fn ($atom) => $atom->kind === TOTAL_FINANCIAL_ENTRY_ATOM_KIND
                    ))[0] ?? null;

                    $is_quantified_total_pair = $quantity_atom !== null && $total_atom !== null;
                    $is_priced_quantity_pair = $price_atom !== null && $quantity_atom !== null;
                    $is_priced_total_pair = $price_atom !== null && $total_atom !== null;

                    if ($is_quantified_total_pair || $is_priced_total_pair) {
                        $numerical_value = $total_atom->numerical_value;
                    } elseif ($is_priced_quantity_pair) {
                        $numerical_value = $quantity_atom->numerical_value->multipliedBy(
                            $price_atom->numerical_value
                        );
                    } else {
                        return false;
                    }
                } else {
                    $unresolved_itemized_entry_atoms[$modifier_atom_id] = $financial_entry_atom;
                    continue;
                }
            }

            switch ($modifier_atom_kind) {
                case REAL_DEBIT_MODIFIER_ATOM_KIND:
                    $real_debit_total = $real_debit_total->plus($numerical_value);
                    break;
                case REAL_CREDIT_MODIFIER_ATOM_KIND:
                    $real_credit_total = $real_credit_total->plus($numerical_value);
                    break;
                case REAL_EMERGENT_MODIFIER_ATOM_KIND:
                    // TODO: To finish this problem, there must be a way to keep track of item
                    // TODO: calculations.
                    $real_credit_total = $real_credit_total->plus($numerical_value);
                    break;
            }
        }

        $imaginary_debit_total = RationalNumber::zero();
        $imaginary_credit_total = RationalNumber::zero();

        if (count($unresolved_itemized_entry_atoms) > 0) {
            return false;
        }

        $is_balanced = $real_debit_total->minus($real_credit_total)->isZero();

        return $is_balanced;
    }
}
