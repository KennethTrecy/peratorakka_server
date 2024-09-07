<?php

namespace App\Controllers;

// use CodeIgniter\HTTP\IncomingRequest;
use App\Helpers\RequireCompatibleTokenExpiration;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Controllers\RegisterController as BaseRegisterController;
use Config\App;
use Config\Database;
use Config\Services;

class RegisterController extends BaseRegisterController
{
    use RequireCompatibleTokenExpiration;

    public function customRegisterAction(): ResponseInterface
    {
        helper([ "auth", "setting", "session" ]);

        $session = session();


        // Remove the following keys to prevent log in errors
        $session->remove("errors");

        // Remove previous users
        auth()->logout();
        $session->remove(setting("Auth.sessionConfig")["field"]);

        $app = new App();
        if ($app->userCountLimit > 0) {
            // Prevent new users to register if limit was already met.
            $database = Database::connect();
            $user_count = $database->table("users")->select("id")->countAll();
            if ($user_count >= $app->userCountLimit) {
                return $this->response
                    ->setStatusCode(422)
                    ->setJSON([
                        "errors" => [
                            [
                                "message" => implode(" ", [
                                    "The server has too many users already.",
                                    "Please find or create another Peratorakka server to manage your finances."
                                ])
                            ]
                        ]
                    ]);
            }
        }

        $_POST = array_merge($_POST, $this->request->getJSON(true) ?? []);
        Services::resetSingle("request");

        // Uncomment if the rebuilding request from new instance is preferred.
        // Do not forget to use necessary classes.
        // $this->request = new IncomingRequest(
        //     new App(),
        //     $this->request->getUri(),
        //     $this->request->getJSON(true),
        //     $this->request->getUserAgent()
        // );
        $this->request = service("request");

        if (!$this->hasCompatibleTokenExpirationType($this->request)) {
            return $this->response
                ->setStatusCode(422)
                ->setJSON([
                    "errors" => [
                        [
                            "message" => "The client is not compatible with the server."
                        ]
                    ]
                ]);
        }

        $original_response = parent::registerAction();

        $new_response = $this->response;

        $raw_errors = $session->getFlashdata("errors");
        if (is_null($raw_errors)) {
            $message = $session->getFlashdata("message");
            if (!is_null($message)) {
                $current_user = auth()->user();
                $token = $current_user->generateAccessToken(
                    Time::now("Asia/Manila")->toDateTimeString()
                );

                $new_response = $new_response
                    ->setStatusCode(200)
                    ->setJSON([
                        "meta" => [
                            "id" => $current_user->id,
                            "username" => $current_user->username,
                            "message" => $message,
                            "token" => [
                                "data" => $token->raw_token,
                                "expiration" => [
                                    "type" => MAINTENANCE_TOKEN_EXPIRATION_TYPE,
                                    "data" => YEAR
                                ]
                            ]
                        ]
                    ]);
            }

            $new_response = $new_response->setStatusCode(201);
        } else {
            $formalized_errors = [];

            foreach ($raw_errors as $field => $message) {
                array_push($formalized_errors, [
                    "field" => $field,
                    "message" => $message
                ]);
            }

            $new_response = $new_response
                ->setStatusCode(401)
                ->setJSON([
                    "errors" => $formalized_errors
                ]);
        }

        return $new_response;
    }
}
