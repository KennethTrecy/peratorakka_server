<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

use Config\Database;

class CreateFrozenPeriodsTable extends Migration
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
        $this->forge->addPrimaryKey("id", "pk_frozen_periods");
        if ($database->DBDriver !== "SQLite3") {
            $this->forge->addForeignKey(
                "user_id",
                "users",
                "id",
                "CASCADE",
                "CASCADE",
                "frozen_periods_user_id_foreign"
            );
        }
        $this->forge->createTable("frozen_periods");
    }

    public function down()
    {
        $this->forge->dropTable("frozen_periods");
    }
}
