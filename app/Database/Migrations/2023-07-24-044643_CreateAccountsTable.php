<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateAccountsTable extends Migration
{
    public function up()
    {
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
                "unique" => true,
            ],
            "description" => [
                "type" => "TEXT",
                "null" => true,
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
        $this->forge->addForeignKey("currency_id", "currencies", "id", "CASCADE", "CASCADE");
        $this->forge->createTable("accounts");
    }

    public function down()
    {
        $this->forge->dropTable("accounts");
    }
}
