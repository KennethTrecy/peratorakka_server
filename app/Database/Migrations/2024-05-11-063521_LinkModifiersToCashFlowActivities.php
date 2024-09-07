<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Database\Migration;
use Config\Database;

class LinkModifiersToCashFlowActivities extends Migration
{
    public function up()
    {
        $database = Database::connect();

        $hasAddedForeignKey = false;
        try {
            $new_fields = [
                "debit_cash_flow_activity_id" => [
                    "type" => "BIGINT",
                    "unsigned" => true,
                    "default" => null
                ],
                "credit_cash_flow_activity_id" => [
                    "type" => "BIGINT",
                    "unsigned" => true,
                    "default" => null
                ]
            ];
            $this->forge->addColumn("modifiers", $new_fields);

            if ($database->DBDriver !== "SQLite3") {
                $this->forge->addForeignKey(
                    "debit_cash_flow_activity_id",
                    "cash_flow_activities",
                    "id",
                    "CASCADE",
                    "CASCADE",
                    "modifiers_debit_cash_flow_activity_id_foreign"
                );
                $this->forge->addForeignKey(
                    "credit_cash_flow_activity_id",
                    "cash_flow_activities",
                    "id",
                    "CASCADE",
                    "CASCADE",
                    "modifiers_credit_cash_flow_activity_id_foreign"
                );
                $this->forge->processIndexes("modifiers");
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

    private function downWithForeignKey(bool $mustRemoveForeign)
    {
        $database = Database::connect();

        if ($mustRemoveForeign && $database->DBDriver !== "SQLite3") {
            $this->forge->dropKey(
                "modifiers",
                "modifiers_debit_cash_flow_activity_id_foreign",
                false
            );
            $this->forge->dropKey(
                "modifiers",
                "modifiers_credit_cash_flow_activity_id_foreign",
                false
            );
            $this->forge->processIndexes("modifiers");
        }

        $this->forge->dropColumn("modifiers", "debit_cash_flow_activity_id");
        $this->forge->dropColumn("modifiers", "credit_cash_flow_activity_id");
    }
}
