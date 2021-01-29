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

        switch (true) {
            case array_key_exists(Constants::OQL_SELECT, $aMetric):
                $oReader = new OqlSelectReader($sMetricName, $aMetric);
                break;
            case array_key_exists(Constants::CUSTOM, $aMetric):
                $oReader = new CustomReader($sMetricName, $aMetric);
                break;
            case array_key_exists(Constants::OQL_GROUPBY, $aMetric):
                $oReader = new OqlGroupByReader($sMetricName, $aMetric);
                break;
            case array_key_exists(Constants::OQL_COUNT, $aMetric):
                $oReader = new OqlCountReader($sMetricName, $aMetric);
                break;
            case array_key_exists(Constants::CONF, $aMetric):
                $oReader = new ConfReader($sMetricName, $aMetric);
                break;
            default:
                throw new \Exception(sprintf('reader not found for metric %s', $sMetricName));

        }

        return $oReader;
    }
}