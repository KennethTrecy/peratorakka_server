<?php

namespace Config;

use CodeIgniter\Router\RouteCollection;

// Create a new instance of our RouteCollection class.
$routes = Services::routes();

/*
 * --------------------------------------------------------------------
 * Router Setup
 * --------------------------------------------------------------------
 */
$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('Home');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override();
// The Auto Routing (Legacy) is very dangerous. It is easy to create vulnerable apps
// where controller filters or CSRF protection are bypassed.
// If you don't want to define all routes, please use the Auto Routing (Improved).
// Set `$autoRoutesImproved` to true in `app/Config/Feature.php` and set the following to true.
// $routes->setAutoRoute(false);

/*
 * --------------------------------------------------------------------
 * Route Definitions
 * --------------------------------------------------------------------
 */

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
    }
}

use App\Controllers\AccountController;
use App\Controllers\CurrencyController;
use App\Controllers\ModifierController;
make_owned_resource_routes($routes, CurrencyController::class);
make_owned_resource_routes($routes, AccountController::class);
make_owned_resource_routes($routes, ModifierController::class);

// We get a performance increase by specifying the default
// route since we don't have to scan directories.
$routes->get('/', 'Home::index');

service('auth')->routes($routes);

/*
 * --------------------------------------------------------------------
 * Additional Routing
 * --------------------------------------------------------------------
 *
 * There will often be times that you need additional routing and you
 * need it to be able to override any defaults in this file. Environment
 * based routes is one such time. require() additional route files here
 * to make that happen.
 *
 * You will have access to the $routes object within that file without
 * needing to reload it.
 */
if (is_file(APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php')) {
    require APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php';
}
