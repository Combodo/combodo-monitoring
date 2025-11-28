<?php

namespace Combodo\iTop\Monitoring\Test\CustomReader;

use Combodo\iTop\Monitoring\CustomReader\ItopSynchroLogReader;
use Combodo\iTop\Monitoring\Model\Constants;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @backupGlobals disabled
 */
class ItopSynchroLogReaderTest extends ItopDataTestCase
{
	protected function setUp(): void
	{
		parent::setUp();

		$this->RequireOnceItopFile('env-production/combodo-monitoring/vendor/autoload.php');
		$this->RequireOnceItopFile('core/config.class.inc.php');

		$sOql = <<<OQL
SELECT SynchroLog 
OQL;

		$oSearch = \DBObjectSearch::FromOQL($sOql);
		$oSet = new \DBObjectSet($oSearch);

		while ($oObj = $oSet->Fetch()) {
			$oObj->DBDelete();
		}
	}

	protected function tearDown(): void
	{
	}

	public function GetMetricElapsedAndAgeProvider()
	{
		return [
			'running'                     => [
				'aFieldValues'              => [
					'stats_nb_replica_total' => '456',
					'start_date'             => strtotime('-2 HOURS'),
					'status'                 => 'running',
				],
				'sExpectedStatus'           => 'running',
				'iExpectedAgeInMinutes'     => 120,
				'iExpectedElapsedInSeconds' => 7200,
			],
			'error'                       => [
				'aFieldValues'              => [
					'stats_nb_replica_total' => '456',
					'start_date'             => strtotime('-2 HOURS'),
					'end_date'               => strtotime('-1 HOURS'),
					'status'                 => 'error',
				],
				'sExpectedStatus'           => 'error',
				'iExpectedAgeInMinutes'     => 120,
				'iExpectedElapsedInSeconds' => 3600,
			],
			'end date before start date?' => [
				'aFieldValues'              => [
					'stats_nb_replica_total' => '456',
					'start_date'             => strtotime('-1 HOURS'),
					'end_date'               => strtotime('-2 HOURS'),
					'status'                 => 'error',
				],
				'sExpectedStatus'           => 'error',
				'iExpectedAgeInMinutes'     => 60,
				'iExpectedElapsedInSeconds' => 0,
			],
			'completed'                   => [
				'aFieldValues'              => [
					'stats_nb_replica_total' => '456',
					'start_date'             => strtotime('-2 HOURS'),
					'end_date'               => strtotime('-1 HOURS'),
					'status'                 => 'completed',
				],
				'sExpectedStatus'           => 'completed',
				'iExpectedAgeInMinutes'     => 120,
				'iExpectedElapsedInSeconds' => 3600,
			],
		];
	}

	/**
	 * @dataProvider GetMetricElapsedAndAgeProvider
	 */
	public function testGetMetricElapsedAndAge($aFieldValues, $sExpectedStatus, $iExpectedAgeInMinutes, $iExpectedElapsedInSeconds)
	{
		$currentDate = date(\AttributeDateTime::GetSQLFormat(), strtotime('-1 HOURS'));
		$this->CreateSynchroObj("synchro1", $currentDate, $aFieldValues);

		$oItopSynchroLogReader = new ItopSynchroLogReader('', [Constants::METRIC_LABEL => ['titi' => 'toto']]);
		$aMetrics = $oItopSynchroLogReader->GetMetrics();
		$this->assertEquals(4, sizeof($aMetrics));

		$aExpectedLabels = [
			'titi'   => 'toto',
			'status' => $sExpectedStatus,
			'source' => "synchro1",
		];

		$oErrorMetric = $aMetrics[0];
		$this->assertEquals($aExpectedLabels, $oErrorMetric->GetLabels());
		$this->assertEquals('synchro log error count.', $oErrorMetric->GetDescription());
		$this->assertEquals('itop_synchrolog_error_count', $oErrorMetric->GetName());
		$this->assertEquals(0, $oErrorMetric->GetValue());

		$oCountMetric = $aMetrics[1];
		$this->assertEquals($aExpectedLabels, $oCountMetric->GetLabels());
		$this->assertEquals('synchro log replica count.', $oCountMetric->GetDescription());
		$this->assertEquals('itop_synchrolog_replica_count', $oCountMetric->GetName());
		$this->assertEquals(456, $oCountMetric->GetValue());

		$oAgeMetric = $aMetrics[2];
		$this->assertEquals($aExpectedLabels, $oAgeMetric->GetLabels());
		$this->assertEquals('synchro log age in minutes.', $oAgeMetric->GetDescription());
		$this->assertEquals('itop_synchrolog_inminutes_age', $oAgeMetric->GetName());
		$this->AssertEqualsWithMoreInfos($iExpectedAgeInMinutes, $oAgeMetric->GetValue(), 2, "kpi value should have same value +/- 2 minutes due to CRUD perf with SynchroLog");

		$oElapsedMetric = $aMetrics[3];
		$this->assertEquals($aExpectedLabels, $oElapsedMetric->GetLabels());
		$this->assertEquals('synchro log elapsed time in seconds.', $oElapsedMetric->GetDescription());
		$this->assertEquals('itop_synchrolog_inseconds_elapsed', $oElapsedMetric->GetName());
		if ($sExpectedStatus === "running") {
			$this->AssertEqualsWithMoreInfos($oAgeMetric->GetValue() * 60, $oElapsedMetric->GetValue(), "kpi value should have same value +/- 1 minute due to CRUD perf with SynchroLog");
		} else {
			$this->AssertEqualsWithMoreInfos($iExpectedElapsedInSeconds, $oElapsedMetric->GetValue());
		}
	}

