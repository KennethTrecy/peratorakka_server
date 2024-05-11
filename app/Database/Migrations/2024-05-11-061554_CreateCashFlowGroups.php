<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

use Config\Database;

class CreateCashFlowGroups extends Migration
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
        $this->forge->addPrimaryKey("id", "pk_cash_flow_groups");
        if ($database->DBDriver !== "SQLite3") {
            $this->forge->addUniqueKey([ "user_id", "name" ], "cash_flow_groups_user_id_name");
            $this->forge->addForeignKey(
                "user_id",
                "users",
                "id",
                "CASCADE",
                "CASCADE",
                "cash_flow_groups_user_id_foreign"
            );
        }
        $this->forge->createTable("cash_flow_groups");
    }

    public function down()
    {
        $this->forge->dropTable("cash_flow_groups");
    }
}
