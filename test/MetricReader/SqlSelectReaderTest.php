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

use Combodo\iTop\Monitoring\MetricReader\SqlSelectReader;
use Combodo\iTop\Monitoring\Model\MonitoringMetric;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;

/**
 * @group sampleDataNeeded
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @backupGlobals disabled
 */
class SqlSelectReaderTest extends ItopDataTestCase
{
    protected function setUp(): void
    {
        //require_once '/home/combodo/workspace/iTop/approot.inc.php';
        parent::setUp();

        @require_once(APPROOT . 'env-production/combodo-monitoring/vendor/autoload.php');
        require_once(APPROOT . 'core/config.class.inc.php');

    }

    /**
     * @dataProvider GetMetricsProvider
     */
    public function testGetGetValue(array $aMetric, $aExpectedMetrics)
    {
        $oOqlSelectReader = new SqlSelectReader('foo', $aMetric);
	    $aMetrics = $oOqlSelectReader->GetMetrics();

	    $this->assertNotEmpty($aMetrics);
	    $this->assertEquals(sizeof($aExpectedMetrics), sizeof($aMetrics));
	    $iIndex = 0;
	    foreach ($aMetrics as $oMetric) {
	        /** @var MonitoringMetric $oMetric */
		    $this->assertEquals("foo", $oMetric->GetName());
		    $this->assertEquals("gabuzomeu", $oMetric->GetDescription());
		    $this->assertEquals($aExpectedMetrics[$iIndex]['value'], $oMetric->GetValue());
		    $this->assertEquals($aExpectedMetrics[$iIndex]['labels'], $oMetric->GetLabels());
		    $iIndex++;
	    }
    }

    public function GetMetricsProvider(): array
    {
    	$sSql = <<<SQL
SELECT table_name, table_rows FROM information_schema.tables WHERE table_schema = 'team' AND table_type = 'BASE TABLE' and table_name IN ('workorder', 'storagesystem') order by table_name desc;
SQL;

	    return [
	        'nominal usecase' => [
		        'aMetric' => [
		        	'static_labels' => [
		        		'env' => 'prod'
			        ],
			        'sql_select' => [
				        'select' => $sSql,
				        //dynamic labels
				        'labels' =>  ['name' => 'table_name'],
				        'value' => 'table_rows'
			        ],
			        'description' => 'gabuzomeu',
		        ],
		        'aExpectedMetrics' => [
		        	[
		        		'value' => "0", 'labels' => [ 'env' => 'prod', 'name' => 'workorder' ],
			        ],
			        [
				        'value' => "0", 'labels' => [ 'env' => 'prod', 'name' => 'storagesystem' ],
			        ]
		        ]
	        ],
        ];
    }
}
