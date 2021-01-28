<?php
/**
 *  @copyright   Copyright (C) 2010-2019 Combodo SARL
 *  @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Monitoring\Controller;

use Combodo\iTop\Monitoring\MetricReader\MetricReaderFactory;
use Combodo\iTop\Monitoring\Model\MonitoringMetric;
use Combodo\iTop\Monitoring\Model\Constants;
use Combodo\iTop\Application\TwigBase\Controller\Controller as BaseController;
use PHPUnit\Runner\Exception;
use utils;

class Controller extends BaseController {


    public function OperationExposePrometheusMetrics() {
        $sCollection = utils::ReadParam('collection', null);

        if (is_null($sCollection)) {
            throw new \Exception('Missing mandatory GET parameter collection');
        }

        $aMetricParams = $this->ReadMetricConf($sCollection);

        $aParams = array();

        $aMetrics = $this->ReadMetrics($aMetricParams);

        $aTwigMetrics = [];
        if (is_array($aMetrics) && count($aMetrics) != 0){
            foreach ($aMetrics as $oMetric){
                /** @var MonitoringMetric $oMetric*/
                $aTwigMetrics[] = $oMetric;
            }
        }

        $aParams[Constants::METRICS] = $aTwigMetrics;
        //$this->DisplayPage($aParams, null, Constants::ENUM_PAGE_TYPE_TXT);


        header('Content-Type: text/plain; charset=UTF-8');
        $sOutput = "";
        foreach ($aMetrics as $oMetric){
            /** @var MonitoringMetric $oMetric*/
            $sOutput .=  "# " . $oMetric->GetDescription() . "\n";
            $sLabels = "";
            foreach ($oMetric->GetLabels() as $sKey => $sValue) {
                $sLabels .= empty($sLabels) ? "" : ",";
                $sLabels .= "$sKey=\"$sValue\"" ;
            }
            $sOutput .= $oMetric->GetName() . "{" . $sLabels . "} " . $oMetric->GetValue() . "\n";
            $sOutput .=  "\n";
        }

        echo $sOutput;
    }

    /**
     * @param null $sConfigFile
     *
     * @throws \Exception
     */
    public function ReadMetricConf($sCollection, ?\Config $config = null){
        $config = $config ?: \utils::GetConfig();
        $aModuleSetting = $config->GetModuleSetting(Constants::EXEC_MODULE, Constants::METRICS);

        if (!array_key_exists($sCollection, $aModuleSetting)) {
            throw new \Exception(sprintf('Collection "%s" not found (should be an index of $MyModuleSettings["%s"]["%s"])', $sCollection, Constants::EXEC_MODULE, Constants::METRICS));
        }

        return $aModuleSetting[$sCollection];
    }

    /**
     * @param $aMetricParams
     * @return array
     */
    public function ReadMetrics($aMetricParams){
        /** @var array[MonitoringMetric] $aMetrics */
        $aMetrics = [];
        if (!is_array($aMetricParams) || empty($aMetricParams)){
            \IssueLog::Info("No metrics configured");
            return $aMetrics;
        }

        $oMetricReaderFactory = new MetricReaderFactory();

        try {
            foreach ($aMetricParams as $sMetricName => $aMetric) {

                if (!isset($aMetric[Constants::METRIC_DESCRIPTION])) {
                    throw new Exception("Metric $sMetricName has no sDescription. Please provide it.");
                }

                $oReader = $oMetricReaderFactory->GetReader($sMetricName, $aMetric);
                if (is_null($oReader)) {
                    continue;
                }

                $aMonitoringMetrics = $oReader->GetMetrics();

                if (is_null($aMonitoringMetrics)) {
                    continue;
                }

                $aMetrics = array_merge($aMetrics, $aMonitoringMetrics);
            }
        }catch (\Exception $e){
            //fail and return nothing on purpose
            $aMetrics = [];
            \IssueLog::Error($e->getMessage());
        }

        return $aMetrics;
    }


}