<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;
use Config\Database;

class CreateModifierAtomActivitiesTable extends Migration
{
    public function up()
    {
        $database = Database::connect();

        $this->forge->addField([
            "modifier_atom_id" => [
                "type" => "BIGINT",
                "unsigned" => true,
            ],
            "cash_flow_activity_id" => [
                "type" => "BIGINT",
                "unsigned" => true,
            ]
        ]);
        $this->forge->addUniqueKey(
            [ "modifier_atom_id", "cash_flow_activity_id" ],
            "modifier_atom_activities_modifier_atom_id_cash_flow_activity_id"
        );
        if ($database->DBDriver !== "SQLite3") {
            $this->forge->addForeignKey(
                "modifier_atom_id",
                "modifier_atoms",
                "id",
                "CASCADE",
                "CASCADE",
                "modifier_atom_activities_modifier_atom_id_foreign"
            );
            $this->forge->addForeignKey(
                "cash_flow_activity_id",
                "cash_flow_activities",
                "id",
                "CASCADE",
                "CASCADE",
                "modifier_atom_activities_cash_flow_activity_id_foreign"
            );
        }
        $this->forge->createTable("modifier_atom_activities");
    }

    public function down()
    {
        $this->forge->dropTable("modifier_atom_activities");
    }
}
