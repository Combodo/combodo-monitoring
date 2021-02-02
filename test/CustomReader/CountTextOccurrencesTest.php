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

namespace Combodo\iTop\Monitoring\Test\CustomReader;


use Combodo\iTop\Monitoring\CustomReader\CountTextOccurrences;
use Combodo\iTop\Monitoring\MetricReader\ConfReader;
use Combodo\iTop\Test\UnitTest\ItopTestCase;

class CountTextOccurrencesTest extends ItopTestCase
{
    public function setUp()
    {
        //@include_once '/home/combodo/workspace/iTop/approot.inc.php';
        @include_once '/home/nono/PhpstormProjects/iTop/approot.inc.php';
        parent::setUp();

        require_once(APPROOT . 'env-production/combodo-monitoring/vendor/autoload.php');
    }

    /**
     * @dataProvider FetchCounterProvider
     */
    public function testFetchCounter($iExpectedCounter, $aMetricConf)
    {
        $oCountTextOccurrences = new CountTextOccurrences($aMetricConf);

        $reflector = new \ReflectionObject($oCountTextOccurrences);
        $method = $reflector->getMethod('FetchCounter');
        $method->setAccessible(true);
        $iCounter = $method->invoke($oCountTextOccurrences);

        $this->assertEquals($iExpectedCounter, $iCounter);
    }

    public function FetchCounterProvider()
    {
        return [
            'nominal' => [
                'iExpectedCounter' => 3,
                'aMetricConf' => [
                    'description' => 'custom class (custom)',
                    'custom' => [
                        'class' => '\Combodo\iTop\Monitoring\CustomReader\CountTextOccurrences',
                        'file' => __DIR__.'/../ressources/CustomReader/3-occurences.log',
                        'needle' => 'deadlock detected: user=',
                    ],
                ]
            ],

            'str not found' => [
                'iExpectedCounter' => 0,
                'aMetricConf' => [
                    'description' => 'custom class (custom)',
                    'custom' => [
                        'class' => '\Combodo\iTop\Monitoring\CustomReader\CountTextOccurrences',
                        'file' => __DIR__.'/../ressources/CustomReader/3-occurences.log',
                        'needle' => 'I am not found',
                    ],
                ]
            ],


            'non existent file' => [
                'iExpectedCounter' => 0,
                'aMetricConf' => [
                    'description' => 'custom class (custom)',
                    'custom' => [
                        'class' => '\Combodo\iTop\Monitoring\CustomReader\CountTextOccurrences',
                        'file' => __DIR__.'/../ressources/CustomReader/404.log',
                        'needle' => 'deadlock detected: user=',
                    ],
                ]
            ],

        ];
    }

}