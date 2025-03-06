<?php

namespace Combodo\iTop\Monitoring\Test\CustomReader;

use Combodo\iTop\Monitoring\CustomReader\ActiveSessionReader;
use Combodo\iTop\Monitoring\Model\MonitoringMetric;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @backupGlobals disabled
 */
class ActiveSessionReaderTest extends ItopDataTestCase
{
	private $sDir;

	protected function setUp(): void
	{
		parent::setUp();

        $this->RequireOnceItopFile('env-production/combodo-monitoring/vendor/autoload.php');

		$this->sDir = sys_get_temp_dir() . '/itop_session';
		@mkdir($this->sDir);
	}

	protected function tearDown(): void
	{
		parent::tearDown();
		foreach (glob($this->sDir . '/**') as $sFile){
			@unlink($sFile);
		}

		@rmdir($this->sDir);
	}

	public function testUnauthSessions() {
		$oiTopSessionReader = new ActiveSessionReader('itop_session', []);

		$sFiles = [];
		$sFiles[] = tempnam($this->sDir, 'sess_');
		$sFiles[] = tempnam($this->sDir, 'sess_');
		$sFiles[] = tempnam($this->sDir, 'sess_');
		$sFiles[] = $this->sDir . '/fake';

		$aMetrics = $oiTopSessionReader->FetchCounter($sFiles);
		$this->assertEquals(1, sizeof($aMetrics));
		/** @var MonitoringMetric $oMetric */
		$oMetric =  $aMetrics[0];
		$this->assertEquals('itop_session_count', $oMetric->GetName());
		$this->assertEquals("3", $oMetric->GetValue());
		$this->assertEquals(['login_mode' => 'no_auth', 'context' => ''], $oMetric->GetLabels());
	}

	public function testAuthSessions() {
		$oiTopSessionReader = new ActiveSessionReader('itop_session', []);

		$sFiles = [];

		$aExpected = [
			'form' => [
				'TAG_PORTAL' => 1,
				'TAG_CONSOLE' => 2,
				'TAG_REST' => 3
			],
			'token' => [
				'TAG_SYNCHRO' => 4,
				'TAG_REST' => 5,
			],
		];

		foreach ($aExpected as $sLoginMode => $aSubExpected){
			foreach ($aSubExpected as $sContext => $iCount){
				for($i=0; $i<$iCount; $i++){
					$sFile = $this->sDir . 'sess_'.$sLoginMode . '_' . $sContext . '_' . $i;
					$sFiles[] = $sFile;
					file_put_contents($sFile,
						json_encode(
							[
								'login_mode' => $sLoginMode,
								'user_id' => $i,
								'context' => $sContext
							]
						)
					);
				}
			}
		}

		var_dump($sFiles);

		$aMetrics = $oiTopSessionReader->FetchCounter($sFiles);
		$this->assertEquals(15, sizeof($aMetrics), var_export($aMetrics, true));

		foreach ($aExpected as $sLoginMode => $aSubExpected){
			foreach ($aSubExpected as $sContext => $iCount){
				/** @var MonitoringMetric $oMetric */
				$oMetric =  array_shift($aMetrics);
				var_dump($oMetric);
				$this->assertEquals('itop_session_count', $oMetric->GetName(), var_export($oMetric, true));
				$this->assertEquals($iCount, $oMetric->GetValue(), var_export($oMetric, true));
				$this->assertEquals(['login_mode' => $sLoginMode, 'context' => $sContext], $oMetric->GetLabels(), var_export($oMetric, true));
			}
		}

		foreach ($aExpected as $sLoginMode => $aSubExpected){
			foreach ($aSubExpected as $sContext => $iCount){
				/** @var MonitoringMetric $oMetric */
				$oMetric =  array_shift($aMetrics);
				var_dump($oMetric);
				$this->assertEquals('itop_session_elapsedinsecond_sum', $oMetric->GetName(), var_export($oMetric, true));
				//$this->assertEquals($iCount, $oMetric->GetValue(), var_export($oMetric, true));
				$this->assertEquals(['login_mode' => $sLoginMode, 'context' => $sContext], $oMetric->GetLabels(), var_export($oMetric, true));
			}
		}

		foreach ($aExpected as $sLoginMode => $aSubExpected){
			foreach ($aSubExpected as $sContext => $iCount){
				/** @var MonitoringMetric $oMetric */
				$oMetric =  array_shift($aMetrics);
				var_dump($oMetric);
				$this->assertEquals('itop_session_elapsedinsecond_max', $oMetric->GetName(), var_export($oMetric, true));
				//$this->assertEquals($iCount, $oMetric->GetValue(), var_export($oMetric, true));
				$this->assertEquals(['login_mode' => $sLoginMode, 'context' => $sContext], $oMetric->GetLabels(), var_export($oMetric, true));
			}
		}

	}
}
