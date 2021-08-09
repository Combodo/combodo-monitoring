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

class ItoBackupReader implements CustomReaderInterface
{
    private $aMetricConf;
    private $sMetricName;

    public function __construct($sMetricName, $aMetricConf)
    {
        $this->aMetricConf = $aMetricConf;
        $this->sMetricName = 'itop_backup_';
    }

    /**
     * {@inheritDoc}
     */
    public function GetMetrics($sDir = null): ?array
    {
        $sDesc = 'iTop backup ';

        $aFiles = $this->ListFiles($sDir);

        if (array_key_exists(Constants::METRIC_LABEL, $this->aMetricConf)) {
            $aLabels = $this->aMetricConf[Constants::METRIC_LABEL];
        } else {
            $aLabels = [];
        }

        $iSize = sizeof($aFiles);
        $oCountBackupMetric = new MonitoringMetric($this->sMetricName.'count',
            'count iTop backup files.',
            $iSize,
            $aLabels
        );

        $iLastBackupSizeInBytes = -1;
        $iLastBackupAgeInDays = -1;
        if (0 != $iSize) {
            $sLastBackupPath = $aFiles[0];
            $iLastBackupSizeInBytes = filesize($sLastBackupPath);
            $iLastBackupAgeInDays = (strtotime('now') - filemtime($sLastBackupPath)) / 3600;
        }

        $oLastBackupSizeMetric = new MonitoringMetric($this->sMetricName.'lastbackup_inbytes_size',
            'last iTop backup file size in bytes.',
            $iLastBackupSizeInBytes,
            $aLabels
        );

        $oLastBackupAgeMetric = new MonitoringMetric($this->sMetricName.'lastbackup_ageinhours_count',
            'last iTop backup file age in hours.',
            $iLastBackupAgeInDays,
            $aLabels
        );

        $aMetrics = [$oCountBackupMetric, $oLastBackupSizeMetric, $oLastBackupAgeMetric];

        return $aMetrics;
    }

    /**
     * List and order by date the backups in the given directory
     * Note: the algorithm is currently based on the file modification date... because there is no "creation date" in general.
     *
     * @param string $sBackupDir
     *
     * @return array
     */
    public function ListFiles($sDir)
    {
        $sBackupDir = (null == $sDir) ? APPROOT.'data/backups/' : $sDir;
        if (!is_dir($sBackupDir)) {
            return [];
        }

        $aFiles = $this->glob_recursive($sBackupDir);
        $aTimes = [];
        // Legacy format -limited to 4 Gb
        foreach ($aFiles as $sFilePath) {
            $aTimes[] = filemtime($sFilePath); // unix time
        }
        array_multisort($aTimes, $aFiles);

        return $aFiles;
    }

    private function glob_recursive($sDirPath): array
    {
        $aFiles = [];

        foreach (glob($sDirPath.'/*') as $sFilePath) {
            if ($this->endsWith($sFilePath, '.zip')
                || $this->endsWith($sFilePath, '.tar.gz')) {
                if (is_file($sFilePath)) {
                    $aFiles[] = $sFilePath;
                }
            } elseif (is_dir($sFilePath)) {
                $aFiles = array_merge($aFiles,
                    $this->glob_recursive($sFilePath));
            }
        }

        return $aFiles;
    }

    private function endsWith($sHaystack, $sNeedle)
    {
        return substr($sHaystack, -strlen($sNeedle)) == $sNeedle;
    }
}
