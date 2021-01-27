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
        $oSearch = \DBSearch::FromOQL($this->aMetric[Constants::OQL_COUNT]);

        if (array_key_exists(Constants::OQL_ORDERBY, $this->aMetric)) {
            $aOrderBy = $this->aMetric[Constants::OQL_ORDERBY];
        } else {
            $aOrderBy = [];
        }
        if (array_key_exists(Constants::OQL_LIMIT_COUNT, $this->aMetric)) {
            $iLimitCount = $this->aMetric[Constants::OQL_LIMIT_COUNT];
        } else {
            $iLimitCount = 0;
        }
        if (array_key_exists(Constants::OQL_LIMIT_START, $this->aMetric)) {
            $iLimitStart = $this->aMetric[Constants::OQL_LIMIT_START];
        } else {
            $iLimitStart = 0;
        }
        if (array_key_exists(Constants::OQL_COLUMNS, $this->aMetric)) {
            $aOptimizeColumnsLoad = $this->aMetric[Constants::OQL_COLUMNS];
        } else {
            $aOptimizeColumnsLoad = null;
        }


        if (array_key_exists(Constants::OQL_GROUPBY, $this->aMetric)) {
            $aDynamicLabelFields = explode(",", $this->aMetric[Constants::OQL_GROUPBY]);
            if (count($aDynamicLabelFields)==0){
                throw new \Exception("Strange configuration on $this->sMetricName:" . $this->aMetric[Constants::OQL_GROUPBY]);
            } else if (count($aDynamicLabelFields)==1){
                throw new \Exception("Missing OQL field inside $this->sMetricName configuration:" . $this->aMetric[Constants::OQL_GROUPBY]);
            }

            $sLabelName = trim($aDynamicLabelFields[0]);
            $sOqlField = trim($aDynamicLabelFields[1]);
            $oExpr = \Expression::FromOQL($sOqlField);
            $aGroupByExpr=[ $sLabelName => $oExpr ];
            $sSQL = $oSearch->MakeGroupByQuery([], $aGroupByExpr, false, [], $aOrderBy, $iLimitCount, $iLimitStart);

//            if ($returnSQL) {
//                return $sSQL;
//            }

            return $this->FetchGroupByMetrics($this->sMetricName, $aGroupByExpr, $sSQL);
        } else{
            $oSet = new \DBObjectSet($oSearch);
            $oSet->SetOrderBy($aOrderBy);
            $oSet->SetLimit($iLimitCount, $iLimitStart);
            if (!is_null($aOptimizeColumnsLoad)) {
                $oSet->OptimizeColumnLoad($aOptimizeColumnsLoad);
            }
//            if ($returnSQL) {
//                return $oSet;
//            }

            return [ new MonitoringMetric($this->sMetricName, "",  "" . $oSet->Count()) ] ;
        }
    }
    /**
     * @param string $sMetricName
     * @param \DBSearch $oSearch
     * @param $aGroupByExpr
     * @return array|null
     * @throws \CoreException
     * @throws \MySQLException
     * @throws \MySQLHasGoneAwayException
     */
    private function FetchGroupByMetrics($sMetricName, $aGroupByExpr, $sSQL)
    {
        $resQuery = \CMDBSource::Query($sSQL);
        if (!$resQuery)
        {
            return null;
        }
        else
        {
            $aMonitoringMetrics = [];
            while ($aRes = \CMDBSource::FetchArray($resQuery)) {
                $sValue = $aRes['_itop_count_'];
                $oMonitoringMetrics = new MonitoringMetric($sMetricName, "", $sValue);
                foreach (array_keys($aGroupByExpr) as $sLabelName) {
                    $sLabelName = $sLabelName;
                    $oMonitoringMetrics->AddLabel($sLabelName, $aRes[$sLabelName]);
                }
                $aMonitoringMetrics[] = $oMonitoringMetrics;
                unset($aRes);
            }
            \CMDBSource::FreeResult($resQuery);
            return $aMonitoringMetrics;
        }
    }
}