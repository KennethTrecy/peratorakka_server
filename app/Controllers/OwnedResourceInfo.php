<?php

namespace App\Controllers;


class OwnedResourceInfo
{
    private string $collective_name;
    private string $model_name;

    public function __construct(string $collective_name, string $model_name) {
        $this->collective_name = $collective_name;
        $this->model_name = $model_name;
    }

    public function getCollectiveName() {
        return $this->collective_name;
    }

    public function getModelName() {
        return $this->model_name;
    }
}
