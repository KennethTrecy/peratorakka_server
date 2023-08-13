<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class SimplifyModifiers extends Migration
{
    public function up()
    {
        $this->forge->dropForeignKey("modifiers", "modifiers_account_id_foreign");
        $this->forge->dropForeignKey("modifiers", "modifiers_opposite_account_id_foreign");
        $this->forge->dropColumn("modifiers", "result_side");

        $renamed_fields = [
            "account_id" => [
                "name" => "debit_account_id",
                "type" => "BIGINT",
                "unsigned" => true,
                "null" => false,
            ],
            "opposite_account_id" => [
                "name" => "credit_account_id",
                "type" => "BIGINT",
                "unsigned" => true,
                "null" => false,
            ],
        ];
        $this->forge->modifyColumn("modifiers", $renamed_fields);

        $this->forge->addForeignKey("debit_account_id", "accounts", "id", "CASCADE", "CASCADE");
        $this->forge->addForeignKey("credit_account_id", "accounts", "id", "CASCADE", "CASCADE");
        $this->forge->processIndexes("modifiers");
    }

    public function down()
    {
        $this->forge->dropForeignKey("modifiers", "modifiers_debit_account_id_foreign");
        $this->forge->dropForeignKey("modifiers", "modifiers_credit_account_id_foreign");

        $renamed_fields = [
            "debit_account_id" => [
                "name" => "account_id",
                "type" => "BIGINT",
                "unsigned" => true,
                "null" => false,
            ],
            "credit_account_id" => [
                "name" => "opposite_account_id",
                "type" => "BIGINT",
                "unsigned" => true,
                "null" => false,
            ],
        ];
        $this->forge->modifyColumn("modifiers", $renamed_fields);
        $old_fields = [
            "result_side" => [
                "type" => "INT",
                "unsigned" => true,
                "null" => false
            ]
        ];
        $this->forge->addColumn("modifiers", $old_fields);

        $this->forge->addForeignKey("account_id", "accounts", "id", "CASCADE", "CASCADE");
        $this->forge->addForeignKey("opposite_account_id", "accounts", "id", "CASCADE", "CASCADE");
        $this->forge->processIndexes("modifiers");
    }
}
