<?php

/*
 | --------------------------------------------------------------------
 | App Namespace
 | --------------------------------------------------------------------
 |
 | This defines the default Namespace that is used throughout
 | CodeIgniter to refer to the Application directory. Change
 | this constant to change the namespace that all application
 | classes should use.
 |
 | NOTE: changing this will require manually modifying the
 | existing namespaces of App\* namespaced-classes.
 */
defined("APP_NAMESPACE") || define("APP_NAMESPACE", "App");

/*
 | --------------------------------------------------------------------------
 | Composer Path
 | --------------------------------------------------------------------------
 |
 | The path that Composer"s autoload file is expected to live. By default,
 | the vendor folder is in the Root directory, but you can customize that here.
 */
defined("COMPOSER_PATH") || define("COMPOSER_PATH", ROOTPATH . "vendor/autoload.php");

/*
 |--------------------------------------------------------------------------
 | Timing Constants
 |--------------------------------------------------------------------------
 |
 | Provide simple ways to work with the myriad of PHP functions that
 | require information to be in seconds.
 */
defined("SECOND") || define("SECOND", 1);
defined("MINUTE") || define("MINUTE", 60);
defined("HOUR")   || define("HOUR", 3600);
defined("DAY")    || define("DAY", 86400);
defined("WEEK")   || define("WEEK", 604800);
defined("MONTH")  || define("MONTH", 2_592_000);
defined("YEAR")   || define("YEAR", 31_536_000);
defined("DECADE") || define("DECADE", 315_360_000);

/*
 | --------------------------------------------------------------------------
 | Exit Status Codes
 | --------------------------------------------------------------------------
 |
 | Used to indicate the conditions under which the script is exit()ing.
 | While there is no universal standard for error codes, there are some
 | broad conventions.  Three such conventions are mentioned below, for
 | those who wish to make use of them.  The CodeIgniter defaults were
 | chosen for the least overlap with these conventions, while still
 | leaving room for others to be defined in future versions and user
 | applications.
 |
 | The three main conventions used for determining exit status codes
 | are as follows:
 |
 |    Standard C/C++ Library (stdlibc):
 |       http://www.gnu.org/software/libc/manual/html_node/Exit-Status.html
 |       (This link also contains other GNU-specific conventions)
 |    BSD sysexits.h:
 |       http://www.gsp.com/cgi-bin/man.cgi?section=3&topic=sysexits
 |    Bash scripting:
 |       http://tldp.org/LDP/abs/html/exitcodes.html
 |
 */
defined("EXIT_SUCCESS")        || define("EXIT_SUCCESS", 0);        // no errors
defined("EXIT_ERROR")          || define("EXIT_ERROR", 1);          // generic error
defined("EXIT_CONFIG")         || define("EXIT_CONFIG", 3);         // configuration error
defined("EXIT_UNKNOWN_FILE")   || define("EXIT_UNKNOWN_FILE", 4);   // file not found
defined("EXIT_UNKNOWN_CLASS")  || define("EXIT_UNKNOWN_CLASS", 5);  // unknown class
defined("EXIT_UNKNOWN_METHOD") || define("EXIT_UNKNOWN_METHOD", 6); // unknown class member
defined("EXIT_USER_INPUT")     || define("EXIT_USER_INPUT", 7);     // invalid user input
defined("EXIT_DATABASE")       || define("EXIT_DATABASE", 8);       // database error
defined("EXIT__AUTO_MIN")      || define("EXIT__AUTO_MIN", 9);      // lowest automatically-assigned error code
defined("EXIT__AUTO_MAX")      || define("EXIT__AUTO_MAX", 125);    // highest automatically-assigned error code

/**
 * @deprecated Use \CodeIgniter\Events\Events::PRIORITY_LOW instead.
 */
define("EVENT_PRIORITY_LOW", 200);

/**
 * @deprecated Use \CodeIgniter\Events\Events::PRIORITY_NORMAL instead.
 */
define("EVENT_PRIORITY_NORMAL", 100);

/**
 * @deprecated Use \CodeIgniter\Events\Events::PRIORITY_HIGH instead.
 */
define("EVENT_PRIORITY_HIGH", 10);

define("DATE_TIME_STRING_FORMAT", "Y-m-d H:i:s");

/*
 | --------------------------------------------------------------------------
 | Ownership Search Modes
 | --------------------------------------------------------------------------
 |
 | Influences how to search for resource(s) to be checked for ownership.
 | - SEARCH_NORMALLY. Checks for resource(s) owned by the user and present.
 | - SEARCH_WITH_DELETED. Checks for resource(s) owned by the user, regardless whether it is
 |   present or has been soft deleted.
 | - SEARCH_ONLY_DELETED. Checks for resource(s) owned by the user and has been soft deleted.
 */
define("SEARCH_NORMALLY", "NORMAL");
define("SEARCH_WITH_DELETED", "WITH_DELETED");
define("SEARCH_ONLY_DELETED", "ONLY_DELETED");

