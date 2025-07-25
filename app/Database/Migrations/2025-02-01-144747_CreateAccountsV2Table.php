<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;
use Config\Database;

class CreateAccountsV2Table extends Migration
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
        $this->forge->addPrimaryKey("id", "pk_accounts_v2");
        $this->forge->addUniqueKey(
            [ "currency_id", "name" ],
            "accounts_v2_currency_id_name"
        );
        if ($database->DBDriver !== "SQLite3") {
            $this->forge->addForeignKey(
                "currency_id",
                "currencies_v2",
                "id",
                "CASCADE",
                "CASCADE",
                "accounts_v2_currency_id_foreign"
            );
        }
        $this->forge->createTable("accounts_v2");
    }

    public function down()
    {
        $this->forge->dropTable("accounts_v2");
    }
}
