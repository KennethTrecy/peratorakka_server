<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;

class CreateSummaryCalculationsTable extends Migration
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
            "account_id" => [
                "type" => "BIGINT",
                "unsigned" => true,
            ],
            "unadjusted_debit_amount" => [
                "type" => "TEXT",
            ],
            "unadjusted_credit_amount" => [
                "type" => "TEXT",
            ],
            "adjusted_debit_amount" => [
                "type" => "TEXT",
            ],
            "adjusted_credit_amount" => [
                "type" => "TEXT",
            ]
        ]);
        $this->forge->addPrimaryKey("id", "pk_summary_calculations");
        if ($database->DBDriver !== "SQLite3") {
            $this->forge->addForeignKey(
                "frozen_period_id",
                "frozen_periods",
                "id",
                "CASCADE",
                "CASCADE",
                "summary_calculations_frozen_period_id_foreign"
            );
            $this->forge->addForeignKey(
                "account_id",
                "accounts",
                "id",
                "CASCADE",
                "CASCADE",
                "summary_calculations_account_id_foreign"
            );
        }
        $this->forge->createTable("summary_calculations");
    }

    public function down()
    {
        $this->forge->dropTable("summary_calculations");
    }
}
