<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;
use Config\Database;

class CreatePrecisionFormatsTable extends Migration
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
            "minimum_presentational_precision" => [
                "type" => "INT",
                "unsigned" => true
            ],
            "maximum_presentational_precision" => [
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
        $this->forge->addPrimaryKey("id", "pk_precision_formats");
        $this->forge->addUniqueKey(
            [ "user_id", "name" ],
            "precision_formats_v2_user_id_name"
        );
        if ($database->DBDriver !== "SQLite3") {
            $this->forge->addForeignKey(
                "user_id",
                "users",
                "id",
                "CASCADE",
                "CASCADE",
                "precision_formats_user_id_foreign"
            );
        }
        $this->forge->createTable("precision_formats");
    }

    public function down()
    {
        $this->forge->dropTable("precision_formats");
    }
}
