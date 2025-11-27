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

class ConfReader implements MetricReaderInterface
{
	public const CONF = 'conf';

	protected $sMetricName;
	protected $aMetric;

	public function __construct($sMetricName, $aMetric)
	{
		$this->sMetricName = $sMetricName;
		$this->aMetric = $aMetric;
	}

	/**
	 * {@inheritDoc}
	 */
	public function GetMetrics(): ?array
	{
		if (!is_array($this->aMetric) || !array_key_exists(self::CONF, $this->aMetric)) {
			return null;
		}

		$sValue = $this->GetValue();

		$sDescription = $this->aMetric[Constants::METRIC_DESCRIPTION];
		$aLabels = $this->aMetric[Constants::METRIC_LABEL] ?? [];

		return [new MonitoringMetric($this->sMetricName, $sDescription, $sValue, $aLabels)];
	}

	private function GetValue(?\Config $config = null)
	{
		$config = $config ?: \utils::GetConfig();

		$aMetricConf = $this->aMetric[self::CONF] ?: [];

		if (!is_array($aMetricConf)) {
			throw new \Exception(sprintf('Metric %s is not configured with a proper array ("%s" given).', $this->sMetricName, $aMetricConf));
		}

		if ('MySettings' == $aMetricConf[0]) {
			if (!$config->IsProperty($aMetricConf[1])) {
				throw new \Exception("Metric $this->sMetricName was not found in configuration found.");
			}

			$sValue = $config->Get($aMetricConf[1]);
			$aParamPath = array_slice($aMetricConf, 2);
		} else {
			$sValue = $config->GetModuleSetting($aMetricConf[1], $aMetricConf[2], null);
			$aParamPath = array_slice($aMetricConf, 3);
		}

		foreach ($aParamPath as $key) {
			$sValue = $sValue[$key] ?? null;
		}

		if (is_null($sValue)) {
			throw new \Exception("Metric $this->sMetricName was not found in configuration found.");
		}

		return $sValue;
	}
}
