<?php

namespace Combodo\iTop\Monitoring\Test\CustomReader;

use Combodo\iTop\Monitoring\CustomReader\DbToolsService;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @backupGlobals disabled
 */
class DbToolsServiceTest extends ItopDataTestCase
{
	protected function setUp(): void
	{
        $this->RequireOnceItopFile('approot.inc.php');
		parent::setUp();

        $this->RequireOnceItopFile('/env-production/combodo-monitoring/vendor/autoload.php');
        $this->RequireOnceItopFile('/core/config.class.inc.php');
	}

	public function GetDBTablesInfoAnalyzeFrequencyProvider(){
		return [
            'never analyzed yet' => [
                'sPreviousAnalyzeTimestamp' => null,
                'bExpectedAnalysisTriggered' => true,
            ],
			'analyze implemented/ require analyze' => [
				'sPreviousAnalyzeTimestamp' => 'now - 2 minutes',
				'bExpectedAnalysisTriggered' => true,
			],
			'analyze implemented/ no analyze required' => [
				'sPreviousAnalyzeTimestamp' => 'now - 30 seconds',
				'bExpectedAnalysisTriggered' => false,
			],
		];
	}

	/**
	 * @dataProvider GetDBTablesInfoAnalyzeFrequencyProvider
	 */
	public function testGetDBTablesInfoAnalyzeFrequency(?string $sPreviousAnalyzeTimestamp, bool $bExpectedAnalysisTriggered) {
		$oDbToolsService = new DbToolsService();
		if (! $oDbToolsService->IsAnalyzeImplementedInITop()){
			$this->markTestSkipped('Analyze not implemented');
		}

		$sFile = $oDbToolsService->GetDbAnalyzeFrequencyFile();
		if (is_null($sPreviousAnalyzeTimestamp)){
			@unlink($sFile);
		} else {
			$iPreviousTimeStamp = strtotime($sPreviousAnalyzeTimestamp);
			file_put_contents($sFile, $iPreviousTimeStamp);
		}

		$iNow = strtotime('now');
		$oDbToolsService->GetDBTablesInfo(1, true);

        $sNextTimeStamp = file_get_contents($sFile);
        if (is_null($sPreviousAnalyzeTimestamp)){
            $this->assertTrue($sNextTimeStamp >= $iNow, "$sNextTimeStamp >= $iNow");
        } else {
            if ($bExpectedAnalysisTriggered) {
                $this->assertTrue($sNextTimeStamp > $iPreviousTimeStamp, "Analysis triggered: $sNextTimeStamp > $iPreviousTimeStamp");
            } else {
                $this->assertEquals($iPreviousTimeStamp, $sNextTimeStamp);
            }
        }
	}
}
