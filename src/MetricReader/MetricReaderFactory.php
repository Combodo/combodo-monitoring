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

class MetricReaderFactory
{

    public function GetReader($sMetricName, $aMetric): ?MetricReaderInterface
    {
        if (!is_array($aMetric) ) {
            return null;
        }

        $aKeyToClass = [
            Constants::OQL_SELECT => OqlSelectReader::class,
            Constants::CUSTOM => CustomReader::class,
            Constants::OQL_GROUPBY => OqlGroupByReader::class,
            Constants::OQL_COUNT => OqlCountReader::class,
            Constants::CONF => ConfReader::class,
            Constants::OQL_COUNT_UNIQUE => OqlCountUniqueReader::class,
        ];

        $intersec = array_intersect_key($aKeyToClass, $aMetric);
        $count = count($intersec);

        if ($count == 0) {
            throw new \Exception(sprintf('reader not found for metric %s', $sMetricName));
        }

        if ($count > 1) {
            $aKeys = implode(', ', array_keys($intersec));
            throw new \Exception(sprintf('only one metric at a time is authorized (found: %s) for metric %s', $aKeys, $sMetricName));
        }

        $className = reset($intersec);
        $oReader = new $className($sMetricName, $aMetric);

        return $oReader;
    }
}