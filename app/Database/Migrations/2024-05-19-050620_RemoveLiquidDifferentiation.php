<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RemoveLiquidDifferentiation extends Migration
{
    public function up()
    {
        $this->forge->dropColumn("cash_flow_categories", "kind");
    }

    public function down()
    {
        $old_fields = [
            "kind" => [
                "type" => "INT",
                "unsigned" => true,
                "null" => true
            ]
        ];
        $this->forge->addColumn("cash_flow_categories", $old_fields);
    }
}
