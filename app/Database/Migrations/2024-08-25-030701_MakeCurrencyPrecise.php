<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class MakeCurrencyPrecise extends Migration
{
    public function up()
    {
        $this->forge->addColumn("currencies", [
            "presentational_precision" => [
                "type" => "INT",
                "unsigned" => true
            ]
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn("currencies", [
            "presentational_precision"
        ]);
    }
}
