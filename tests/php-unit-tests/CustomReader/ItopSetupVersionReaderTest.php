<?php

namespace Combodo\iTop\Monitoring\Test\CustomReader;

use Combodo\iTop\Monitoring\CustomReader\ItopSetupVersionReader;
use Combodo\iTop\Monitoring\Model\MonitoringMetric;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @backupGlobals disabled
 */
class ItopSetupVersionReaderTest extends ItopDataTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->RequireOnceItopFile('env-production/combodo-monitoring/vendor/autoload.php');
		$this->RequireOnceItopFile('core/config.class.inc.php');
	}

	/**
	 * @dataProvider GetItopVersionIdProvider
	 */
	public function testGetItopVersionId(string $sInitialValue, string $sExpectedReturnedValue)
	{
		$oItopSetupVersionReader = new ItopSetupVersionReader('', []);

		$this->assertEquals(
			$sExpectedReturnedValue,
			$oItopSetupVersionReader->GetItopShortVersionId($sInitialValue)
		);
	}

	public function GetItopVersionIdProvider()
	{
		return [
			'no version found' => ['3.0.0-dev-svn', '0' ],
			'no version found bis' => ['LATEST', '0' ],
			'version found' => [ '3.0.0-dev-6517', '6517'],
		];
	}

	public function testGetMetrics()
	{
		$oItopSetupVersionReader = new ItopSetupVersionReader('', ['static_labels' => ['toto' => 'titi']]);
		$aMetrics = $oItopSetupVersionReader->GetMetrics();
		$this->assertEquals(1, count($aMetrics));
		/** @var MonitoringMetric $oMetric */
		$oMetric = $aMetrics[0];
		if (defined('ITOP_REVISION')
			&& filter_var(ITOP_REVISION, FILTER_VALIDATE_INT)) {
			$sItopSetupVersion = ITOP_REVISION;
		} else {
			$sItopSetupVersion = 0;
		}

		$this->assertEquals('iTop after setup version (code + datamodel)', $oMetric->GetDescription());
		$this->assertEquals('itop_setup_version', $oMetric->GetName());
		$this->assertEquals($sItopSetupVersion, $oMetric->GetValue());
		$this->assertEquals(['toto' => 'titi'], $oMetric->GetLabels());
	}
}
