<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

use Config\Database;

class CreateModifiersTable extends Migration
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
            "debit_account_id" => [
                "type" => "BIGINT",
                "unsigned" => true,
            ],
            "credit_account_id" => [
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
        $this->forge->addPrimaryKey("id", "pk_modifiers");
        $this->forge->addUniqueKey("name", "modifiers_name_key");
        $this->forge->addForeignKey(
            "debit_account_id",
            "accounts",
            "id",
            "CASCADE",
            "CASCADE",
            $database->DBDriver === "SQLite3"
                ? "modifiers_debit_account_id_foreign"
                : null
        );
        $this->forge->addForeignKey(
            "credit_account_id",
            "accounts",
            "id",
            "CASCADE",
            "CASCADE",
            $database->DBDriver === "SQLite3"
                ? "modifiers_credit_account_id_foreign"
                : null
        );
        $this->forge->createTable("modifiers");
    }

    public function down()
    {
        $this->forge->dropTable("modifiers");
    }
}