/*
 | --------------------------------------------------------------------------
 | Account Kinds
 | --------------------------------------------------------------------------
 |
 | There are different account kinds that the system can handle.
 | - UNKNOWN_ACCOUNT_KIND. Account that may represent other kinds not supported by the system at
 |   the current version. This case may happen when the system downgraded.
 | - ASSET_ACCOUNT_KIND. Account kind that may represent asset accounts.
 | - LIABILITY_ACCOUNT_KIND. Account kind that may represent liability accounts.
 | - EQUITY_ACCOUNT_KIND. Account kind that may represent equity accounts.
 | - EXPENSE_ACCOUNT_KIND. Account kind that may represent expense accounts.
 | - INCOME_ACCOUNT_KIND. Account kind that may represent income accounts.
 |
 | When the user creates an account, certain kinds can be accepted by the server.
 | When the server finds an account kind not existing in the current version,
 | it will be labeled as unknown.
 */
define("UNKNOWN_ACCOUNT_KIND", "unknown");
define("ASSET_ACCOUNT_KIND", "asset");
define("LIABILITY_ACCOUNT_KIND", "liability");
define("EQUITY_ACCOUNT_KIND", "equity");
define("EXPENSE_ACCOUNT_KIND", "expense");
define("INCOME_ACCOUNT_KIND", "income");

define("ACCEPTABLE_ACCOUNT_KINDS", [
    ASSET_ACCOUNT_KIND,
    LIABILITY_ACCOUNT_KIND,
    EQUITY_ACCOUNT_KIND,
    EXPENSE_ACCOUNT_KIND,
    INCOME_ACCOUNT_KIND,
]);

define("ACCOUNT_KINDS", [
    UNKNOWN_ACCOUNT_KIND,
    ...ACCEPTABLE_ACCOUNT_KINDS
]);

/*
 | --------------------------------------------------------------------------
 | Modifier Actions
 | --------------------------------------------------------------------------
 |
 | There are different modifier actions that the system can handle.
 | - UNKNOWN_MODIFIER_ACTION. A modifier with this action is not supported by the system at
 |   the current version. This case may happen when the system downgraded.
 | - RECORD_MODIFIER_ACTION. A modifier with this action can create normal journal entries.
 | - CLOSE_MODIFIER_ACTION. A modifier with this action can create closing journal entries.
 | - EXCHANGE_MODIFIER_ACTION. A modifier with this action can create exchange journal entries.
 */
define("UNKNOWN_MODIFIER_ACTION", "unknown");
define("RECORD_MODIFIER_ACTION", "record");
define("CLOSE_MODIFIER_ACTION", "close");
define("EXCHANGE_MODIFIER_ACTION", "exchange");

define("ACCEPTABLE_MODIFIER_ACTIONS", [
    RECORD_MODIFIER_ACTION,
    CLOSE_MODIFIER_ACTION,
    EXCHANGE_MODIFIER_ACTION,
]);

define("MODIFIER_ACTIONS", [
    UNKNOWN_MODIFIER_ACTION,
    ...ACCEPTABLE_MODIFIER_ACTIONS
]);

/*
 | --------------------------------------------------------------------------
 | Modifier Kinds
 | --------------------------------------------------------------------------
 |
 | There are different modifier kinds that the system can handle.
 | - UNKNOWN_MODIFIER_KIND. A modifier with this kind is not supported by the system at
 |   the current version. This case may happen when the system downgraded.
 | - REACTIVE_MODIFIER_KIND. A modifier with this kind can be invoked through events.
 | - DEPENDENT_MODIFIER_KIND. A modifier with this kind can be invoked if the parent modifier was
 |   invoked.
 | - MANUAL_MODIFIER_KIND. A modifier with this kind can be invoked through manual input by the
 |   user.
 */
define("UNKNOWN_MODIFIER_KIND", "unknown");
define("REACTIVE_MODIFIER_KIND", "reactive");
define("DEPENDENT_MODIFIER_KIND", "dependent");
define("MANUAL_MODIFIER_KIND", "manual");

define("ACCEPTABLE_MODIFIER_KINDS", [
    MANUAL_MODIFIER_KIND,
    // REACTIVE_MODIFIER_KIND,
    // DEPENDENT_MODIFIER_KIND
]);

define("MODIFIER_KINDS", [
    UNKNOWN_MODIFIER_KIND,
    ...ACCEPTABLE_MODIFIER_KINDS
]);

/*
 | --------------------------------------------------------------------------
 | Token Expiration Types
 | --------------------------------------------------------------------------
 |
 | There are different token expiration types that the system can handle.
 | - MAINTENANCE_TOKEN_EXPIRATION_TYPE. Default behaviour of the framework.
 */
define("MAINTENANCE_TOKEN_EXPIRATION_TYPE", "maintenance");
define("SUPPORTED_TOKEN_EXPIRATION_TYPES", [
    MAINTENANCE_TOKEN_EXPIRATION_TYPE
]);
