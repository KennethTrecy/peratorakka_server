<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFrozenPeriodsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            "id" => [
                "type" => "BIGINT",
                "unsigned" => true,
                "auto_increment" => true,
            ],
            "user_id" => [
                "type" => "INT",
                "unsigned" => true,
            ],
            "started_at" => [
                "type" => "DATETIME",
            ],
            "finished_at" => [
                "type" => "DATETIME",
            ]
        ]);
        $this->forge->addPrimaryKey("id");
        $this->forge->addForeignKey("user_id", "users", "id", "CASCADE", "CASCADE");
        $this->forge->createTable("frozen_periods");
    }

    public function down()
    {
        $this->forge->dropTable("frozen_periods");
    }
}
