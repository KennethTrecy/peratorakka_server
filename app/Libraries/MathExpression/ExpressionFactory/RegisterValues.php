<?php

namespace App\Libraries\MathExpression\ExpressionFactory;

use App\Casts\AccountKind;
use App\Exceptions\ExpressionException;
use App\Libraries\Context\FlashCache;
use App\Libraries\Context;
use App\Models\AccountCollectionModel;
use App\Models\AccountModel;
use App\Models\CollectionModel;
use Closure;
use CodeIgniter\Database\BaseBuilder;
use Xylemical\Expressions\Token;
use Xylemical\Expressions\Value;

trait RegisterValues
{
    public function addValues()
    {
        $this->addValue('COLLECTION\[\d+\]', "evaluateCollection");
        $this->addValue(
            '('.join('|', ACCEPTABLE_ACCOUNT_KINDS).')_ACCOUNTS',
            "evaluateAccountKind"
        );
    }

    private function evaluateCollection(array $values, Context $context, Token $token): string
    {
        $value = $token->getValue();
        preg_match('/COLLECTION\[(?P<collection_id>\d+)\]/', $value, $matches);
        $collection_id = $matches["collection_id"];

        $builder = model(CollectionModel::class, false)
            ->builder()
            ->where("id", $collection_id);

        $key = $this->cache->store($builder);

        return $key;
    }

    private function evaluateAccountKind(array $values, Context $context, Token $token): string
    {
        $raw_kind = explode("_", $token->getValue());
        $kind = strtolower($raw_kind[0]);

        $builder = model(AccountModel::class, false)
            ->builder()
            ->where("kind", AccountKind::set($kind));

        $key = $this->cache->store($builder);

        return $key;
    }

    private function addValue(string $name, string $function_name)
    {
        $callback = Closure::fromCallable([ $this, $function_name ]);
        $this->addOperator(new Value($name, $callback));
    }
}
