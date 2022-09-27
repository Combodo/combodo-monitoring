<?php
/**
 *  @copyright   Copyright (C) 2010-2019 Combodo SARL
 *  @license     http://opensource.org/licenses/AGPL-3.0
 */
namespace Combodo\iTop\Monitoring\Service;

use Combodo\iTop\Monitoring\MetricReader\MetricReaderFactory;
use Combodo\iTop\Monitoring\Model\MonitoringMetric;
use Combodo\iTop\Monitoring\Model\Constants;
use utils;

class MonitoringService {
    /**
     * @param string $sCollectionName
     * @return array[MonitoringMetricInterface]
     * @throws \Exception
     */
    public function GetMetrics(string $sCollectionName) : array {
        $sCollection = utils::ReadParam('collection', null);

        if (is_null($sCollection)) {
            throw new \Exception('Missing mandatory GET parameter collection');
        }

        $aMetricParams = $this->ReadMetricConf($sCollection);

        $aMetricsWithDuplicas = $this->ReadMetrics($aMetricParams);

        //deduplicate metrics
	    return $this->RemoveDuplicates($aMetricsWithDuplicas);
    }

    public function RemoveDuplicates(array $aDuplicateMetrics) : array
    {
	    $aMetrics = [];

	    if (sizeof($aDuplicateMetrics) === 0){
	    	return $aMetrics;
	    }

	    foreach ($aDuplicateMetrics as $oMetric){
		    /** @var MonitoringMetric $oMetric*/
		    $sKey = sprintf("%s_%s",
			    $oMetric->GetName(),
			    implode("_", $oMetric->GetLabels())
		    );

		    if (array_key_exists($sKey, $aMetrics)){
		    	continue;
		    }

		    $aMetrics[$sKey] = $oMetric;
	    }

	    return array_values($aMetrics);
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
            return $aMetrics;
        }

        $oMetricReaderFactory = new MetricReaderFactory();

        try {
            foreach ($aMetricParams as $sMetricName => $aMetric) {

                if (!isset($aMetric[Constants::METRIC_DESCRIPTION])) {
                    throw new \Exception("Metric $sMetricName has no description. Please provide it.");
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
