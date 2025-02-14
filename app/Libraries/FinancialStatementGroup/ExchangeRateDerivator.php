<?php

namespace App\Libraries\FinancialStatementGroup;

use Brick\Math\BigRational;

class ExchangeRateDerivator
{
    /**
     * @var ExchangeRateInfo[]
     */
    private readonly array $exchange_rate_infos;

    /**
     * @var ExchangeRateInfo[]
     */
    private array $processed_exchange_rate_infos = [];

    public function __construct(array $exchange_rate_infos)
    {
        $this->exchange_rate_infos = array_merge(
            $exchange_rate_infos,
            array_map(
                function (ExchangeRateInfo $exchange_rate_info) {
                    return $exchange_rate_info->reverse();
                },
                $exchange_rate_infos
            )
        );
    }

    public function deriveExchangeRate(
        int $source_currency_id,
        int $destination_currency_id
    ): BigRational {
        $exchange_rate_key = $source_currency_id."_".$destination_currency_id;

        if (!isset($this->processed_exchange_rate_infos[$exchange_rate_key])) {
            $this->processed_exchange_rate_infos[$exchange_rate_key]
                = $this->shortenExchangeRatePath(
                    $source_currency_id,
                    $destination_currency_id
                );
        }

        return $this->processed_exchange_rate_infos[$exchange_rate_key];
    }

    public function exportExchangeRates(): array {
        $raw_exchange_rates = [];
        foreach ($this->processed_exchange_rate_infos as $exchange_rate_key => $exchange_rate) {
            [ $source_currency_id, $destination_currency_id ] = explode(",", $exchange_rate_key);
            if ($source_currency_id !== $destination_currency_id) {
                $raw_exchange_rates[$exchange_rate_key] = [
                    "source" => [
                        "currency_id" => $source_currency_id,
                        "value" => $exchange_rate->getDenominator()
                    ],
                    "destination" => [
                        "currency_id" => $destination_currency_id,
                        "value" => $exchange_rate->getNumerator()
                    ]
                ];
            }
        }

        foreach ($this->exchange_rate_infos as $exchange_rate_info) {
            $source_currency_id = $exchange_rate_info->source_currency_id;
            $destination_currency_id = $exchange_rate_info->destination_currency_id;
            $exchange_rate_key = $source_currency_id."_".$destination_currency_id;

            if (!isset($raw_exchange_rates[$exchange_rate_key])) {
                $exchange_rate = $this->shortenExchangeRatePath(
                    $source_currency_id,
                    $destination_currency_id
                );

                $raw_exchange_rates[$exchange_rate_key] = [
                    "source" => [
                        "currency_id" => $source_currency_id,
                        "value" => $exchange_rate->getDenominator()
                    ],
                    "destination" => [
                        "currency_id" => $destination_currency_id,
                        "value" => $exchange_rate->getNumerator()
                    ]
                ];
            }
        }

        return $raw_exchange_rates;
    }

    private function shortenExchangeRatePath(
        int $source_currency_id,
        int $destination_currency_id
    ): BigRational {
        if ($source_currency_id === $destination_currency_id) {
            return BigRational::of(1);
        }

        $initial_nodes = array_map(
            function ($exchange_rate_info) {
                return [ $exchange_rate_info ];
            },
            array_filter(
                $this->exchange_rate_infos,
                function (ExchangeRateInfo $exchange_rate_info) use ($source_currency_id) {
                    return $exchange_rate_info->source_currency_id === $source_currency_id;
                }
            )
        );

        $paths = array_reduce(
            array_map(
                function ($path) use ($destination_currency_id) {
                    return $this->findPaths($destination_currency_id, $path);
                },
                $initial_nodes
            ),
            function ($previous_paths, $current_path_collection) {
                return array_merge($previous_paths, $current_path_collection);
            },
            []
        );
        $light_path = $this->findLightestPath($paths, time());
        $timestamps = array_map(
            function (ExchangeRateInfo $exchange_rate_info) {
                return new \DateTime($exchange_rate_info->updated_at);
            },
            $light_path
        );
        rsort($timestamps);

        $simplified_value = array_reduce(
            $light_path,
            function ($current_value, $current_exchange_rate) {
                return $current_value->multipliedBy($current_exchange_rate->destination_value)
                    ->dividedBy($current_exchange_rate->source_value);
            },
            BigRational::of(1)
        )->simplified();

        return $simplified_value;
    }


    public function findPaths(int $destination_currency_id, array $current_path): array
    {
        $last_path_index = count($current_path) - 1;
        $last_exchange_rate = $current_path[$last_path_index];
        if ($last_exchange_rate->destination_currency_id === $destination_currency_id) {
            return [ $current_path ];
        }

        $last_found_currency_IDs = array_reduce(
            $current_path,
            function ($previous_IDs, $exchange_rate) {
                return array_merge($previous_IDs, [
                    $exchange_rate->source_currency_id,
                    $exchange_rate->destination_currency_id
                ]);
            },
            []
        );

        $found_paths = [];

        foreach ($this->exchange_rate_infos as $exchange_rate) {
            if (
                $exchange_rate->source_currency_id === $last_exchange_rate->destination_currency_id
                && !in_array($exchange_rate->destination_currency_id, $last_found_currency_IDs)
            ) {
                $new_path = array_merge($current_path, [$exchange_rate]);
                $found_paths = array_merge(
                    $found_paths,
                    $this->findPaths($destination_currency_id, $new_path)
                );
            }
        }

        return $found_paths;
    }

    private function findLightestPath(array $paths, int $current_time): array
    {
        $last_average_weight = INF;
        $last_path = [];

        foreach ($paths as $path) {
            $average_weight = $this->weighPath($path, $current_time);
            if ($average_weight < $last_average_weight) {
                $last_average_weight = $average_weight;
                $last_path = $path;
            }

            if ($average_weight === $last_average_weight) {
                $timestamps_in_last_path = array_map(
                    function ($exchange_rate) {
                        return new \DateTime($exchange_rate->updated_at);
                    },
                    $last_path
                );
                $timestamps_in_current_path = array_map(
                    function ($exchange_rate) {
                        return new \DateTime($exchange_rate->updated_at);
                    },
                    $path
                );

                rsort($timestamps_in_last_path);
                rsort($timestamps_in_current_path);

                // Get path with latest timestamp
                if ($timestamps_in_current_path[0] > $timestamps_in_last_path[0]) {
                    $last_path = $path;
                }
            }
        }

        return $last_path;
    }

    private function weighPath(array $path, int $current_time): int
    {
        $total_weight = 0;

        foreach ($path as $exchange_rate_info) {
            $last_update = new \DateTime($exchange_rate_info->updated_at);
            $edge_weight = $current_time - $last_update->getTimestamp();
            $total_weight += $edge_weight;
        }

        return $total_weight / count($path);
    }
}
