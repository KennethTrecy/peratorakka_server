<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;
use Config\Database;

class CreateRealAdjustedSummaryCalculationsTable extends Migration
{
    public function up()
    {
        $database = Database::connect();

        $this->forge->addField([
            "frozen_account_hash" => [
                "type" => "CHARACTER",
                "constraint" => 72
            ],
            "opened_amount" => [
                "type" => "TEXT"
            ],
            "closed_amount" => [
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
                "real_adjusted_summary_calculations_frozen_account_hash_foreign"
            );
        }
        $this->forge->createTable("real_adjusted_summary_calculations");
    }

    public function down()
    {
        $this->forge->dropTable("real_adjusted_summary_calculations");
    }
}
