<?php
/**
 *  @copyright   Copyright (C) 2010-2019 Combodo SARL
 *  @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Integrity\Monitoring\Controller;

use Combodo\iTop\Application\TwigBase\Controller\Controller;
use PHPUnit\Runner\Exception;
use SetupUtils;
use utils;
use Combodo\iTop\Integrity\Monitoring\Controller\CombodoMonitoringMetric;

define('MONITORING_CONFIG_FILE', 'monitoring-itop.ini');
define('OQL_COUNT', 'oql_count');
define('CONF', 'conf');
define('METRIC_DESCRIPTION', 'description');
define('METRICS', 'metrics');

class CombodoMonitoringController extends Controller {
    public function OperationExposeMetrics() {
        $aParams = array();

        $aIniParams = $this->readConf();
        $aMetrics = $this->readMetrics($aIniParams);

        $aTwigMetrics = [];
        if (is_array($aMetrics) && count($aMetrics) != 0){
            foreach ($aMetrics as $oMetric){
                /** @var CombodoMonitoringMetric $oMetric*/
                $aTwigMetrics[] = $oMetric;
            }
        }

        $aParams[METRICS] = $aTwigMetrics;
        $this->DisplayPage($aParams);
    }

    /**
     * @param null $sConfigFile
     */
    public function readConf($sConfigFile=null){
        $aIniParams = [];
        $sConfigFile = is_null($sConfigFile) ? APPCONF.'production/'.MONITORING_CONFIG_FILE : $sConfigFile;
        if (!is_file($sConfigFile) || !is_readable($sConfigFile)){
            \IssueLog::Error("Cannot read monitoring config file : $sConfigFile");
            return $aIniParams;
        }


        $aIniParams = parse_ini_file($sConfigFile, true);
        if (is_array($aIniParams) && array_key_exists(METRICS, $aIniParams)) {
            foreach ($aIniParams[METRICS] as $sKey => $oValue) {
                if (is_array($oValue) && array_key_exists(CONF, $oValue)) {
                    if (strstr($oValue[CONF], '.')){
                        $sMetricConfig = explode(".", $oValue[CONF]);
                        $aIniParams[METRICS][$sKey][CONF] = $sMetricConfig;
                    }
                }
            }
        }

        return $aIniParams;
    }

    /**
     * @param $aIniParams
     * @return array
     */
    public function readMetrics($aIniParams){
        /** @var array[CombodoMonitoringMetric] $aMetrics */
        $aMetrics = [];
        if (!is_array($aIniParams) || !array_key_exists(METRICS, $aIniParams)){
            \IssueLog::Info("No metrics configured");
            return $aMetrics;
        }

        try {
            foreach ($aIniParams[METRICS] as $sMetricName => $aMetric) {
                $combodoMonitoringMetric = $this->computeOqlMetric($sMetricName, $aMetric);
                if (is_null($combodoMonitoringMetric)){
                    $combodoMonitoringMetric = $this->computeConfMetric($sMetricName, $aMetric);
                }

                if (!is_null($combodoMonitoringMetric)){
                    $aMetrics[] = $combodoMonitoringMetric;
                }
            }
        }catch (\Exception $e){
            //fail and return nothing on purpose
            $aMetrics = [];
            \IssueLog::Error($e->getMessage());
        }

        return $aMetrics;
    }

    /**
     * @param $sMetricName
     * @param $aMetric
     * @return \Combodo\iTop\Integrity\Monitoring\Controller\CombodoMonitoringMetric|null
     * @throws \Exception
     */
    public function computeOqlMetric($sMetricName, $aMetric) {
        if (is_array($aMetric) && array_key_exists(OQL_COUNT, $aMetric)) {
            $oSearch = \DBSearch::FromOQL($aMetric[OQL_COUNT]);
            $oSet = new \DBObjectSet($oSearch);

            $sValue = "" . $oSet->Count();
            $sDescription = "";
            if (key_exists(METRIC_DESCRIPTION, $aMetric)) {
                $sDescription = $aMetric[METRIC_DESCRIPTION];
            }

            if (empty($sDescription)) {
                throw new Exception("Metric $sMetricName has no sDescription. Please provide it.");
            }

            return new CombodoMonitoringMetric($sMetricName, $sDescription, $sValue);
        }

        return null;
    }

    /**
     * @param $sMetricName
     * @param $aMetric
     * @return \Combodo\iTop\Integrity\Monitoring\Controller\CombodoMonitoringMetric|null
     * @throws \Exception
     */
    public function computeConfMetric($sMetricName, $aMetric) {
        if (is_array($aMetric) && array_key_exists(CONF, $aMetric)) {
            $aConfParamPath = $aMetric[CONF];
            if (!empty($aConfParamPath)) {
                $sType = null;
                $sModule = null;
                foreach ($aConfParamPath as $sConfParam){
                    if (is_null($sType)){
                        $sType = $sConfParam;
                        continue;
                    }
                    if ($sType==='MySettings'){
                        $sValue = utils::GetConfig()->Get($sConfParam);
                        break;
                    } else if ($sType==='MyModuleSettings'){
                        if (is_null($sModule)){
                            $sModule = $sConfParam;
                            continue;
                        }
                        $sValue = utils::GetConfig()->GetModuleSetting($sModule, $sConfParam, null);
                        break;
                    } /*else if ($sType==='MyModules'){
                    $sKey = key($aPath);
                    $sKey2 = $aPath[$sKey];
                    $aAddons = utils::GetConfig()->GetAddons();
                    if (array_key_exists($sKey, $aAddons) && array_key_exists($sKey2, $aAddons[$sKey])){
                        $sValue = $aAddons[$sKey][$sKey2];
                    }
                }*/
                }
            }

            if (is_null($sValue)) {
                throw new Exception("Metric $sMetricName was not found in configuration found.");
            }

            $sDescription = "";
            if (key_exists(METRIC_DESCRIPTION, $aMetric)) {
                $sDescription = $aMetric[METRIC_DESCRIPTION];
            }

            if (empty($sDescription)) {
                throw new Exception("Metric $sMetricName has no sDescription. Please provide it.");
            }

            return new CombodoMonitoringMetric($sMetricName, $sDescription, "" . $sValue);
        }

        return null;
    }
}