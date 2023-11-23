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

        $this->RequireOnceItopFile(APPROOT.'/env-production/combodo-monitoring/vendor/autoload.php');
        $this->RequireOnceItopFile('/core/config.class.inc.php');
	}

	public function GetDBTablesInfoAnalyzeFrequencyProvider(){
		return [
			'nominal case when no analyze/ no previous file' => [
				'bRunTestOnlyIfAnalyzeImplementedInITop' => true,
				'sPreviousAnalyzeTimestamp' => null,
				'bExpectedAnalysisTriggered' => true,
			],
			'analyze implemented/ require analyze' => [
				'bRunTestOnlyIfAnalyzeImplementedInITop' => false,
				'sPreviousAnalyzeTimestamp' => 'now - 2 minutes',
				'bExpectedAnalysisTriggered' => true,
			],
			'analyze implemented/ no analyze required' => [
				'bRunTestOnlyIfAnalyzeImplementedInITop' => false,
				'sPreviousAnalyzeTimestamp' => 'now - 30 seconds',
				'bExpectedAnalysisTriggered' => false,
			],
		];
	}

	/**
	 * @dataProvider GetDBTablesInfoAnalyzeFrequencyProvider
	 */
	public function testGetDBTablesInfoAnalyzeFrequency(bool $bRunTestOnlyIfAnalyzeImplementedInITop, ?string $sPreviousAnalyzeTimestamp, bool $bExpectedAnalysisTriggered) {
		$oDbToolsService = new DbToolsService();
		$bAnalyzeImplementedInITop = $oDbToolsService->IsAnalyzeImplementedInITop();
		if (! $bAnalyzeImplementedInITop && $bRunTestOnlyIfAnalyzeImplementedInITop){
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
		$oDbToolsService->GetDBTablesInfo(1);
		if ($bAnalyzeImplementedInITop){
			$sNextTimeStamp = file_get_contents($sFile);
			if (is_null($sPreviousAnalyzeTimestamp)){
				$this->assertTrue($sNextTimeStamp >= $iNow, "$sNextTimeStamp >= $iNow");
			} else {
				if ($bExpectedAnalysisTriggered) {
					$this->assertTrue($sNextTimeStamp > $iPreviousTimeStamp, "$sNextTimeStamp > $iPreviousTimeStamp");
				} else {
					$this->assertEquals($iPreviousTimeStamp, $sNextTimeStamp);
				}
			}
		}
	}
}
