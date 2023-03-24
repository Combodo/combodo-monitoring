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

class ItopSynchroLogReader implements CustomReaderInterface
{
	const STRTOTIME_MIN_STARTDATE="STRTOTIME_MIN_STARTDATE";

    private $aMetricConf;
    private $sMetricName;

    public function __construct($sMetricName, $aMetricConf)
    {
        $this->aMetricConf = $aMetricConf;
        $this->sMetricName = 'itop_synchrolog_';
    }

    /**
     * {@inheritDoc}
     */
    public function GetMetrics(): ?array
    {
        $aMetrics=[];

        if (array_key_exists(Constants::METRIC_LABEL, $this->aMetricConf)) {
            $aLabels = $this->aMetricConf[Constants::METRIC_LABEL];
        } else {
            $aLabels = [];
        }


	    $aSynchroLogObjects = $this->ListSynchroLogObjects();

	    $aErrorFields = [
		    'stats_nb_obj_obsoleted_errors',
		    'stats_nb_obj_deleted_errors',
		    'stats_nb_obj_created_errors',
		    'stats_nb_obj_updated_errors',
		    'stats_nb_replica_reconciled_errors'
	    ];

	    foreach($aSynchroLogObjects as $sSynchroSourceName => $oSynchroLog){
		    $iErrors = 0;
		    $sStatus = $oSynchroLog->Get('status');
			$aCurrentLabels= array_merge($aLabels,
				[
					'status'=> $sStatus,
					'source'=> $sSynchroSourceName,
				]
			);

		    foreach ($aErrorFields as $sField){
			    $iErrors += $oSynchroLog->Get($sField);
		    }

		    $aMetrics[] = new MonitoringMetric($this->sMetricName.'error_count',
			    'synchro log error count.',
			    $iErrors,
			    $aCurrentLabels
		    );

		    $aMetrics[] = new MonitoringMetric($this->sMetricName.'replica_count',
			    'synchro log replica count.',
			    $oSynchroLog->Get('stats_nb_replica_total'),
			    $aCurrentLabels
		    );

		    $sStartDate = $oSynchroLog->Get('start_date');
		    $oStartDate = \DateTime::createFromFormat(\AttributeDateTime::GetSQLFormat(), $sStartDate);
		    $iAgeInMinutes = (int) ((strtotime('now') - $oStartDate->getTimestamp()) / 60);
		    $aMetrics[] = new MonitoringMetric($this->sMetricName.'inminutes_age',
			    'synchro log age in minutes.',
			    $iAgeInMinutes,
			    $aCurrentLabels
		    );

		    if ('running' === $sStatus){
			    $iElapsedInSeconds=$iAgeInMinutes*60;
		    } else {
			    $sEndDate = $oSynchroLog->Get('end_date');
			    $oEndDate = \DateTime::createFromFormat(\AttributeDateTime::GetSQLFormat(), $sEndDate);
			    $iElapsedInSeconds = (int) (($oEndDate->getTimestamp() - $oStartDate->getTimestamp()));
		    }

			if ($iElapsedInSeconds<0){
				$iElapsedInSeconds=0;
			}

		    $aMetrics[] = new MonitoringMetric($this->sMetricName.'inseconds_elapsed',
			    'synchro log elapsed time in seconds.',
			    $iElapsedInSeconds,
			    $aCurrentLabels
		    );
	    }

        return $aMetrics;
    }

    /**
     * List last synchrolog per synchrosource
     *
     * @return array
     */
    public function ListSynchroLogObjects() : array
    {
		if (array_key_exists(self::STRTOTIME_MIN_STARTDATE, $this->aMetricConf)){
			$sLimit = $this->aMetricConf[self::STRTOTIME_MIN_STARTDATE];
		} else {
			$sLimit = '-24 HOURS';
		}
	    $currentDate = date(\AttributeDateTime::GetSQLFormat(), strtotime($sLimit));

	    $sOql = <<<OQL
SELECT sl, sds FROM 
SynchroLog AS sl
JOIN SynchroDataSource AS sds
ON sl.sync_source_id = sds.id
WHERE sl.start_date>"$currentDate"
OQL;

	    $oSearch = \DBObjectSearch::FromOQL($sOql);
	    $oSet = new \DBObjectSet($oSearch, ['start_date'=> false], [], null, 0 , 1);
	    $aOptimizeColumnsLoad=[
		    'sds' => [
				'name',
		    ],
		    'sl' => [
				'stats_nb_replica_total',
				'start_date',
				'end_date',
			    'status',
			    'stats_nb_obj_obsoleted_errors',
			    'stats_nb_obj_deleted_errors',
			    'stats_nb_obj_created_errors',
			    'stats_nb_obj_updated_errors',
			    'stats_nb_replica_reconciled_errors'
		    ],
	    ];
	    $oSet->OptimizeColumnLoad($aOptimizeColumnsLoad);
	    $aSynchroLogs = [];

	    /* var DBObject $oObject  */
	    while ($aObjects = $oSet->FetchAssoc()) {
		    $oSynchroLog = $aObjects['sl'];
		    $oSynchroDataSource = $aObjects['sds'];

		    $sSourceName = $oSynchroDataSource->Get('name');
		    if (array_key_exists($sSourceName, $aSynchroLogs)) {
				continue;
			}

		    $aSynchroLogs[$sSourceName] = $oSynchroLog;
	    }

        return $aSynchroLogs;
    }
}
