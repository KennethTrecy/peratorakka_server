<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use Config\Database;

class RenewAccountUniqueness extends Migration
{
    public function up()
    {
        $database = Database::connect();

        if ($database->DBDriver === "SQLite3") {
            return;
        }

        $this->forge->dropKey("accounts", "accounts_name_key", false);
        $this->forge->addUniqueKey([ "currency_id", "name" ], "accounts_currency_id_name");
        $this->forge->processIndexes("accounts");
    }

    public function down()
    {
        $database = Database::connect();

        if ($database->DBDriver === "SQLite3") {
            return;
        }

        $this->forge->dropKey("accounts", "accounts_currency_id_name", false);
        $this->forge->addUniqueKey("name", "accounts_name_key");
        $this->forge->processIndexes("accounts");
    }
}
