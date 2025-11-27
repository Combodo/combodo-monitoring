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
use Combodo\iTop\Monitoring\Model\Constants;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @backupGlobals disabled
 */
class OqlCountReaderTest extends ItopDataTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->RequireOnceItopFile('env-production/combodo-monitoring/vendor/autoload.php');
		$this->RequireOnceItopFile('core/config.class.inc.php');

	}

	public function testGetMetrics()
	{
		$aMetric = [
				'oql_count' => [
					'select' => 'SELECT Person',
				],
				'description' => 'person metric',
				'static_labels' => ['a' => 'b'],
		];
		$oOqlCountReader = new OqlCountReader('itop_person_count', $aMetric);

		$aMetrics = $oOqlCountReader->GetMetrics();
		$this->assertEquals(1, count($aMetrics));

		/* @var \Combodo\iTop\Monitoring\Model\MonitoringMetric $oMetric */
		$oMetric = $aMetrics[0];

		$oSearch = \DBSearch::FromOQL('SELECT Person');
		$oSet = new \DBObjectSet($oSearch);
		$iCount = $oSet->Count();

		$this->assertEquals('itop_person_count', $oMetric->GetName());
		$this->assertEquals($iCount, $oMetric->GetValue());
		$this->assertEquals('person metric', $oMetric->GetDescription());
		$this->assertEquals(['a' => 'b'], $oMetric->GetLabels());
	}
}
