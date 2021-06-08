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

class OqlSelectReader implements MetricReaderInterface
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
        $sDescription = $this->aMetric[Constants::METRIC_DESCRIPTION];
        $aStaticLabels = $this->aMetric[Constants::METRIC_LABEL] ?? [];
        $aColumns = $this->aMetric[Constants::OQL_SELECT][Constants::LABELS] ?? [];
        $sColumnValue = $this->aMetric[Constants::OQL_SELECT][Constants::VALUE] ?? null;

        if (empty($aColumns)) {
            throw new \Exception("Metric $this->sMetricName Must provide at least one column to be read.");
        }

        if ($sColumnValue === null) {
            throw new \Exception("Metric $this->sMetricName Must provide at least one value to be read.");
        }

        $oSet = $this->GetObjectSet();
        $aMetrics = [];
        while ($oObject = $oSet->Fetch()) {
            $aCurrentLabels = [];
            foreach ($aColumns as $sLabel => $sColumn) {
                $aCurrentLabels[$sLabel] = $oObject->Get($sColumn);
            }

            $sMetricValue = $oObject->Get($sColumnValue);
            $aCurrentLabels = array_merge($aStaticLabels, $aCurrentLabels);
            $aMetrics[] = new MonitoringMetric(
                $this->sMetricName,
                $sDescription,
                $sMetricValue,
                $aCurrentLabels
            );
        }

        return $aMetrics;
    }

    /**
     * @return \DBObjectSet
     * @throws \CoreException
     * @throws \MySQLException
     * @throws \OQLException
     */
    private function GetObjectSet(): \DBObjectSet
    {
        $oSearch = \DBSearch::FromOQL($this->aMetric[Constants::OQL_SELECT][Constants::SELECT]);

        $aOrderBy = $this->aMetric[Constants::OQL_SELECT][Constants::ORDERBY] ?? [];
        $iLimitCount = $this->aMetric[Constants::OQL_SELECT][Constants::LIMIT_COUNT] ?? 0;
        $iLimitStart = $this->aMetric[Constants::OQL_SELECT][Constants::LIMIT_START] ?? 0;
        $aColumns = $this->aMetric[Constants::OQL_SELECT][Constants::LABELS] ?? [];
	    $sValue = $this->aMetric[Constants::OQL_SELECT][Constants::VALUE] ?? '';

        $oSet = new \DBObjectSet($oSearch);
        $oSet->SetOrderBy($aOrderBy);
        $oSet->SetLimit($iLimitCount, $iLimitStart);

        $sAlias = $oSearch->GetClassAlias();
        $aOptimizeColumnsLoad = [];
        foreach ($aColumns as $sKey => $sAttribute) {
            $aOptimizeColumnsLoad[$sAlias][] = $sAttribute;
        }
        if ($sValue !== 'id'){
			//id not an attribute def. cannot optimize it...
	        $aOptimizeColumnsLoad[$sAlias][] = $sValue;
        }

        if (!empty($aOptimizeColumnsLoad)) {
            $oSet->OptimizeColumnLoad($aOptimizeColumnsLoad);
        }

        return $oSet;
    }
}