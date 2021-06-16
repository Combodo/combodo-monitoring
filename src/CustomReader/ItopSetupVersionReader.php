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
use Combodo\iTop\Monitoring\MetricReader\OqlSelectReader;
use Combodo\iTop\Monitoring\Model\Constants;
use Combodo\iTop\Monitoring\Model\MonitoringMetric;

class ItopSetupVersionReader extends OqlSelectReader implements CustomReaderInterface
{
    public function __construct($sMetricName, $aMetricConf)
    {
    	$sOql = sprintf("SELECT ModuleInstallation WHERE name = '%s'", ITOP_APPLICATION);
        $aMetricConf = array_merge($aMetricConf,
            [
                Constants::METRIC_DESCRIPTION => 'iTop applicative version',
                Constants::OQL_SELECT => [
                    Constants::SELECT => $sOql,
                    Constants::VALUE => 'version',
	                Constants::ORDERBY => ['installed' => false],
                    Constants::LIMIT_COUNT => '1',
                ]
            ]
        );
	    parent::__construct('itop_setup_version', $aMetricConf);
    }

    /**
     * @inheritDoc
     */
    public function GetMetrics(): ?array
    {
        $aMetrics = parent::GetMetrics();

	    if (sizeof($aMetrics) !== 0) {
		    foreach ($aMetrics as $oMetric){
			    /** @var MonitoringMetric $oMetric */
			    $sNewValue = $this->GetItopShortVersionId($oMetric);
			    $oMetric->SetValue($sNewValue);
			    preg_match('', $oMetric->GetValue(), $aMatches);
		    }
	    }
        return $aMetrics;
    }

	/**
	 * Return short itop version id. when not found returns 0.
	 * Exemple: 3.0.0-dev-6517 will return 6517
	 */
    public function GetItopShortVersionId(MonitoringMetric $oMetric) : string {
	    /** @var MonitoringMetric $oMetric */
	    if (false === preg_match('/.*-(\d+)/', $oMetric->GetValue(), $aMatches) ||
	        sizeof($aMatches) !== 2){
	    	return '0';
	    }
	    var_dump($aMatches);
	    return $aMatches[1];
    }
}
