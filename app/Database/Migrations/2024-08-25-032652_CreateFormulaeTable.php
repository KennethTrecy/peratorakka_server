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
            "user_id" => [
                "type" => "INT",
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
        $this->forge->addUniqueKey("name", "formulae_name_key");
        if ($database->DBDriver !== "SQLite3") {
            $this->forge->addForeignKey(
                "user_id",
                "users",
                "id",
                "CASCADE",
                "CASCADE",
                "formulae_user_id_foreign"
            );
        }
        $this->forge->createTable("formulae");
    }

    public function down()
    {
        $this->forge->dropTable("formulae");
    }
}
