<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class ExampleTest extends TestCase
{
    public function testName()
    {
        $expected = 81;

        $product = 9 * 9;

        $this->assertEquals($expected, $product);
    }
}
