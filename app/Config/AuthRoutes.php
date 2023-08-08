<?php

declare(strict_types=1);

namespace Config;

use CodeIgniter\Shield\Config\AuthRoutes as ShieldAuthRoutes;

use App\Controllers\RegisterController;
use App\Controllers\LoginController;

class AuthRoutes extends ShieldAuthRoutes
{
    public array $routes = [
        "register" => [
            [
                "get",
                "register",
                "RegisterController::registerView",
                "register", // Route name
            ],
            [
                "post",
                "register",
                [ RegisterController::class, "customRegisterAction" ],
            ],
        ],
        "login" => [
            [
                "get",
                "login",
                "LoginController::loginView",
                "login", // Route name
            ],
            [
                "post",
                "login",
                [ LoginController::class, "customLoginAction" ],
            ],
        ],
        "magic-link" => [
            [
                "get",
                "login/magic-link",
                "MagicLinkController::loginView",
                "magic-link",        // Route name
            ],
            [
                "post",
                "login/magic-link",
                "MagicLinkController::loginAction",
            ],
            [
                "get",
                "login/verify-magic-link",
                "MagicLinkController::verify",
                "verify-magic-link", // Route name
            ],
        ],
        "logout" => [
            [
                "get",
                "logout",
                [ LoginController::class, "customLogoutAction" ],
                "logout", // Route name
            ],
        ],
        "auth-actions" => [
            [
                "get",
                "auth/a/show",
                "ActionController::show",
                "auth-action-show", // Route name
            ],
            [
                "post",
                "auth/a/handle",
                "ActionController::handle",
                "auth-action-handle", // Route name
            ],
            [
                "post",
                "auth/a/verify",
                "ActionController::verify",
                "auth-action-verify", // Route name
            ],
        ],
    ];
}
