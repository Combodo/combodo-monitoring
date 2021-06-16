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
    protected $sMetricName;
	protected $aMetric;
	protected $sDefaultAlias;

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

        if ($sColumnValue === null) {
            throw new \Exception("Metric $this->sMetricName Must provide at least one value to be read.");
        }

        $oSet = $this->GetObjectSet();
        $aMetrics = [];
        while ($aObjects = $oSet->FetchAssoc()) {
	        $bExposeCurrentMetric = true;
        	if (sizeof($aObjects) === 0){
        		continue;
	        }

        	//used only for simple OQL that retunr one  single object.
	        //it is used for fields without any alias
        	$oDefaultObject = $aObjects[$this->sDefaultAlias];

            $aCurrentLabels = [];
            foreach ($aColumns as $sLabel => $sColumn) {
            	$sValue = $this->GetColumnValueFromObjects($sColumn, $oDefaultObject, $aObjects);
            	if ($sValue == null){
	                //missing label: do not keep metric
		            $bExposeCurrentMetric = false;
		            break;
            	}
	            $aCurrentLabels[$sLabel] = $sValue;
            }

            if (false == $bExposeCurrentMetric){
            	//missing label
            	continue;
            }

	        $sMetricValue = $this->GetColumnValueFromObjects($sColumnValue, $oDefaultObject, $aObjects);
	        if ($sMetricValue != null){
		        $aCurrentLabels = array_merge($aStaticLabels, $aCurrentLabels);
		        $aMetrics[] = new MonitoringMetric(
			        $this->sMetricName,
			        $sDescription,
			        $sMetricValue,
			        $aCurrentLabels
		        );
	        }
        }

        return $aMetrics;
    }

    private function GetColumnValueFromObjects(string $sColumn, $oDefaultObject, array $aObjects) {
	    if (strpos($sColumn, ".") === false){
		    return $oDefaultObject->Get($sColumn);
	    } else {
		    $aFields = explode(".", $sColumn);
		    if (sizeof($aFields) === 2){
			    $sAlias = $aFields[0];
			    $sColumn = $aFields[1];

			    if (array_key_exists($sAlias, $aObjects)){
				    $oObject = $aObjects[$sAlias];
				    if ($oObject == null){
			    		return null;
				    }
				    return $oObject->Get($sColumn);
			    }
		    }
	    }

	    return null;
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
	    $sValueColumn = $this->aMetric[Constants::OQL_SELECT][Constants::VALUE] ?? '';

        $oSet = new \DBObjectSet($oSearch);
        $oSet->SetOrderBy($aOrderBy);
        $oSet->SetLimit($iLimitCount, $iLimitStart);

        $aSelectedClasses = $oSearch->GetSelectedClasses();
	    $aSelectedAliases = array_keys($aSelectedClasses);
	    $this->sDefaultAlias = '';
	    if (sizeof($aSelectedClasses) >= 1){
	        $this->sDefaultAlias = $aSelectedAliases[0];
        }

        $aOptimizeColumnsLoad = [];
        foreach ($aColumns as $sLabel => $sColumn) {
	        $aOptimizeColumnsLoad = $this->CompleteColumnsLoadForOptimization($aOptimizeColumnsLoad, $sColumn);
        }

		$aOptimizeColumnsLoad = $this->CompleteColumnsLoadForOptimization($aOptimizeColumnsLoad, $sValueColumn);


        if (!empty($aOptimizeColumnsLoad)) {
            $oSet->OptimizeColumnLoad($aOptimizeColumnsLoad);
        }

        return $oSet;
    }

    private function CompleteColumnsLoadForOptimization(array $aOptimizeColumnsLoad, string $sColumn) : array{
	    if ($sColumn === 'id') {
		    //id not an attribute def. cannot optimize it...
		    return $aOptimizeColumnsLoad;
	    }

	    if (strpos($sColumn, ".") === false){
		    $aOptimizeColumnsLoad[$this->sDefaultAlias][] = $sColumn;
	    } else {
		    $aFields = explode(".", $sColumn);
		    if (sizeof($aFields) === 2){
			    $aOptimizeColumnsLoad[$aFields[0]][] = $aFields[1];
		    }
	    }

	    return $aOptimizeColumnsLoad;
    }
}