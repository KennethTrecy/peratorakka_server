<?php

namespace Tests\Feature\Helper;

use App\Libraries\Context;
use App\Libraries\FinancialEntryAtomInputExaminer;
use App\Libraries\ModifierAtomInputExaminer;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use Faker\Factory;

class AuthenticatedContextualHTTPTestCase extends AuthenticatedHTTPTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Context::clear();
        FinancialEntryAtomInputExaminer::clear();
        ModifierAtomInputExaminer::clear();
    }
}
