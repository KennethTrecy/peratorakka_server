<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;
use Config\Database;

class CreateRealFlowCalculationsTable extends Migration
{
    public function up()
    {
        $database = Database::connect();

        $this->forge->addField([
            "frozen_account_hash" => [
                "type" => "CHARACTER",
                "constraint" => 72
            ],
            "cash_flow_activity_id" => [
                "type" => "BIGINT",
                "unsigned" => true,
            ],
            "net_amount" => [
                "type" => "TEXT"
            ]
        ]);
        if ($database->DBDriver !== "SQLite3") {
            $this->forge->addForeignKey(
                "frozen_account_hash",
                "frozen_accounts",
                "hash",
                "CASCADE",
                "CASCADE",
                "real_flow_calculations_frozen_account_hash_foreign"
            );
        }
        $this->forge->createTable("real_flow_calculations");
    }

    public function down()
    {
        $this->forge->dropTable("real_flow_calculations");
    }
}
