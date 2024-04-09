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

class ItopVersionReader implements CustomReaderInterface
{
    private $aMetricConf;
    private $sMetricName;

    public function __construct($sMetricName, $aMetricConf)
    {
        $this->aMetricConf = $aMetricConf;
        $this->sMetricName = 'itop_version';
    }

    /**
     * @inheritDoc
     */
    public function GetMetrics(): ?array
    {
	    $sValue = 0;
	    if (defined('ITOP_REVISION')) {
	    	$sValue = $this->GetVersionValue(ITOP_REVISION);
	    }

        $sDesc = 'iTop applicative version';
	    if (array_key_exists(Constants::METRIC_LABEL, $this->aMetricConf)){
            $aLabels = $this->aMetricConf[Constants::METRIC_LABEL];
        } else {
	        $aLabels = [];
        }
        return [ new MonitoringMetric($this->sMetricName, $sDesc, $sValue, $aLabels)];
    }

	/**
	 * function used in prod and for testing purpose as well to simulate different constant value
	 */
    public function GetVersionValue($sItopVersionConstant) : int {
	    if (filter_var($sItopVersionConstant, FILTER_VALIDATE_INT)){
		    return (int) $sItopVersionConstant;
	    }
	    return 0;
    }
}
