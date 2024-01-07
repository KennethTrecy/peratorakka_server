<?php

namespace App\Controllers;

use Exception;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Shield\Authentication\Passwords;
use CodeIgniter\Shield\Controllers\RegisterController as BaseRegisterController;
use CodeIgniter\Validation\Validation;

use App\Exceptions\InvalidRequest;

class UserController extends BaseRegisterController
{
    use ResponseTrait;

    protected static function getIndividualName(): string {
        return "user";
    }

    public function update() {
        $current_user = auth()->user();
        $table_names = config("Auth")->tables;
        $validation = static::makeIdentityValidation($current_user->id, $table_names);

        $request_document = $this->request->getJson(true);
        $is_success = $validation->run($request_document);

        if ($is_success) {
            $users = $this->getUserProvider();

            $current_user->fill($request_document["user"]);

            try {
                $users->save($current_user);

                return $this->respondNoContent();
            } catch (Exception $error) {
                throw new ServerFailure(
                    "There is an error on updating the user to the database server."
                );
            }
        }

        throw new InvalidRequest($validation);
    }

    public function updatePassword() {
        $current_user = auth()->user();
        $validation = static::makePasswordValidation();

        $request_document = $this->request->getJson(true);
        $is_success = $validation->run($request_document);

        if ($is_success) {
            $users = $this->getUserProvider();

            $current_user->fill([
                "password" => $request_document["user"]["new_password"]
            ]);

            try {
                $users->save($current_user);

                return $this->respondNoContent();
            } catch (Exception $error) {
                throw new ServerFailure(
                    "There is an error on updating the password of user to the database server."
                );
            }
        }

        throw new InvalidRequest($validation);
    }

    private static function makeIdentityValidation(
        int $current_user_id,
        array $table_names
    ): Validation {
        $validation = single_service("validation");
        $individual_name = static::getIndividualName();

        $usernameRules = array_merge(
            config("Auth")->usernameValidationRules["rules"],
            [
                sprintf(
                    "is_unique[%s.username,id,$current_user_id]",
                    $table_names["users"]
                )
            ]
        );
        $emailRules = array_merge(
            config("Auth")->emailValidationRules["rules"],
            [
                sprintf(
                    "is_unique[%s.secret,id,$current_user_id]",
                    $table_names["identities"]
                )
            ]
        );

        $validation->setRule($individual_name, "user", [
            "required"
        ]);
        $validation->setRule("$individual_name.email", "email", $emailRules);
        $validation->setRule("$individual_name.username", "username", $usernameRules);

        return $validation;
    }

    private static function makePasswordValidation(): Validation {
        $validation = single_service("validation");
        $individual_name = static::getIndividualName();

        $oldPasswordRules = "required|"
            . Passwords::getMaxLengthRule()
            . "|must_be_same_as_password_of_current_user";
        $newPasswordRules = "required|"
            . Passwords::getMaxLengthRule()
            . "|strong_password";
        $confirmNewPasswordRules = "required|matches[$individual_name.new_password]";

        $validation->setRule($individual_name, "user", [
            "required"
        ]);
        $validation->setRule("$individual_name.old_password", "old password", $oldPasswordRules);
        $validation->setRule("$individual_name.new_password", "new password", $newPasswordRules);
        $validation->setRule(
            "$individual_name.confirm_new_password",
            "confirm new password",
            $newPasswordRules
        );

        return $validation;
    }
}
