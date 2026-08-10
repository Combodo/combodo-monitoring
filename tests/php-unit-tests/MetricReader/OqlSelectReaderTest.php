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
		$aMetrics = $oOqlCountReader->GetMetrics();
		$this->assertEquals($oSet->Count(), count($aMetrics));

		while ($oUser = $oSet->Fetch()) {
			$value = $oUser->Get($searchAlias);
			$aExpectedLabels = [];
			foreach ($aLabels as $sMonitoringLabel => $siTopField) {
				$aExpectedLabels[$sMonitoringLabel] = $oUser->Get($siTopField);
			}
			$aExpectedRes[$this->GetMetricKey($value, $aExpectedLabels)] = $aExpectedLabels;
		}

		/* @var \Combodo\iTop\Monitoring\Model\MonitoringMetric $oMetric */
		foreach ($aMetrics as $oMetric) {
			$this->assertEquals('foo', $oMetric->GetName());
			$this->assertEquals('user metric', $oMetric->GetDescription());

			var_dump($oMetric->GetValue());
			var_dump($oMetric->GetLabels());
			$sMetricKey = $this->GetMetricKey($oMetric->GetValue(), $oMetric->GetLabels());
			$aExpectedLabels = $aExpectedRes[$sMetricKey] ?? null;
			$this->assertNotNull($aExpectedLabels, "should find metric with itop value ($sMetricKey) among ".var_export(array_keys($aExpectedRes), true));

			$this->assertEquals($aExpectedLabels, $oMetric->GetLabels(), "labels associated to object with ID ({$oMetric->GetValue()}) should match");
		}
	}

	public function GetMetricsWithJointsProvider()
	{
		return [
			'jointure using fields on both sides with FROM syntax/ up.profileid' => [
				'oql' => 'SELECT up, p FROM URP_UserProfile AS up JOIN URP_Profiles AS p ON up.profileid = p.id',
				'labels' => [
					'profile' => 'p.name',
					'name' => 'up.profile',
				],
				'value' => 'up.profileid',
				'searchAlias' => 'up.id',
			],
			'jointure using fields on both sides with FROM syntax /p.id' => [
				'oql' => 'SELECT up, p FROM URP_UserProfile AS up JOIN URP_Profiles AS p ON up.profileid = p.id',
				'labels' => [
					'profile' => 'p.name',
					'name' => 'up.profile',
				],
				'value' => 'p.id',
				'searchAlias' => 'p.id',
			],
		];
	}

	/**
	 * @group cbd-monitoring-ci
	 * @dataProvider GetMetricsWithJointsProvider
	 */
	public function testGetMetricsWithJoints($sOql, $aLabels, $sValue)
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
		$aMetrics = $oOqlCountReader->GetMetrics();
		$this->assertEquals($oSet->Count(), count($aMetrics));

		while ($aFetchAssoc = $oSet->FetchAssoc()) {
			$value = $this->GetFromFetchAssoc($sValue, $aFetchAssoc);
			$aExpectedLabels = [];
			foreach ($aLabels as $sMonitoringLabel => $siTopField) {
				$aExpectedLabels[$sMonitoringLabel] = $this->GetFromFetchAssoc($siTopField, $aFetchAssoc);
			}
			$aExpectedRes[$this->GetMetricKey($value, $aExpectedLabels)] = $aExpectedLabels;
		}
		var_dump(array_keys($aExpectedRes));

		/* @var \Combodo\iTop\Monitoring\Model\MonitoringMetric $oMetric */
		foreach ($aMetrics as $oMetric) {
			$this->assertEquals('foo', $oMetric->GetName());
			$this->assertEquals('user metric', $oMetric->GetDescription());

			$sMetricKey = $this->GetMetricKey($oMetric->GetValue(), $oMetric->GetLabels());
			$aExpectedLabels = $aExpectedRes[$sMetricKey] ?? null;
			$this->assertNotNull($aExpectedLabels, "should find metric with itop value ($sMetricKey) among ".var_export(array_keys($aExpectedRes), true));

			$this->assertEquals($aExpectedLabels, $oMetric->GetLabels(), "labels associated to object with ID ({$oMetric->GetValue()}) should match");
		}
	}

	private function GetFromFetchAssoc($sItopField, array $aGetFromFetchAssoc): string
	{
		$aSplit = explode('.', $sItopField);
		$sObjKey = $aSplit[0];
		$sItopAttr = $aSplit[1];
		$oObj = $aGetFromFetchAssoc[$sObjKey];
		return $oObj->Get($sItopAttr);
	}

	private function GetMetricKey($value, array $aLabels): string
	{
		return "{$value}_".implode("_", $aLabels);
	}
}
