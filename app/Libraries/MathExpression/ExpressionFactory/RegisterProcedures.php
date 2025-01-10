<?php

namespace App\Libraries\MathExpression\ExpressionFactory;

use App\Exceptions\ExpressionException;
use App\Libraries\Context\FlashCache;
use App\Libraries\Context;
use App\Libraries\Context\ContextKeys;
use App\Models\AccountCollectionModel;
use App\Models\AccountModel;
use App\Models\CollectionModel;
use Brick\Math\BigRational;
use Closure;
use CodeIgniter\Database\BaseBuilder;
use Xylemical\Expressions\Procedure;
use Xylemical\Expressions\Operator;
use Xylemical\Expressions\Token;

trait RegisterProcedures
{
    public function addProcedures()
    {
        $this->addProcedure(
            "SHIFT_CYCLE",
            3,
            "processShiftCycle"
        );
        $this->addProcedure(
            "TOTAL_(OPENED|UNADJUSTED|CLOSED)_(DEBIT|CREDIT)_AMOUNT",
            1,
            "processTotalAmount"
        );
        $this->addCustomOperator("\*\*", 7, Operator::RIGHT_ASSOCIATIVE, 2, "exponentiate");
    }

    private function addProcedure(string $name, int $arity, string $function_name)
    {
        $callback = Closure::fromCallable([ $this, $function_name ]);
        $this->addOperator(new Procedure($name, $arity, $callback));
    }

    private function addCustomOperator(
        string $regex,
        int $precedence,
        int $associativity,
        int $arity,
        string $function_name
    ) {
        $callback = Closure::fromCallable([ $this, $function_name ]);
        $this->addOperator(new Operator($regex, $precedence, $associativity, $arity, $callback));
    }

    private function processTotalAmount(array $values, Context $context, Token $token)
    {
        $function_name = $token->getValue();

        $builder_key = $values[0];

        /**
         * @var BaseBuilder
         */
        $builder = $this->cache->flash($builder_key);

        if ($builder === null) {
            throw new ExpressionException(
                "A collection or account kind is expected for \"$function_name\" function."
            );
        }

        $compiled_select = base64_encode($builder->getCompiledSelect(false));
        $memo_key = $function_name.'_'.$compiled_select;

        if (!is_null($this->memo->read($memo_key, null))) {
            return $this->memo->read($memo_key);
        }

        $linked_accounts = [];

        if ($builder instanceof BaseBuilder) {
            $table = $builder->getTable();

            switch ($table) {
                case model(CollectionModel::class, false)->getTable():
                    $account_collections = model(AccountCollectionModel::class)
                        ->whereIn("collection_id", $builder->select("id"))
                        ->findAll();

                    foreach ($account_collections as $document) {
                        $account_id = $document->account_id;
                        array_push($linked_accounts, $account_id);
                    }
                    break;
                case model(AccountModel::class, false)->getTable():
                    $account_collections = $builder->select("id")->get()->getResult();

                    foreach ($account_collections as $document) {
                        $account_id = $document->id;
                        array_push($linked_accounts, $account_id);
                    }

                    break;
                default:
                    throw new ExpressionException(
                        "A collection or account kind is expected for \"$function_name\" function."
                    );
            }
        }

        $linked_accounts = array_unique($linked_accounts);

        /**
         * @var TimeGroupManager
         */
        $time_group_manager = $context->getVariable(ContextKeys::TIME_GROUP_MANAGER);
        $native_procedure_name = implode(
            "",
            explode(
                "_",
                lcfirst(ucwords($function_name, "_"))
            )
        );

        $result = json_encode(
            $time_group_manager->$native_procedure_name($linked_accounts)
        );

        $this->memo->write($memo_key, $result);

        return $result;
    }

    private function processShiftCycle(array $values, Context $context, Token $token)
    {
        if (!is_numeric($values[1]) || !is_int(+$values[1]) || +$values[1] < 1) {
            throw new ExpressionException(
                "SHIFT_CYCLE's second parameter must be a positive integer."
            );
        }

        $current_result = $values[0];
        $shift = +$values[1];
        $default_shifted_value = $values[2];

        $result = json_decode($current_result, false);
        $shifted_result = array_fill(0, $shift, $default_shifted_value);
        $shifted_result = array_merge(array_values($shifted_result), array_values($result));
        $shifted_result = array_slice($shifted_result, 0, count($result));
        $shifted_result = json_encode(array_values($shifted_result));

        return $shifted_result;
    }

    private function exponentiate(array $values, Context $context, Token $token) {
        $base = $values[0];
        $exponent = $values[1];

        return $this->math->power($base, $exponent);
    }
}
