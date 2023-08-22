<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCurrencies extends Migration
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
            "code" => [
                "type" => "VARCHAR",
                "constraint" => "255",
                "unique" => true,
            ],
            "name" => [
                "type" => "VARCHAR",
                "constraint" => "255",
                "unique" => true,
            ]
        ]);
        $this->forge->addPrimaryKey("id");
        $this->forge->addForeignKey("user_id", "users", "id", "CASCADE", "CASCADE");
        $this->forge->createTable("currencies");
    }

    public function down()
    {
        $this->forge->dropTable("currencies");
    }
}
