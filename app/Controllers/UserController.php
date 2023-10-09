<?php

namespace App\Controllers;

use Exception;

use CodeIgniter\API\ResponseTrait;
use CodeIgniter\Shield\Controllers\RegisterController as BaseRegisterController;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Validation\Validation;

use App\Exceptions\UnauthorizedRequest;

class UserController extends BaseRegisterController
{
    use ResponseTrait;

    protected static function getIndividualName(): string {
        return "user";
    }

    public function update() {
        $current_user = auth()->user();
        $validation = static::makeIdentityValidation();

        $request_document = $this->request->getJson(true);
        $is_success = $validation->run($request_document);

        if ($is_success) {
            $users = $this->getUserProvider();

            $current_user->fill($request_document);

            try {
                $users->save($user);

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

            $current_user->fill($request_document);

            try {
                $users->save($user);

                return $this->respondNoContent();
            } catch (Exception $error) {
                throw new ServerFailure(
                    "There is an error on updating the user to the database server."
                );
            }
        }

        throw new InvalidRequest($validation);
    }

    private static function makeIdentityValidation(): Validation {
        $validation = single_service("validation");
        $individual_name = static::getIndividualName();

        $usernameRules = array_merge(
            config("AuthSession")->usernameValidationRules,
            [sprintf("is_unique[%s.username]", $this->tables["users"])]
        );
        $emailRules = array_merge(
            config("AuthSession")->emailValidationRules,
            [sprintf("is_unique[%s.secret]", $this->tables["identities"])]
        );

        $validation->setRule($individual_name, "user", [
            "required"
        ]);
        $validation->setRule("$individual_name.email", "email", $registrationEmailRules);
        $validation->setRule("$individual_name.username", "username", $registrationUsernameRules);

        return $validation;
    }

    private static function makePasswordValidation(): Validation {
        $validation = single_service("validation");
        $individual_name = static::getIndividualName();

        $oldPasswordRules = "required|" . Passwords::getMaxLenghtRule();
        $newPasswordRules = "required|" . Passwords::getMaxLenghtRule() . "|strong_password";
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
