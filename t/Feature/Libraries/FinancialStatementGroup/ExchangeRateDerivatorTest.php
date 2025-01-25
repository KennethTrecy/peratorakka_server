<?php

namespace Tests\Feature\Libraries\FinancialStatementGroup;

use App\Libraries\FinancialStatementGroup\ExchangeRateDerivator;
use App\Libraries\FinancialStatementGroup\ExchangeRateInfo;
use Brick\Math\BigRational;
use CodeIgniter\I18n\Time;
use PHPUnit\Framework\TestCase;

class ExchangeRateDerivatorTest extends TestCase
{
    public function testSimpleDerivation()
    {
        $exchange_rate_infos = [
            new ExchangeRateInfo(
                1,
                BigRational::of(1),
                2,
                BigRational::of(2),
                new Time("2022-01-01 00:00:00")
            )
        ];
        $derivator = new ExchangeRateDerivator($exchange_rate_infos);

        $this->assertEquals(
            BigRational::of("2/1"),
            $derivator->deriveExchangeRate(1, 2)
        );
    }

    public function testSimpleReverseDerivation()
    {
        $exchange_rate_infos = [
            new ExchangeRateInfo(
                1,
                BigRational::of(1),
                2,
                BigRational::of(2),
                new Time("2022-01-01 00:00:00")
            )
        ];
        $derivator = new ExchangeRateDerivator($exchange_rate_infos);

        $this->assertEquals(
            BigRational::of("1/2"),
            $derivator->deriveExchangeRate(2, 1)
        );
    }

    public function testIdentityDerivation()
    {
        $exchange_rate_infos = [
            new ExchangeRateInfo(
                1,
                BigRational::of(1),
                2,
                BigRational::of(2),
                new Time("2022-01-01 00:00:00")
            )
        ];
        $derivator = new ExchangeRateDerivator($exchange_rate_infos);

        $this->assertEquals(
            BigRational::of("1/1"),
            $derivator->deriveExchangeRate(1, 1)
        );
    }

    public function testIndirectDerivation()
    {
        $exchange_rate_infos = [
            new ExchangeRateInfo(
                1,
                BigRational::of(1),
                2,
                BigRational::of(2),
                new Time("2022-01-01 00:00:00")
            ),
            new ExchangeRateInfo(
                2,
                BigRational::of(1),
                3,
                BigRational::of(2),
                new Time("2022-01-01 00:00:00")
            )
        ];
        $derivator = new ExchangeRateDerivator($exchange_rate_infos);

        $this->assertEquals(
            BigRational::of("4/1"),
            $derivator->deriveExchangeRate(1, 3)
        );
    }

    public function testLatestIndirectDerivation()
    {
        $exchange_rate_infos = [
            new ExchangeRateInfo(
                1,
                BigRational::of(1),
                2,
                BigRational::of(2),
                new Time("2022-01-01 00:00:00")
            ),
            new ExchangeRateInfo(
                2,
                BigRational::of(1),
                3,
                BigRational::of(2),
                new Time("2022-01-01 00:00:00")
            ),
            new ExchangeRateInfo(
                3,
                BigRational::of(1),
                4,
                BigRational::of(2),
                new Time("2022-01-01 00:00:00")
            ),
            new ExchangeRateInfo(
                3,
                BigRational::of(3),
                2,
                BigRational::of(1),
                new Time("2023-01-01 00:00:00")
            )
        ];
        $derivator = new ExchangeRateDerivator($exchange_rate_infos);

        $this->assertEquals(
            BigRational::of("12/1"),
            $derivator->deriveExchangeRate(1, 4)
        );
    }

    public function testLatestIndirectOverDirectDerivation()
    {
        $exchange_rate_infos = [
            new ExchangeRateInfo(
                1,
                BigRational::of(1),
                2,
                BigRational::of(2),
                new Time("2022-01-01 00:00:00")
            ),
            new ExchangeRateInfo(
                2,
                BigRational::of(1),
                3,
                BigRational::of(2),
                new Time("2022-01-01 00:00:00")
            ),
            new ExchangeRateInfo(
                3,
                BigRational::of(1),
                4,
                BigRational::of(2),
                new Time("2022-01-01 00:00:00")
            ),
            new ExchangeRateInfo(
                3,
                BigRational::of(3),
                2,
                BigRational::of(1),
                new Time("2023-01-01 00:00:00")
            ),
            new ExchangeRateInfo(
                1,
                BigRational::of(1),
                4,
                BigRational::of(5),
                new Time("2019-01-01 00:00:00")
            )
        ];
        $derivator = new ExchangeRateDerivator($exchange_rate_infos);

        $this->assertEquals(
            BigRational::of("12/1"),
            $derivator->deriveExchangeRate(1, 4)
        );
    }
}
