<?php

namespace App\Controllers;

use CodeIgniter\API\ResponseTrait;
// use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Controllers\LoginController as BaseLoginController;

// use Config\App;
use Config\Services;

use App\Helpers\RequireCompatibleTokenExpiration;

class LoginController extends BaseLoginController {
    use ResponseTrait;
    use RequireCompatibleTokenExpiration;

    public function customLoginAction(): ResponseInterface {
        $session = session();

        // Remove the following keys to prevent log in errors
        $session->remove("errors");

        // Remove previous users
        auth()->logout();
        $session->remove(setting("Auth.sessionConfig")["field"]);

        $_POST = array_merge($_POST, $this->request->getJSON(true));
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

        $original_response = $this->loginAction();

        $new_response = $original_response->removeHeader("Location");

        $raw_error = $session->getFlashdata("errors");
        if (is_null($raw_error)) {
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
                        "token" => [
                            "data" => $token->raw_token,
                            "expiration" => [
                                "type" => MAINTENANCE_TOKEN_EXPIRATION_TYPE,
                                "data" => YEAR
                            ]
                        ]
                    ]
                ]);
        } else {
            $formalized_errors = [
                [
                    "message" => $raw_error
                ]
            ];

            $new_response = $new_response
                ->setStatusCode(401)
                ->setJSON([
                    "errors" => $formalized_errors
                ]);
        }

        return $new_response;
    }

    public function customLogoutAction(): ResponseInterface {
        $session = session();
        $has_authorization_header = $this->request->hasHeader("Authorization");

        if ($has_authorization_header) {
            $authorization = $this->request->getHeaderLine("Authorization");
            $separator_index = strpos($authorization, " ");
            $scheme = substr($authorization, 0, $separator_index);

            if (strtolower($scheme) === "bearer") {
                $token = substr($authorization, $separator_index + 1);
                $current_user = auth()->user();
                $current_user->revokeAccessToken($token);
            } else {
                $formalized_errors = [
                    [
                        "message" => "The authentication scheme \"$scheme\" is not supported by the server."
                    ]
                ];

                return $this->response
                    ->setStatusCode(400)
                    ->setJSON([
                        "errors" => $formalized_errors
                    ]);
            }
        }

        $original_response = $this->logoutAction();
        $session->set("user.id", null);

        $new_response = $original_response->removeHeader("Location");

        $raw_error = $session->getFlashdata("error");
        if (is_null($raw_error)) {
            $new_response = $new_response
                ->setStatusCode(200)
                ->setJSON([
                    "message" => $session->getFlashdata("message")
                ]);
        } else {
            $formalized_errors = [
                [
                    "message" => $raw_error
                ]
            ];

            $new_response = $new_response
                ->setStatusCode(401)
                ->setJSON([
                    "errors" => $formalized_errors
                ]);
        }

        return $new_response;
    }
}
