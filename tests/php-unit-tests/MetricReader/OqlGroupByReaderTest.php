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

use Combodo\iTop\Monitoring\MetricReader\OqlGroupByReader;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @backupGlobals disabled
 */
class OqlGroupByReaderTest extends ItopDataTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->RequireOnceItopFile('env-production/combodo-monitoring/vendor/autoload.php');
        $this->RequireOnceItopFile('core/config.class.inc.php');

    }


	public function GetMetricsProvider() {
		return [
			'SELECT User (1)' => [
				'oql' => 'SELECT User',
				'where_filter' => 'User.first_name'
			],
			'SELECT User (2)' => [
				'oql' => 'SELECT User AS e',
				'where_filter' => 'e.first_name'
			],
			/*'SELECT User (3)' => [
				'oql' => 'SELECT User',
				'where_filter' => 'first_name'
			],*/
		];
	}

	/**
	 * @dataProvider GetMetricsProvider
	 */
	public function testGetMetrics($sOql, $sGroupByField)
	{
		$aMetric = [
			'oql_groupby' => [
				'select' => $sOql,
				'groupby' => ['first_nameAlias' => $sGroupByField],
			],
			'description' => 'user metric',
		];
		$oOqlCountReader = new OqlGroupByReader('foo', $aMetric);

		$aExpectedRes = [];
		$oSearch = \DBSearch::FromOQL('SELECT User');
		$oSet = new \DBObjectSet($oSearch);
		while ($oUser = $oSet->Fetch()) {
			$sKey = $oUser->Get('first_name');
			if (! in_array($aExpectedRes, $aExpectedRes)){
				$aExpectedRes[$sKey]= 1;
			} else {
				$aExpectedRes[$sKey]= $aExpectedRes[$sKey] + 1;
			}
		}

		$aMetrics = $oOqlCountReader->GetMetrics();
		$this->assertEquals(count($aExpectedRes), count($aMetrics));

		/* @var \Combodo\iTop\Monitoring\Model\MonitoringMetric $oMetric */
		foreach ($aMetrics as $oMetric) {
			$this->assertEquals('foo', $oMetric->GetName());
			$this->assertEquals('user metric', $oMetric->GetDescription());

			$aLabels = $oMetric->GetLabels();
			$this->assertEquals(1, count($aLabels));
			$this->assertEquals(['first_nameAlias'], array_keys($aLabels));
			$sCurrentName = $aLabels['first_nameAlias'];
			$this->assertEquals($aExpectedRes[$sCurrentName], $oMetric->GetValue());
		}
	}
}
