<?php
/**
 *  @copyright   Copyright (C) 2010-2019 Combodo SARL
 *  @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Integrity\Monitoring\Controller;

use Combodo\iTop\Application\TwigBase\Controller\Controller;
use PHPUnit\Runner\Exception;
use utils;

class CombodoMonitoringController extends Controller {
    const EXEC_MODULE = 'combodo-monitoring';
    const OQL_COUNT = 'oql_count';
    const OQL_GROUPBY = 'oql_groupby';
    const CONF = 'conf';
    const METRIC_DESCRIPTION = 'description';
    const METRIC_LABEL = 'label';
    const METRICS = 'metrics';

    public function OperationExposePrometheusMetrics() {
        $aParams = array();

        $aMetricParams = $this->ReadMetricConf();
        $aMetrics = $this->ReadMetrics($aMetricParams);

        $aTwigMetrics = [];
        if (is_array($aMetrics) && count($aMetrics) != 0){
            foreach ($aMetrics as $oMetric){
                /** @var CombodoMonitoringMetric $oMetric*/
                $aTwigMetrics[] = $oMetric;
            }
        }

        $aParams[self::METRICS] = $aTwigMetrics;
        //$this->DisplayPage($aParams, null, self::ENUM_PAGE_TYPE_TXT);


        header('Content-Type: text/plain; charset=UTF-8');
        $sOutput = "";
        foreach ($aMetrics as $oMetric){
            /** @var CombodoMonitoringMetric $oMetric*/
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
     */
    public function ReadMetricConf($sConfigFile=null){
        $aMetricParams = \utils::GetConfig()->GetModuleSetting(self::EXEC_MODULE, self::METRICS);
        foreach ($aMetricParams as $sKey => $oValue) {
            if (is_array($oValue) && array_key_exists(self::CONF, $oValue)) {
                if (strstr($oValue[self::CONF], '.')){
                    $sMetricConfig = explode(".", $oValue[self::CONF]);
                    $aMetricParams[$sKey][self::CONF] = $sMetricConfig;
                }
            }
        }
        return $aMetricParams;
    }

    /**
     * @param $aMetricParams
     * @return array
     */
    public function ReadMetrics($aMetricParams){
        /** @var array[CombodoMonitoringMetric] $aMetrics */
        $aMetrics = [];
        if (!is_array($aMetricParams) || empty($aMetricParams)){
            \IssueLog::Info("No metrics configured");
            return $aMetrics;
        }

        try {
            foreach ($aMetricParams as $sMetricName => $aMetric) {
                /** @var array[CombodoMonitoringMetric] $aCombodoMonitoringMetrics */
                $aCombodoMonitoringMetrics = $this->ComputeOqlMetrics($sMetricName, $aMetric);
                if (is_null($aCombodoMonitoringMetrics)){
                    $aCombodoMonitoringMetrics = $this->ComputeConfMetrics($sMetricName, $aMetric);
                }

                if (is_null($aCombodoMonitoringMetrics)) {
                    continue;
                }

                foreach ($aCombodoMonitoringMetrics as $oCombodoMonitoringMetrics){
                    $this->FillDescription($aMetric, $sMetricName, $oCombodoMonitoringMetrics);
                    $this->FillLabels($aMetric, $oCombodoMonitoringMetrics);
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
    public function ComputeOqlMetrics($sMetricName, $aMetric) {
        if (is_array($aMetric) && array_key_exists(self::OQL_COUNT, $aMetric)) {
            $oSearch = \DBSearch::FromOQL($aMetric[self::OQL_COUNT]);
            if (array_key_exists(self::OQL_GROUPBY, $aMetric)) {
                $aDynamicLabelFields = explode(",", $aMetric[self::OQL_GROUPBY]);
                if (count($aDynamicLabelFields)==0){
                    throw new \Exception("Strange configuration on $sMetricName:" . $aMetric[self::OQL_GROUPBY]);
                } else if (count($aDynamicLabelFields)==1){
                    throw new \Exception("Missing OQL field inside $sMetricName configuration:" . $aMetric[self::OQL_GROUPBY]);
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
                    $oCombodoMonitoringMetrics->AddLabel($sLabelName, $aRes[$sLabelName]);
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
    public function ComputeConfMetrics($sMetricName, $aMetric) {
        if (is_array($aMetric) && array_key_exists(self::CONF, $aMetric)) {
            $aConfParamPath = $aMetric[self::CONF];
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
    public function FillDescription($aMetric, $sMetricName, $combodoMonitoringMetric): void {
        $sDescription = "";
        if (array_key_exists(self::METRIC_DESCRIPTION, $aMetric)) {
            $sDescription = $aMetric[self::METRIC_DESCRIPTION];
        }

        if (empty($sDescription)) {
            throw new Exception("Metric $sMetricName has no sDescription. Please provide it.");
        }

        $combodoMonitoringMetric->SetDescription($sDescription);
    }

    /**
     * @param $aMetric
     * @param \Combodo\iTop\Integrity\Monitoring\Controller\CombodoMonitoringMetric $combodoMonitoringMetric
     */
    public function FillLabels($aMetric, $combodoMonitoringMetric): void {
        if (array_key_exists(self::METRIC_LABEL, $aMetric)) {
            $aLabelKeyValue = explode(",", $aMetric[self::METRIC_LABEL]);
            $combodoMonitoringMetric->AddLabel(trim($aLabelKeyValue[0]), trim($aLabelKeyValue[1]));
        }
    }
}