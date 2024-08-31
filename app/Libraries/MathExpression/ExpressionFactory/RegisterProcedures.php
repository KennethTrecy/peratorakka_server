<?php

namespace App\Libraries\MathExpression\ExpressionFactory;

use Closure;

use App\Exceptions\ExpressionException;
use App\Libraries\FlashCache;
use App\Libraries\MathExpression\Context;
use App\Models\AccountCollectionModel;
use App\Models\CollectionModel;
use App\Models\AccountModel;
use CodeIgniter\Database\BaseBuilder;
use Xylemical\Expressions\Token;
use Xylemical\Expressions\Procedure;

trait RegisterProcedures
{
    public function addProcedures() {
        $this->addProcedure(
            "TOTAL_(OPENED|UNADJUSTED|CLOSED)_(DEBIT|CREDIT)_AMOUNT",
            1,
            "processTotalAmount"
        );
    }

    private function addProcedure(string $name, int $arity, string $function_name) {
        $callback = Closure::fromCallable([ $this, $function_name ]);
        $this->addOperator(new Procedure($name, $arity, $callback));
    }

    private function processTotalAmount(array $values, Context $context, Token $token) {
        $function_name = $token->getValue();

        /**
         * @var BaseBuilder
         */
        $builder = $this->cache->flash($values[0]);

        if ($builder === null) {
            throw new ExpressionException(
                "A collection or account kind is expected for \"$function_name\" function."
            );
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
        $time_group_manager = $context->timeGroupManager();
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
        return $result;
    }
}
