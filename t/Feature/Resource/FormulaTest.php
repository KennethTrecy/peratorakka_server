<?php

namespace Tests\Feature\Resource;

use App\Exceptions\InvalidRequest;
use App\Exceptions\MissingResource;

use App\Models\FormulaModel;
use App\Models\CurrencyModel;
use CodeIgniter\Test\Fabricator;
use Tests\Feature\Helper\AuthenticatedHTTPTestCase;
use Throwable;

class FormulaTest extends AuthenticatedHTTPTestCase
{
    public function testDefaultIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currency = $currency_fabricator->create();
        $formula_fabricator = new Fabricator(FormulaModel::class);
        $formula_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $formulae = $formula_fabricator->create(10);

        $result = $authenticated_info->getRequest()->get("/api/v1/formulae");

        $result->assertOk();
        $result->assertJSONExact([
            "meta" => [
                "overall_filtered_count" => 10
            ],
            "formulae" => json_decode(json_encode($formulae)),
            "currencies" => [ $currency ],
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
        $formula_fabricator = new Fabricator(FormulaModel::class);
        $formula_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $formula = $formula_fabricator->create();

        $result = $authenticated_info->getRequest()->get("/api/v1/formulae/$formula->id");

        $result->assertOk();
        $result->assertJSONExact([
            "formula" => json_decode(json_encode($formula)),
            "currencies" => json_decode(json_encode([ $currency ]))
        ]);
    }

    public function testDefaultCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currency = $currency_fabricator->create();
        $formula_fabricator = new Fabricator(FormulaModel::class);
        $formula_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $formula = $formula_fabricator->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/formulae", [
                "formula" => $formula->toArray()
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "formula" => $formula->toArray()
        ]);
    }

    public function testDefaultUpdate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currency = $currency_fabricator->create();
        $formula_fabricator = new Fabricator(FormulaModel::class);
        $formula_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $formula = $formula_fabricator->create();
        $new_details = $formula_fabricator->make();

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v1/formulae/$formula->id", [
                "formula" => $new_details->toArray()
            ]);

        $result->assertStatus(204);
        $this->seeInDatabase("formulae", array_merge(
            [ "id" => $formula->id ],
            $new_details->toRawArray()
        ));
    }

    public function testDefaultDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currency = $currency_fabricator->create();
        $formula_fabricator = new Fabricator(FormulaModel::class);
        $formula_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $formula = $formula_fabricator->create();

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/formulae/$formula->id");

        $result->assertStatus(204);
        $this->seeInDatabase("formulae", array_merge(
            [ "id" => $formula->id ]
        ));
        $this->dontSeeInDatabase("formulae", [
            "id" => $formula->id,
            "deleted_at" => null
        ]);
    }

    public function testDefaultRestore()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currency = $currency_fabricator->create();
        $formula_fabricator = new Fabricator(FormulaModel::class);
        $formula_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $formula = $formula_fabricator->create();
        model(FormulaModel::class)->delete($formula->id);

        $result = $authenticated_info
            ->getRequest()
            ->patch("/api/v1/formulae/$formula->id");

        $result->assertStatus(204);
        $this->seeInDatabase("formulae", [
            "id" => $formula->id,
            "deleted_at" => null
        ]);
    }

    public function testDefaultForceDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currency = $currency_fabricator->create();
        $formula_fabricator = new Fabricator(FormulaModel::class);
        $formula_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $formula = $formula_fabricator->create();
        model(FormulaModel::class)->delete($formula->id);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/formulae/$formula->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "formulae", []);
    }

    public function testEmptyIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $result = $authenticated_info->getRequest()->get("/api/v1/formulae");

        $result->assertOk();
        $result->assertJSONExact([
            "meta" => [
                "overall_filtered_count" => 0
            ],
            "formulae" => [],
            "currencies" => [],
        ]);
    }

    public function testQueriedIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currency = $currency_fabricator->create();
        $formula_fabricator = new Fabricator(FormulaModel::class);
        $formula_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $formulae = $formula_fabricator->create(10);

        $result = $authenticated_info->getRequest()->get("/api/v1/formulae", [
            "page" => [
                "limit" => 5
            ]
        ]);

        $result->assertOk();
        $result->assertJSONExact([
            "meta" => [
                "overall_filtered_count" => 10
            ],
            "formulae" => json_decode(json_encode(array_slice($formulae, 0, 5))),
            "currencies" => [ $currency ],
        ]);
    }

    public function testMissingShow()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currency = $currency_fabricator->create();
        $formula_fabricator = new Fabricator(FormulaModel::class);
        $formula_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $formula = $formula_fabricator->create();
        $formula->id = $formula->id + 1;

        $this->expectException(MissingResource::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info->getRequest()->get("/api/v1/formulae/$formula->id");
    }

    public function testInvalidCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currency = $currency_fabricator->create();
        $formula_fabricator = new Fabricator(FormulaModel::class);
        $formula_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "name" => "@only alphanumeric characters only"
        ]);
        $formula = $formula_fabricator->make();

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v1/formulae", [
                "formula" => $formula->toArray()
            ]);
    }

    public function testInvalidUpdate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currency = $currency_fabricator->create();
        $formula_fabricator = new Fabricator(FormulaModel::class);
        $formula_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $formula = $formula_fabricator->create();
        $formula_fabricator->setOverrides([
            "currency_id" => $currency->id,
            "name" => "@only alphanumeric characters only"
        ]);
        $new_details = $formula_fabricator->make();

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v1/formulae/$formula->id", [
                "formula" => $new_details->toArray()
            ]);
    }

    public function testUnownedDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $another_user->id
        ]);
        $currency = $currency_fabricator->create();
        $formula_fabricator = new Fabricator(FormulaModel::class);
        $formula_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $formula = $formula_fabricator->create();

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v1/formulae/$formula->id");
            $this->assertTrue(false);
        } catch (MissingResource $error) {
            $this->seeInDatabase("formulae", array_merge(
                [ "id" => $formula->id ]
            ));
            $this->seeInDatabase("formulae", [
                "id" => $formula->id,
                "deleted_at" => null
            ]);
            throw $error;
        } catch (Throwable $error) {
            $this->assertTrue(false);
        }
    }

    public function testDoubleDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $another_user->id
        ]);
        $currency = $currency_fabricator->create();
        $formula_fabricator = new Fabricator(FormulaModel::class);
        $formula_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $formula = $formula_fabricator->create();
        model(FormulaModel::class)->delete($formula->id);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v1/formulae/$formula->id");
            $this->assertTrue(false);
        } catch (MissingResource $error) {
            $this->seeInDatabase("formulae", array_merge(
                [ "id" => $formula->id ]
            ));
            $this->dontSeeInDatabase("formulae", [
                "id" => $formula->id,
                "deleted_at" => null
            ]);
            throw $error;
        } catch (Throwable $error) {
            $this->assertTrue(false);
        }
    }

    public function testDoubleRestore()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $another_user->id
        ]);
        $currency = $currency_fabricator->create();
        $formula_fabricator = new Fabricator(FormulaModel::class);
        $formula_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $formula = $formula_fabricator->create();

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->patch("/api/v1/formulae/$formula->id");
            $this->assertTrue(false);
        } catch (MissingResource $error) {
            $this->seeInDatabase("formulae", [
                "id" => $formula->id,
                "deleted_at" => null
            ]);
            throw $error;
        } catch (Throwable $error) {
            $this->assertTrue(false);
        }
    }

    public function testImmediateForceDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $authenticated_info->getUser()->id
        ]);
        $currency = $currency_fabricator->create();
        $formula_fabricator = new Fabricator(FormulaModel::class);
        $formula_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $formula = $formula_fabricator->create();

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v1/formulae/$formula->id/force");
        $result->assertStatus(204);
        $this->seeNumRecords(0, "formulae", []);
    }

    public function testDoubleForceDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        $currency_fabricator = new Fabricator(CurrencyModel::class);
        $currency_fabricator->setOverrides([
            "user_id" => $another_user->id
        ]);
        $currency = $currency_fabricator->create();
        $formula_fabricator = new Fabricator(FormulaModel::class);
        $formula_fabricator->setOverrides([
            "currency_id" => $currency->id
        ]);
        $formula = $formula_fabricator->create();
        model(FormulaModel::class)->delete($formula->id, true);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v1/formulae/$formula->id/force");
            $this->assertTrue(false);
        } catch (MissingResource $error) {
            $this->seeNumRecords(0, "formulae", []);
            throw $error;
        } catch (Throwable $error) {
            $this->assertTrue(false);
        }
    }
}
