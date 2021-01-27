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
use PHPUnit\Runner\Exception;
use utils;

class OqlConfReader implements MetricReaderInterface
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
        if (is_array($this->aMetric) && array_key_exists(Constants::CONF, $this->aMetric)) {
            $aConfParamPath = $this->aMetric[Constants::CONF];
            if (!empty($aConfParamPath)) {
                $sType = null;
                $sModule = null;
                foreach ($aConfParamPath as $sConfParam){
                    if (is_null($sType)){
                        $sType = $sConfParam;
                        continue;
                    }
                    if ($sType==='MySettings'){
                        $sValue = utils::GetConfig()->Get($sConfParam);
                        break;
                    } else if ($sType==='MyModuleSettings'){
                        if (is_null($sModule)){
                            $sModule = $sConfParam;
                            continue;
                        }
                        $sValue = utils::GetConfig()->GetModuleSetting($sModule, $sConfParam, null);
                        break;
                    }
                }
            }

            if (is_null($sValue)) {
                throw new Exception("Metric $this->sMetricName was not found in configuration found.");
            }

            return [ new MonitoringMetric($this->sMetricName, "", "" . $sValue) ] ;
        }

        return null;
    }
}