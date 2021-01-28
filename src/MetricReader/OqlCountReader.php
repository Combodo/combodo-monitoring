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

class OqlCountReader implements MetricReaderInterface
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
        $oSet = $this->GetObjectSet();

        $sDescription = $this->aMetric[Constants::METRIC_DESCRIPTION];
        $aLabels = $this->aMetric[Constants::METRIC_LABEL] ?? [];

        return [ new MonitoringMetric($this->sMetricName, $sDescription,  $oSet->Count(), $aLabels)] ;

    }

    /**
     * @return \DBObjectSet
     * @throws \CoreException
     * @throws \MySQLException
     * @throws \OQLException
     */
    private function GetObjectSet(): \DBObjectSet
    {
        $oSearch = \DBSearch::FromOQL($this->aMetric[Constants::OQL_COUNT]);

        $aOrderBy = $this->aMetric[Constants::OQL_ORDERBY] ?? [];
        $iLimitCount = $this->aMetric[Constants::OQL_LIMIT_COUNT] ?? 0;
        $iLimitStart = $this->aMetric[Constants::OQL_LIMIT_START] ?? 0;
        $aOptimizeColumnsLoad = $this->aMetric[Constants::OQL_COLUMNS] ?? null;

        $oSet = new \DBObjectSet($oSearch);
        $oSet->SetOrderBy($aOrderBy);
        $oSet->SetLimit($iLimitCount, $iLimitStart);

        if (!is_null($aOptimizeColumnsLoad))
        {
            $oSet->OptimizeColumnLoad($aOptimizeColumnsLoad);
        }

        return $oSet;
    }
}