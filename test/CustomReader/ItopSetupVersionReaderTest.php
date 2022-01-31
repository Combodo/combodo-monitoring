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
class ItopSetupVersionReaderTest extends ItopDataTestCase {

    public function setUp()
	{
		//@include_once '/home/combodo/workspace/iTop/approot.inc.php';
		//@include_once '/home/nono/PhpstormProjects/iTop/approot.inc.php';
		parent::setUp();

		@require_once(APPROOT . 'env-production/combodo-monitoring/vendor/autoload.php');
		require_once(APPROOT . 'core/config.class.inc.php');
	}

	/**
	 * @dataProvider GetItopVersionIdProvider
	 */
	public function testGetItopVersionId(string $sInitialValue, string $sExpectedReturnedValue){
    	$oItopSetupVersionReader = new ItopSetupVersionReader('', []);

    	$oMetric = new MonitoringMetric('toto', '', $sInitialValue);
		$this->assertEquals($sExpectedReturnedValue,
			$oItopSetupVersionReader->GetItopShortVersionId($oMetric)
		);
	}

	public function GetItopVersionIdProvider(){
		return [
			'no version found' => ['3.0.0-dev-svn', '0' ],
			'no version found bis' => ['LATEST', '0' ],
			'version found' => [ '3.0.0-dev-6517', '6517']
		];
	}
}
