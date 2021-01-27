<?php
/*
 * Copyright (C) 2013-2021 Combodo SARL
 * This file is part of iTop.
 * iTop is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * iTop is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License
 */


namespace Combodo\iTop\Monitoring\Test\Controller;

use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use \Combodo\iTop\Monitoring\Controller\Controller;
use \Combodo\iTop\Monitoring\Model\MonitoringMetric;

class ControllerTest extends ItopDataTestCase {
    /** @var Controller $monitoringController */
    private $monitoringController;

    public function setUp()
    {
        require_once '/home/nono/PhpstormProjects/iTop/approot.inc.php';
        parent::setUp();

        require_once(APPROOT . 'core/config.class.inc.php');
        require_once(APPROOT . 'env-production/combodo-monitoring/src/Controller/Controller.php');
        require_once(APPROOT . 'env-production/combodo-monitoring/src/Model/MonitoringMetric.php');

        if (!defined('MODULESROOT'))
        {
            define('MODULESROOT', APPROOT.'env-production/');
        }

        $this->monitoringController = new Controller(MODULESROOT.'combodo-monitoring/src/view');
    }

    /**
     * @dataProvider GetMonitoringConf
     * @param array $aMetricConf
     * @param array $aExpectedMetrics
     */
        public function testReadMetrics($aMetricConf, $aExpectedMetricFields) {
        $aExpectedMetrics=[];
        foreach ($aExpectedMetricFields as $aPerMetricValues){
            $aLabel = count($aPerMetricValues)==4 ? $aPerMetricValues[3] : [];
            $aExpectedMetrics[] = new MonitoringMetric($aPerMetricValues[0], $aPerMetricValues[1], $aPerMetricValues[2], $aLabel);
        }
        $aMetrics = $this->monitoringController->ReadMetrics($aMetricConf);
        $this->assertEquals(
            $aExpectedMetrics,
            $aMetrics);
    }

    public function GetMonitoringConf() {
        return [
           'conf MySettings' => [
                ['access_mode' =>
                    [
                        'description' => 'access mode',
                        'conf' => [ 'MySettings', 'access_mode'],
                        'label' => 'labelname1,labelvalue1'
                    ]
                ],
                [["access_mode", 'access mode', "3", ["labelname1" => "labelvalue1"]]]
            ],
            'conf MySettings not found' => [
                ['access_message' =>
                    [
                        'description' => 'access message',
                        'conf' => [ 'MySettings', 'access_message2']
                    ]
                ],
                []
            ],
            'conf MyModuleSettings' => [
                ['retention_count' =>
                    [
                        'description' => 'retention count',
                        'conf' => [ 'MyModuleSettings', 'itop-backup', 'retention_count']
                    ]
                ],
                [["retention_count", 'retention count', "5"]]
            ],
            'conf MyModuleSettings not found' => [
                ['access_message' =>
                    [
                        'description' => 'retention count',
                        'conf' => [ 'MyModuleSettings', 'itop-backup2', 'retention_count']
                    ]
                ],
                []
            ],
            'conf MyModuleSettings not found2' => [
                ['access_message' =>
                    [
                        'description' => 'retention count',
                        'conf' => [ 'MyModuleSettings', 'itop-backup', 'retention_count2']
                    ]
                ],
                []
            ],
            /*'conf addons' => [
                ['user_rights' =>
                    [
                        'description' => 'user rights',
                        'conf' => [ 'MyModules', 'addons', 'user rights']
                    ]
                ],
                [["user_rights", 'user rights', "addons/userrights/userrightsprofile.class.inc.php"]]
            ],
            'conf addons not found' => [
                ['user_rights' =>
                    [
                        'description' => 'user rights',
                        'conf' => [ 'MyModules', 'addons2', 'user ri    ghts']
                    ]
                ],
                []
            ],
            'conf addons not found2' => [
                ['user_rights' =>
                    [
                        'description' => 'user rights',
                        'conf' => [ 'MyModules', 'addons', 'user rights2']
                    ]
                ],
                []
            ],*/
            'conf MyModuleSettings2' => [
                ['itop_backup_weekdays_count' =>
                    [
                        'description' => 'User authorized quota',
                        'conf' => [ 'MyModuleSettings', 'itop-backup', 'week_days']
                    ]
                ],
                [["itop_backup_weekdays_count", 'User authorized quota', "monday, tuesday, wednesday, thursday, friday"]]
            ],
            'oql_count' => [
                ['itop_user_count' =>
                    [
                        'description' => 'Nb of users',
                        'oql_count' => 'SELECT User WHERE id=1',
                        'label' => ' labelname2 , labelvalue2 '
                    ]
                ],
                [["itop_user_count", 'Nb of users', "1", ["labelname2" => "labelvalue2"]]]
            ],
            'oql_label' => [
                ['itop_user_count' =>
                    [
                        'description' => 'Nb of URP_UserProfile par type',
                        'oql_count' => 'SELECT URP_UserProfile JOIN URP_Profiles AS URP_Profiles_profileid ON URP_UserProfile.profileid = URP_Profiles_profileid.id WHERE URP_UserProfile.userid=1',
                        'oql_groupby' => 'profile, URP_Profiles_profileid.friendlyname'
                    ]
                ],
                [["itop_user_count", 'Nb of URP_UserProfile par type', "1", ["profile" => "Administrator"]]]
            ],
            'no_description_in_oql_metric' => [
                ['itop_user_count' =>
                    [
                        'oql_count' => 'SELECT User'
                    ]
                ],
                []
            ],
            'conf_without_description' => [
                ['itop_user_quota_count' =>
                    [
                        'conf' => 'ee.rrr'
                    ]
                ],
                []
            ],
            'no_metric' => [
                ['xxx' =>
                    ['itop_user_count' =>
                        [
                            'oql_count' => 'SELECT User'
                        ]
                    ]
                ],
                []
            ],
        ];
    }

