<?php
/*
 * Copyright (C) 2013-2021 Combodo SARL
 * This file is part of iTop.
 * iTop is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * iTop is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License
 */

namespace Combodo\iTop\Monitoring\Test\MetricReader;

use Combodo\iTop\Monitoring\MetricReader\CustomReader;
use Combodo\iTop\Monitoring\Model\MonitoringMetric;
use Combodo\iTop\Monitoring\Test\MetricReader\CustomReaders\CustomReaderImpl;
use Combodo\iTop\Test\UnitTest\ItopTestCase;

class CustomReaderTest extends ItopTestCase
{
    public function setUp()
    {
        @include_once '/home/nono/PhpstormProjects/iTop/approot.inc.php';
        parent::setUp();

        require_once(APPROOT . 'env-production/combodo-monitoring/vendor/autoload.php');
    }

    /**
     * @dataProvider GetMetricsProvider
     */
    public function testGetGetValue(array $aMetric, $aExpectedResult)
    {
        $oCustomReader = new CustomReader('foo', $aMetric);
        $aResult = $oCustomReader->GetMetrics();

        $this->assertEquals($aExpectedResult, $aResult);
    }

    public function GetMetricsProvider(): array
    {
        $this->setUp();

        return [
            'MySettings nominal' => [
                'aMetric' => [
                    'custom' => ['class' => CustomReaderImpl::class],
                    'description' => 'descriptionFromConf'
                ],
                'aExpectedResult' => [ new MonitoringMetric('foo', 'descriptionFromConf', 42, ['baz' => 'iste'])],
            ],
        ];
    }
}