<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;
use Config\Database;

class CreateNumericalToolV2Table extends Migration
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
            "currency_id" => [
                "type" => "BIGINT",
                "unsigned" => true,
            ],
            "name" => [
                "type" => "VARCHAR",
                "constraint" => "255",
            ],
            "kind" => [
                "type" => "INT",
                "unsigned" => true,
            ],
            "recurrence" => [
                "type" => "INT",
                "unsigned" => true
            ],
            "recency" => [
                "type" => "INT",
                "unsigned" => false
            ],
            "exchange_rate_basis" => [
                "type" => "INT",
                "unsigned" => false
            ],
            "order" => [
                "type" => "INT",
                "unsigned" => true
            ],
            "notes" => [
                "type" => "TEXT",
                "null" => true,
            ],
            "configuration" => [
                "type" => "TEXT"
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
        $this->forge->addPrimaryKey("id", "pk_numerical_tools_v2");
        if ($database->DBDriver !== "SQLite3") {
            $this->forge->addForeignKey(
                "currency_id",
                "currencies",
                "id",
                "CASCADE",
                "CASCADE",
                "collections_currency_id_foreign"
            );
        }
        $this->forge->createTable("numerical_tools_v2");
    }

    public function down()
    {
        $this->forge->dropTable("numerical_tools_v2");
    }
}
