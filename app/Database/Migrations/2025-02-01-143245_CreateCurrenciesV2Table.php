<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;
use Config\Database;

class CreateCurrenciesV2Table extends Migration
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
            "precision_format_id" => [
                "type" => "INT",
                "unsigned" => true,
            ],
            "code" => [
                "type" => "VARCHAR",
                "constraint" => "255",
            ],
            "name" => [
                "type" => "VARCHAR",
                "constraint" => "255",
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
        $this->forge->addPrimaryKey("id", "pk_currencies_v2");
        $this->forge->addUniqueKey(
            [ "precision_format_id", "code", "name" ],
            "currencies_v2_precision_format_id_code_name"
        );
        if ($database->DBDriver !== "SQLite3") {
            $this->forge->addForeignKey(
                "precision_format_id",
                "precision_formats",
                "id",
                "CASCADE",
                "CASCADE",
                "currencies_v2_precision_format_id_foreign"
            );
        }
        $this->forge->createTable("currencies_v2");
    }

    public function down()
    {
        $this->forge->dropTable("currencies_v2");
    }
}
