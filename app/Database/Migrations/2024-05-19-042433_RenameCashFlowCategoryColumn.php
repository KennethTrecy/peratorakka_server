<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RenameCashFlowCategoryColumn extends Migration
{
    public function up()
    {
        $renamed_fields = [
            "cash_flow_category_id" => [
                "name" => "increase_cash_flow_category_id",
                "type" => "BIGINT",
                "unsigned" => true,
                "null" => true
            ]
        ];
        $this->forge->modifyColumn("accounts", $renamed_fields);
    }

    public function down()
    {
        $renamed_fields = [
            "increase_cash_flow_category_id" => [
                "name" => "cash_flow_category_id",
                "type" => "BIGINT",
                "unsigned" => true,
                "null" => true
            ]
        ];
        $this->forge->modifyColumn("accounts", $renamed_fields);
    }
}
