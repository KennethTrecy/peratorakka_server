<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateSummaryCalculationsTable extends Migration
{
    public function up()
    {
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
        $this->forge->addForeignKey(
            "frozen_period_id",
            "frozen_periods",
            "id",
            "CASCADE",
            "CASCADE"
        );
        $this->forge->addForeignKey("account_id", "accounts", "id", "CASCADE", "CASCADE");
        $this->forge->createTable("summary_calculations");
    }

    public function down()
    {
        $this->forge->dropTable("summary_calculations");
    }
}
