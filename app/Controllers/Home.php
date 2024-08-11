<?php

namespace App\Controllers;

use Config\App;

class Home extends BaseController
{
    public function index()
    {
        $metadata = json_decode(file_get_contents(ROOTPATH."/public/metadata.json"), true);
        return response()->setJSON([
            "data" => [
                "csrf_token" => csrf_hash()
            ],
            "meta" => array_merge($metadata, [
                "userCountLimit" => (new App())->userCountLimit,
                "environment" => $_ENV["CI_ENVIRONMENT"]
            ])
        ]);
    }
}
