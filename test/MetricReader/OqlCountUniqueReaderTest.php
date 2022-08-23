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

use Combodo\iTop\Monitoring\MetricReader\OqlCountUniqueReader;
use Combodo\iTop\Monitoring\MetricReader\OqlGroupByReader;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @backupGlobals disabled
 */
class OqlCountUniqueReaderTest extends ItopDataTestCase
{
    protected function setUp(): void
    {
//        @include_once '/home/nono/PhpstormProjects/iTop/approot.inc.php';
        parent::setUp();

        @require_once(APPROOT . 'env-production/combodo-monitoring/vendor/autoload.php');
        require_once(APPROOT . 'core/config.class.inc.php');

    }

    /**
     * @dataProvider GetMetricsProvider
     */
    public function testMakeSql(array $aMetric, $aExpectedResult)
    {
        $OqlGroupByReader = new OqlCountUniqueReader('foo', $aMetric);

        $reflector = new \ReflectionObject($OqlGroupByReader);
        $method = $reflector->getMethod('MakeSql');
        $method->setAccessible(true);
        /** @var \DBObjectSet $oSet */
        $sSQL = $method->invoke($OqlGroupByReader);

        $this->assertEquals($aExpectedResult, $sSQL);
    }

    public function GetMetricsProvider(): array
    {
        return [

            'group by' => [
                'aConf' => [
                    'description' => 'ordered users',
                    'oql_count_unique' => [
                        'select' => 'SELECT User',
                        'groupby' => ['first_name' => 'first_nameAlias'],
                    ],
                ],
                'sExpectedSql' => "SELECT `first_nameAlias` AS `first_name`, COUNT(DISTINCT COALESCE(`User`.`id`, 0)) AS _itop_count_ FROM `priv_user` AS `User` WHERE 1 GROUP BY `first_nameAlias`  ",
            ],

        ];
    }
}
