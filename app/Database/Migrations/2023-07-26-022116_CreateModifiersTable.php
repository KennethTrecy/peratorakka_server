<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateModifiersTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            "id" => [
                "type" => "BIGINT",
                "unsigned" => true,
                "auto_increment" => true,
            ],
            "account_id" => [
                "type" => "BIGINT",
                "unsigned" => true,
            ],
            "opposite_account_id" => [
                "type" => "BIGINT",
                "unsigned" => true,
            ],
            "name" => [
                "type" => "VARCHAR",
                "constraint" => "255",
                "unique" => true,
            ],
            "description" => [
                "type" => "TEXT",
                "null" => true,
            ],
            "result_side" => [
                "type" => "INT",
                "unsigned" => true,
            ],
            "kind" => [
                "type" => "INT",
                "unsigned" => true,
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
        $this->forge->addForeignKey("account_id", "accounts", "id", "CASCADE", "CASCADE");
        $this->forge->addForeignKey("opposite_account_id", "accounts", "id", "CASCADE", "CASCADE");
        $this->forge->createTable("modifiers");
    }

    public function down()
    {
        $this->forge->dropTable("modifiers");
    }
}