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

use Combodo\iTop\Monitoring\MetricReader\ConfReader;
use Combodo\iTop\Test\UnitTest\ItopTestCase;
use Config;
use ReflectionObject;

class ConfReaderTest extends ItopTestCase
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
    public function testGetGetValue(array $aMetric, $aConfigGetterResponse, $aExpectedResult, ?string $sExpectedException)
    {
        $oConfigMock = $this->createMock(Config::class);
        $oConfigMock->expects($this->any())
            ->method('GetModuleSetting')
            ->willReturn( $aConfigGetterResponse)
        ;
        $oConfigMock->expects($this->any())
            ->method('Get')
            ->willReturn( $aConfigGetterResponse)
        ;

        if (null != $sExpectedException) {
            $this->expectExceptionMessageRegExp($sExpectedException);
        }

        $oOqlConfReader = new ConfReader('foo', $aMetric);

        $reflector = new ReflectionObject($oOqlConfReader);
        $method = $reflector->getMethod('GetValue');
        $method->setAccessible(true);
        $aResult = $method->invoke($oOqlConfReader, $oConfigMock);

        $this->assertEquals($aExpectedResult, $aResult);
    }

    public function GetMetricsProvider()
    {
        return [
            'conf must be an array' => [
                'aMetric' => ['conf' => 'Not an array wich is forbidden'],
                'aConfigGetterResponse' => ['not even read'],
                'aExpectedResult' => null,
                'sExpectedException' => '/Metric foo is not configured with a proper array/',
            ],
            'MySettings nominal' => [
                'aMetric' => ['conf' => ['MySettings', 'foo']],
                'aConfigGetterResponse' => 'bar',
                'aExpectedResult' => 'bar',
                'sExpectedException' => null,
            ],
            'MySettings with depth 1' => [
                'aMetric' => ['conf' => ['MySettings', 'foo', 'bar']],
                'aConfigGetterResponse' =>  ['bar' => 'baz'],
                'aExpectedResult' => 'baz',
                'sExpectedException' => null,
            ],
            'MySettings with depth 3' => [
                'aMetric' => ['conf' => ['MySettings', 'foo', 'bar', 'baz', 3]],
                'aConfigGetterResponse' =>  ['bar' => ['baz' => [3 => 42]]],
                'aExpectedResult' => 42,
                'sExpectedException' => null,
            ],
            'MySettings with invalid path' => [
                'aMetric' => ['conf' => ['MySettings', 'foo', 'bar', 'baz', 3]],
                'aConfigGetterResponse' =>  ['bar' => ['baz' => ['no key "3"']]],
                'aExpectedResult' => null,
                'sExpectedException' => '/Metric foo was not found in configuration found./',
            ],
            'MySettings no matching conf' => [
                'aMetric' => ['conf' => ['MySettings', 'foo']],
                'aConfigGetterResponse' => null,
                'aExpectedResult' => null,
                'sExpectedException' => '/Metric foo was not found in configuration found./',
            ],

            'MyModuleSettings nominal' => [
                'aMetric' => ['conf' => ['MyModuleSettings', 'module-name', 'foo']],
                'aConfigGetterResponse' => ['foo' => 'bar'],
                'aExpectedResult' => ['foo' => 'bar'],
                'sExpectedException' => null,
            ],
            'MyModuleSettings with depth' => [
                'aMetric' => ['conf' => ['MyModuleSettings', 'module-name', 'foo', 'bar']],
                'aConfigGetterResponse' => ['bar' => 'baz'],
                'aExpectedResult' => 'baz',
                'sExpectedException' => null,
            ],
            'MyModuleSettings no matching conf' => [
                'aMetric' => ['conf' => ['MyModuleSettings', 'module-name', 'foo']],
                'aConfigGetterResponse' => null,
                'aExpectedResult' => null,
                'sExpectedException' => '/Metric foo was not found in configuration found./',
            ],

            'MyModuleSettings backup weekdays' => [
                'aMetric' => ['conf' => ['MyModuleSettings', 'module-name', 'foo']],
                'aConfigGetterResponse' => 'monday, tuesday, wednesday, thursday, friday',
                'aExpectedResult' => 'monday, tuesday, wednesday, thursday, friday',
                'sExpectedException' => null,
            ],

        ];
    }
}