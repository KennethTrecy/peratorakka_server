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

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currencies = $currency_fabricator->create(10);

        $result = $authenticated_info->getRequest()->get("/api/v1/currencies");

        $result->assertOk();
        $result->assertJSONExact([
            "currencies" => json_decode(json_encode($currencies))
        ]);
    }

    public function testDefaultShow()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currency = $currency_fabricator->create();
        $currency_id = $currency["id"];

        $result = $authenticated_info->getRequest()->get("/api/v1/currencies/$currency_id");

        $result->assertOk();
        $result->assertJSONExact([
            "currency" => json_decode(json_encode($currency))
        ]);
    }
}
