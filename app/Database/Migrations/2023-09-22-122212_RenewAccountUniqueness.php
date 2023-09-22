<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class RenewAccountUniqueness extends Migration
{
    public function up()
    {
        $this->forge->dropKey("accounts", "accounts_name_key", false);
        $this->forge->addUniqueKey([ "currency_id", "name" ]);
        $this->forge->processIndexes("accounts");
    }

    public function down()
    {
        $this->forge->dropKey("accounts", "accounts_currency_id_name", false);
        $this->forge->addUniqueKey("name", "accounts_name_key");
        $this->forge->processIndexes("accounts");
    }
}
