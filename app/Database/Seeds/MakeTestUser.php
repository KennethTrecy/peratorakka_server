<?php

namespace App\Database\Seeds;

use App\Libraries\NumericalToolConfiguration\CollectionSource;
use App\Libraries\NumericalToolConfiguration\FormulaSource;
use App\Models\AccountCollectionModel;
use App\Models\AccountModel;
use App\Models\CashFlowActivityModel;
use App\Models\CollectionModel;
use App\Models\CurrencyModel;
use App\Models\Deprecated\DeprecatedFinancialEntryModel;
use App\Models\Deprecated\DeprecatedFlowCalculationModel;
use App\Models\Deprecated\DeprecatedSummaryCalculation;
use App\Models\FormulaModel;
use App\Models\FrozenPeriodModel;
use App\Models\ModifierModel;
use App\Models\NumericalToolModel;
use App\Models\PrecisionFormatModel;
use CodeIgniter\Database\Seeder;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\Fabricator;

class MakeTestUser extends Seeder
{
    public function run()
    {
        helper([ "auth" ]);

        $users = auth()->getProvider();

        $user = new User([
            'username' => 'Test Account',
            'email'    => 'test@example.com',
            'password' => '12345678',
        ]);
        $users->save($user);
        $user->id = $users->getInsertID();
        $users->makeInitialData($user);
    }
}
