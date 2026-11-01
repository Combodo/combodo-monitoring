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
					'start_date'             => '-2 HOURS',
					'status'                 => 'running',
				],
				'sExpectedStatus'           => 'running',
				'iExpectedAgeInMinutes'     => 120,
				'iExpectedElapsedInSeconds' => 7200,
			],
			'error'                       => [
				'aFieldValues'              => [
					'stats_nb_replica_total' => '456',
					'start_date'             => '-2 HOURS',
					'end_date'               => '-1 HOURS',
					'status'                 => 'error',
				],
				'sExpectedStatus'           => 'error',
				'iExpectedAgeInMinutes'     => 120,
				'iExpectedElapsedInSeconds' => 3600,
			],
			'end date before start date?' => [
				'aFieldValues'              => [
					'stats_nb_replica_total' => '456',
					'start_date'             => '-1 HOURS',
					'end_date'               => '-2 HOURS',
					'status'                 => 'error',
				],
				'sExpectedStatus'           => 'error',
				'iExpectedAgeInMinutes'     => 60,
				'iExpectedElapsedInSeconds' => 0,
			],
			'completed'                   => [
				'aFieldValues'              => [
					'stats_nb_replica_total' => '456',
					'start_date'             => '-2 HOURS',
					'end_date'               => '-1 HOURS',
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
		foreach (['start_date', 'end_date'] as $sField) {
			if (array_key_exists($sField, $aFieldValues)) {
				//replace operand like -1 HOUR by a timestamp
				$aFieldValues[$sField] = strtotime($aFieldValues[$sField]);
			}
		}
		$sSynchroName = "synchro_".uniqid();
		$this->CreateSynchroObj($sSynchroName, $aFieldValues);
		$oItopSynchroLogReader = new ItopSynchroLogReader('', [Constants::METRIC_LABEL => ['titi' => 'toto']]);
		$aMetrics = $oItopSynchroLogReader->GetMetrics();
		$this->assertEquals(4, sizeof($aMetrics));

		$aExpectedLabels = [
			'titi'   => 'toto',
			'status' => $sExpectedStatus,
			'source' => $sSynchroName,
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
		$aFieldValues['start_date'] = date(\AttributeDateTime::GetSQLFormat(), strtotime('-1 HOURS'));
		$sSynchroName = "synchro".uniqid();
		$this->CreateSynchroObj($sSynchroName, $aFieldValues);

		$oItopSynchroLogReader = new ItopSynchroLogReader('', [Constants::METRIC_LABEL => ['titi' => 'toto']]);
		$aMetrics = $oItopSynchroLogReader->GetMetrics();
		$this->assertEquals(4, sizeof($aMetrics));

		$aExpectedLabels = [
			'titi'   => 'toto',
			'status' => 'running',
			'source' => $sSynchroName,
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
		$aFieldValues = ['start_date' => date(\AttributeDateTime::GetSQLFormat(), strtotime('-12 HOURS'))];

		$sUID = uniqid();
		$aSynchroNames = ["synchro1_".$sUID, "synchro2_".$sUID];
		foreach ($aSynchroNames as $sSynchroName) {
			$oSynchroLog = $this->CreateSynchroObj($sSynchroName, $aFieldValues);
			$aSyncroLogIds[] = $oSynchroLog->GetKey();
		}

		$oItopSynchroLogReader = new ItopSynchroLogReader('', []);
		$aRes = $oItopSynchroLogReader->ListSynchroLogObjects();
		$this->assertEquals(2, sizeof($aRes));
		$aKeys = array_keys($aRes);
		sort($aKeys);
		$this->assertEquals($aSynchroNames, $aKeys);

		foreach ($aRes as $oSynchroLog) {
			$this->assertContains($oSynchroLog->GetKey(), $aSyncroLogIds);
		}
	}

	public function testListSynchroLogObjects_KeepOnlyMostRecentSynchroLogPerSource()
	{
		$aExpectedSyncroLogIds = [];

		$sUID = uniqid();
		for ($i = 1; $i <= 2; $i++) {
			$aFieldValues = ['start_date' => date(\AttributeDateTime::GetSQLFormat(), strtotime('-12 HOURS'))];
			$sSynchroName = "synchro{$i}_".$sUID;
			$oSynchroLog = $this->CreateSynchroObj($sSynchroName, $aFieldValues);

			$aFieldValues = [
				'start_date'     => date(\AttributeDateTime::GetSQLFormat(), strtotime('-6 HOURS')),
				'sync_source_id' => $oSynchroLog->Get('sync_source_id'),
			];
			$oSynchroLog = $this->CreateSynchroObj(null, $aFieldValues);

			$aFieldValues = [
				'start_date'     => date(\AttributeDateTime::GetSQLFormat(), strtotime('-1 HOURS')),
				'sync_source_id' => $oSynchroLog->Get('sync_source_id'),
			];
			$oSynchroLog = $this->CreateSynchroObj(null, $aFieldValues);
			$aExpectedSyncroLogIds[$sSynchroName] = $oSynchroLog->GetKey();
		}

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
		$aFieldValues = ['start_date' => date(\AttributeDateTime::GetSQLFormat(), strtotime($sCreatedSynchroLogsStartDateStrToTime))];
		$this->CreateSynchroObj("synchro_".uniqid(), $aFieldValues);

		$aMetricConf = [];
		if ($sMinStartDateConfValue !== null) {
			$aMetricConf[ItopSynchroLogReader::STRTOTIME_MIN_STARTDATE] = $sMinStartDateConfValue;
		}
		$oItopSynchroLogReader = new ItopSynchroLogReader('', $aMetricConf);
		$this->assertEquals($bIsEmpty, empty($oItopSynchroLogReader->ListSynchroLogObjects()));
	}

	private function CreateSynchroObj(?string $sSynchroName, $aAttributeValues = [])
	{
		if (!is_null($sSynchroName)) {
			$sSynchroSourceId = $this->createObject(\SynchroDataSource::class, ['name' => $sSynchroName]);
			$aAttributeValues['sync_source_id'] = $sSynchroSourceId;
		}
		$oObj = $this->createObject(\SynchroLog::class, $aAttributeValues);
		echo "SynchroLog:".$oObj->GetKey()."\n";

		return $oObj;
	}
}
