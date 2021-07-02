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
use Combodo\iTop\Monitoring\MetricReader\CustomReaderInterface;
use IssueLog;

class X509CertificateReader implements CustomReaderInterface
{
    private $aMetricConf;
    private $sMetricName;
    
    public function __construct($sMetricName, $aMetricConf)
    {
        $this->aMetricConf = $aMetricConf ?? 'days_until_certificate_expiration';
        $this->sMetricName = $sMetricName;
    }
    
    /**
     * @inheritDoc
     */
    public function GetMetrics(): ?array
    {
        $x509cert = $this->aMetricConf['certificate_file'];
        $labels = $this->aMetricConf['labels'] ?? [];
        if (!is_readable($x509cert))
        {
            IssueLog::Error("Combodo-monitoring - Error: failed to read certificate file '$x509cert'.");
        }
        $cert = openssl_x509_parse(file_get_contents($x509cert));
        if (is_array($cert))
        {
            $iValidTo = (int)($cert['validTo_time_t'] ?? time());
            $iValidFrom = (int)($cert['validFrom_time_t'] ?? (time() - 86400));
            if ($iValidFrom > time())
            {
                // Certificate is not yet valid !! report a negative value and log a warning
                IssueLog::Error("Combodo-monitoring - Warning: The certificate '$x509cert' is NOT YET valid. validFrom: '{$cert['validFrom']}'.");
                $iRemainingDays = ceil((time() - $iValidFrom)/86400);
            }
            else
            {
                $iRemainingDays = floor(($iValidTo - time())/86400);
            }
            
        }
        else
        {
            IssueLog::Error("Combodo-monitoring - Error: failed to decode certificate file '$x509cert'.");
            $iRemainingDays = -1;
        }
        
        $sDesc = $this->aMetricConf[Constants::METRIC_DESCRIPTION] ?? 'Number of days until certificate expiration';
        
        return [ new MonitoringMetric($this->sMetricName, $sDesc, $iRemainingDays, $labels) ];
    }
}