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


use Combodo\iTop\Monitoring\Model\Constants;
use Combodo\iTop\Monitoring\Model\MonitoringMetric;

class CountTextOccurrences implements \Combodo\iTop\Monitoring\MetricReader\CustomReaderInterface
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
        $iCounter = $this->FetchCounter();

        $sDesc = $this->aMetricConf[Constants::METRIC_DESCRIPTION] ?? '';

        return [ new MonitoringMetric($this->sMetricName, $sDesc, $iCounter)];
    }

    /**
     * @return int
     * @throws \Exception
     */
    private function FetchCounter(): int
    {
        $sDeadlockFilePath = $this->aMetricConf[Constants::CUSTOM]['file'] ?? APPROOT.'log/error.log';
        if (!file_exists($sDeadlockFilePath))
        {
            $iCounter = 0;
        }
        else
        {
            if (!isset($this->aMetricConf[Constants::CUSTOM]['needle']))
            {
                throw new \Exception('key "needle" not found for metric with conf '.var_export($this->aMetricConf, true));
            }

            $sNeedle = $this->aMetricConf[Constants::CUSTOM]['needle'];

            $iCounter = mb_substr_count(
                file_get_contents($sDeadlockFilePath),
                $sNeedle
            );
        }

        return $iCounter;
    }
}