	private function AssertEqualsWithMoreInfos($iExpected, $iActual, $iPrecision = 0, $sMessage = ""): void
	{
		$sMessage .= ". $iExpected equals $iActual (precision:$iPrecision)";
		if ($iPrecision == 0 || $iExpected == 0) {
			$this->AssertEquals($iExpected, $iActual, $sMessage);

			return;
		}

		$iDiv = (int)round($iActual / $iExpected, 0);
		$this->AssertEquals(1, $iDiv, $sMessage);
		$this->AssertTrue($iActual % $iExpected <= $iPrecision, $sMessage);
	}

	public function GetMetricNbErrorsProvider()
	{
		return [
			'no error'                           => [
				'aFieldValues'   => [
					'stats_nb_replica_total' => '456',
				],
				'iExpectedValue' => 0,
			],
			'stats_nb_obj_obsoleted_errors'      => [
				'aFieldValues'   => [
					'stats_nb_replica_total'        => '456',
					'stats_nb_obj_obsoleted_errors' => 1,
				],
				'iExpectedValue' => 1,
			],
			'stats_nb_obj_deleted_errors'        => [
				'aFieldValues'   => [
					'stats_nb_replica_total'      => '456',
					'stats_nb_obj_deleted_errors' => 2,
				],
				'iExpectedValue' => 2,
			],
			'stats_nb_obj_created_errors'        => [
				'aFieldValues'   => [
					'stats_nb_replica_total'      => '456',
					'stats_nb_obj_created_errors' => 3,
				],
				'iExpectedValue' => 3,
			],
			'stats_nb_obj_updated_errors'        => [
				'aFieldValues'   => [
					'stats_nb_replica_total'      => '456',
					'stats_nb_obj_updated_errors' => 4,
				],
				'iExpectedValue' => 4,
			],
			'stats_nb_replica_reconciled_errors' => [
				'aFieldValues'   => [
					'stats_nb_replica_total'             => '456',
					'stats_nb_replica_reconciled_errors' => 5,
				],
				'iExpectedValue' => 5,
			],
			'all'                                => [
				'aFieldValues'   => [
					'stats_nb_replica_total'             => '456',
					'stats_nb_obj_obsoleted_errors'      => 1,
					'stats_nb_obj_deleted_errors'        => 1,
					'stats_nb_obj_created_errors'        => 1,
					'stats_nb_obj_updated_errors'        => 1,
					'stats_nb_replica_reconciled_errors' => 1,
				],
				'iExpectedValue' => 5,
			],
		];
	}

