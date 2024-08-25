<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

use Config\Database;

class CreateAccountCollectionsTable extends Migration
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
            "collection_id" => [
                "type" => "BIGINT",
                "unsigned" => true,
            ],
            "account_id" => [
                "type" => "BIGINT",
                "unsigned" => true,
            ]
        ]);
        $this->forge->addPrimaryKey("id", "pk_account_collections");
        if ($database->DBDriver !== "SQLite3") {
            $this->forge->addForeignKey(
                "collection_id",
                "collections",
                "id",
                "CASCADE",
                "CASCADE",
                "account_collections_collection_id_foreign"
            );
            $this->forge->addForeignKey(
                "account_id",
                "accounts",
                "id",
                "CASCADE",
                "CASCADE",
                "account_collections_account_id_foreign"
            );
        }
        $this->forge->createTable("account_collections");
    }

    public function down()
    {
        $this->forge->dropTable("account_collections");
    }
}
