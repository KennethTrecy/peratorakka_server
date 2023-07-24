<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class TimestampCurrencies extends Migration
{
    public function up()
    {
        $this->forge->addColumn("currencies", [
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
    }

    public function down()
    {
        $this->forge->dropColumn("currencies", [
            "created_at",
            "updated_at",
            "deleted_at"
        ]);
    }
}
