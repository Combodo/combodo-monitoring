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


use Combodo\iTop\Application\Helper\iTopSessionHandler;
use Combodo\iTop\Monitoring\MetricReader\CustomReaderInterface;
use Combodo\iTop\Monitoring\Model\MonitoringMetric;

class iTopSessionReader implements CustomReaderInterface
{
    private $aMetricConf;
    private $sMetricName;

    public function __construct($sMetricName, $aMetricConf)
    {
        $this->aMetricConf = $aMetricConf;
        $this->sMetricName = $sMetricName;
    }

    /**
     * @inheritDoc
     */
    public function GetMetrics(): ?array
    {
        //$sDesc = $this->aMetricConf[Constants::METRIC_DESCRIPTION] ?? '';

	    if (! class_exists('Combodo\iTop\Application\Helper\iTopSessionHandler')) {
		    \IssueLog::Error("iTopSessionHandler class does not exist. Metric iTopSessionReader is not working with current iTop version");

		    return [];
	    }

	    $oItopSessionHandler = new iTopSessionHandler();
	    $aFiles = $oItopSessionHandler->ListSessionFiles();

	    return $this->FetchCounter($aFiles);
    }


	public function FetchCounter(array $aSessionFiles): array
    {
		$aCount = [];
		$aSessionElapsedMax = [];
	    $aElapsedSum = [];
		foreach ($aSessionFiles as $sFile){
			if (! is_file($sFile)){
				continue;
			}

			$aData = json_decode(file_get_contents($sFile), true);

			if (is_array($aData) && array_key_exists('login_mode', $aData)){
				$sLoginMode = $aData['login_mode'];
			} else {
				$sLoginMode = 'no_auth';
			}

			if (is_array($aData) && array_key_exists('context', $aData)){
				$sContext = $aData['context'];
			} else {
				$sContext = '';
			}

			if (! array_key_exists($sLoginMode, $aCount)){
				$aCount[$sLoginMode] = [];
			}

			if (array_key_exists($sContext, $aCount[$sLoginMode])){
				$iCount = $aCount[$sLoginMode][$sContext] + 1;
			} else {
				$iCount = 1;
			}
			$aCount[$sLoginMode][$sContext] = $iCount;

			if ($sLoginMode === 'no_auth'){
				continue;
			}

			if (is_array($aData) && array_key_exists('creation_time', $aData)) {
				$iCreationTime = $aData['creation_time'];
				$iElapsedInSeconds = filemtime($sFile) - $iCreationTime;

				if (!array_key_exists($sLoginMode, $aSessionElapsedMax)) {
					$aSessionElapsedMax[$sLoginMode] = [];
					$aElapsedSum[$sLoginMode] = [];
				}

				if (array_key_exists($sContext, $aSessionElapsedMax[$sLoginMode])) {
					$aElapsedSum[$sLoginMode][$sContext] = $aElapsedSum[$sLoginMode][$sContext] + $iElapsedInSeconds;

					if ($iElapsedInSeconds > $aSessionElapsedMax[$sLoginMode][$sContext]) {
						$aSessionElapsedMax[$sLoginMode][$sContext] = $iElapsedInSeconds;
					}
				} else {
					$aElapsedSum[$sLoginMode][$sContext] = $iElapsedInSeconds;
					$aSessionElapsedMax[$sLoginMode][$sContext] = $iElapsedInSeconds;
				}
			}
		}

	    $aRes = [];
	    foreach ($aCount as $sLoginMode => $aLoginModeRes) {
		    foreach ($aLoginModeRes as $sContext => $iCount) {
			    $aRes[] = new MonitoringMetric($this->sMetricName . '_count', "Active session count", $iCount, ['login_mode' => $sLoginMode, 'context' => $sContext]);
		    }
	    }

	    foreach ($aElapsedSum as $sLoginMode => $aLoginModeRes) {
		    foreach ($aLoginModeRes as $sContext => $iCount) {
			    $aRes[] = new MonitoringMetric($this->sMetricName . '_elapsedinsecond_sum', "Sum of active session elapsed time in seconds", $iCount, ['login_mode' => $sLoginMode, 'context' => $sContext]);
		    }
	    }

	    foreach ($aSessionElapsedMax as $sLoginMode => $aLoginModeRes) {
		    foreach ($aLoginModeRes as $sContext => $iCount) {
			    $aRes[] = new MonitoringMetric($this->sMetricName . '_elapsedinsecond_max', "Max elapsed time in seconds amoung active sessions", $iCount, ['login_mode' => $sLoginMode, 'context' => $sContext]);
		    }
	    }
        return $aRes;
    }
}
