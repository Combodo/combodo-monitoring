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

class SqlSelectReader implements MetricReaderInterface
{
	protected $sMetricName;
	protected $aMetric;
	protected $sDefaultAlias;

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
		$sDescription = $this->aMetric[Constants::METRIC_DESCRIPTION];
		$aStaticLabels = $this->aMetric[Constants::METRIC_LABEL] ?? [];
		$aColumns = $this->aMetric[Constants::SQL_SELECT][Constants::LABELS] ?? [];
		$sColumnValue = $this->aMetric[Constants::SQL_SELECT][Constants::VALUE] ?? null;

		if ($sColumnValue === null) {
			throw new \Exception("Metric $this->sMetricName Must provide at least one value to be read. " . var_export($this->aMetric, true));
		}

		$sSqlQuery = $this->aMetric[Constants::SQL_SELECT][Constants::SELECT];
		$oResult = \CMDBSource::Query($sSqlQuery);

		$aResults = $oResult->fetch_all(MYSQLI_ASSOC);
		$aMetrics = [];
		foreach ($aResults as $aResult) {
			$aLabels = [];
			foreach ($aStaticLabels as $sStaticLabel => $sStaticLabelValue){
				$aLabels[$sStaticLabel] = $sStaticLabelValue;
			}

			foreach ($aColumns as $sColumnLabel => $sColumnName){
				$aLabels[$sColumnLabel] = $aResult[$sColumnName];
			}
			$sValue = $aResult[$sColumnValue];
			$aMetrics[] = new MonitoringMetric($this->sMetricName, $sDescription, $sValue, $aLabels);
		}

		return $aMetrics;
	}
}
