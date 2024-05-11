<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RenameSummaryCalculationsColumns extends Migration
{
    public function up()
    {
        $renamed_fields = [
            "adjusted_debit_amount" => [
                "name" => "closed_debit_amount",
                "type" => "TEXT",
                "null" => false
            ],
            "adjusted_credit_amount" => [
                "name" => "closed_credit_amount",
                "type" => "TEXT",
                "null" => false
            ]
        ];
        $this->forge->modifyColumn("summary_calculations", $renamed_fields);
    }

    public function down()
    {
        $renamed_fields = [
            "closed_debit_amount" => [
                "name" => "adjusted_debit_amount",
                "type" => "TEXT",
                "null" => false
            ],
            "closed_credit_amount" => [
                "name" => "adjusted_credit_amount",
                "type" => "TEXT",
                "null" => false
            ]
        ];
        $this->forge->modifyColumn("summary_calculations", $renamed_fields);
    }
}
