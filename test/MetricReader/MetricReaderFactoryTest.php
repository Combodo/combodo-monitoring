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

use Combodo\iTop\Monitoring\MetricReader\MetricReaderFactory;
use Combodo\iTop\Monitoring\MetricReader\OqlCountReader;

class MetricReaderFactoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider GetReaderProvider
     */
    public function testGetReader($aMetric, $sExpectedClass)
    {
        $oMetricReaderFactory = new MetricReaderFactory();
        $result = $oMetricReaderFactory->GetReader('foo', $aMetric);
        $this->assertInstanceOf($sExpectedClass, $result);
    }

    public function GetReaderProvider()
    {
        return [
            'count reader' => [
                'aMetric' => '',
                'sExpectedClass' => OqlCountReader::class,
            ],
            'conf reader' => [
                'aMetric' => '',
                'sExpectedClass' => OqlCountReader::class,
            ],
        ];
    }
}