<?php

namespace App\Contracts;

interface APIException
{
    public function serialize(): array;
}
