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

use Combodo\iTop\Monitoring\MetricReader\OqlCountReader;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;

class OqlCountReaderTest extends ItopDataTestCase
{
    public function setUp()
    {
        //@include_once '/home/nono/PhpstormProjects/iTop/approot.inc.php';
        parent::setUp();

        @require_once(APPROOT . 'env-production/combodo-monitoring/vendor/autoload.php');
        require_once(APPROOT . 'core/config.class.inc.php');

    }

    /**
     * @dataProvider GetObjectSetProvider
     */
    public function testGetObjectSet(array $aMetric, $aExpectedResult)
    {
        $oOqlCountReader = new OqlCountReader('foo', $aMetric);

        //call private method of the tested class
        $reflector = new \ReflectionObject($oOqlCountReader);
        $method = $reflector->getMethod('GetObjectSet');
        $method->setAccessible(true);
        /** @var \DBObjectSet $oSet */
        $oSet = $method->invoke($oOqlCountReader);

        //access the private DBSearch and uses it to make the SQL query
        $reflector = new \ReflectionObject($oSet);
        $secret = $reflector->getProperty('m_oFilter');
        $secret->setAccessible(true);
        /** @var \DBSearch $m_oFilter */
        $m_oFilter =  $secret->getValue($oSet);
        $sSQL = $m_oFilter->MakeSelectQuery([], [], null, null, 0, 0, true);

        $this->assertEquals($aExpectedResult, $sSQL);
    }

    public function GetObjectSetProvider(): array
    {
        return [
            'oql_count' => [
                'aMetric' => [
                    'oql_count' => [
                        'select' => 'SELECT User',
                    ],
                    'description' => 'ordered users',
                ],
                'aExpectedResult' => "SELECT COUNT(*) AS COUNT FROM (SELECT
 DISTINCT COALESCE(`User`.`id`, 0) AS idCount0 
 FROM 
   `priv_user` AS `User`
 WHERE 1 ) AS _alderaan_ WHERE idCount0>0",
            ],

        ];
    }
}
