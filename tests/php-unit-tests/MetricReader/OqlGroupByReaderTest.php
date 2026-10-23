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
use Combodo\iTop\Monitoring\Model\MonitoringMetric;
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

	public function GetMetricsProvider()
	{
		return [
			'SELECT User (1)' => [
				'oql' => 'SELECT User WHERE login LIKE "LOGIN_PREFIX%"',
				'where_filter' => 'User.first_name',
			],
			'SELECT User (2)' => [
				'oql' => 'SELECT User AS e WHERE login LIKE "LOGIN_PREFIX%"',
				'where_filter' => 'e.first_name',
			],
		];
	}

	private function PrepareUsers($sLoginPrefix): array
	{
		$i = 0;
		$sPwd = '123456abcdeFGTR@';
		$this->CreateContactlessUser($sLoginPrefix.$i++, self::$aURP_Profiles['Administrator'], $sPwd);
		$this->CreateContactlessUser($sLoginPrefix.$i++, self::$aURP_Profiles['Administrator'], $sPwd);
		$this->CreateContactlessUser($sLoginPrefix.$i++, self::$aURP_Profiles['Administrator'], $sPwd);
		$aExpectedRes = [
			(string) new MonitoringMetric('foo', 'user metric', 3, ['first_nameAlias' => 'NULL']),
		];

		$this->CreateTestOrganization();
		for ($j = 0; $j < 2; $j++) {
			$sCurrentUID = $sLoginPrefix.$i++;
			/** @var \Person $oPerson */
			$oPerson = $this->createObject('Person', [
				'name' => $sCurrentUID,
				'first_name' => $sCurrentUID,
				'org_id' => $this->getTestOrgId(),
			]);
			$this->CreateUser($sCurrentUID, self::$aURP_Profiles['Administrator'], $sPwd, $oPerson->GetKey());
			$aExpectedRes[] = (string) new MonitoringMetric('foo', 'user metric', 1, ['first_nameAlias' => $sCurrentUID]);
		}
		return $aExpectedRes;
	}

	/**
	 * @dataProvider GetMetricsProvider
	 */
	public function testGetMetrics($sOql, $sGroupByField)
	{
		$sLoginPrefix = "OqlGroupByReader".uniqid()."_";
		$aExpectedRes = $this->PrepareUsers($sLoginPrefix);

		$aMetric = [
			'oql_groupby' => [
				'select' => str_replace('LOGIN_PREFIX', $sLoginPrefix, $sOql),
				'groupby' => ['first_nameAlias' => $sGroupByField],
			],
			'description' => 'user metric',
		];
		$oOqlCountReader = new OqlGroupByReader('foo', $aMetric);

		$aMetrics = $oOqlCountReader->GetMetrics();
		$this->assertEquals(count($aExpectedRes), count($aMetrics));

		$aRes = [];
		/* @var \Combodo\iTop\Monitoring\Model\MonitoringMetric $oMetric */
		foreach ($aMetrics as $oMetric) {
			$aRes[] = (string)$oMetric;
		}

		self::assertEquals($aExpectedRes, $aRes);
	}
}
