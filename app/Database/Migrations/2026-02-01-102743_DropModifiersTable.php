<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;
use Config\Database;

class DropModifiersTable extends Migration
{
    public function up()
    {
        $this->forge->dropTable("modifiers");
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
            "action" => [
                "type" => "INT",
                "unsigned" => true,
                "null" => false,
                "default" => array_search(RECORD_MODIFIER_ACTION, MODIFIER_ACTIONS)
            ],
            "debit_cash_flow_activity_id" => [
                "type" => "BIGINT",
                "unsigned" => true,
                "default" => null
            ],
            "credit_cash_flow_activity_id" => [
                "type" => "BIGINT",
                "unsigned" => true,
                "default" => null
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
        $this->forge->addUniqueKey(
            [
                "debit_account_id",
                "credit_account_id",
                "name"
            ],
            "modifiers_debit_account_id_credit_account_id_name"
        );
        if ($database->DBDriver !== "SQLite3") {
            $this->forge->addForeignKey(
                "debit_account_id",
                "accounts",
                "id",
                "CASCADE",
                "CASCADE",
                "modifiers_debit_account_id_foreign"
            );
            $this->forge->addForeignKey(
                "credit_account_id",
                "accounts",
                "id",
                "CASCADE",
                "CASCADE",
                "modifiers_credit_account_id_foreign"
            );
            $this->forge->addForeignKey(
                "debit_cash_flow_activity_id",
                "cash_flow_activities",
                "id",
                "CASCADE",
                "CASCADE",
                "modifiers_debit_cash_flow_activity_id_foreign"
            );
            $this->forge->addForeignKey(
                "credit_cash_flow_activity_id",
                "cash_flow_activities",
                "id",
                "CASCADE",
                "CASCADE",
                "modifiers_credit_cash_flow_activity_id_foreign"
            );
        }
        $this->forge->createTable("modifiers");
    }
}
