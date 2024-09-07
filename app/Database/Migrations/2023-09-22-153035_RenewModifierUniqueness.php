<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;

class RenewModifierUniqueness extends Migration
{
    public function up()
    {
        $database = Database::connect();

        if ($database->DBDriver === "SQLite3") {
            return;
        }

        $this->forge->dropKey("modifiers", "modifiers_name_key", false);
        $this->forge->addUniqueKey(
            [
                "debit_account_id",
                "credit_account_id",
                "name"
            ],
            "modifiers_debit_account_id_credit_account_id_name"
        );
        $this->forge->processIndexes("modifiers");
    }

    public function down()
    {
        $database = Database::connect();

        if ($database->DBDriver === "SQLite3") {
            return;
        }

        $this->forge->dropKey(
            "modifiers",
            "modifiers_debit_account_id_credit_account_id_name",
            false
        );
        $this->forge->addUniqueKey("name", "modifiers_name_key");
        $this->forge->processIndexes("modifiers");
    }
}
