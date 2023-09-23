<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

use Config\Database;

class RenewCurrencyUniqueness extends Migration
{
    public function up()
    {
        $database = Database::connect();

        if ($database->DBDriver === "SQLite3") return;

        $this->forge->dropKey("currencies", "currencies_code_key", false);
        $this->forge->dropKey("currencies", "currencies_name_key", false);
        $this->forge->addUniqueKey([ "user_id", "code", "name" ]);
        $this->forge->processIndexes("currencies");
    }

    public function down()
    {
        $database = Database::connect();

        if ($database->DBDriver === "SQLite3") return;

        $this->forge->dropKey("currencies", "currencies_user_id_code_name", false);
        $this->forge->addUniqueKey("code", "currencies_code_key");
        $this->forge->addUniqueKey("name", "currencies_name_key");
        $this->forge->processIndexes("currencies");
    }
}
