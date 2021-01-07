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
define('OQL_GROUPBY', 'oql_groupby');
define('CONF', 'conf');
define('METRIC_DESCRIPTION', 'description');
define('METRIC_LABEL', 'label');
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
        $this->DisplayPage($aParams, null, self::PAGE_TYPE_BASIC_HTML);
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
                /** @var array[CombodoMonitoringMetric] $aCombodoMonitoringMetrics */
                $aCombodoMonitoringMetrics = $this->computeOqlMetrics($sMetricName, $aMetric);
                if (is_null($aCombodoMonitoringMetrics)){
                    $aCombodoMonitoringMetrics = $this->computeConfMetrics($sMetricName, $aMetric);
                }

                if (is_null($aCombodoMonitoringMetrics)) {
                    continue;
                }

                foreach ($aCombodoMonitoringMetrics as $oCombodoMonitoringMetrics){
                    $this->fillDescription($aMetric, $sMetricName, $oCombodoMonitoringMetrics);
                    $this->fillLabels($aMetric, $oCombodoMonitoringMetrics);
                }

                $aMetrics = array_merge($aMetrics, $aCombodoMonitoringMetrics);
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
    public function computeOqlMetrics($sMetricName, $aMetric) {
        if (is_array($aMetric) && array_key_exists(OQL_COUNT, $aMetric)) {
            $oSearch = \DBSearch::FromOQL($aMetric[OQL_COUNT]);
            if (array_key_exists(OQL_GROUPBY, $aMetric)) {
                $aDynamicLabelFields = explode(",", $aMetric[OQL_GROUPBY]);
                if (count($aDynamicLabelFields)==0){
                    throw new \Exception("Strange configuration on $sMetricName:" . $aMetric[OQL_GROUPBY]);
                } else if (count($aDynamicLabelFields)==1){
                    throw new \Exception("Missing OQL field inside $sMetricName configuration:" . $aMetric[OQL_GROUPBY]);
                }

                $sLabelName = trim($aDynamicLabelFields[0]);
                $sOqlField = trim($aDynamicLabelFields[1]);
                $oExpr = \Expression::FromOQL($sOqlField);
                $aGroupByExpr=[ $sLabelName => $oExpr ];
                return $this->FetchGroupByMetrics($sMetricName, $oSearch, $aGroupByExpr);
            } else{
                $oSet = new \DBObjectSet($oSearch);
                return [ new CombodoMonitoringMetric($sMetricName, "",  "" . $oSet->Count()) ] ;
            }
        }

        return null;
    }

    /**
     * @param string $sMetricName
     * @param \DBSearch $oSearch
     * @param $aGroupByExpr
     * @return array|null
     * @throws \CoreException
     * @throws \MySQLException
     * @throws \MySQLHasGoneAwayException
     */
    private function FetchGroupByMetrics($sMetricName, $oSearch, $aGroupByExpr)
    {
        $sSQL = $oSearch->MakeGroupByQuery([], $aGroupByExpr);
        $resQuery = \CMDBSource::Query($sSQL);
        if (!$resQuery)
        {
            return null;
        }
        else
        {
            $aCombodoMonitoringMetrics = [];
            while ($aRes = \CMDBSource::FetchArray($resQuery)) {
                $sValue = $aRes['_itop_count_'];
                $oCombodoMonitoringMetrics = new CombodoMonitoringMetric($sMetricName, "", $sValue);
                foreach (array_keys($aGroupByExpr) as $sLabelName) {
                    $sLabelName = $sLabelName;
                    $oCombodoMonitoringMetrics->addLabel($sLabelName, $aRes[$sLabelName]);
                }
                $aCombodoMonitoringMetrics[] = $oCombodoMonitoringMetrics;
                unset($aRes);
            }
            \CMDBSource::FreeResult($resQuery);
            return $aCombodoMonitoringMetrics;
        }
    }


    /**
     * @param $sMetricName
     * @param $aMetric
     * @return array[\Combodo\iTop\Integrity\Monitoring\Controller\CombodoMonitoringMetric]|null
     * @throws \Exception
     */
    public function computeConfMetrics($sMetricName, $aMetric) {
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

            return [ new CombodoMonitoringMetric($sMetricName, "", "" . $sValue) ] ;
        }

        return null;
    }

    /**
     * @param $aMetric
     * @param string $sMetricName
     * @param \Combodo\iTop\Integrity\Monitoring\Controller\CombodoMonitoringMetric $combodoMonitoringMetric
     */
    public function fillDescription($aMetric, $sMetricName, $combodoMonitoringMetric): void {
        $sDescription = "";
        if (array_key_exists(METRIC_DESCRIPTION, $aMetric)) {
            $sDescription = $aMetric[METRIC_DESCRIPTION];
        }

        if (empty($sDescription)) {
            throw new Exception("Metric $sMetricName has no sDescription. Please provide it.");
        }

        $combodoMonitoringMetric->setDescription($sDescription);
    }

    /**
     * @param $aMetric
     * @param \Combodo\iTop\Integrity\Monitoring\Controller\CombodoMonitoringMetric $combodoMonitoringMetric
     */
    public function fillLabels($aMetric, $combodoMonitoringMetric): void {
        if (array_key_exists(METRIC_LABEL, $aMetric)) {
            $aLabelKeyValue = explode(",", $aMetric[METRIC_LABEL]);
            $combodoMonitoringMetric->addLabel(trim($aLabelKeyValue[0]), trim($aLabelKeyValue[1]));
        }
    }
}