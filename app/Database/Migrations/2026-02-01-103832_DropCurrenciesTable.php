<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;
use Config\Database;

class DropCurrenciesTable extends Migration
{
    public function up()
    {
        $this->forge->dropTable("currencies");
    }

    public function down()
    {
        $database = Database::connect();

        $this->forge->addField([
            "id" => [
                "type" => "BIGINT",
                "unsigned" => true,
                "auto_increment" => true,
            ],
            "user_id" => [
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
            "presentational_precision" => [
                "type" => "INT",
                "unsigned" => true
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
        $this->forge->addPrimaryKey("id", "pk_currencies");
        $this->forge->addUniqueKey([ "user_id", "code", "name" ], "currencies_user_id_code_name");
        if ($database->DBDriver !== "SQLite3") {
            $this->forge->addForeignKey(
                "user_id",
                "users",
                "id",
                "CASCADE",
                "CASCADE",
                "currencies_user_id_foreign"
            );
        }
        $this->forge->createTable("currencies");
    }
}