    /**
     * @dataProvider computeOqlMetricsProvider
     */
    public function testComputeOqlMetrics($aConf, $sExpectedSql)
    {
        $mSQL = $this->monitoringController->ComputeOqlMetrics('foo', $aConf, true);

        if ($mSQL instanceof DBObjectSet) {
            $reflector = new ReflectionObject($mSQL);

            $secret = $reflector->getProperty('m_aAttToLoad');
            $secret->setAccessible(true);
            $m_aAttToLoad =  $secret->getValue($mSQL);

            $method = $reflector->getMethod('_makeSelectQuery');
            $method->setAccessible(true);
            $sSQL = $method->invoke($mSQL, $m_aAttToLoad);
        } else if (is_string($mSQL)) {
            $sSQL = $mSQL;
        } else {
            var_dump($mSQL);
            $this->assertEquals(false, true);
        }

        $this->assertEquals($sExpectedSql, $sSQL);

    }

    public function computeOqlMetricsProvider()
    {
        return [
            'oql_columns' => [
                'aConf' => [
                    'description' => 'ordered users',
                    'oql_count' => 'SELECT User',
                    'oql_columns' => ['User' => ['first_name', 'last_name']],
                ],
                'sExpectedSql' => "SELECT
 DISTINCT `User`.`id` AS `Userid`,
 `Person_contactid`.`first_name` AS `Userfirst_name`,
 `Person_contactid_Contact`.`name` AS `Userlast_name`,
 CAST(CONCAT(COALESCE(`User`.`login`, '')) AS CHAR) AS `Userfriendlyname`,
 `User`.`finalclass` AS `Userfinalclass`
 FROM 
   `priv_user` AS `User`
   LEFT JOIN (
      `person` AS `Person_contactid` 
      INNER JOIN 
         `contact` AS `Person_contactid_Contact`
       ON `Person_contactid`.`id` = `Person_contactid_Contact`.`id`
   ) ON `User`.`contactid` = `Person_contactid`.`id`
 WHERE 1
 ORDER BY `Userfriendlyname` ASC
 ",
            ],
            'oql_order' => [
                'aConf' => [
                    'description' => 'ordered users',
                    'oql_count' => 'SELECT User',
                    'oql_orderby' => ['first_name' => true, 'last_name' => false]
                ],
                'sExpectedSql' => "SELECT
 DISTINCT `User`.`id` AS `Userid`,
 `User`.`contactid` AS `Usercontactid`,
 `Person_contactid_Contact`.`name` AS `Userlast_name`,
 `Person_contactid`.`first_name` AS `Userfirst_name`,
 `Person_contactid_Contact`.`email` AS `Useremail`,
 `Person_contactid_Contact`.`org_id` AS `Userorg_id`,
 `User`.`login` AS `Userlogin`,
 `User`.`language` AS `Userlanguage`,
 `User`.`status` AS `Userstatus`,
 `User`.`finalclass` AS `Userfinalclass`,
 CAST(CONCAT(COALESCE(`User`.`login`, '')) AS CHAR) AS `Userfriendlyname`,
 CAST(CONCAT(COALESCE(`Person_contactid`.`first_name`, ''), COALESCE(' ', ''), COALESCE(`Person_contactid_Contact`.`name`, '')) AS CHAR) AS `Usercontactid_friendlyname`,
 COALESCE((`Person_contactid_Contact`.`status` = 'inactive'), 0) AS `Usercontactid_obsolescence_flag`,
 CAST(CONCAT(COALESCE(`Organization_org_id`.`name`, '')) AS CHAR) AS `Userorg_id_friendlyname`,
 COALESCE((`Organization_org_id`.`status` = 'inactive'), 0) AS `Userorg_id_obsolescence_flag`
 FROM 
   `priv_user` AS `User`
   LEFT JOIN (
      `person` AS `Person_contactid` 
      INNER JOIN (
         `contact` AS `Person_contactid_Contact` 
         INNER JOIN 
            `organization` AS `Organization_org_id`
          ON `Person_contactid_Contact`.`org_id` = `Organization_org_id`.`id`
      ) ON `Person_contactid`.`id` = `Person_contactid_Contact`.`id`
   ) ON `User`.`contactid` = `Person_contactid`.`id`
 WHERE 1
 ORDER BY `Userfirst_name` ASC, `Userlast_name` DESC
 ",
            ],
            'oql_limit' => [
                'aConf' => [
                    'description' => 'ordered users',
                    'oql_count' => 'SELECT User',
                    'oql_limit_count' => '42',
                    'oql_limit_start' => '24',
                ],
                'sExpectedSql' => "SELECT
 DISTINCT `User`.`id` AS `Userid`,
 `User`.`contactid` AS `Usercontactid`,
 `Person_contactid_Contact`.`name` AS `Userlast_name`,
 `Person_contactid`.`first_name` AS `Userfirst_name`,
 `Person_contactid_Contact`.`email` AS `Useremail`,
 `Person_contactid_Contact`.`org_id` AS `Userorg_id`,
 `User`.`login` AS `Userlogin`,
 `User`.`language` AS `Userlanguage`,
 `User`.`status` AS `Userstatus`,
 `User`.`finalclass` AS `Userfinalclass`,
 CAST(CONCAT(COALESCE(`User`.`login`, '')) AS CHAR) AS `Userfriendlyname`,
 CAST(CONCAT(COALESCE(`Person_contactid`.`first_name`, ''), COALESCE(' ', ''), COALESCE(`Person_contactid_Contact`.`name`, '')) AS CHAR) AS `Usercontactid_friendlyname`,
 COALESCE((`Person_contactid_Contact`.`status` = 'inactive'), 0) AS `Usercontactid_obsolescence_flag`,
 CAST(CONCAT(COALESCE(`Organization_org_id`.`name`, '')) AS CHAR) AS `Userorg_id_friendlyname`,
 COALESCE((`Organization_org_id`.`status` = 'inactive'), 0) AS `Userorg_id_obsolescence_flag`
 FROM 
   `priv_user` AS `User`
   LEFT JOIN (
      `person` AS `Person_contactid` 
      INNER JOIN (
         `contact` AS `Person_contactid_Contact` 
         INNER JOIN 
            `organization` AS `Organization_org_id`
          ON `Person_contactid_Contact`.`org_id` = `Organization_org_id`.`id`
      ) ON `Person_contactid`.`id` = `Person_contactid_Contact`.`id`
   ) ON `User`.`contactid` = `Person_contactid`.`id`
 WHERE 1
 ORDER BY `Userfriendlyname` ASC
 LIMIT 24, 42",
            ],
            'oql_order group by' => [
                'aConf' => [
                    'description' => 'ordered users',
                    'oql_count' => 'SELECT User',
                    'oql_groupby' => 'first_name, first_nameAlias',
                    'oql_orderby' => ['first_name' => true, '_itop_count_' => false]
                ],
                'sExpectedSql' => "SELECT `first_nameAlias` AS `first_name`, COUNT(DISTINCT COALESCE(`User`.`id`, 0)) AS _itop_count_ FROM `priv_user` AS `User` WHERE 1 GROUP BY `first_nameAlias` ORDER BY first_name ASC, _itop_count_ DESC ",
            ],
            'oql_limit group by' => [
                'aConf' => [
                    'description' => 'ordered users',
                    'oql_count' => 'SELECT User',
                    'oql_groupby' => 'profile, URP_Profiles_profileid.friendlyname',
                    'oql_limit_count' => '42',
                    'oql_limit_start' => '24',
                ],
                'sExpectedSql' => "SELECT `URP_Profiles_profileid`.`friendlyname` AS `profile`, COUNT(DISTINCT COALESCE(`User`.`id`, 0)) AS _itop_count_ FROM `priv_user` AS `User` WHERE 1 GROUP BY `URP_Profiles_profileid`.`friendlyname`  LIMIT 24, 42",
            ],

            'realife example' => [
                'aConf' => [
                    'description' => 'dismantle free instances',
                    'oql_count' => 'SELECT EventLoginUsage 
                                    JOIN User ON EventLoginUsage.user_id = User.id
                                    WHERE User.id NOT IN (
                                        SELECT User   
                                            JOIN URP_UserProfile ON URP_UserProfile.userid = User.id    
                                            JOIN URP_Profiles ON URP_UserProfile.profileid = URP_Profiles.id WHERE URP_Profiles.name="Administrator"
                                    )
',
                    'oql_columns' => ['EventLoginUsage' => ['date']],
                    'oql_orderby' => ['date' => false],
                    'oql_limit_count' => '1',
                    'oql_limit_start' => '0',
                ],
                'sExpectedSql' => "SELECT
 DISTINCT `EventLoginUsage`.`id` AS `EventLoginUsageid`,
 `EventLoginUsage_Event`.`date` AS `EventLoginUsagedate`,
 CAST(CONCAT(COALESCE('EventLoginUsage', '')) AS CHAR) AS `EventLoginUsagefriendlyname`,
 `EventLoginUsage_Event`.`realclass` AS `EventLoginUsagefinalclass`
 FROM 
   `priv_event_loginusage` AS `EventLoginUsage`
   INNER JOIN 
      `priv_user` AS `User`
    ON `EventLoginUsage`.`user_id` = `User`.`id`
   INNER JOIN 
      `priv_event` AS `EventLoginUsage_Event`
    ON `EventLoginUsage`.`id` = `EventLoginUsage_Event`.`id`
 WHERE (`User`.`id` NOT IN (SELECT
 DISTINCT `User1`.`id` AS `User1id`
 FROM 
   `priv_user` AS `User1`
   INNER JOIN (
      `priv_urp_userprofile` AS `URP_UserProfile` 
      INNER JOIN 
         `priv_urp_profiles` AS `URP_Profiles`
       ON `URP_UserProfile`.`profileid` = `URP_Profiles`.`id`
   ) ON `User1`.`id` = `URP_UserProfile`.`userid`
 WHERE (`URP_Profiles`.`name` = 'Administrator')
  ))
 ORDER BY `EventLoginUsagedate` DESC
 LIMIT 0, 1",
            ],
        ];
    }

    /**
     * @dataProvider ReadMetricConfProvider
     */
    public function  testReadMetricConf(string $sCollection, array $aConf, $aExpectedResult, ?string $sExpectedException)
    {
        if (null !== $sExpectedException) {
            $this->expectExceptionMessageRegExp($sExpectedException);
        }

        $oConfigMock = $this->createMock(Config::class);
        $oConfigMock->expects($this->any())
            ->method('GetModuleSetting')
            ->willReturn( $aConf);

        $result = $this->monitoringController->ReadMetricConf($sCollection, $oConfigMock);

        $this->assertEquals($aExpectedResult, $result);
    }

    public function ReadMetricConfProvider()
    {
        return [
            'nominal' => [
                'sCollection' => 'foo',
                'aConf' => ['foo' => ['whatever']],
                'aExpectedResult' => ['whatever'],
                'sExpectedException' => null
            ],
            'missing collection' => [
                'sCollection' => 'foo',
                'aConf' => ['bar' => []],
                'aExpectedResult' => null,
                'sExpectedException' => '/Collection "foo" not found/'
            ],

        ];
    }
}
