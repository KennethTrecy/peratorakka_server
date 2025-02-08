<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;
use Config\Database;

class CreateFinancialEntryAtomsTable extends Migration
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
            "financial_entry_id" => [
                "type" => "BIGINT",
                "unsigned" => true
            ],
            "modifier_atom_id" => [
                "type" => "BIGINT",
                "unsigned" => true
            ],
            "numerical_value" => [
                "type" => "TEXT"
            ]
        ]);
        $this->forge->addPrimaryKey("id", "pk_financial_entry_atoms");
        if ($database->DBDriver !== "SQLite3") {
            $this->forge->addForeignKey(
                "financial_entry_id",
                "financial_entries_v2",
                "id",
                "CASCADE",
                "CASCADE",
                "financial_entry_atoms_financial_entry_id_foreign"
            );
            $this->forge->addForeignKey(
                "modifier_atom_id",
                "modifier_atoms",
                "id",
                "CASCADE",
                "CASCADE",
                "financial_entry_atoms_modifier_atom_id_foreign"
            );
        }
        $this->forge->createTable("financial_entry_atoms");
    }

    public function down()
    {
        $this->forge->dropTable("financial_entry_atoms");
    }
}
