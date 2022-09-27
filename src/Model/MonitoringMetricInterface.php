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

namespace Combodo\iTop\Monitoring\Model;

/**
 * Interface MonitoringMetricInterface
 *
 * @package Combodo\iTop\Monitoring\Model
 */
interface MonitoringMetricInterface {
	/**
	 * get metric name
	 */
    public function GetName() : string;

	/**
	 * get metric name
	 */
    public function GetDescription(): string;

	/**
	 * return metric value... should be a float cast into string otherwise prometheus will consider target as down (unparsable format)
	 */
    public function GetValue(): string;

    /**
     * return metric labels (key/values)
     * example: [ 'impact' => "object-storage-collector", 'issue' => "Collect-BackupArchivalCollector"  ]
    ),
     * @return string[]
     */
    public function GetLabels() : array ;
}
