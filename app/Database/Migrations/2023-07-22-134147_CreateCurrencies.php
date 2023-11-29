<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

use Config\Database;

class CreateCurrencies extends Migration
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
            "code" => [
                "type" => "VARCHAR",
                "constraint" => "255",
            ],
            "name" => [
                "type" => "VARCHAR",
                "constraint" => "255",
            ]
        ]);
        $this->forge->addPrimaryKey("id", "pk_currencies");
        $this->forge->addUniqueKey("code", "currencies_code_key");
        $this->forge->addUniqueKey("name", "currencies_name_key");
        if ($database->DBDriver !== "SQLite3") {
            $this->forge->addForeignKey(
                "user_id",
                "users",
                "id",
                "CASCADE",
                "CASCADE",
                "currencies_user_id_foreign"
            );
        }
        $this->forge->createTable("currencies");
    }

    public function down()
    {
        $this->forge->dropTable("currencies");
    }
}
