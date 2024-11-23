<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;
use Config\Database;

class CreateFormulaeTable extends Migration
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
            "description" => [
                "type" => "TEXT",
                "null" => true,
            ],
            "output_format" => [
                "type" => "INT",
                "unsigned" => true,
            ],
            "exchange_rate_basis" => [
                "type" => "INT",
                "unsigned" => true,
            ],
            "presentational_precision" => [
                "type" => "INT",
                "unsigned" => true
            ],
            "formula" => [
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
        $this->forge->addPrimaryKey("id", "pk_formulae");
        if ($database->DBDriver !== "SQLite3") {
            $this->forge->addUniqueKey([ "user_id", "name" ], "formulae_user_id_name");
            $this->forge->addForeignKey(
                "currency_id",
                "currencies",
                "id",
                "CASCADE",
                "CASCADE",
                "formulae_currency_id_foreign"
            );
        }
        $this->forge->createTable("formulae");
    }

    public function down()
    {
        $this->forge->dropTable("formulae");
    }
}
