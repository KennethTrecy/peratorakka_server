<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;
use Config\Database;

class CreateFinancialEntriesV2Table extends Migration
{
    public function up()
    {
        $database = Database::connect();

        $this->forge->addField([
            "id" => [
                "type" => "BIGINT",
                "unsigned" => true,
                "auto_increment" => true,
            ],
            "modifier_id" => [
                "type" => "BIGINT",
                "unsigned" => true
            ],
            "transacted_at" => [
                "type" => "DATETIME",
            ],
            "remarks" => [
                "type" => "TEXT",
                "null" => true,
            ],
            "created_at" => [
                "type" => "DATETIME",
                "default" => new RawSql("CURRENT_TIMESTAMP"),
            ],
            "updated_at" => [
                "type" => "DATETIME",
                "default" => new RawSql("CURRENT_TIMESTAMP"),
            ],
            "deleted_at" => [
                "type" => "DATETIME",
                "null" => true,
            ]
        ]);
        $this->forge->addPrimaryKey("id", "pk_financial_entries_v2");
        if ($database->DBDriver !== "SQLite3") {
            $this->forge->addForeignKey(
                "modifier_id",
                "modifiers_v2",
                "id",
                "CASCADE",
                "CASCADE",
                "financial_entries_v2_modifier_id_foreign"
            );
        }
        $this->forge->createTable("financial_entries_v2");
    }

    public function down()
    {
        $this->forge->dropTable("financial_entries_v2");
    }
}
