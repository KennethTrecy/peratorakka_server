<?php

namespace App\Libraries;

use App\Contracts\NumericalToolSource;
use App\Exceptions\NumericalToolConfigurationException;
use App\Libraries\Context;
use App\Libraries\Context\ContextKeys;
use App\Libraries\NumericalToolConfiguration\CollectionSource;
use App\Libraries\NumericalToolConfiguration\FormulaSource;

class NumericalToolConfiguration
{
    public static function parseConfiguration(array $configuration): NumericalToolConfiguration
    {
        if (
            isset($configuration["sources"])
            && is_array($configuration["sources"])
            && count($configuration["sources"])
        ) {
            $sources = $configuration["sources"];
            $parsed_sources = [];
            foreach ($sources as $i => $source) {
                if (isset($source["type"])) {
                    switch ($source["type"]) {
                        case CollectionSource::sourceType(): {
                            $parsed_source = CollectionSource::parseConfiguration($source);

                            if (is_null($parsed_source)) {
                                throw new NumericalToolConfigurationException(
                                    "Incorrect configuration for source #".($i+1)
                                    ." which is a ". $source["type"] . " source."
                                );
                            }

                            array_push($parsed_sources, $parsed_source);

                            break;
                        }

                        case FormulaSource::sourceType(): {
                            $parsed_source = FormulaSource::parseConfiguration($source);

                            if (is_null($parsed_source)) {
                                throw new NumericalToolConfigurationException(
                                    "Incorrect configuration for source #".($i+1)
                                    ." which is a ". $source["type"] . " source."
                                );
                            }

                            array_push($parsed_sources, $parsed_source);

                            break;
                        }

                        default:
                            throw new NumericalToolConfigurationException(
                                "Unknown source type: ".$source["type"] .
                                " for source #".($i+1)
                            );
                    }
                } else {
                    throw new NumericalToolConfigurationException(
                        "Missing type for source #".($i+1)
                    );
                }

                $output_format_code = $parsed_sources[$i]->outputFormatCode();

                if ($parsed_sources[0]->outputFormatCode() !== $output_format_code) {
                    throw new NumericalToolConfigurationException(
                        "Source #".($i+1)+" has different output format."
                        ." Every source must have same output format."
                    );
                }
            }

            return new NumericalToolConfiguration($parsed_sources);
        } else {
            throw new NumericalToolConfigurationException("Missing sources");
        }
    }

    public readonly array $sources;

    private function __construct(array $sources)
    {
        $this->sources = $sources;
    }

    public function calculate(Context $context): array
    {
        $collection_cache = $context->getVariable(ContextKeys::COLLECTION_CACHE);

        $collection_IDs = [];
        foreach ($this->sources as $source) {
            if ($source instanceof CollectionSource) {
                array_push($collection_IDs, $source->collection_id);
            }
        }

        $collection_cache->loadCollections($collection_IDs);

        $results = [];
        foreach ($this->sources as $source) {
            $results = array_merge($results, $source->calculate($context));
        }
        return $results;
    }

    public function __toString(): string
    {
        return json_encode([
            "sources" => array_map(
                function (NumericalToolSource $source) {
                    return $source->toArray();
                },
                $this->sources
            )
        ]);
    }
}
