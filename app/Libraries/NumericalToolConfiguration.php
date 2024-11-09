<?php

namespace App\Libraries;

use App\Exceptions\NumericalToolConfigurationException;
use App\Contracts\NumericalToolSource;
use App\Libraries\NumericalToolConfiguration\CollectionSource;
use App\Libraries\Context;
use App\Libraries\Context\ContextKeys;
use App\Libraries\MathExpression\ExpressionFactory;
use App\Libraries\MathExpression\PeratorakkaMath;
use App\Libraries\Context\TimeGroupManager;
use Xylemical\Expressions\Evaluator;
use Xylemical\Expressions\Lexer;
use Xylemical\Expressions\Parser;

class NumericalToolConfiguration
{
    public static function parseConfiguration(array $configuration): NumericalToolConfiguration
    {
        if (
            isset($configuration["sources"])
            && is_array($configuration["sources"])
            && count($configuration["sources"])
        ) {
            $context = new Context();

            $sources = $configuration["sources"];
            $parsed_sources = [];
            foreach ($sources as $i => $source) {
                if (isset($source["type"])) {
                    if ($source["type"] === CollectionSource::sourceType()) {
                        $parsed_source = CollectionSource::parseConfiguration($context, $source);

                        if (is_null($parsed_source)) {
                            throw new NumericalToolConfigurationException(
                                "Incorrect configuration for source #".($i+1)
                                ." which is a ". $source["type"] . " source."
                            );
                        }


                        array_push($parsed_sources, $parsed_source);
                    }
                } else {
                    throw new NumericalToolConfigurationException(
                        "Missing type for source #".($i+1)
                    );
                }

                $output_format_code = $parsed_sources[i]->outputFormatCode();

                if ($parsed_sources[0]->outputFormatCode() !== $output_format_code) {
                    throw new NumericalToolConfigurationException(
                        "Source #".($i+1)+" has different output format."
                        ." Every source must have same output format."
                    );
                }
            }

            return new NumericalToolConfiguration($context, $parsed_sources);
        } else {
            throw new NumericalToolConfigurationException("Missing sources");
        }
    }

    public readonly Context $context;
    public readonly array $sources;

    private function __construct(Context $context, array $sources)
    {
        $this->context = $context;
        $this->sources = $sources;
    }

    public function __serialize(): array
    {
        return [
            "sources" => $this->sources
        ];
    }
}
