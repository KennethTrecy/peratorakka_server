<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\Exceptions\DatabaseException;

use Config\Database;

class LinkAccountsToCashFlowGroups extends Migration
{
    public function up()
    {
        $database = Database::connect();

        $hasAddedForeignKey = false;
        try {
            $new_fields = [
                "cash_flow_group_id" => [
                    "type" => "BIGINT",
                    "unsigned" => true,
                    "null" => true,
                    "default" => null
                ]
            ];
            $this->forge->addColumn("accounts", $new_fields);

            if ($database->DBDriver !== "SQLite3") {
                $this->forge->addForeignKey(
                    "cash_flow_group_id",
                    "cash_flow_groups",
                    "id",
                    "CASCADE",
                    "CASCADE",
                    "accounts_cash_flow_group_id_foreign"
                );
                $this->forge->processIndexes("accounts");
                $hasAddedForeignKey = true;
            }
        } catch (DatabaseException $error) {
            $this->downWithForeignKey($hasAddedForeignKey);
            throw $error;
        } catch (\TypeError $error) {
            $this->downWithForeignKey($hasAddedForeignKey);
            throw $error;
        } catch (\ErrorException $error) {
            $this->downWithForeignKey($hasAddedForeignKey);
            throw $error;
        }
    }

    public function down()
    {
        $this->downWithForeignKey(true);
    }

    private function downWithForeignKey(bool $mustRemoveForeign) {
        $database = Database::connect();

        if ($mustRemoveForeign && $database->DBDriver !== "SQLite3") {
            $this->forge->dropKey("accounts", "accounts_cash_flow_group_id_foreign", false);
            $this->forge->processIndexes("accounts");
        }

        $this->forge->dropColumn("accounts", "cash_flow_group_id");
    }
}
