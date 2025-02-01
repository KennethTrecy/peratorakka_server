<?php

namespace Tests\Feature\Resource;

use App\Exceptions\InvalidRequest;
use App\Exceptions\MissingResource;
use App\Models\PrecisionFormatModel;
use CodeIgniter\Test\Fabricator;
use Tests\Feature\Helper\AuthenticatedHTTPTestCase;
use Throwable;

class PrecisionFormatTest extends AuthenticatedHTTPTestCase
{
    public function testDefaultIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $detailss
        ] = PrecisionFormatModel::createTestResources(
            $authenticated_info->getUser()->id,
            10,
            []
        );

        $result = $authenticated_info->getRequest()->get("/api/v2/precision_formats");

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "overall_filtered_count" => 10
            ],
            "precision_formats" => json_decode(json_encode($detailss))
        ]);
    }

    public function testDefaultShow()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details
        ] = PrecisionFormatModel::createTestResource($authenticated_info->getUser()->id, []);

        $result = $authenticated_info->getRequest()->get(
            "/api/v2/precision_formats/{$details->id}"
        );

        $result->assertOk();
        $result->assertJSONExact([
            "precision_format" => json_decode(json_encode($details))
        ]);
    }

    public function testDefaultCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details
        ] = PrecisionFormatModel::makeTestResource($authenticated_info->getUser()->id, []);

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/precision_formats", [
                "precision_format" => $details->toArray()
            ]);

        $result->assertOk();
        $result->assertJSONFragment([
            "precision_format" => $details->toArray()
        ]);
    }

    public function testDefaultUpdate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details,
            $new_details
        ] = PrecisionFormatModel::createAndMakeTestResources(
            $authenticated_info->getUser()->id,
            []
        );

        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v2/precision_formats/$details->id", [
                "precision_format" => $new_details->toArray()
            ]);

        $result->assertStatus(204);
        $this->seeInDatabase("precision_formats", array_merge(
            [ "id" => $details->id ],
            $new_details->toRawArray()
        ));
    }

    public function testDefaultDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details
        ] = PrecisionFormatModel::createTestResource($authenticated_info->getUser()->id, []);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v2/precision_formats/$details->id");

        $result->assertStatus(204);
        $this->seeInDatabase("precision_formats", array_merge(
            [ "id" => $details->id ]
        ));
        $this->dontSeeInDatabase("precision_formats", [
            "id" => $details->id,
            "deleted_at" => null
        ]);
    }

    public function testDefaultRestore()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details
        ] = PrecisionFormatModel::createTestResource($authenticated_info->getUser()->id, []);
        model(PrecisionFormatModel::class)->delete($details->id);

        $result = $authenticated_info
            ->getRequest()
            ->patch("/api/v2/precision_formats/$details->id");

        $result->assertStatus(204);
        $this->seeInDatabase("precision_formats", [
            "id" => $details->id,
            "deleted_at" => null
        ]);
    }

    public function testDefaultForceDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details
        ] = PrecisionFormatModel::createTestResource($authenticated_info->getUser()->id, []);
        model(PrecisionFormatModel::class)->delete($details->id);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v2/precision_formats/$details->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "precision_formats", []);
    }

    public function testEmptyIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        $result = $authenticated_info->getRequest()->get("/api/v2/precision_formats");

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "overall_filtered_count" => 0
            ],
            "precision_formats" => []
        ]);
    }

    public function testQueriedIndex()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $detailss
        ] = PrecisionFormatModel::createTestResources(
            $authenticated_info->getUser()->id,
            10,
            []
        );

        $result = $authenticated_info->getRequest()->get("/api/v2/precision_formats", [
            "sort" => [ [ "id", "ASC" ] ],
            "page" => [
                "limit" => 5
            ]
        ]);

        $result->assertOk();
        $result->assertJSONExact([
            "@meta" => [
                "overall_filtered_count" => 10
            ],
            "precision_formats" => json_decode(json_encode(array_slice($detailss, 0, 5)))
        ]);
    }

    public function testMissingShow()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details
        ] = PrecisionFormatModel::createTestResource($authenticated_info->getUser()->id, []);
        $details->id = $details->id + 1;

        $this->expectException(MissingResource::class);
        $this->expectExceptionCode(404);
        $result = $authenticated_info->getRequest()->get("/api/v2/precision_formats/$details->id");
    }

    public function testInvalidCreate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details
        ] = PrecisionFormatModel::makeTestResource($authenticated_info->getUser()->id, [
            "overrides" => [
                "name" => "@only alphanumeric characters only"
            ]
        ]);

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->post("/api/v2/precision_formats", [
                "precision_formats" => $details->toArray()
            ]);
    }

    public function testInvalidUpdate()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();

        [
            $details,
            $new_details
        ] = PrecisionFormatModel::createAndMakeTestResources(
            $authenticated_info->getUser()->id,
            [
                "make_overrides" => [
                    "name" => "@only alphanumeric characters only"
                ]
            ]
        );

        $this->expectException(InvalidRequest::class);
        $this->expectExceptionCode(400);
        $result = $authenticated_info
            ->getRequest()
            ->withBodyFormat("json")
            ->put("/api/v2/precision_formats/$details->id", [
                "precision_formats" => $new_details->toArray()
            ]);
    }

    public function testUnownedDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        [
            $details
        ] = PrecisionFormatModel::createTestResource($another_user->id, []);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v2/precision_formats/$details->id");
            $this->assertTrue(false);
        } catch (MissingResource $error) {
            $this->seeInDatabase("precision_formats", array_merge(
                [ "id" => $details->id ]
            ));
            $this->seeInDatabase("precision_formats", [
                "id" => $details->id,
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

        [
            $details
        ] = PrecisionFormatModel::createTestResource($authenticated_info->getUser()->id, []);
        model(PrecisionFormatModel::class)->delete($details->id);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v2/precision_formats/$details->id");
        } catch (MissingResource $error) {
            $this->seeInDatabase("precision_formats", array_merge(
                [ "id" => $details->id ]
            ));
            $this->dontSeeInDatabase("precision_formats", [
                "id" => $details->id,
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

        [
            $details
        ] = PrecisionFormatModel::createTestResource($authenticated_info->getUser()->id, []);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->patch("/api/v2/precision_formats/$details->id");
        } catch (MissingResource $error) {
            $this->seeInDatabase("precision_formats", [
                "id" => $details->id,
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

        [
            $details
        ] = PrecisionFormatModel::createTestResource($authenticated_info->getUser()->id, []);

        $result = $authenticated_info
            ->getRequest()
            ->delete("/api/v2/precision_formats/$details->id/force");

        $result->assertStatus(204);
        $this->seeNumRecords(0, "precision_formats", []);
    }

    public function testDoubleForceDelete()
    {
        $authenticated_info = $this->makeAuthenticatedInfo();
        $another_user = $this->makeUser();

        [
            $details
        ] = PrecisionFormatModel::createTestResource($authenticated_info->getUser()->id, []);
        model(PrecisionFormatModel::class)->delete($details->id, true);

        try {
            $this->expectException(MissingResource::class);
            $this->expectExceptionCode(404);
            $result = $authenticated_info
                ->getRequest()
                ->delete("/api/v2/precision_formats/$details->id/force");
        } catch (MissingResource $error) {
            $this->seeNumRecords(0, "precision_formats", []);
            throw $error;
        } catch (Throwable $error) {
            $this->assertTrue(false);
        }
    }
}
