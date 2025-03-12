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

use Combodo\iTop\SessionTracker\SessionHandler;
use Combodo\iTop\Monitoring\MetricReader\CustomReaderInterface;
use Combodo\iTop\Monitoring\Model\MonitoringMetric;
use MetaModel;
use Organization;

class ActiveOrgIncludedSessionReader implements CustomReaderInterface
{
    private $sMetricName;
    private string $sOrgUidField;
	private array $aOrgUids=[];
	private array $aFieldInSessionByOtherMetricName=[];
	public const NO_ORG_UID = 'no_uid';

    public function __construct($sMetricName, $aMetricConf)
    {
        $this->sMetricName = $sMetricName;
        $this->sOrgUidField = $aMetricConf['org_field'] ?? 'name';

		$this->aFieldInSessionByOtherMetricName = $aMetricConf['other_session_metrics'] ?? [];
    }

	/**
	 * testing purpose only
	 */
	public function SetOrgUids(array $aOrgUids) : void
	{
		$this->aOrgUids = $aOrgUids;
	}

    /**
     * @inheritDoc
     */
    public function GetMetrics(): ?array
    {
        //$sDesc = $this->aMetricConf[Constants::METRIC_DESCRIPTION] ?? '';

	    if (! class_exists('Combodo\iTop\SessionTracker\SessionHandler')) {
		    \IssueLog::Error("SessionHandler class does not exist. Metric ActiveSessionReader is not working with current iTop version");

		    return [];
	    }

	    $oItopSessionHandler = new SessionHandler();
	    $aFiles = $oItopSessionHandler->list_session_files();

	    return $this->FetchCounter($aFiles);
    }


