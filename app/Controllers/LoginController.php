<?php

namespace App\Controllers;

// use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Controllers\LoginController as BaseLoginController;

// use Config\App;
use Config\Services;

class LoginController extends BaseLoginController {
    public function customLoginAction(): ResponseInterface {
        $session = session();
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

        $original_response = parent::loginAction();

        $new_response = $this->response;

        $raw_errors = $session->getFlashdata("errors");
        if (is_null($raw_errors)) {
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
