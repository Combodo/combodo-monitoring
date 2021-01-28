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

namespace Combodo\iTop\Monitoring\Test\MetricReader\CustomReaders;

use Combodo\iTop\Monitoring\Model\Constants;
use Combodo\iTop\Monitoring\Model\MonitoringMetric;

class CustomReaderImpl implements \Combodo\iTop\Monitoring\MetricReader\CustomReaderInterface
{

    private $aMetricConf;

    public function __construct($aMetricConf)
    {
        $this->aMetricConf = $aMetricConf;
    }

    /**
     * @inheritDoc
     */
    public function GetMetrics(): ?array
    {
        return [ new MonitoringMetric('foo', $this->aMetricConf[Constants::METRIC_DESCRIPTION] ?? '', 42, ['baz' => 'iste'])];
    }
}