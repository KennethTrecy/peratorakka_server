<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\SecureHeaders;
use Fluent\Cors\Filters\CorsFilter;

use App\Filters\EnsureOwnership;
use App\Filters\ChainAuth;

class Filters extends BaseConfig
{
    /**
     * Configures aliases for Filter classes to
     * make reading things nicer and simpler.
     *
     * @var array<string, string>
     * @phpstan-var array<string, class-string>
     */
    public array $aliases = [
        "csrf"             => CSRF::class,
        "toolbar"          => DebugToolbar::class,
        "honeypot"         => Honeypot::class,
        "invalidchars"     => InvalidChars::class,
        "secureheaders"    => SecureHeaders::class,
        "ensure_ownership" => EnsureOwnership::class,
        "cors"             => CorsFilter::class,
        "auth_thru_chain"  => ChainAuth::class
    ];

    /**
     * List of filter aliases that are always
     * applied before and after every request.
     *
     * @var array<string, array<string, array<string, string>>>|array<string, array<string>>
     * @phpstan-var array<string, list<string>>|array<string, array<string, array<string, string>>>
     */
    public array $globals = [
        "before" => [
            // "honeypot",
            // "csrf",
            // "invalidchars",
            "auth_thru_chain" => [
                "except" => [
                    "/",
                    "login*",
                    "register",
                    "auth/a/*"
                ]
            ],
            "cors",
        ],
        "after" => [
            // "toolbar",
            "cors",
            // "honeypot",
            // "secureheaders",
        ],
    ];

    /**
     * List of filter aliases that works on a
     * particular HTTP method (GET, POST, etc.).
     *
     * Example:
     * "post" => ["foo", "bar"]
     *
     * If you use this, you should disable auto-routing because auto-routing
     * permits any HTTP method to access a controller. Accessing the controller
     * with a method you don't expect could bypass the filter.
     */
    public array $methods = [];

    /**
     * List of filter aliases that should run on any
     * before or after URI patterns.
     *
     * Example:
     * "isLoggedIn" => ["before" => ["account/*", "profiles/*"]]
     */
    public array $filters = [
        "auth-rates" => [
            "before" => [
                "login*",
                "register",
                "auth/*"
            ]
        ]
    ];
}
