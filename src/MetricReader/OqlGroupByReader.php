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

namespace Combodo\iTop\Monitoring\MetricReader;


use Combodo\iTop\Monitoring\Model\Constants;
use Combodo\iTop\Monitoring\Model\MonitoringMetric;

class OqlGroupByReader implements MetricReaderInterface
{
    private $sMetricName;
    private $aMetric;

    public function __construct($sMetricName, $aMetric)
    {
        $this->sMetricName = $sMetricName;
        $this->aMetric = $aMetric;
    }

    /**
     * @inheritDoc
     */
    public function GetMetrics(): ?array
    {

        $sSQL = $this->MakeSql();
        return $this->FetchGroupByMetrics($this->sMetricName, $sSQL);

    }
    /**
     * @return string
     * @throws \OQLException
     */
    private function MakeSql(): string
    {
        $oSearch = \DBSearch::FromOQL($this->aMetric[Constants::OQL_COUNT]);
        $aGroupBy = $this->aMetric[Constants::OQL_GROUPBY];

        $aGroupByExp = [];
        foreach ($aGroupBy as $sAlias => $sOQLField) {
            $aGroupByExp[$sAlias] = \Expression::FromOQL($sOQLField);
        }

        $sSQL = $oSearch->MakeGroupByQuery([], $aGroupByExp);

        return $sSQL;
    }

    /**
     * @return array|null
     * @throws \CoreException
     * @throws \MySQLException
     * @throws \MySQLHasGoneAwayException
     */
    private function FetchGroupByMetrics($sMetricName, $sSQL)
    {
        $aGroupBy = $this->aMetric[Constants::OQL_GROUPBY];

        $resQuery = \CMDBSource::Query($sSQL);
        if (!$resQuery)  {
            return null;
        }

        $sDescription = $this->aMetric[Constants::METRIC_DESCRIPTION];
        $aStaticLabels = $this->aMetric[Constants::METRIC_LABEL] ?? [];

        $aMonitoringMetrics = [];
        while ($aRes = \CMDBSource::FetchArray($resQuery)) {
            $sValue = $aRes['_itop_count_'];

            $aLabels = $aStaticLabels;
            foreach (array_keys($aGroupBy) as $sLabelName) {
                $aLabels[$sLabelName] = $aRes[$sLabelName];
            }

            $aMonitoringMetrics[] = new MonitoringMetric($sMetricName, $sDescription, $sValue, $aLabels);

            unset($aRes);
        }
        \CMDBSource::FreeResult($resQuery);
        return $aMonitoringMetrics;
    }
}