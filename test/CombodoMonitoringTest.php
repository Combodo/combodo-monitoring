<?php

use Combodo\iTop\Monitoring\Controller\Controller;
use Combodo\iTop\Monitoring\Model\Constants;
use Combodo\iTop\Monitoring\Model\MonitoringMetric;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;

class CombodoMonitoringTest extends ItopDataTestCase
{
    private $sUrl;
    private $sConfigFile;
    private $sConfBackupPath;

    public function setUp()
    {
        //@include_once '/home/nono/PhpstormProjects/iTop/approot.inc.php';
        //@include_once '/home/combodo/workspace/iTop/approot.inc.php';

        parent::setUp();

        require_once APPROOT.'core/config.class.inc.php';
        require_once APPROOT.'application/utils.inc.php';

        if (!defined('MODULESROOT')) {
            define('MODULESROOT', APPROOT.'env-production/');
        }

        $this->sConfigFile = \utils::GetConfig()->GetLoadedFile();
        @chmod($this->sConfigFile, 0770);
        $this->sUrl = \MetaModel::GetConfig()->Get('app_root_url').'/pages/exec.php?exec_module=combodo-monitoring&exec_page=index.php&exec_env=production';
        @chmod($this->sConfigFile, 0444); // Read-only

        $this->sConfBackupPath = tempnam(sys_get_temp_dir(), 'conf.php');
        copy($this->sConfigFile, $this->sConfBackupPath);
    }

    public function tearDown()
    {
        @chmod($this->sConfigFile, 0770);
        copy($this->sConfBackupPath, $this->sConfigFile);
        @chmod($this->sConfigFile, 0444); // Read-only
    }

