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

use Combodo\iTop\Monitoring\MetricReader\OqlSelectReader;
use Combodo\iTop\Monitoring\Model\MonitoringMetric;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;

/**
 * @group sampleDataNeeded
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @backupGlobals disabled
 */
class OqlSelectReaderTest extends ItopDataTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->RequireOnceItopFile('env-production/combodo-monitoring/vendor/autoload.php');
		$this->RequireOnceItopFile('core/config.class.inc.php');

	}

	public function GetMetricsProvider()
	{
		return [
			'oql_columns with org_id (to optimize as well)' => [
				'oql' => 'SELECT User',
				'labels' => ['firstname' => 'first_name', 'lastname' => 'last_name'],
				'value' => 'org_id',
				'searchAlias' => 'org_id',
			],
			'oql_columns with User.org_id (alias)' => [
				'oql' => 'SELECT User',
				'labels' => ['firstname' => 'first_name', 'lastname' => 'last_name'],
				'value' => 'User.org_id',
				'searchAlias' => 'org_id',
			],
			'oql_columns with id (not an attributedef optimizable)' => [
				'oql' => 'SELECT User',
				'labels' => ['firstname' => 'first_name', 'lastname' => 'last_name'],
				'value' => 'id',
				'searchAlias' => 'org_id',
			],
			'jointure using fields on both sides with FROM syntax' => [
				'oql' => 'SELECT up, p FROM URP_UserProfile AS up JOIN URP_Profiles AS p ON up.profileid = p.id',
				'labels' => [
					'profile' => 'p.name',
					'name' => 'up.profile',
				],
				'value' => 'up.profileid',
				'searchAlias' => 'id',
			],
		];
	}

	/**
	 * @group cbd-monitoring-ci
	 * @dataProvider GetMetricsProvider
	 */
	public function testGetMetrics($sOql, $aLabels, $sValue, $searchAlias)
	{
		$aMetric = [
			'oql_select' => [
				'select' => $sOql,
				'labels' =>  $aLabels,
				'value' => $sValue,
			],
			'description' => 'user metric',
		];
		$oOqlCountReader = new OqlSelectReader('foo', $aMetric);

		$aExpectedRes = [];
		$oSearch = \DBSearch::FromOQL($sOql);
		$oSet = new \DBObjectSet($oSearch);
		while ($oUser = $oSet->Fetch()) {
			$aFields = array_keys($aLabels);
			$sFirst = $aFields[0];
			$sSecond = $aFields[1];
			$aExpectedRes[$sFirst][$sSecond] = $oUser->Get($searchAlias);
		}

		$aMetrics = $oOqlCountReader->GetMetrics();
		$this->assertEquals(count($aExpectedRes), count($aMetrics));

		/* @var \Combodo\iTop\Monitoring\Model\MonitoringMetric $oMetric */
		foreach ($aMetrics as $oMetric) {
			$this->assertEquals('foo', $oMetric->GetName());
			$this->assertEquals('user metric', $oMetric->GetDescription());

			$aExpecteLabelKeys = array_keys($aLabels);
			$sCurrentFirst = $aExpecteLabelKeys[0];
			$sCurrentLast = $aExpecteLabelKeys[1];
			sort($aExpecteLabelKeys);

			$aMetricLabels = $oMetric->GetLabels();
			var_dump($aMetricLabels);
			$aLabelKeys = array_keys($aMetricLabels);
			sort($aLabelKeys);
			$this->assertEquals($aExpecteLabelKeys, $aLabelKeys);
			var_dump($sCurrentFirst);
			var_dump($sCurrentLast);
			var_dump($aExpectedRes);
			$this->assertEquals($aExpectedRes[$sCurrentFirst][$sCurrentLast], $oMetric->GetValue());
		}
	}
}
