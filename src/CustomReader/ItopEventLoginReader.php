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

class ItopEventLoginReader implements CustomReaderInterface
{
    private $aMetricConf;
    private $sMetricName;

    public function __construct($sMetricName, $aMetricConf)
    {
        $this->aMetricConf = $aMetricConf;
        $this->sMetricName = 'itop_eventlogin';
    }

    /**
     * {@inheritDoc}
     */
    public function GetMetrics($sDir = null): ?array
    {
        $aMetrics = [];

        if (array_key_exists(Constants::METRIC_LABEL, $this->aMetricConf)) {
            $aLabels = $this->aMetricConf[Constants::METRIC_LABEL];
        } else {
            $aLabels = [];
        }

        foreach ($this->ListEventLogin() as $aEventLoginMetric) {
            $aCurrentLabels = array_merge([], $aLabels);
            $aCurrentLabels['profiles'] = $aEventLoginMetric['labels']['profiles'];
            $aCurrentLabels['account_type'] = $aEventLoginMetric['labels']['account_type'];

            $aMetrics[] = new MonitoringMetric($this->sMetricName,
                'nb of connection in the last 1 hour.',
                $aEventLoginMetric['count'],
                $aCurrentLabels
            );
        }

        return $aMetrics;
    }

    public function ListEventLogin(): array
    {
        $currentDate = date(\AttributeDateTime::GetSQLFormat(), strtotime('-1 HOURS'));

        $sOql = <<<OQL
SELECT u,e FROM EventLoginUsage AS e JOIN User AS u ON e.user_id=u.id
WHERE e.date> ":date"
OQL;

        $oSearch = \DBObjectSearch::FromOQL($sOql, ['date' => $currentDate]);
        $oSet = new \DBObjectSet($oSearch);

        $aRes = [];

        /* var DBObject $oObject  */
        while ($aObjects = $oSet->FetchAssoc()) {
            $sProfiles = null;
            $oUser = $aObjects['u'];

            $oProfileSet = $oUser->Get('profile_list');
            while ($oProfile = $oProfileSet->Fetch()) {
                $sProfile = str_replace(" ","_",strtolower($oProfile->Get('profile')));
                $sProfiles = (is_null($sProfiles)) ? $sProfile : "$sProfiles+$sProfile";
            }

            $sAccountType = strtolower(get_class($oUser));
            $sKey = "${sProfiles}_$sAccountType";
            if (isset($aRes[$sKey])) {
                $aRes[$sKey]['count'] = $aRes[$sKey]['count'] + 1;
            } else {
                $aRes[$sKey] = [
                    'count' => 1,
                    'labels' => [
                        'profiles' => $sProfiles,
                        'account_type' => $sAccountType,
                    ],
                ];
            }
        }

        return $aRes;
    }
}
