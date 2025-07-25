<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;
use Config\Database;

class CreateFrozenAccountsTable extends Migration
{
    public function up()
    {
        $database = Database::connect();

        $this->forge->addField([
            "hash" => [
                "type" => "CHARACTER",
                "constraint" => 72
            ],
            "frozen_period_id" => [
                "type" => "BIGINT",
                "unsigned" => true
            ],
            "account_id" => [
                "type" => "BIGINT",
                "unsigned" => true
            ]
        ]);
        $this->forge->addPrimaryKey("hash", "pk_frozen_accounts");
        if ($database->DBDriver !== "SQLite3") {
            $this->forge->addForeignKey(
                "frozen_period_id",
                "frozen_periods",
                "id",
                "CASCADE",
                "CASCADE",
                "frozen_accounts_frozen_period_id_foreign"
            );
            $this->forge->addForeignKey(
                "account_id",
                "accounts_v2",
                "id",
                "CASCADE",
                "CASCADE",
                "frozen_accounts_account_id_foreign"
            );
        }
        $this->forge->createTable("frozen_accounts");
    }

    public function down()
    {
        $this->forge->dropTable("frozen_accounts");
    }
}
