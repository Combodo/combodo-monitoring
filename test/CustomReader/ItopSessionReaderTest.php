<?php

namespace Combodo\iTop\Monitoring\Test\CustomReader;

use Combodo\iTop\Monitoring\CustomReader\iTopSessionReader;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @backupGlobals disabled
 */
class ItopSessionReaderTest extends ItopDataTestCase
{
	protected function setUp(): void
	{
		//@include_once '/home/combodo/workspace/iTop/approot.inc.php';
		parent::setUp();

		@require_once APPROOT.'env-production/combodo-monitoring/vendor/autoload.php';
		require_once APPROOT.'core/config.class.inc.php';
	}

	public function testSessionCount() {
		// Create data fixture => User + Person
		$iNum = uniqid();
		$sLogin = "Session".$iNum;
		$this->CreateContactlessUser($sLogin, 1, "Abcdef@12345678");
		\UserRights::Login($sLogin);

		$oiTopSessionReader = new iTopSessionReader('itop_session_count', []);
		$aMetrics = $oiTopSessionReader->GetMetrics();
		$oMetric = array_pop($aMetrics);
		$this->assertNotEquals("0", $oMetric->GetValue());
	}
}
