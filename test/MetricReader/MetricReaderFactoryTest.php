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
use Combodo\iTop\Monitoring\MetricReader\MetricReaderFactory;
use Combodo\iTop\Monitoring\MetricReader\ConfReader;
use Combodo\iTop\Monitoring\MetricReader\OqlCountReader;
use Combodo\iTop\Monitoring\MetricReader\OqlCountUniqueReader;
use Combodo\iTop\Monitoring\MetricReader\OqlGroupByReader;
use Combodo\iTop\Monitoring\MetricReader\OqlSelectReader;
use Combodo\iTop\Test\UnitTest\ItopTestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @backupGlobals disabled
 */
class MetricReaderFactoryTest extends ItopTestCase
{
    public function setUp(): void
    {
        //@include_once '/home/nono/PhpstormProjects/iTop/approot.inc.php';
        parent::setUp();

        @require_once(APPROOT . 'env-production/combodo-monitoring/vendor/autoload.php');
    }

    /**
     * @dataProvider GetReaderProvider
     */
    public function testGetReader($aMetric, $sExpectedClass, $sExpectedException)
    {
        if (! is_null($sExpectedException)) {
            $this->expectExceptionMessageRegExp($sExpectedException);
        }
        $oMetricReaderFactory = new MetricReaderFactory();
        $result = $oMetricReaderFactory->GetReader('foo', $aMetric);
        $this->assertInstanceOf($sExpectedClass, $result);
    }

    public function GetReaderProvider()
    {
        return [
            'count' => [
                'aMetric' => [
                    'oql_count' => [ ],
                ],
                'sExpectedClass' => OqlCountReader::class,
                'sExpectedException' => null,
            ],
            'select' => [
                'aMetric' => [
                    'oql_select' => [ ],
                ],
                'sExpectedClass' => OqlSelectReader::class,
                'sExpectedException' => null,
            ],
            'group by' => [
                'aMetric' => [
                    'oql_groupby' => [ ],
                ],
                'sExpectedClass' => OqlGroupByReader::class,
                'sExpectedException' => null,
            ],
            'custom' => [
                'aMetric' => [
                    'custom' => [ ],
                ],
                'sExpectedClass' => CustomReader::class,
                'sExpectedException' => null,
            ],
            'conf' => [
                'aMetric' => [
                    'conf' => [ ],
                ],
                'sExpectedClass' => ConfReader::class,
                'sExpectedException' => null,
            ],

            'oql count unique' => [
                'aMetric' => [
                    'oql_count_unique' => [ ],
                ],
                'sExpectedClass' => OqlCountUniqueReader::class,
                'sExpectedException' => null,
            ],
            'wrong parameter' => [
                'aMetric' => [
                ],
                'sExpectedClass' => null,
                'sExpectedException' => '/reader not found for metric/',
            ],
            'refuse two metrics' => [
                'aMetric' => [
                    'conf' => [ ],
                    'oql_select' => [ ],
                ],
                'sExpectedClass' => null,
                'sExpectedException' => '/only one metric at a time is authorized/',
            ],
        ];
    }
}
