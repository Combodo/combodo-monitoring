<?php

use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use Combodo\iTop\Monitoring\Model\Constants;

class CombodoMonitoringTest extends ItopDataTestCase {
    private $sUrl;
    private $sConfigFile;
    private $sConfBackupPath;

    public function setUp()
    {
        require_once '/home/nono/PhpstormProjects/iTop/approot.inc.php';
        parent::setUp();

        require_once(APPROOT . 'core/config.class.inc.php');
        require_once(APPROOT . 'application/utils.inc.php');

        if (!defined('MODULESROOT'))
        {
            define('MODULESROOT', APPROOT.'env-production/');
        }

        $this->sConfigFile = \utils::GetConfig()->GetLoadedFile();
        @chmod($this->sConfigFile, 0770);
        $this->sUrl = \MetaModel::GetConfig()->Get('app_root_url') . "/pages/exec.php?exec_module=combodo-monitoring&exec_page=index.php&exec_env=production";
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

    private function CallRestApi($sUrl){
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "$sUrl");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $sContent = curl_exec($ch);
        $iCode = curl_getinfo($ch,  CURLINFO_HTTP_CODE);
        curl_close ($ch);

        return [ $sContent, $iCode];
    }

    /**
     * @dataProvider MonitoringProvider
     */
    public function testMonitoringPage($aMetricConf, $sExpectedContentPath, $iExpectedHttpCode){
        @chmod($this->sConfigFile, 0770);
        \utils::GetConfig()->SetModuleSetting(Constants::EXEC_MODULE, 'access_token', 'toto123');
        \utils::GetConfig()->SetModuleSetting(Constants::EXEC_MODULE, 'authorized_network', '');
        \utils::GetConfig()->SetModuleSetting(Constants::EXEC_MODULE, Constants::METRICS, $aMetricConf);
        \utils::GetConfig()->WriteToFile();
        @chmod($this->sConfigFile, 0444); // Read-only

        $aResp = $this->CallRestApi("$this->sUrl&access_token=toto123&collection=collection1");

        $this->assertEquals($iExpectedHttpCode, $aResp[1], $aResp[0]);
        $this->assertEquals(file_get_contents($sExpectedContentPath), $aResp[0]);
    }

    public function MonitoringProvider(){
        $sRessourcesDir = __DIR__ . "/ressources";

        return [
            'all' => [
                'aMetricConf' => [
                    'collection1' => [
                        'itop_user_count' => array(
                            'description' => 'Nb of users (oql_count)',
                            'oql_count' => 'SELECT URP_UserProfile  WHERE URP_UserProfile.userid=1',
                            'label' => ['toto' => 'titi']
                        ),
                        'itop_user_groupby_count' => array(
                            'description' => 'Nb of users (oql_groupby)',
                            'oql_count' => 'SELECT URP_UserProfile JOIN URP_Profiles AS URP_Profiles_profileid ON URP_UserProfile.profileid =URP_Profiles_profileid.id WHERE URP_UserProfile.userid=1',
                            'oql_groupby' => ['profile' => 'URP_Profiles_profileid.friendlyname'],
                        ),
                        'itop_backup_retention_count' => array(
                            'description' => 'Retention count (conf)',
                            'conf' => ['MyModuleSettings', 'itop-backup', 'retention_count'],
                            'label' => ['shadok' => 'gabuzomeu']
                        ),
                        'itop_custom' => array(
                            'description' => 'custom class (custom)',
                            'custom' => ['class' => '\Combodo\iTop\Monitoring\Test\MetricReader\CustomReaders\CustomReaderImpl']
                        ),
                    ],
                ],
                'sExpectedContentPath' => "$sRessourcesDir/prometheus_content.txt",
                'iExpectedHttpCode' => 200
            ],
        ];
    }

    public function testTokenConf(){
        @chmod($this->sConfigFile, 0770);
        \utils::GetConfig()->SetModuleSetting(Constants::EXEC_MODULE, 'access_token', 'toto123');
        \utils::GetConfig()->SetModuleSetting(Constants::EXEC_MODULE, 'authorized_network', '');
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
     * @throws ConfigException
     * @throws CoreException
     */
    public function testAuthorizedNetwork($sNetworkRegexp, $iHttpCode){
        @chmod($this->sConfigFile, 0770);
        \utils::GetConfig()->SetModuleSetting(Constants::EXEC_MODULE, 'access_token', 'toto123');
        \utils::GetConfig()->SetModuleSetting(Constants::EXEC_MODULE, 'authorized_network', $sNetworkRegexp);
        \utils::GetConfig()->SetModuleSetting(Constants::EXEC_MODULE, Constants::METRICS, ['collection1' => []]);
        \utils::GetConfig()->WriteToFile();
        @chmod($this->sConfigFile, 0444); // Read-only

        $aResp = $this->CallRestApi("$this->sUrl&access_token=toto123&collection=collection1");
        $sErrorCode = $aResp[1];
        $this->assertEquals($iHttpCode, $sErrorCode, "wrong http error code. $sErrorCode instead of $iHttpCode. " . $aResp[0]);
        if (500 == $sErrorCode){
            $this->assertContains('Exception : Unauthorized network', $aResp[0]);
        }
    }


    public function NetworkProvider(){
        return [
            'network ok' => [ 'sNetworkRegexp' => '127\\.0\\.0\\.\\d{1,3}', 'iHttpCode' => 200 ],
            'wrong network' => [ 'sNetworkRegexp' => '20\\.\\d{1,3}\\.\\d{1,3}\\.\\d{1,3}', 'iHttpCode' => 500 ],
        ];
    }

    public function testCollection(){
        @chmod($this->sConfigFile, 0770);
        \utils::GetConfig()->SetModuleSetting(Constants::EXEC_MODULE, 'access_token', 'toto123');
        \utils::GetConfig()->SetModuleSetting(Constants::EXEC_MODULE, 'authorized_network', '');
        \utils::GetConfig()->SetModuleSetting(Constants::EXEC_MODULE, Constants::METRICS, ['collection1' => []]);
        \utils::GetConfig()->WriteToFile();
        @chmod($this->sConfigFile, 0444); // Read-only

        $aResp = $this->CallRestApi("$this->sUrl&access_token=toto123&collection=collection1");
        $this->assertEquals(200, $aResp[1], "wrong http error code. $aResp[1] instead of 200");
        $aResp = $this->CallRestApi("$this->sUrl&access_token=toto123");
        $this->assertEquals(500, $aResp[1], "Missing collection in GET params. $aResp[1] instead of 500");
        $this->assertContains('Exception : Missing mandatory GET parameter collection', $aResp[0]);
    }
}