	/**
	 * @dataProvider GetMetricNbErrorsProvider
	 */
	public function testGetMetricNbErrors($aFieldValues, $iExpectedValue)
	{
		$currentDate = date(\AttributeDateTime::GetSQLFormat(), strtotime('-1 HOURS'));
		$this->CreateSynchroObj("synchro1", $currentDate, $aFieldValues);

		$oItopSynchroLogReader = new ItopSynchroLogReader('', [Constants::METRIC_LABEL => ['titi' => 'toto']]);
		$aMetrics = $oItopSynchroLogReader->GetMetrics();
		$this->assertEquals(4, sizeof($aMetrics));

		$aExpectedLabels = [
			'titi'   => 'toto',
			'status' => 'running',
			'source' => "synchro1",
		];

		$oMonitoringMetric = $aMetrics[0];
		$this->assertEquals($aExpectedLabels, $oMonitoringMetric->GetLabels());
		$this->assertEquals('synchro log error count.', $oMonitoringMetric->GetDescription());
		$this->assertEquals('itop_synchrolog_error_count', $oMonitoringMetric->GetName());
		$this->assertEquals($iExpectedValue, $oMonitoringMetric->GetValue());

		$oMonitoringMetric = $aMetrics[1];
		$this->assertEquals($aExpectedLabels, $oMonitoringMetric->GetLabels());
		$this->assertEquals('synchro log replica count.', $oMonitoringMetric->GetDescription());
		$this->assertEquals('itop_synchrolog_replica_count', $oMonitoringMetric->GetName());
		$this->assertEquals(456, $oMonitoringMetric->GetValue());

		$oMonitoringMetric = $aMetrics[2];
		$this->assertEquals($aExpectedLabels, $oMonitoringMetric->GetLabels());
		$this->assertEquals('synchro log age in minutes.', $oMonitoringMetric->GetDescription());
		$this->assertEquals('itop_synchrolog_inminutes_age', $oMonitoringMetric->GetName());

		$oMonitoringMetric = $aMetrics[3];
		$this->assertEquals($aExpectedLabels, $oMonitoringMetric->GetLabels());
		$this->assertEquals('synchro log elapsed time in seconds.', $oMonitoringMetric->GetDescription());
		$this->assertEquals('itop_synchrolog_inseconds_elapsed', $oMonitoringMetric->GetName());
	}

	public function testListSynchroLogObjects_OneSynchroLogPerSource()
	{
		$aSyncroLogIds = [];
		$currentDate = date(\AttributeDateTime::GetSQLFormat(), strtotime('-12 HOURS'));
		$oSynchroLog = $this->CreateSynchroObj("synchro1", $currentDate);
		$aSyncroLogIds[] = $oSynchroLog->GetKey();

		$currentDate = date(\AttributeDateTime::GetSQLFormat(), strtotime('-12 HOURS'));
		$oSynchroLog = $this->CreateSynchroObj("synchro2", $currentDate);
		$aSyncroLogIds[] = $oSynchroLog->GetKey();

		$oItopSynchroLogReader = new ItopSynchroLogReader('', []);
		$aRes = $oItopSynchroLogReader->ListSynchroLogObjects();
		$this->assertEquals(2, sizeof($aRes));
		$aKeys = array_keys($aRes);
		sort($aKeys);
		$this->assertEquals(["synchro1", "synchro2"], $aKeys);

		foreach ($aRes as $oSynchroLog) {
			$this->assertContains($oSynchroLog->GetKey(), $aSyncroLogIds);
		}
	}

