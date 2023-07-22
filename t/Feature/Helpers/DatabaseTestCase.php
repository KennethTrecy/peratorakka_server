<?php

namespace Tests\Helpers;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

class DatabaseTestCase extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate     = true;
    protected $migrateOnce = false;
    protected $refresh     = true;
    protected $namespace   = null;

    protected $seedOnce = false;
    protected $seed     = "";
    protected $basePath = "app/Database/Seeds";
}
