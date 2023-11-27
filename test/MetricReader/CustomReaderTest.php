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

use Combodo\iTop\Monitoring\MetricReader\CustomReader;
use Combodo\iTop\Monitoring\Model\MonitoringMetric;
use Combodo\iTop\Monitoring\Test\MetricReader\CustomReaders\CustomReaderImpl;
use Combodo\iTop\Test\UnitTest\ItopTestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @backupGlobals disabled
 * @group cbd-monitoring-ci
 */
class CustomReaderTest extends ItopTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->RequireOnceItopFile('env-production/combodo-monitoring/vendor/autoload.php');
    }

    public function testGetGetValue()
    {
	    $aMetric = [
		    'custom' => ['class' => CustomReaderImpl::class],
		    'description' => 'descriptionFromConf'
	    ];
        $oCustomReader = new CustomReader('foo', $aMetric);
        $aResult = $oCustomReader->GetMetrics();

	    $aExpectedResult = [ new MonitoringMetric('foo', 'descriptionFromConf', 42, ['baz' => 'iste'])];
	    $this->assertEquals($aExpectedResult, $aResult);
    }
}
