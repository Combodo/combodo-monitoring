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

class CustomReader implements MetricReaderInterface
{
	protected $sMetricName;
	protected $aMetric;

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
		$sClassName = $this->aMetric[Constants::CUSTOM]['class'] ?? null;

		if (!is_a($sClassName, CustomReaderInterface::class, true)) {
			throw new \Exception("Metric $this->sMetricName is not properly configured: '$sClassName' must implement ".CustomReaderInterface::class);
		}

		$oCustomReader = new $sClassName($this->sMetricName, $this->aMetric);
		return $oCustomReader->GetMetrics() ;
	}
}
