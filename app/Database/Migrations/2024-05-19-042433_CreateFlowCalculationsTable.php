<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

use Config\Database;

class CreateFlowCalculationsTable extends Migration
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
            "frozen_period_id" => [
                "type" => "BIGINT",
                "unsigned" => true,
            ],
            "cash_flow_activity_id" => [
                "type" => "BIGINT",
                "unsigned" => true,
            ],
            "account_id" => [
                "type" => "BIGINT",
                "unsigned" => true,
            ],
            "net_amount" => [
                "type" => "TEXT",
            ]
        ]);
        $this->forge->addPrimaryKey("id", "pk_flow_calculations");
        if ($database->DBDriver !== "SQLite3") {
            $this->forge->addForeignKey(
                "frozen_period_id",
                "frozen_periods",
                "id",
                "CASCADE",
                "CASCADE",
                "flow_calculations_frozen_period_id_foreign"
            );
            $this->forge->addForeignKey(
                "cash_flow_activity_id",
                "cash_flow_activities",
                "id",
                "CASCADE",
                "CASCADE",
                "flow_calculations_cash_flow_activity_id_foreign"
            );
            $this->forge->addForeignKey(
                "account_id",
                "accounts",
                "id",
                "CASCADE",
                "CASCADE",
                "flow_calculations_account_id_foreign"
            );
        }
        $this->forge->createTable("flow_calculations");
    }

    public function down()
    {
        $this->forge->dropTable("flow_calculations");
    }
}
