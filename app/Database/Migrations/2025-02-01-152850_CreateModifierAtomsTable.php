<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;
use Config\Database;

class CreateModifierAtomsTable extends Migration
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
            "modifier_id" => [
                "type" => "BIGINT",
                "unsigned" => true,
            ],
            "account_id" => [
                "type" => "BIGINT",
                "unsigned" => true,
            ],
            "kind" => [
                "type" => "INT",
                "unsigned" => true,
            ]
        ]);
        $this->forge->addPrimaryKey("id", "pk_modifier_atoms");
        $this->forge->addUniqueKey(
            [ "modifier_id", "account_id" ],
            "modifier_atoms_modifier_id_account_id"
        );
        if ($database->DBDriver !== "SQLite3") {
            $this->forge->addForeignKey(
                "modifier_id",
                "modifiers_v2",
                "id",
                "CASCADE",
                "CASCADE",
                "modifier_atoms_modifier_id_foreign"
            );
            $this->forge->addForeignKey(
                "account_id",
                "accounts_v2",
                "id",
                "CASCADE",
                "CASCADE",
                "modifier_atoms_account_id_foreign"
            );
        }
        $this->forge->createTable("modifier_atoms");
    }

    public function down()
    {
        $this->forge->dropTable("modifier_atoms");
    }
}
