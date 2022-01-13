<?php

namespace Combodo\iTop\Monitoring\Test\CustomReader;

use Combodo\iTop\Monitoring\CustomReader\ItopBackupReader;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;

class ItopBackupReaderTest extends ItopDataTestCase
{
    private $sDir;

    public function setUp()
    {
        @include_once '/home/combodo/workspace/iTop/approot.inc.php';
        //@include_once '/home/nono/PhpstormProjects/iTop/approot.inc.php';
        parent::setUp();

        require_once APPROOT.'env-production/combodo-monitoring/vendor/autoload.php';
        require_once APPROOT.'core/config.class.inc.php';

        $tempnam = tempnam(sys_get_temp_dir(), 'backup_');
        unlink($tempnam);
        $this->sDir = $tempnam.'/backups/';
        mkdir($this->sDir, 0777, $recursive = true);
    }

    public function tearDown()
    {
        $this->unlinkRecursive($this->sDir);
    }

    private function unlinkRecursive($sPath)
    {
        if (is_dir($sPath)) {
            foreach (glob("$sPath/*") as $subPath) {
                $this->unlinkRecursive($subPath);
            }
            rmdir($sPath);
            echo "remove directory $sPath\n";
        } else {
            echo "remove file $sPath\n";
            unlink($sPath);
        }
    }

    public function testGetMetrics()
    {
        $aLabels = ['toto' => 'titi'];
        $oItopBackupReader = new ItopBackupReader('', ['static_labels' => $aLabels]);

        $this->createFile($this->sDir.'/manual', '.zip');
        $this->createFile($this->sDir.'/manual', '.tar.gz');

        $this->createFile($this->sDir.'/auto', '.tar.gz');
        $this->createFile($this->sDir.'/auto', '.zip');

        $sLastBackupPath = $this->createFile($this->sDir.'/auto', '.zip');
        $iLastBackupSize = filesize($sLastBackupPath);
        touch($sLastBackupPath, strtotime('-13 hours'));
        $oMetrics = $oItopBackupReader->GetMetrics($this->sDir);
        $this->assertEquals(3, sizeof($oMetrics));

        /* MonitoringMetric $oMetric */
        $oMetric = $oMetrics[0];
        $this->assertEquals('itop_backup_count', $oMetric->GetName());
        $this->assertEquals(4, $oMetric->GetValue());
        $this->assertEquals($aLabels, $oMetric->GetLabels());
        $this->assertEquals('count iTop backup files.', $oMetric->GetDescription());

        $oMetric = $oMetrics[1];
        $this->assertEquals('itop_backup_lastbackup_inbytes_size', $oMetric->GetName());
        $this->assertEquals($iLastBackupSize, $oMetric->GetValue());
        $this->assertEquals($aLabels, $oMetric->GetLabels());
        $this->assertEquals('last iTop backup file size in bytes.', $oMetric->GetDescription());

        $oMetric = $oMetrics[2];
        $this->assertEquals('itop_backup_lastbackup_ageinhours_count', $oMetric->GetName());
        $this->assertEquals(13, $oMetric->GetValue());
        $this->assertEquals($aLabels, $oMetric->GetLabels());
        $this->assertEquals('last iTop backup file age in hours.', $oMetric->GetDescription());
    }

    public function testGetMetricsNoFiles()
    {
        $aLabels = ['toto' => 'titi'];
        $oItopBackupReader = new ItopBackupReader('', ['static_labels' => $aLabels]);

        $oMetrics = $oItopBackupReader->GetMetrics($this->sDir);
        $this->assertEquals(3, sizeof($oMetrics));

        /* MonitoringMetric $oMetric */
        $oMetric = $oMetrics[0];
        $this->assertEquals('itop_backup_count', $oMetric->GetName());
        $this->assertEquals(0, $oMetric->GetValue());
        $this->assertEquals($aLabels, $oMetric->GetLabels());
        $this->assertEquals('count iTop backup files.', $oMetric->GetDescription());

        $oMetric = $oMetrics[1];
        $this->assertEquals('itop_backup_lastbackup_inbytes_size', $oMetric->GetName());
        $this->assertEquals(-1, $oMetric->GetValue());
        $this->assertEquals($aLabels, $oMetric->GetLabels());
        $this->assertEquals('last iTop backup file size in bytes.', $oMetric->GetDescription());

        $oMetric = $oMetrics[2];
        $this->assertEquals('itop_backup_lastbackup_ageinhours_count', $oMetric->GetName());
        $this->assertEquals(-1, $oMetric->GetValue());
        $this->assertEquals($aLabels, $oMetric->GetLabels());
        $this->assertEquals('last iTop backup file age in hours.', $oMetric->GetDescription());
    }

    private function createFile($sDirPath, $sSuffix): string
    {
        if (!is_dir($sDirPath)) {
            mkdir($sDirPath);
        }
        $sDate = date('c');
        $sFilePath = "$sDirPath/$sDate.$sSuffix";
        file_put_contents($sFilePath, $sDate);

        return $sFilePath;
    }
}
