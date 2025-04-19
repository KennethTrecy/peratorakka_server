<?php

namespace App\Models;

use App\Entities\FrozenAccount;
use CodeIgniter\Shield\Entities\User;
use Faker\Generator;

class FrozenAccountModel extends BaseResourceModel
{
    protected $table = "frozen_accounts";
    protected $primaryKey = "hash";
    protected $returnType = FrozenAccount::class;
    protected $allowedFields = [
        "hash",
        "frozen_period_id",
        "account_id"
    ];
    protected $useTimestamps = false;
    protected $useSoftDeletes = false;

    protected $sortable_fields = [];

    public function fake(Generator &$faker)
    {
        return [];
    }

    public function limitSearchToUser(BaseResourceModel $query_builder, User $user)
    {
        return $query_builder
            ->whereIn(
                "frozen_period_id",
                model(FrozenPeriodModel::class, false)
                    ->builder()
                    ->select("id")
                    ->where("user_id", $user->id)
            )
            ->whereIn(
                "account_id",
                model(AccountModel::class, false)
                    ->builder()
                    ->select("id")
                    ->whereIn(
                        "currency_id",
                        model(CurrencyModel::class, false)
                            ->builder()
                            ->select("id")
                            ->whereIn(
                                "precision_format_id",
                                model(PrecisionFormatModel::class, false)
                                    ->builder()
                                    ->select("id")
                                    ->where("user_id", $user->id)
                            )
                    )
            );
    }

    public static function extractLinkedAccounts(array $frozen_accounts): array
    {
        return array_map(fn ($frozen_account) => $frozen_account->account_id, $frozen_accounts);
    }

    public static function generateAccountHash(
        string $started_at,
        string $finished_at,
        int $account_id
    ): string {
        $key = $account_id.$finished_at.$started_at;

        return sha1($key).md5($key);
    }
}
