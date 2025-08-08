<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;
use Config\Database;

class CreateItemCalculations extends Migration
{
    public function up()
    {
        $database = Database::connect();

        $this->forge->addField([
            "frozen_account_hash" => [
                "type" => "CHARACTER",
                "constraint" => 72
            ],
            "financial_entry_id" => [
                "type" => "BIGINT",
                "unsigned" => true
            ],
            "unit_price" => [
                "type" => "TEXT"
            ],
            "remaining_quantity" => [
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
                "item_calculations_frozen_account_hash_foreign"
            );
            $this->forge->addForeignKey(
                "financial_entry_id",
                "financial_entries_v2",
                "id",
                "CASCADE",
                "CASCADE",
                "item_calculations_financial_entry_id_foreign"
            );
        }
        $this->forge->createTable("item_calculations");
    }

    public function down()
    {
        $this->forge->dropTable("item_calculations");
    }
}
