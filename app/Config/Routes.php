<?php

namespace Config;

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


use App\Controllers\CurrencyController;
use App\Models\CurrencyModel;

$routes->delete("/api/v1/currencies/force/(:num)", [ CurrencyController::class, "forceDelete" ], [
    "filter" => "ensure_ownership:".implode(",", [
        CurrencyModel::class,
        SEARCH_WITH_DELETED
    ])
]);
$routes->get("/api/v1/currencies/(:num)", [ CurrencyController::class, "show" ], [
    "filter" => "ensure_ownership:".implode(",", [
        CurrencyModel::class,
        SEARCH_WITH_DELETED
    ])
]);
$routes->put("/api/v1/currencies/(:num)", [ CurrencyController::class, "update" ], [
    "filter" => "ensure_ownership:".implode(",", [
        CurrencyModel::class,
        SEARCH_NORMALLY
    ])
]);
$routes->delete("/api/v1/currencies/(:num)", [ CurrencyController::class, "delete" ], [
    "filter" => "ensure_ownership:".implode(",", [
        CurrencyModel::class,
        SEARCH_NORMALLY
    ])
]);
$routes->patch("/api/v1/currencies/(:num)", [ CurrencyController::class, "restore" ], [
    "filter" => "ensure_ownership:".implode(",", [
        CurrencyModel::class,
        SEARCH_ONLY_DELETED
    ])
]);
$routes->get("/api/v1/currencies", [ CurrencyController::class, "index" ]);
$routes->post("/api/v1/currencies", [ CurrencyController::class, "create" ]);

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
