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
use Combodo\iTop\Monitoring\CustomReader\ItopReadOnlyCustomerReader;
use Combodo\iTop\Monitoring\MetricReader\ConfReader;
use Combodo\iTop\Monitoring\Model\MonitoringMetric;
use Combodo\iTop\Test\UnitTest\ItopTestCase;

class ItopReadOnlyCustomReaderInterfaceTest extends ItopTestCase
{
    public function setUp()
    {
        //@include_once '/home/combodo/workspace/iTop/approot.inc.php';
        //@include_once '/home/nono/PhpstormProjects/iTop/approot.inc.php';
        parent::setUp();

        @require_once(APPROOT . 'env-production/combodo-monitoring/vendor/autoload.php');
    }

	/**
	 * @dataProvider ReadonlyProvider
	 * @param $sMetricname
	 * @param false $fileExists
	 */
    public function testNominal($sMetricname, $fileExists = false)
    {
        $itopReadOnlyCustomerReader = new ItopReadOnlyCustomerReader($sMetricname, []);

        clearstatcache();
	    $sreadOnlyFile = APPROOT.'data/.readonly';
        if ($fileExists && ! is_file($sreadOnlyFile)){
        	touch($sreadOnlyFile);
        } else if (!$fileExists && is_file($sreadOnlyFile)){
        	unlink($sreadOnlyFile);
        }

        $aMetrics = $itopReadOnlyCustomerReader->GetMetrics();
        $this->assertEquals(1, sizeof($aMetrics));
        /** @var MonitoringMetric $oMetric */
        $oMetric = $aMetrics[0];

        $this->assertEquals($sMetricname, $oMetric->GetName());
        $this->assertEquals($fileExists ? 1 : 0, $oMetric->GetValue());

	    if (is_file($sreadOnlyFile)){
		    unlink($sreadOnlyFile);
	    }
    }

	public function ReadonlyProvider() {
    	return [
    		'itop running' => [ 'itop-readonly-ok' ],
    		'itop in readonly mode' => [ 'itop-readonly-setup', true ],
	    ];
	}
}
