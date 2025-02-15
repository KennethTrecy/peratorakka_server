<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;
use Config\Database;

class CreateFormulaeV2Table extends Migration
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
            "expression" => [
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
        $this->forge->addPrimaryKey("id", "pk_formulae_v2");
        if ($database->DBDriver !== "SQLite3") {
            $this->forge->addUniqueKey(
                [ "precision_format_id", "name" ],
                "formulae_v2_precision_format_id_name"
            );
            $this->forge->addForeignKey(
                "precision_format_id",
                "precision_formats",
                "id",
                "CASCADE",
                "CASCADE",
                "formulae_v2_precision_format_id_foreign"
            );
        }
        $this->forge->createTable("formulae_v2");
    }

    public function down()
    {
        $this->forge->dropTable("formulae_v2");
    }
}
