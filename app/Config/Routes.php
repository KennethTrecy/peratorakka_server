<?php

use CodeIgniter\Router\RouteCollection;

if (!function_exists("make_owned_resource_routes")) {
    function make_owned_resource_routes(
        RouteCollection $routes,
        string $controller,
        array $accessible_operations = [
            "forceDelete",
            "show",
            "update",
            "delete",
            "restore",
            "index",
            "create"
        ]
    ) {
        $owned_resource_info = $controller::getInfo();
        $collective_name = $owned_resource_info->getCollectiveName();
        $model_name = $owned_resource_info->getModelName();
        $possible_operations = [
            "forceDelete" => [
                "http_method" => "delete",
                "uri" => "/api/v1/$collective_name/(:num)/force",
                "search_mode_options" => [ SEARCH_WITH_DELETED, 4 ]
            ],
            "show" => [
                "http_method" => "get",
                "uri" => "/api/v1/$collective_name/(:num)",
                "search_mode_options" => [ SEARCH_WITH_DELETED ]
            ],
            "update" => [
                "http_method" => "put",
                "uri" => "/api/v1/$collective_name/(:num)",
                "search_mode_options" => [ SEARCH_NORMALLY ]
            ],
            "delete" => [
                "http_method" => "delete",
                "uri" => "/api/v1/$collective_name/(:num)",
                "search_mode_options" => [ SEARCH_NORMALLY ]
            ],
            "restore" => [
                "http_method" => "patch",
                "uri" => "/api/v1/$collective_name/(:num)",
                "search_mode_options" => [ SEARCH_ONLY_DELETED ]
            ],
            "index" => [
                "http_method" => "get",
                "uri" => "/api/v1/$collective_name"
            ],
            "create" => [
                "http_method" => "post",
                "uri" => "/api/v1/$collective_name"
            ]
        ];

        $remaining_operations = array_intersect_key(
            $possible_operations,
            array_flip($accessible_operations)
        );
        $allowed_methods = [];
        foreach ($remaining_operations as $controller_method => $route_info) {
            $HTTP_method = $route_info["http_method"];
            $URI = $route_info["uri"];
            if (isset($route_info["search_mode_options"])) {
                $routes->$HTTP_method($URI, [ $controller, $controller_method ], [
                    "filter" => "ensure_ownership:".implode(",", [
                        $model_name,
                        ...$route_info["search_mode_options"]
                    ])
                ]);
            } else {
                $routes->$HTTP_method($URI, [ $controller, $controller_method ]);
            }

            if (!isset($allowed_methods[$URI])) {
                $allowed_methods[$URI] = [];
            }

            array_push($allowed_methods[$URI], $HTTP_method);
        }

        foreach ($allowed_methods as $URI => $HTTP_methods) {
            $routes->options($URI, function () {
                $response = response();
                $response->setStatusCode(204);
                $response->setHeader(
                    "Allow:",
                    implode(", ", array_merge([ "OPTIONS" ], $HTTP_methods))
                );

                return $response;
            });
        }
    }
}

use App\Controllers\AccessTokenController;
use App\Controllers\AccountCollectionController;
use App\Controllers\AccountController;
use App\Controllers\CashFlowActivityController;
use App\Controllers\CollectionController;
use App\Controllers\CurrencyController;
use App\Controllers\FinancialEntryController;
use App\Controllers\FormulaController;
use App\Controllers\FrozenPeriodController;
use App\Controllers\ModifierController;
use App\Controllers\NumericalToolController;
use App\Controllers\UserController;

make_owned_resource_routes($routes, AccountController::class);
make_owned_resource_routes($routes, CashFlowActivityController::class);
make_owned_resource_routes($routes, CollectionController::class);
make_owned_resource_routes($routes, CurrencyController::class);
make_owned_resource_routes($routes, FinancialEntryController::class);
make_owned_resource_routes($routes, ModifierController::class);
make_owned_resource_routes($routes, FormulaController::class);
make_owned_resource_routes($routes, NumericalToolController::class);
make_owned_resource_routes($routes, FrozenPeriodController::class, [
    "forceDelete",
    "show",
    "index",
    "create"
]);
make_owned_resource_routes($routes, AccountCollectionController::class, [
    "forceDelete",
    "show",
    "create"
]);
make_owned_resource_routes($routes, AccessTokenController::class, [
    "forceDelete",
    "index"
]);
$routes->post(
    "api/v1/".FrozenPeriodController::getInfo()->getCollectiveName()."/dry_run",
    [
        FrozenPeriodController::class,
        "dry_run_create"
    ]
);
$routes->post(
    "api/v1/".FrozenPeriodController::getInfo()->getCollectiveName()."/recalculate",
    [
        FrozenPeriodController::class,
        "recalculate"
    ]
);
$routes->get(
    "api/v1/".NumericalToolController::getInfo()->getCollectiveName()."/calculate/(:num)",
    [
        NumericalToolController::class,
        "calculate"
    ],
    [
        "filter" => "ensure_ownership:".implode(",", [
            NumericalToolController::getInfo()->getModelName(),
            SEARCH_NORMALLY
        ])
    ]
);
$routes->patch(
    "api/v1/user",
    [
        UserController::class,
        "update"
    ]
);
$routes->patch(
    "api/v1/user/password",
    [
        UserController::class,
        "updatePassword"
    ]
);

$routes->get("/", "Home::index");
$routes->options("(:any)", static function () {});

service("auth")->routes($routes);