	public function testListSynchroLogObjects_KeepOnlyMostRecentSynchroLogPerSource()
	{
		$aExpectedSyncroLogIds = [];

		$currentDate = date(\AttributeDateTime::GetSQLFormat(), strtotime('-12 HOURS'));
		$oSynchroLog = $this->CreateSynchroObj("synchro1", $currentDate);

		$currentDate = date(\AttributeDateTime::GetSQLFormat(), strtotime('-6 HOURS'));
		$oSynchroLog = $this->CreateSynchroObj(null, $currentDate, ['sync_source_id' => $oSynchroLog->Get('sync_source_id')]);

		$currentDate = date(\AttributeDateTime::GetSQLFormat(), strtotime('-1 HOURS'));
		$oSynchroLog = $this->CreateSynchroObj(null, $currentDate, ['sync_source_id' => $oSynchroLog->Get('sync_source_id')]);
		$aExpectedSyncroLogIds['synchro1'] = $oSynchroLog->GetKey();

		$currentDate = date(\AttributeDateTime::GetSQLFormat(), strtotime('-12 HOURS'));
		$oSynchroLog2 = $this->CreateSynchroObj("synchro2", $currentDate);

		$currentDate = date(\AttributeDateTime::GetSQLFormat(), strtotime('-6 HOURS'));
		$oSynchroLog2 = $this->CreateSynchroObj(null, $currentDate, ['sync_source_id' => $oSynchroLog2->Get('sync_source_id')]);

		$currentDate = date(\AttributeDateTime::GetSQLFormat(), strtotime('-1 HOURS'));
		$oSynchroLog2 = $this->CreateSynchroObj(null, $currentDate, ['sync_source_id' => $oSynchroLog2->Get('sync_source_id')]);
		$aExpectedSyncroLogIds['synchro2'] = $oSynchroLog2->GetKey();

		$oItopSynchroLogReader = new ItopSynchroLogReader('', []);
		$aRes = $oItopSynchroLogReader->ListSynchroLogObjects();

		$aSyncroLogIds = [];
		foreach ($aRes as $sSynchroName => $oSynchroLog) {
			$aSyncroLogIds[$sSynchroName] = $oSynchroLog->GetKey();
		}
		$this->assertEquals($aExpectedSyncroLogIds, $aSyncroLogIds);
	}

	public function SynchroTooOldProvider()
	{
		return [
			'default STRTOTIME_MIN_STARTDATE of 24h | no synchrologs'     => [
				'sCreatedSynchroLogsStartDateStrToTime' => '-25 HOURS',
				'sMinStartDateConfValue'                => null,
				'bIsEmpty'                              => true,
			],
			'default STRTOTIME_MIN_STARTDATE of 24h | 1 synchrolog found' => [
				'sCreatedSynchroLogsStartDateStrToTime' => '-23 HOURS',
				'sMinStartDateConfValue'                => null,
				'bIsEmpty'                              => false,
			],
			'STRTOTIME_MIN_STARTDATE set to 4h | no synchrologs'          => [
				'sCreatedSynchroLogsStartDateStrToTime' => '-5 HOURS',
				'sMinStartDateConfValue'                => '-4 HOURS',
				'bIsEmpty'                              => true,
			],
			'STRTOTIME_MIN_STARTDATE set to 4h | 1 synchrolog found'      => [
				'sCreatedSynchroLogsStartDateStrToTime' => '-3 HOURS',
				'sMinStartDateConfValue'                => '-4 HOURS',
				'bIsEmpty'                              => false,
			],
		];
	}

	/**
	 * @dataProvider SynchroTooOldProvider
	 */
	public function testListSynchroLogObjects_SynchroTooOld($sCreatedSynchroLogsStartDateStrToTime, $sMinStartDateConfValue, $bIsEmpty)
	{
		$sStartDate = date(\AttributeDateTime::GetSQLFormat(), strtotime($sCreatedSynchroLogsStartDateStrToTime));
		$this->CreateSynchroObj("synchro1", $sStartDate);

		$aMetricConf = [];
		if ($sMinStartDateConfValue !== null) {
			$aMetricConf[ItopSynchroLogReader::STRTOTIME_MIN_STARTDATE] = $sMinStartDateConfValue;
		}
		$oItopSynchroLogReader = new ItopSynchroLogReader('', $aMetricConf);
		$this->assertEquals($bIsEmpty, empty($oItopSynchroLogReader->ListSynchroLogObjects()));
	}

	private function CreateSynchroObj(?string $sSynchroName, $sStartDate, $aAttributeValues = [])
	{
		$aParams = ['start_date' => $sStartDate];
		if (!is_null($sSynchroName)) {
			$sSynchroSourceId = $this->createObject(\SynchroDataSource::class, ['name' => $sSynchroName]);
			$aParams['sync_source_id'] = $sSynchroSourceId;
		}
		$oObj = $this->createObject(\SynchroLog::class, array_merge($aParams, $aAttributeValues));
		echo "SynchroLog:".$oObj->GetKey()."\n";

		return $oObj;
	}
}
