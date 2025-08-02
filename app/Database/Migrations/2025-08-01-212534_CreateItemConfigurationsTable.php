<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;
use Config\Database;

class CreateItemConfigurationsTable extends Migration
{
    public function up()
    {
        $database = Database::connect();

        $this->forge->addField([
            "account_id" => [
                "type" => "BIGINT",
                "unsigned" => true
            ],
            "item_detail_id" => [
                "type" => "BIGINT",
                "unsigned" => true
            ],
            "valuation_method" => [
                "type" => "INT",
                "unsigned" => true,
            ]
        ]);
        $this->forge->addUniqueKey("account_id", "item_configurations_account_id_key");
        if ($database->DBDriver !== "SQLite3") {
            $this->forge->addForeignKey(
                "account_id",
                "accounts_v2",
                "id",
                "CASCADE",
                "CASCADE",
                "item_configurations_account_id_foreign"
            );
            $this->forge->addForeignKey(
                "item_detail_id",
                "item_details",
                "id",
                "CASCADE",
                "CASCADE",
                "item_configurations_item_detail_id_foreign"
            );
        }
        $this->forge->createTable("item_configurations");
    }

    public function down()
    {
        $this->forge->dropTable("item_configurations");
    }
}
