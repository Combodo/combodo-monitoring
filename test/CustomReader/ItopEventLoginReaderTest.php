<?php

namespace Combodo\iTop\Test\UnitTest;

use AttributeDateTime;
use Combodo\iTop\Monitoring\CustomReader\ItopEventLoginReader;

/**
 * @group user_profiles
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @backupGlobals disabled
 */
class ItopEventLoginReaderTest extends ItopDataTestCase
{
    const USE_TRANSACTION = false;
    private array $aProfiles = [];
    private array $aUsers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->RequireOnceItopFile('env-production/combodo-monitoring/vendor/autoload.php');

        $this->CreateTestOrganization();
        $this->readProfiles();
    }

    private function readProfiles()
    {
        $oSearch = \DBObjectSearch::FromOQL('SELECT URP_Profiles');
        $oSet = new \DBObjectSet($oSearch);

        /* var \URP_Profiles $oObject  */
        while ($aObject = $oSet->Fetch()) {
            $sName = $aObject->Get('name');
            $this->aProfiles[$sName] = $aObject->GetKey();
        }
    }

    public function GetMetricsProvider()
    {
        return [
            'test one eventlogin per user' => [
                'users' => [
                    'user1' => ['login' => 'login1', 'profiles' => ['Administrator']],
                    'user2' => ['login' => 'login2', 'profiles' => ['Portal user']],
                    'user3' => ['login' => 'login3', 'profiles' => ['Configuration Manager', 'Portal power user']],
                    'user4' => ['login' => 'login4', 'profiles' => ['Portal power user', 'Configuration Manager']],
                ],
                'event_login' => [
                    ['user' => 'user1', 'recent' => true],
                    ['user' => 'user2', 'recent' => true],
                    ['user' => 'user3', 'recent' => true],
                    ['user' => 'user4', 'recent' => true],
                ],
                'expected_metrics' => [
                    ['metric_name' => 'itop_eventlogin', 'metric_value' => 1, 'account_type' => 'userlocal',  'profiles' => 'administrator'],
                    ['metric_name' => 'itop_eventlogin', 'metric_value' => 1, 'account_type' => 'userlocal',  'profiles' => 'portal_user'],
                    ['metric_name' => 'itop_eventlogin', 'metric_value' => 2, 'account_type' => 'userlocal',  'profiles' => 'configuration_manager+portal_power_user'],
                ],
            ],
            'test eventlogin in the last hour only' => [
                'users' => [
                    'user1' => ['login' => 'login1', 'profiles' => ['Administrator']],
                ],
                'event_login' => [
                    ['user' => 'user1', 'recent' => false],
                    ['user' => 'user1', 'recent' => true],
                    ['user' => 'user1', 'recent' => false],
                ],
                'expected_metrics' => [
                    ['metric_name' => 'itop_eventlogin', 'metric_value' => 1, 'account_type' => 'userlocal',  'profiles' => 'administrator'],
                ],
            ],
        ];
    }

    private function CreateUserWithProfiles($sLogin, $aUserProfiles)
    {
        $sPassword = '123456789@AbCdE';
        $oSet = null;

        $oPerson = $this->CreatePerson($sLogin);

        foreach ($aUserProfiles as $sProfileName) {
            $oUserProfile = new \URP_UserProfile();
            $oUserProfile->Set('profileid', $this->aProfiles[$sProfileName]);
            $oUserProfile->Set('reason', 'UNIT Tests');

            if (is_null($oSet)) {
                $oSet = \DBObjectSet::FromObject($oUserProfile);
            } else {
                $oSet->AddObject($oUserProfile);
            }
        }

        $oUser = $this->createObject('UserLocal', [
            'contactid' => $oPerson->GetKey(),
            'login' => $sLogin,
            'password' => $sPassword,
            'language' => 'EN US',
            'profile_list' => $oSet,
        ]);
        $this->debug("Created {$oUser->GetName()} ({$oUser->GetKey()})");

        return $oUser;
    }

    /**
     * @dataProvider GetMetricsProvider
     */
    public function testGetMetrics($aUsers, $aEventLogins, $aExpectedMetrics)
    {
	    $oObjSearch = new \DBObjectSearch('EventLoginUsage');
	    $oSet = new \DBObjectSet($oObjSearch);
	    while ($oEventLoginUsage = $oSet->Fetch()) {
		    $oEventLoginUsage->DBDelete();
	    }

        foreach ($aUsers as $sUserId => $aUser) {
            $sLogin = $aUser['login'];
            $aUserProfiles = $aUser['profiles'];
            $oUser = $this->CreateUserWithProfiles($sLogin, $aUserProfiles);
            $this->aUsers[$sUserId] = $oUser;
        }

        foreach ($aEventLogins as $aEventLogin) {
            $oUser = $this->aUsers[$aEventLogin['user']];
	        $initialDate = date(AttributeDateTime::GetFormat());
	        $oEventLoginObject = $this->CreateObject('EventLoginUsage', [
                'date' => $initialDate,
                'userinfo' => $oUser,
                'user_id' => $oUser->GetKey(),
                'message' => 'Successful login',
            ]);

	        if (false === $aEventLogin['recent']) {
		        $updatedDate = date(AttributeDateTime::GetFormat(), strtotime('-2 HOURS'));
		        $oEventLoginObject->Set('date', $updatedDate);
                $oEventLoginObject->DBWrite();
	        }
	        //var_dump(['id' => $oEventLoginObject->GetKey(), 'date' => $oEventLoginObject->Get('date') ]);
        }

        $aLabels = ['toto' => 'titi'];
        $oItopEventLoginReader = new ItopEventLoginReader('', ['static_labels' => $aLabels]);

        $aMetrics = $oItopEventLoginReader->GetMetrics();
        $sizeof = sizeof($aExpectedMetrics);
        //var_dump($aExpectedMetrics);
        $this->assertEquals($sizeof, sizeof($aMetrics));
        for ($i = 0; $i < $sizeof; ++$i) {
            /* @var \Combodo\iTop\Monitoring\Model\MonitoringMetric $oMetric */
            $oMetric = $aMetrics[$i];

            $aExpectedMetric = $aExpectedMetrics[$i];

            $this->assertEquals($aExpectedMetric['metric_name'], $oMetric->GetName());
            $this->assertEquals($aExpectedMetric['metric_value'], $oMetric->GetValue());
            $aLabels = $oMetric->GetLabels();
            $this->assertEquals($aExpectedMetric['account_type'], $aLabels['account_type']);
            $this->assertEquals($aExpectedMetric['profiles'], $aLabels['profiles']);
        }
    }
}
