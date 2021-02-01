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

namespace Combodo\iTop\Monitoring\CustomReader;


use Combodo\iTop\Monitoring\Model\Constants;
use Combodo\iTop\Monitoring\Model\MonitoringMetric;

class NumberOfUniqueConnections implements \Combodo\iTop\Monitoring\MetricReader\CustomReaderInterface
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
        $sOQL = 'SELECT EventLoginUsage WHERE date > DATE_SUB(NOW(), INTERVAL 24 HOUR)';

        $oFilter = \DBObjectSearch::FromOQL($sOQL);
        $oSet = new \DBObjectSet($oFilter);

        $Counter = [];
        foreach ($oSet->Fetch() as $oEventLoginUsage) {
            $Counter[$oEventLoginUsage->Get('user_info')] = true;
        }

        $sDesc = $this->aMetricConf[Constants::METRIC_DESCRIPTION] ?? '';

        return [ new MonitoringMetric('itop_distinct_users_count', $sDesc, count($Counter))];
    }
}
