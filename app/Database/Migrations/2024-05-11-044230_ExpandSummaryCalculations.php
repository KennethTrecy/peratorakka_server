<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ExpandSummaryCalculations extends Migration
{
    public function up()
    {
        $new_fields = [
            "opened_debit_amount" => [
                "type" => "TEXT",
                "null" => false,
                "default" => "0"
            ],
            "opened_credit_amount" => [
                "type" => "TEXT",
                "null" => false,
                "default" => "0"
            ]
        ];
        $this->forge->addColumn("summary_calculations", $new_fields);
    }

    public function down()
    {
        $this->forge->dropColumn("summary_calculations", "opened_debit_amount");
        $this->forge->dropColumn("summary_calculations", "opened_credit_amount");
    }
}
