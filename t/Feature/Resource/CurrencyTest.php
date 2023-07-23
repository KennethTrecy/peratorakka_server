<?php

namespace Tests\Feature\Authentication;

use CodeIgniter\Test\Fabricator;

use Tests\Feature\Helper\AuthenticatedHTTPTestCase;
use App\Models\CurrencyModel;

class CurrencyTest extends AuthenticatedHTTPTestCase
{
    public function testDefaultIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $provider = model(setting("Auth.userProvider"));
        $this->seeInDatabase("users", [
            "id" => $authenticated_info->getUser()->id
        ]);

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currencies = $currency_fabricator->create(10);

        $result = $authenticated_info->getRequest()->withHeaders([
            "Accept" => "application/json"
        ])->get("/api/v1/currencies");

        $result->assertOk();
        $result->assertJSONExact([
            "currencies" => json_decode(json_encode($currencies))
        ]);
    }
}
