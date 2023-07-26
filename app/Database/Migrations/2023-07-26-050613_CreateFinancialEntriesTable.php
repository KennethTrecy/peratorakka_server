<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateFinancialEntriesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            "id" => [
                "type" => "BIGINT",
                "unsigned" => true,
                "auto_increment" => true,
            ],
            "modifier_id" => [
                "type" => "BIGINT",
                "unsigned" => true,
            ],
            "transacted_at" => [
                "type" => "DATETIME",
            ],
            "debit_amount" => [
                "type" => "VARCHAR",
                "constraint" => "255",
            ],
            "credit_amount" => [
                "type" => "VARCHAR",
                "constraint" => "255",
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
        $this->forge->addPrimaryKey("id");
        $this->forge->addForeignKey("modifier_id", "modifiers", "id", "CASCADE", "CASCADE");
        $this->forge->createTable("financial_entries");
    }

    public function down()
    {
        $this->forge->dropTable("financial_entries");
    }
}