    private function CallRestApi($sUrl)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "$sUrl");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $sContent = curl_exec($ch);
        $iCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [$sContent, $iCode];
    }

    public function testMonitoringPage()
    {
        $sRessourcesDir = __DIR__.'/ressources';
        $aMetricConf = [
            'collection1' => [
                'itop_user_select' => [
                    'description' => 'Name of profile (oql_select)',
	                'static_labels' => ['toto' => 'titi'],
                    'oql_select' => [
                        'select' => 'SELECT URP_UserProfile WHERE URP_UserProfile.userid=1',
                        'labels' => ['profile' => 'profile'],
                        'value' => 'userid',
                    ],
                ],
                'itop_user_count' => [
                    'description' => 'Nb of users (oql_count)',
                    'oql_count' => [
                        'select' => 'SELECT URP_UserProfile WHERE URP_UserProfile.userid=1',
                    ],
                    'static_labels' => ['toto' => 'titi'],
                ],
                'itop_user_groupby_count' => [
                    'description' => 'Nb of users (oql_groupby)',
                    'oql_groupby' => [
                        'select' => 'SELECT URP_UserProfile JOIN URP_Profiles ON URP_UserProfile.profileid =URP_Profiles.id WHERE URP_Profiles.id=1',
                        'groupby' => ['profile' => 'URP_UserProfile.profile'],
                    ],
                ],
                'itop_backup_retention_count' => [
                    'description' => 'Retention count (conf)',
                    'conf' => ['MyModuleSettings', 'itop-backup', 'retention_count'],
                    'static_labels' => ['shadok' => 'gabuzomeu'],
                ],
                'itop_custom' => [
                    'description' => 'custom class (custom)',
                    'custom' => ['class' => '\Combodo\iTop\Monitoring\Test\MetricReader\CustomReaders\CustomReaderImpl'],
                ],
                'itop_profile_unique_count' => [
                    'description' => 'number of profiles',
                    'oql_count_unique' => [
                        'select' => 'SELECT URP_Profiles',
                        'groupby' => ['name' => 'name'],
                    ],
                ],
	            'itop_version' => [
		            'description' => 'test itop version',
		            'custom' => ['class' => '\Combodo\iTop\Monitoring\CustomReader\ItopVersionReader'],
	            ],
	            'itop_setup_version' => [
		            'description' => 'test itop setup version',
		            'custom' => ['class' => '\Combodo\iTop\Monitoring\CustomReader\ItopSetupVersionReader'],
	            ],
            ],
        ];

        $oOqlCountUniqueReader = new \Combodo\iTop\Monitoring\MetricReader\OqlCountUniqueReader('itop_profile_unique_count', $aMetricConf['collection1']['itop_profile_unique_count']);
        /** @var MonitoringMetric $oMetric*/
        $oMetric = $oOqlCountUniqueReader->GetMetrics()[0];
        $iTopProfileUniqueCount = $oMetric->GetValue();

        @chmod($this->sConfigFile, 0770);
        \utils::GetConfig()->SetModuleSetting(Constants::EXEC_MODULE, 'access_token', 'toto123');
        \utils::GetConfig()->SetModuleSetting(Constants::EXEC_MODULE, 'authorized_network', []);
        \utils::GetConfig()->SetModuleSetting(Constants::EXEC_MODULE, Constants::METRICS, $aMetricConf);
        \utils::GetConfig()->WriteToFile();
        @chmod($this->sConfigFile, 0444); // Read-only

        $aResp = $this->CallRestApi("$this->sUrl&access_token=toto123&collection=collection1");

        $this->assertEquals(200, $aResp[1], $aResp[0]);
	    $sContent = file_get_contents("$sRessourcesDir/prometheus_content.txt");
	    $sContent = str_replace('XXX', $iTopProfileUniqueCount, $sContent);

	    if (defined('ITOP_REVISION') &&
            (ITOP_REVISION == (int) ITOP_REVISION)) {
		    $sItopApplicativeVersion = ITOP_REVISION;
		    $sItopSetupVersion = ITOP_REVISION;
	    } else {
		    $sItopApplicativeVersion = '0';
		    $sItopSetupVersion = '0';
	    }

	    $sContent = str_replace('ITOP_SETUP_VERSION', $sItopSetupVersion, $sContent);
	    $sContent = str_replace('ITOP_VERSION', $sItopApplicativeVersion, $sContent);

        $this->assertEquals($sContent, $aResp[0]);
    }

    public function testTokenConf()
    {
        @chmod($this->sConfigFile, 0770);
        \utils::GetConfig()->SetModuleSetting(Constants::EXEC_MODULE, 'access_token', 'toto123');
        \utils::GetConfig()->SetModuleSetting(Constants::EXEC_MODULE, 'authorized_network', []);
        \utils::GetConfig()->SetModuleSetting(Constants::EXEC_MODULE, Constants::METRICS, ['collection1' => []]);
        \utils::GetConfig()->WriteToFile();
        @chmod($this->sConfigFile, 0444); // Read-only

        $aResp = $this->CallRestApi("$this->sUrl&access_token=toto123&collection=collection1");
        $this->assertEquals(200, $aResp[1], "wrong http error code. $aResp[1] instead of 200");
        $aResp = $this->CallRestApi("$this->sUrl&access_token=toto124&collection=collection1");
        $this->assertEquals(500, $aResp[1], "wrong http error code. $aResp[1] instead of 500");
        $this->assertContains('Exception : Invalid token', $aResp[0]);
    }

    /**
     * @dataProvider NetworkProvider
     *
     * @throws ConfigException
     * @throws CoreException
     */
    public function testAuthorizedNetwork($aNetworkRegexps, $iHttpCode)
    {
        @chmod($this->sConfigFile, 0770);
        \utils::GetConfig()->SetModuleSetting(Constants::EXEC_MODULE, 'access_token', 'toto123');
        \utils::GetConfig()->SetModuleSetting(Constants::EXEC_MODULE, 'authorized_network', $aNetworkRegexps);
        \utils::GetConfig()->SetModuleSetting(Constants::EXEC_MODULE, Constants::METRICS, ['collection1' => []]);
        \utils::GetConfig()->WriteToFile();
        @chmod($this->sConfigFile, 0444); // Read-only

        $aResp = $this->CallRestApi("$this->sUrl&access_token=toto123&collection=collection1");
        $sErrorCode = $aResp[1];
        $this->assertEquals($iHttpCode, $sErrorCode, "wrong http error code. $sErrorCode instead of $iHttpCode. ".$aResp[0]);
        if (500 == $sErrorCode) {
            $this->assertContains('Exception : Unauthorized network', $aResp[0]);
        }
    }

    public function NetworkProvider()
    {
        $sLocalIp = gethostbyname(gethostname());
        $aExploded = explode('.', $sLocalIp);
        $sSubnet = sprintf('%s.%s.0.1', $aExploded[0], $aExploded[1]);

        //$sLocalIp = gethostbyname(parse_url($this->sUrl, PHP_URL_HOST));
        return [
            'wrong conf' => ['aNetworkRegexps' => '', 'iHttpCode' => 500],
            'empty' => ['aNetworkRegexps' => [], 'iHttpCode' => 200],
            //"ok for IP $sLocalIp" => [ 'aNetworkRegexps' => [$sLocalIp], 'iHttpCode' => 200 ],
            "ok for $sSubnet/24" => ['aNetworkRegexps' => [$sSubnet.'/24'], 'iHttpCode' => 200],
            "ok with further authorized networks + $sSubnet/24" => ['aNetworkRegexps' => ['20.0.0.0/24', "$sSubnet/24"], 'iHttpCode' => 200],
            'wrong network' => ['aNetworkRegexps' => ['20.0.0.0/24'], 'iHttpCode' => 500],
            'wrong IP' => ['aNetworkRegexps' => ['20.0.0.0'], 'iHttpCode' => 500],
        ];
    }

    public function testCollection()
    {
        @chmod($this->sConfigFile, 0770);
        \utils::GetConfig()->SetModuleSetting(Constants::EXEC_MODULE, 'access_token', 'toto123');
        \utils::GetConfig()->SetModuleSetting(Constants::EXEC_MODULE, 'authorized_network', []);
        \utils::GetConfig()->SetModuleSetting(Constants::EXEC_MODULE, Constants::METRICS, ['collection1' => []]);
        \utils::GetConfig()->WriteToFile();
        @chmod($this->sConfigFile, 0444); // Read-only

        $aResp = $this->CallRestApi("$this->sUrl&access_token=toto123&collection=collection1");
        $this->assertEquals(200, $aResp[1], "wrong http error code. $aResp[1] instead of 200");
        $aResp = $this->CallRestApi("$this->sUrl&access_token=toto123");
        $this->assertEquals(500, $aResp[1], "Missing collection in GET params. $aResp[1] instead of 500");
        $this->assertContains('Exception : Missing mandatory GET parameter collection', $aResp[0]);
    }

    /**
     * @dataProvider CheckIpProvider
     */
    public function testCheckIpFunction(string $clientIp, array $aNetworks, bool $bExpectedRes)
    {
        $oController = new Controller('', '');

        $this->assertEquals($bExpectedRes, $oController->CheckIpFunction($clientIp, $aNetworks));
    }

    public function CheckIpProvider()
    {
        return [
          'IP match' => ['127.0.0.1', ['127.0.0.1'], true],
          'IP no match' => ['127.0.0.1', ['127.0.0.2'], false],
          'network match' => ['127.0.0.1', ['127.0.0.2/8'], true],
          'network match2' => ['127.0.1.1', ['127.0.0.1/16'], true],
          'network match3' => ['127.0.0.1', ['127.0.1.1/16'], true],
          'network match4' => ['127.0.0.1', ['127.0.0.1/24'], true],
          'network match5' => ['127.0.1.1', ['127.0.1.2/8'], true],
          'network match6' => ['127.0.1.1', ['127.0.1.2/24'], true],
        ];
    }
}
