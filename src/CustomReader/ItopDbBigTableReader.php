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
use Combodo\iTop\DBTools\Service\DBToolsUtils;

class ItopDbBigTableReader implements CustomReaderInterface
{
    CONST EXCESSIVE_OBJECTCOUNT_THRESHOLD = 100000;
    CONST EXCESSIVE_DISKSPACE_THRESHOLD = 250; //mb
    CONST EXCESSIVE_OBJECTCOUNT_CONF_PARAM_NAME = 'objectcount_threshold';
    CONST EXCESSIVE_DISKSPACE_CONF_PARAM_NAME = 'diskspace_threshold';

    private $aMetricConf;
    private $sMetricName;

    /** @var DbToolsService $oDbToolsService  */
    private $oDbToolsService;

    public function __construct($sMetricName, $aMetricConf, $oDbToolsService=null)
    {
        $this->aMetricConf = $aMetricConf;
        $this->sMetricName = 'itop_big_table_';
        $this->oDbToolsService = (is_null($oDbToolsService) ? new DbToolsService() : $oDbToolsService);
    }

    /**
     * {@inheritDoc}
     */
    public function GetMetrics(): ?array
    {
        if (array_key_exists(Constants::METRIC_LABEL, $this->aMetricConf)) {
            $aLabels = $this->aMetricConf[Constants::METRIC_LABEL];
        } else {
            $aLabels = [];
        }

        $aMetrics=[];

        //var_dump($this->oDbToolsService->GetDBTablesInfo());
        foreach($this->oDbToolsService->GetDBTablesInfo() as $aTableInfo){
            $aCurrentLabels = [];
            $sTableName = $aTableInfo['table_name'] ?? null;
            $sObjectCount = $aTableInfo['table_rows'] ?? null;
            $sTotalSpace = $aTableInfo['total_length_mb'] ?? null;

            if (is_null($sTableName)||is_null($sObjectCount)||is_null($sTotalSpace)){
                continue;
            }

            $iObjectCount = (int) $sObjectCount;

            $iObjectCountThreshold = $this->GetObjectCountThreshold($sTableName);
            if ($iObjectCount > $iObjectCountThreshold) {
                $aCurrentLabels = $this->GetLabels($aCurrentLabels, $sTableName, $aLabels);

                $aMetrics[] = new MonitoringMetric($this->sMetricName . 'objectcount',
                    "itop tables that reach (configurable) $iObjectCountThreshold objects.",
                    $iObjectCount,
                    $aCurrentLabels
                );
            }

            $iDiskSpaceThreshold = $this->GetDiskSpaceThreshold($sTableName);
            $iTotalSpace = (int) $sTotalSpace;
            if ($iTotalSpace > $iDiskSpaceThreshold) {
                $aCurrentLabels = $this->GetLabels($aCurrentLabels, $sTableName, $aLabels);
                $aMetrics[] = new MonitoringMetric($this->sMetricName . 'diskspace',
                    "itop tables that reach (configurable) $iDiskSpaceThreshold mb in disk space.",
                    $iTotalSpace,
                    $aCurrentLabels
                );
            }
        }

        return $aMetrics;
    }

    private function GetLabels(&$aCurrentLabels, $sTableName, $aLabels) : array {
        if (array_key_exists('table', $aCurrentLabels)){
            return $aCurrentLabels;
        }

        $aCurrentLabels = [ 'table' => $sTableName ];
        foreach ($aLabels as $sKey => $sLabel){
            $aCurrentLabels[$sKey] = $sLabel;
        }
        return $aCurrentLabels;
    }

    public function GetObjectCountThreshold($sTableName){
        if (array_key_exists($sTableName, $this->aMetricConf)) {
            if (array_key_exists(self::EXCESSIVE_OBJECTCOUNT_CONF_PARAM_NAME, $this->aMetricConf[$sTableName])) {
                return $this->aMetricConf[$sTableName][self::EXCESSIVE_OBJECTCOUNT_CONF_PARAM_NAME];
            }
        }

        if (array_key_exists('default_' . self::EXCESSIVE_OBJECTCOUNT_CONF_PARAM_NAME, $this->aMetricConf)) {
            return $this->aMetricConf['default_' . self::EXCESSIVE_OBJECTCOUNT_CONF_PARAM_NAME];
        }

        return self::EXCESSIVE_OBJECTCOUNT_THRESHOLD;
    }

    public function GetDiskSpaceThreshold($sTableName){
        if (array_key_exists($sTableName, $this->aMetricConf)) {
            if (array_key_exists(self::EXCESSIVE_DISKSPACE_CONF_PARAM_NAME, $this->aMetricConf[$sTableName])) {
                return $this->aMetricConf[$sTableName][self::EXCESSIVE_DISKSPACE_CONF_PARAM_NAME];
            }
        }

        if (array_key_exists('default_' . self::EXCESSIVE_DISKSPACE_CONF_PARAM_NAME, $this->aMetricConf)) {
            return $this->aMetricConf['default_' . self::EXCESSIVE_DISKSPACE_CONF_PARAM_NAME];
        }

        return self::EXCESSIVE_DISKSPACE_THRESHOLD;
    }
}
