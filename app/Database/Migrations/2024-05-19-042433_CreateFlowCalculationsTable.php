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
            "summary_calculation_id" => [
                "type" => "BIGINT",
                "unsigned" => true,
            ],
            "cash_flow_category_id" => [
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
                "summary_calculation_id",
                "summary_calculations",
                "id",
                "CASCADE",
                "CASCADE",
                "flow_calculations_summary_calculation_id_foreign"
            );
            $this->forge->addForeignKey(
                "cash_flow_category_id",
                "cash_flow_categories",
                "id",
                "CASCADE",
                "CASCADE",
                "flow_calculations_cash_flow_category_id_foreign"
            );
        }
        $this->forge->createTable("flow_calculations");
    }

    public function down()
    {
        $this->forge->dropTable("flow_calculations");
    }
}
