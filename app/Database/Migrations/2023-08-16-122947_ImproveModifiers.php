<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ImproveModifiers extends Migration
{
    public function up()
    {
        $new_fields = [
            "action" => [
                "type" => "INT",
                "unsigned" => true,
                "null" => false,
                "default" => array_search(RECORD_MODIFIER_ACTION, MODIFIER_ACTIONS)
            ]
        ];
        $this->forge->addColumn("modifiers", $new_fields);
    }

    public function down()
    {
        $this->forge->dropColumn("modifiers", "action");
    }
}