	public function FetchCounter(array $aSessionFiles): array
    {
		$aCountPerOrg = [];
		$aUnexpiredFilesPerOrg = [];
	    $aOtherFieldsInSession=array_unique(array_values($this->aFieldInSessionByOtherMetricName));
		$aCountPerField = [];

	    foreach ($aOtherFieldsInSession as $sField){
		    $aCountPerField[$sField]=[];
	    }

		foreach ($aSessionFiles as $sFile){
			if (! is_file($sFile)){
				continue;
			}

			$aData = json_decode(file_get_contents($sFile), true);

			if (is_array($aData)){
				foreach ($aOtherFieldsInSession as $sField){
					$sVal = $aData[$sField] ?? null;
					if (! is_null($sVal)){
						$iCount = $aCountPerField[$sField][$sVal] ?? 0;
						$iCount++;
						$aCountPerField[$sField][$sVal] = $iCount;
					}
				}

				if (array_key_exists('login_mode', $aData)){
					$sLoginMode = $aData['login_mode'];
				}

				if (array_key_exists('context', $aData)){
					$sContext = $aData['context'];
				}

				$sOrgUid = $this->FetchOrgUid($aData);
			} else {
				$sContext = '';
				$sLoginMode = 'no_auth';
				$sOrgUid = self::NO_ORG_UID;
			}

			$aCount = $aCountPerOrg[$sOrgUid] ?? [];

			if (! array_key_exists($sLoginMode, $aCount)){
				$aCount[$sLoginMode] = [];
			}

			if (array_key_exists($sContext, $aCount[$sLoginMode])){
				$iCount = $aCount[$sLoginMode][$sContext] + 1;
			} else {
				$iCount = 1;
			}
			$aCount[$sLoginMode][$sContext]=$iCount;
			$aCountPerOrg[$sOrgUid] = $aCount;

			if ($sLoginMode === 'no_auth'){
				continue;
			}

			if (! array_key_exists($sOrgUid, $aUnexpiredFilesPerOrg)){
				$aUnexpiredFiles=[];
				$aUnexpiredFilesPerOrg[$sOrgUid] = $aUnexpiredFiles;
			} else {
				$aUnexpiredFiles = $aUnexpiredFilesPerOrg[$sOrgUid];
			}

			if (! array_key_exists($sLoginMode, $aUnexpiredFiles)){
				$aUnexpiredFiles[$sLoginMode] = [];
			}

			if (array_key_exists($sContext, $aUnexpiredFiles[$sLoginMode])){
				$aUnexpiredFilesPerOrg[$sOrgUid][$sLoginMode][$sContext][] = $sFile;
			} else {
				$aUnexpiredFilesPerOrg[$sOrgUid][$sLoginMode][$sContext] = [ $sFile ];
			}
		}

	    $aSessionElapsedMaxPerOrg = [];
	    $aElapsedSumPerOrg = [];
	    //$now = time();

	    foreach ($aUnexpiredFilesPerOrg as $sOrgUid => $aUnexpiredFiles) {
		    $aSessionElapsedMax = [];
		    $aElapsedSum = [];
		    foreach ($aUnexpiredFiles as $sLoginMode => $aFilesPerContext) {
			    foreach ($aFilesPerContext as $sContext => $aFiles) {
				    foreach ($aFiles as $sFile) {
					    $iElapsedInSeconds = filemtime($sFile) - filectime($sFile);
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
		    }
		    $aSessionElapsedMaxPerOrg[$sOrgUid]=$aSessionElapsedMax;
		    $aElapsedSumPerOrg[$sOrgUid]=$aElapsedSum;
	    }

	    $aRes = [];
	    foreach ($aCountPerOrg as $sOrgUid => $aCount) {
		    foreach ($aCount as $sLoginMode => $aLoginModeRes) {
			    foreach ($aLoginModeRes as $sContext => $iCount) {
				    $aRes[] = new MonitoringMetric($this->sMetricName.'_count', "Active session count", $iCount,
					    ['login_mode' => $sLoginMode, 'context' => $sContext, 'org_uid' => $sOrgUid]);
			    }
		    }
	    }

	    foreach ($aElapsedSumPerOrg as $sOrgUid => $aElapsedSum) {
		    foreach ($aElapsedSum as $sLoginMode => $aLoginModeRes) {
			    foreach ($aLoginModeRes as $sContext => $iCount) {
				    $aRes[] = new MonitoringMetric($this->sMetricName.'_elapsedinsecond_sum',
					    "Sum of active session elapsed time in seconds", $iCount,
					    ['login_mode' => $sLoginMode, 'context' => $sContext, 'org_uid' => $sOrgUid]);
			    }
		    }
	    }

	    foreach ($aSessionElapsedMaxPerOrg as $sOrgUid => $aSessionElapsedMax) {
		    foreach ($aSessionElapsedMax as $sLoginMode => $aLoginModeRes) {
			    foreach ($aLoginModeRes as $sContext => $iCount) {
				    $aRes[] = new MonitoringMetric($this->sMetricName.'_elapsedinsecond_max',
					    "Max elapsed time in seconds amoung active sessions", $iCount,
					    ['login_mode' => $sLoginMode, 'context' => $sContext, 'org_uid' => $sOrgUid]);
			    }
		    }
	    }

		foreach ($this->aFieldInSessionByOtherMetricName as $sMetricName =>$sField){
			$aCount = $aCountPerField[$sField];

		    foreach ($aCount as $sVal => $iCount) {
			    $aRes[] = new MonitoringMetric($sMetricName.'_count', "Active session $sField count", $iCount,
				    [$sField => $sVal ]);
		    }
	    }

        return $aRes;
    }

	private function FetchOrgUid(array $aData) : string {
		$sOrgUid = $aData['org_uid'] ?? null;
		if (! is_null($sOrgUid)) {
			return $sOrgUid;
		}

		$sOrgId = $aData['org_id'] ?? "0";
		$sUserId = $aData['user_id'] ?? "0";

		if ($sUserId === "0" && $sOrgId === "0"){
			return self::NO_ORG_UID;
		}

		if ($sOrgId === "0") {
			try {
				$oUser = MetaModel::GetObject(\User::class, $sUserId);
				$oContact = $oUser->GetContactObject();
				$sOrgId = $oContact ? $oContact->Get('org_id') : "0";
			} catch (\Exception $e) {
				\IssueLog::Warning(__METHOD__.': per user_id', null, ['org_id' => $sOrgId, 'error' => $e->getMessage()]);
				return self::NO_ORG_UID;
			}
		}

		if ($sOrgId !== "0") {
			if (array_key_exists($sOrgId, $this->aOrgUids)){
				return $this->aOrgUids[$sOrgId];
			}

			try {
				$oOrg = MetaModel::GetObject(Organization::class, $sOrgId);
				$sName = $oOrg->Get($this->sOrgUidField);
				$this->aOrgUids[$sOrgId] = $sName;

				return $sName;
			} catch (\Exception $e) {
				\IssueLog::Warning(__METHOD__ . ': per org_id', null, ['org_id' => $sOrgId, 'error' => $e->getMessage()]);
				$this->aOrgUids[$sOrgId] = self::NO_ORG_UID;
				return self::NO_ORG_UID;
			}
		}

		return self::NO_ORG_UID;
	}
}
