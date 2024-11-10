<?php

namespace App\Contracts;

use App\Libraries\Context;

/**
 * Representation of a numerical tool.
 *
 * All data points would be presented according to the numerical tool.
 */
interface NumericalToolSource
{
    /**
     * Identifies the name of the source tool.
     *
     * @return string
     */
    public static function sourceType(): string;

    /**
     * Converts an array into an instance.
     *
     * @param array $array
     * @return Self|null
     */
    public static function parseConfiguration(array $array): ?Self;

    /**
     * Makes the output format code to determine if all sources have same output format.
     *
     * @return string
     */
    public function outputFormatCode(): string;

    /**
     * Returns the data points to be shown.
     *
     * @return array
     */
    public function calculate(Context $context): array;

    /**
     * Serializes the object into an array.
     *
     * @return array
     */
    public function toArray(): array;
}
