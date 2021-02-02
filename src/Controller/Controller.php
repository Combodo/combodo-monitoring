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
use Symfony\Component\HttpFoundation\IpUtils;
use utils;

class Controller extends BaseController {
    /** @var string */
    private $m_sAccessAuthorizedNetworkConfigParamId = null;

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

    /**
     * Check if page access is allowed to remote network
     *
     * @throws \Exception
     */
    public function CheckNetworkAccess()
    {
        $sExecModule = utils::ReadParam('exec_module', "");

        if (empty($sExecModule) || empty($this->m_sAccessAuthorizedNetworkConfigParamId)){
            return;
        }

        $aReadAllowedNetworkRegexpPatterns = \MetaModel::GetConfig()->GetModuleSetting($sExecModule, $this->m_sAccessAuthorizedNetworkConfigParamId);
        if (!is_array($aReadAllowedNetworkRegexpPatterns)){
            \IssueLog::Error("'$sExecModule' wrongly configured. please check $this->m_sAccessAuthorizedNetworkConfigParamId config (not an array).");
            return;
        } else if (empty($aReadAllowedNetworkRegexpPatterns)){
            //no rule
            return;
        }

        $aNetworks = [];

        foreach ($aReadAllowedNetworkRegexpPatterns as $sAllowedNetworkRegexpPattern){
            $aNetworks []= trim($sAllowedNetworkRegexpPattern);
        }

        $clientIp = $_SERVER['REMOTE_ADDR'];
        if (!IpUtils::checkIp($clientIp, $aNetworks)){
            \IssueLog::Error("'$sExecModule' page is not authorized to '$clientIp' ip address.");
            http_response_code(500);
            $aResponse = array('sError' => "Exception : Unauthorized network ($clientIp)");
            echo json_encode($aResponse);
        }
    }

    public function CheckIpFunction(string $clientIp, array $aNetworks){
        return IpUtils::checkIp($clientIp, $aNetworks);

        //if that logic is moved back to iTop core: use below logic with
        //https://github.com/rlanvin/php-ip
        //required: composer require rlanvin/php-ip
        //
        /*foreach ($aNetworks as $sNetwork){
            try{
                $block = IPBlock::create($sNetwork);

                if ($block->contains($clientIp)){
                    return true;
                }
            } catch (\InvalidArgumentException $e){
                //not a network: InvalidArgumentException : 127.0.0.2 does not appear to be an IPv4 or IPv6 block
                //IP usecase
                if ($sNetwork == $clientIp){
                    return true;
                }
            }
        }
        return false;*/
    }

    /**
     * Used to ensure iTop security by serving HTTP page to a specific subset of remote networks (white list mode).
     * This security mechanism is applied to current extension when :
     *  - '$m_sAccessAuthorizedNetworkConfigParamId' is configured under $MyModuleSettings section.
     *
     * Extension page will be allowed as long as iTop '$m_sAccessAuthorizedNetworkConfigParamId' regexp configuration value matches $_SERVER['REMOTE_ADDR'] IP address.
     *
     * Example:
     * Let's assume $m_sAccessAuthorizedNetworkConfigParamId='allowed_networks' with iTop $MyModuleSettings below configuration:
     *      'combodo-shadok' => array ( 'allowed_networks' => ['10.0.0.0', '20.0.0.0/24'])
     * 'combodo-shadok' extension main page is rendered only for HTTP client under 10.X.X.X networks.
     * Otherwise an HTTP error code 500 will be returned.
     *
     */
    public function SetAccessAuthorizedNetworkConfigParamId(string $m_sAccessAuthorizedNetworkConfigParamId): void
    {
        $this->m_sAccessAuthorizedNetworkConfigParamId = trim($m_sAccessAuthorizedNetworkConfigParamId) ?? "";
    }
}