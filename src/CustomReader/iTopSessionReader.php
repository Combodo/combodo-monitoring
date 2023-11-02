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
use Combodo\iTop\Monitoring\Model\Constants;
use Combodo\iTop\Monitoring\Model\MonitoringMetric;

class iTopSessionReader implements \Combodo\iTop\Monitoring\MetricReader\CustomReaderInterface
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
	    if (! class_exists('Combodo\iTop\Application\Helper\iTopSessionHandler')) {
			\IssueLog::Error("iTopSessionHandler class does not exist. Metric iTopSessionReader is not working with current iTop version");
			return 0;
	    }

		$oItopSessionHandler = new iTopSessionHandler();
        $aFiles = $oItopSessionHandler->ListSessionFiles();

        return sizeof($aFiles);
    }
}
