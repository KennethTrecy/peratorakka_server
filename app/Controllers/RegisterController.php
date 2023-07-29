<?php

namespace App\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use \CodeIgniter\Shield\Controllers\RegisterController as BaseRegisterController;


class RegisterController extends BaseRegisterController {
    public function customRegisterAction(): ResponseInterface {
        $session = session();
        $original_response = parent::registerAction();

        $new_response = response();

        $raw_errors = $session->getFlashdata("errors");
        if (is_null($raw_errors)) {
            $message = $session->getFlashdata("message");
            if (is_null($message)) {
                $new_response = $new_response->setJSON([
                    "meta" => [
                        "message" => $message
                    ]
                ]);
            }

            $new_response = $new_response->setStatusCode(200);
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
