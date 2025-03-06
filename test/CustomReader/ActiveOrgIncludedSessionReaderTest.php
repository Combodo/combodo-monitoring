<?php

namespace Combodo\iTop\Monitoring\Test\CustomReader;

use Combodo\iTop\Monitoring\CustomReader\ActiveOrgIncludedSessionReader;
use Combodo\iTop\Monitoring\Model\MonitoringMetric;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @backupGlobals disabled
 */
class ActiveOrgIncludedSessionReaderTest extends ItopDataTestCase
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
		$oiTopSessionReader = new ActiveOrgIncludedSessionReader('itop_session', []);

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
		$this->assertEquals(['login_mode' => 'no_auth', 'context' => '', 'org_uid' => 'no_uid'], $oMetric->GetLabels());
	}

	public function testAuthSessions() {
		$oiTopSessionReader = new ActiveOrgIncludedSessionReader('itop_session', []);
		$aMockOrgUids = [
			"1" => "org_uid1",
			"2" => "org_uid2",
			"3" => "org_uid3",
		];
		$oiTopSessionReader->SetOrgUids($aMockOrgUids);
		$sFiles = [];

		$aExpected = [
			'form' => [
				'TAG_PORTAL' => 1,
				'TAG_CONSOLE' => 2,
				'TAG_REST' => 3,
			],
			'token' => [
				'TAG_SYNCHRO' => 4,
				'TAG_REST' => 5,
			],
		];

		foreach ($aMockOrgUids as $sOrgId => $sUid) {
			foreach ($aExpected as $sLoginMode => $aSubExpected) {
				foreach ($aSubExpected as $sContext => $iCount) {
					for ($i = 0; $i < $iCount; $i++) {
						$sFile = $this->sDir.'sess_'.$sLoginMode.'_'.$sContext.'_'.$sOrgId."_".$i;
						$sFiles[] = $sFile;
						file_put_contents($sFile,
							json_encode(
								[
									'login_mode' => $sLoginMode,
									'user_id'    => $i,
									'org_id'    => $sOrgId,
									'context'    => $sContext,
								]
							)
						);
					}
				}
			}
		}

		var_dump($sFiles);

		$aMetrics = $oiTopSessionReader->FetchCounter($sFiles);
		$this->assertEquals(45, sizeof($aMetrics), var_export($aMetrics, true));

		foreach ($aMockOrgUids as $sOrgId => $sUid) {
			foreach ($aExpected as $sLoginMode => $aSubExpected) {
				foreach ($aSubExpected as $sContext => $iCount) {
					/** @var MonitoringMetric $oMetric */
					$oMetric = array_shift($aMetrics);
					var_dump($oMetric);
					$this->assertEquals('itop_session_count', $oMetric->GetName(), var_export($oMetric, true));
					$this->assertEquals($iCount, $oMetric->GetValue(), var_export($oMetric, true));
					$this->assertEquals(['login_mode' => $sLoginMode, 'context' => $sContext, 'org_uid' => $sUid], $oMetric->GetLabels(), var_export($oMetric, true));
				}
			}
		}

		foreach ($aMockOrgUids as $sOrgId => $sUid) {
			foreach ($aExpected as $sLoginMode => $aSubExpected) {
				foreach ($aSubExpected as $sContext => $iCount) {
					/** @var MonitoringMetric $oMetric */
					$oMetric = array_shift($aMetrics);
					var_dump($oMetric);
					$this->assertEquals('itop_session_elapsedinsecond_sum', $oMetric->GetName(), var_export($oMetric, true));
					//$this->assertEquals($iCount, $oMetric->GetValue(), var_export($oMetric, true));
					$this->assertEquals(['login_mode' => $sLoginMode, 'context' => $sContext, 'org_uid' => $sUid], $oMetric->GetLabels(), var_export($oMetric, true));
				}
			}
		}

		foreach ($aMockOrgUids as $sOrgId => $sUid) {
			foreach ($aExpected as $sLoginMode => $aSubExpected) {
				foreach ($aSubExpected as $sContext => $iCount) {
					/** @var MonitoringMetric $oMetric */
					$oMetric = array_shift($aMetrics);
					var_dump($oMetric);
					$this->assertEquals('itop_session_elapsedinsecond_max', $oMetric->GetName(), var_export($oMetric, true));
					//$this->assertEquals($iCount, $oMetric->GetValue(), var_export($oMetric, true));
					$this->assertEquals(['login_mode' => $sLoginMode, 'context' => $sContext, 'org_uid' => $sUid], $oMetric->GetLabels(), var_export($oMetric, true));
				}
			}
		}
	}

	public function testFetchOrgUid_UidProvided()
	{
		$aData=["org_uid" => "gabuzomeu"];
		$oiTopSessionReader = new ActiveOrgIncludedSessionReader('itop_session', []);
		$sRes = $this->InvokeNonPublicMethod(ActiveOrgIncludedSessionReader::class, "FetchOrgUid", $oiTopSessionReader, [$aData]);
		$this->assertEquals("gabuzomeu", $sRes);
	}

	public function testFetchOrgUid_NoFieldAtAll()
	{
		$aData=[];
		$oiTopSessionReader = new ActiveOrgIncludedSessionReader('itop_session', []);
		$sRes = $this->InvokeNonPublicMethod(ActiveOrgIncludedSessionReader::class, "FetchOrgUid", $oiTopSessionReader, [$aData]);
		$this->assertEquals(ActiveOrgIncludedSessionReader::NO_ORG_UID, $sRes);
	}

	public function testFetchOrgUid_ByOrgIdInCache()
	{
		$aData=['org_id' => "1"];
		$oiTopSessionReader = new ActiveOrgIncludedSessionReader('itop_session', []);
		$oiTopSessionReader->SetOrgUids(['1' => "gabuzomeu"]);
		$sRes = $this->InvokeNonPublicMethod(ActiveOrgIncludedSessionReader::class, "FetchOrgUid", $oiTopSessionReader, [$aData]);
		$this->assertEquals("gabuzomeu", $sRes);
	}

	public function testFetchOrgUid_ByOrgIdNoCache()
	{
		$sName = "monitoring-org".uniqid();
		$Org = $this->CreateOrganization($sName);
		$sKey = $Org->GetKey();
		$aData=['org_id' => $sKey];
		$oiTopSessionReader = new ActiveOrgIncludedSessionReader('itop_session', []);
		$oiTopSessionReader->SetOrgUids(["1" => "gabuzomeu"]);
		$sRes = $this->InvokeNonPublicMethod(ActiveOrgIncludedSessionReader::class, "FetchOrgUid", $oiTopSessionReader, [$aData]);
		$this->assertEquals($sName, $sRes);
		$aCache = $this->GetNonPublicProperty($oiTopSessionReader, 'aOrgUids');
		ksort($aCache);

		$aExpected = [
			"1" => "gabuzomeu",
			$sKey => $sName,
		];
		ksort($aExpected);
		$this->assertEquals($aExpected, $aCache);
	}

	public function testFetchOrgUid_ByContactlessUserId()
	{
		$oUser = $this->CreateContactlessUser("Monitoring".uniqid() . "NoOrgUser", ItopDataTestCase::$aURP_Profiles['Service Desk Agent'], "ABCdefg@12345#");
		$aData=['user_id' => $oUser->GetKey()];
		$oiTopSessionReader = new ActiveOrgIncludedSessionReader('itop_session', []);
		$sRes = $this->InvokeNonPublicMethod(ActiveOrgIncludedSessionReader::class, "FetchOrgUid", $oiTopSessionReader, [$aData]);
		$this->assertEquals(ActiveOrgIncludedSessionReader::NO_ORG_UID, $sRes);
	}

	public function testFetchOrgUid_ByUserIdContact()
	{
		$sName = "monitoring-org".uniqid();
		$Org = $this->CreateOrganization($sName);
		$sOrgId = $Org->GetKey();
		$sLogin = "Monitoring".uniqid() . "UserWithOrg";
		$oPerson = $this->CreatePerson("$sLogin", $sOrgId);

		$oProfileLinkSet = new \ormLinkSet(\User::class, 'profile_list', \DBObjectSet::FromScratch(\URP_UserProfile::class));
		$oUserProfile = new \URP_UserProfile();
		$oUserProfile->Set('profileid', ItopDataTestCase::$aURP_Profiles['Service Desk Agent']);
		$oUserProfile->Set('reason', 'UNIT Tests');
		$oProfileLinkSet->AddItem($oUserProfile);

		$oUser = $this->createObject('UserLocal', [
			'login' => $sLogin,
			'password' => "ABCdefg@12345#",
			'language' => 'EN US',
			'profile_list' => $oProfileLinkSet,
			'contactid' => $oPerson->GetKey()
		]);

		$aData=['user_id' => $oUser->GetKey()];
		$oiTopSessionReader = new ActiveOrgIncludedSessionReader('itop_session', []);
		$oiTopSessionReader->SetOrgUids(["1" => "gabuzomeu"]);
		$sRes = $this->InvokeNonPublicMethod(ActiveOrgIncludedSessionReader::class, "FetchOrgUid", $oiTopSessionReader, [$aData]);

		$this->assertEquals($sName, $sRes);

		$aCache = $this->GetNonPublicProperty($oiTopSessionReader, 'aOrgUids');
		ksort($aCache);

		$aExpected = [
			"1" => "gabuzomeu",
			$sOrgId => $sName,
		];
		ksort($aExpected);
		$this->assertEquals($aExpected, $aCache);
	}

	public function testFetchOrgUid_ByUserIdContact_ViaCache()
	{
		$sName = "monitoring-org".uniqid();
		$Org = $this->CreateOrganization($sName);
		$sOrgId = $Org->GetKey();
		$sLogin = "Monitoring".uniqid() . "UserWithOrg";
		$oPerson = $this->CreatePerson("$sLogin", $sOrgId);

		$oProfileLinkSet = new \ormLinkSet(\User::class, 'profile_list', \DBObjectSet::FromScratch(\URP_UserProfile::class));
		$oUserProfile = new \URP_UserProfile();
		$oUserProfile->Set('profileid', ItopDataTestCase::$aURP_Profiles['Service Desk Agent']);
		$oUserProfile->Set('reason', 'UNIT Tests');
		$oProfileLinkSet->AddItem($oUserProfile);

		$oUser = $this->createObject('UserLocal', [
			'login' => $sLogin,
			'password' => "ABCdefg@12345#",
			'language' => 'EN US',
			'profile_list' => $oProfileLinkSet,
			'contactid' => $oPerson->GetKey()
		]);

		$aData=['user_id' => $oUser->GetKey()];
		$oiTopSessionReader = new ActiveOrgIncludedSessionReader('itop_session', []);
		$oiTopSessionReader->SetOrgUids([$sOrgId => $sName]);
		$sRes = $this->InvokeNonPublicMethod(ActiveOrgIncludedSessionReader::class, "FetchOrgUid", $oiTopSessionReader, [$aData]);

		$this->assertEquals($sName, $sRes);

		$aCache = $this->GetNonPublicProperty($oiTopSessionReader, 'aOrgUids');
		ksort($aCache);

		$aExpected = [
			$sOrgId => $sName,
		];
		ksort($aExpected);
		$this->assertEquals($aExpected, $aCache);
	}
}
