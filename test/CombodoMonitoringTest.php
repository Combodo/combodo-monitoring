<?php

use Combodo\iTop\Test\UnitTest\ItopDataTestCase;

class CombodoMonitoringTest extends ItopDataTestCase {
    private $sUrl;

    public function setUp()
    {
        @include_once '/home/combodo/workspace/iTop/approot.inc.php';
        parent::setUp();

        require_once(APPROOT . 'core/config.class.inc.php');
        require_once(APPROOT . 'application/utils.inc.php');

        if (!defined('MODULESROOT'))
        {
            define('MODULESROOT', APPROOT.'env-production/');
        }

        $sConfigFile = \utils::GetConfig()->GetLoadedFile();
        @chmod($sConfigFile, 0770);
        $this->sUrl = \MetaModel::GetConfig()->Get('app_root_url') . "/pages/exec.php?exec_module=combodo-monitoring&exec_page=index.php&exec_env=production";
        @chmod($sConfigFile, 0444); // Read-only
    }


    private function CallRestApi($sUrl){
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $sUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $sContent = curl_exec($ch);
        $iCode = curl_errno($ch);
        curl_close ($ch);

        return [ $sContent, $iCode];
    }

    /**
     * @dataProvider TokenAccessProvider
     */
    public function testTokenAccess($responseContent, $httpCode){
        $aResp = $this->CallRestApi("$this->sUrl&access_token=toto123");

        $this->assertEquals($responseContent, $aResp[0]);
        $this->assertEquals($httpCode, $aResp[1]);
    }

    public function TokenAccessProvider(){
        return [
            [   "", 200 ],
        ];
    }
}
