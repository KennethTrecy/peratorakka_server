<?php

namespace Tests\Feature\Authentication;

use App\Models\UserModel;
use Faker\Factory;
use Tests\Feature\Helper\HTTPTestCase;

class RegisterTest extends HTTPTestCase
{
    public function testValidDetails()
    {
        model(UserModel::class, false)->where("id !=", 0)->delete();

        $faker = Factory::create();
        $password = $faker->password();
        $user_data = [
            "@meta" => [
                "expiration_types" => SUPPORTED_TOKEN_EXPIRATION_TYPES
            ],
            "email" => $faker->email(),
            "username" => "testuser",
            "password" => $password,
            "password_confirm" => $password
        ];

        $result = $this
            ->withSession()
            ->withBodyFormat("json")
            ->post("register", $user_data);

        $result->assertOk();
        $this->seeNumRecords(1, "users", []);
        $this->seeNumRecords(2, "auth_identities", []);
        $this->seeNumRecords(0, "auth_logins", []);
        $this->seeInDatabase("users", [
            "username" => $user_data["username"]
        ]);
        $this->seeInDatabase("auth_identities", [
            "secret" => $user_data["email"]
        ]);
        $this->seeNumRecords(1, "precision_formats", []);
        $this->seeNumRecords(1, "currencies_v2", []);
        $this->seeNumRecords(2, "cash_flow_activities", []);
        $this->seeNumRecords(7, "accounts_v2", []);
        $this->seeNumRecords(9, "modifiers_v2", []);
        $this->seeNumRecords(18, "modifier_atoms", []);
        $this->seeNumRecords(5, "modifier_atom_activities", []);
        $this->seeNumRecords(12, "financial_entries_v2", []);
        $this->seeNumRecords(24, "financial_entry_atoms", []);
        $this->seeNumRecords(1, "frozen_periods", []);
        // A change here may mean the raw data of closing accounts were not removed before creation.
        $this->seeNumRecords(6, "frozen_accounts", []);
        $this->seeNumRecords(3, "real_adjusted_summary_calculations", []);
        $this->seeNumRecords(6, "real_unadjusted_summary_calculations", []);
        $this->seeNumRecords(5, "real_flow_calculations", []);
    }
}
