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

use Combodo\iTop\Monitoring\MetricReader\CustomReaderInterface;
use Combodo\iTop\Monitoring\Model\Constants;
use Combodo\iTop\Monitoring\Model\MonitoringMetric;

class ItopSetupVersionReader implements CustomReaderInterface
{
	protected $sMetricName;
	protected $aMetric;

    public function __construct($sMetricName, $aMetricConf)
    {
	    $this->sMetricName = 'itop_setup_version';
	    $this->aMetric = $aMetricConf;
    }

    /**
     * @inheritDoc
     */
    public function GetMetrics(): ?array
    {
	    $sDescription = 'iTop after setup version (code + datamodel)';
	    $sValueColumn = 'version';
	    $aStaticLabels = $this->aMetric[Constants::METRIC_LABEL] ?? [];

	    $oSearch = \DBSearch::FromOQL(sprintf("SELECT ModuleInstallation WHERE name = '%s'", ITOP_APPLICATION));
	    $oSet = new \DBObjectSet($oSearch);
	    $oSet->SetOrderBy(['installed' => false]);
	    $oSet->SetLimit(0, 1);
		$oSet->OptimizeColumnLoad(['' => [$sValueColumn]]);

	    $aMetrics = [];
	    while ($oObject = $oSet->Fetch()) {
			$sVersion = $oObject->Get($sValueColumn);
		    $aMetrics[] = new MonitoringMetric(
			    $this->sMetricName,
			    $sDescription,
			    $this->GetItopShortVersionId($sVersion),
			    $aStaticLabels
		    );
			break;
	    }
        return $aMetrics;
    }

	/**
	 * Return short itop version id. when not found returns 0.
	 * Exemple: 3.0.0-dev-6517 will return 6517
	 */
    public function GetItopShortVersionId(string $sVersion) : int {
	    if (false === preg_match('/.*-(\d+)/', $sVersion, $aMatches) ||
	        sizeof($aMatches) !== 2){
	    	return 0;
	    }
	    return (int) $aMatches[1];
    }
}
