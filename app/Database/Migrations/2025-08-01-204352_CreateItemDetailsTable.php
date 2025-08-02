<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;
use Config\Database;

class CreateItemDetailsTable extends Migration
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
            "name" => [
                "type" => "VARCHAR",
                "constraint" => "255",
            ],
            "unit" => [
                "type" => "VARCHAR",
                "constraint" => "255",
            ],
            "description" => [
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
        $this->forge->addPrimaryKey("id", "pk_item_details");
        $this->forge->addUniqueKey(
            [ "precision_format_id", "name" ],
            "item_details_precision_format_id_name"
        );
        if ($database->DBDriver !== "SQLite3") {
            $this->forge->addForeignKey(
                "precision_format_id",
                "precision_formats",
                "id",
                "CASCADE",
                "CASCADE",
                "item_details_precision_format_id_foreign"
            );
        }
        $this->forge->createTable("item_details");
    }

    public function down()
    {
        $this->forge->dropTable("item_details");
    }
}
