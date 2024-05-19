<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\Exceptions\DatabaseException;

use Config\Database;

class AddDecreaseCashFlowCategoryColumn extends Migration
{
    public function up()
    {
        $database = Database::connect();

        $hasRemovedOldForeignKey = false;
        $hasAddedNewForeignKey = false;
        try {
            if ($database->DBDriver !== "SQLite3") {
                $hasRemovedOldForeignKey = true;
                $this->forge->dropKey("accounts", "accounts_cash_flow_category_id_foreign", false);
                $this->forge->processIndexes("accounts");
            }

            $new_fields = [
                "decrease_cash_flow_category_id" => [
                    "type" => "BIGINT",
                    "unsigned" => true,
                    "null" => true,
                    "default" => null
                ]
            ];
            $this->forge->addColumn("accounts", $new_fields);

            if ($database->DBDriver !== "SQLite3") {
                $this->forge->addForeignKey(
                    "increase_cash_flow_category_id",
                    "cash_flow_categories",
                    "id",
                    "CASCADE",
                    "CASCADE",
                    "accounts_increase_cash_flow_category_id_foreign"
                );
                $this->forge->addForeignKey(
                    "decrease_cash_flow_category_id",
                    "cash_flow_categories",
                    "id",
                    "CASCADE",
                    "CASCADE",
                    "accounts_decrease_cash_flow_category_id_foreign"
                );
                $this->forge->processIndexes("accounts");
                $hasAddedNewForeignKey = true;
            }
        } catch (DatabaseException $error) {
            $this->downWithForeignKey($hasRemovedOldForeignKey, $hasAddedNewForeignKey);
            throw $error;
        } catch (\TypeError $error) {
            $this->downWithForeignKey($hasRemovedOldForeignKey, $hasAddedNewForeignKey);
            throw $error;
        } catch (\ErrorException $error) {
            $this->downWithForeignKey($hasRemovedOldForeignKey, $hasAddedNewForeignKey);
            throw $error;
        }
    }

    public function down()
    {
        $this->downWithForeignKey(true, true);
    }

    private function downWithForeignKey(bool $mustAddOldForeign, bool $mustRemoveNewForeign) {
        $database = Database::connect();

        if ($mustRemoveNewForeign && $database->DBDriver !== "SQLite3") {
            $this->forge->dropKey(
                "accounts",
                "accounts_increase_cash_flow_category_id_foreign",
                false
            );
            $this->forge->dropKey(
                "accounts",
                "accounts_decrease_cash_flow_category_id_foreign",
                false
            );
            $this->forge->processIndexes("accounts");
        }

        if ($mustAddOldForeign && $database->DBDriver !== "SQLite3") {
            $this->forge->addForeignKey(
                "increase_cash_flow_category_id",
                "cash_flow_categories",
                "id",
                "CASCADE",
                "CASCADE",
                "accounts_cash_flow_category_id_foreign"
            );
            $this->forge->processIndexes("accounts");
        }

        $this->forge->dropColumn("accounts", "decrease_cash_flow_category_id");
    }
}
