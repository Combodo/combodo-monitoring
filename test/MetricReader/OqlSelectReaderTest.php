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

namespace Combodo\iTop\Monitoring\Test\MetricReader;

use Combodo\iTop\Monitoring\MetricReader\OqlSelectReader;
use Combodo\iTop\Monitoring\Model\MonitoringMetric;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;

/**
 * @group sampleDataNeeded
 */
class OqlSelectReaderTest extends ItopDataTestCase
{
    public function setUp()
    {
        //require_once '/home/combodo/workspace/iTop/approot.inc.php';
        parent::setUp();

        require_once(APPROOT . 'env-production/combodo-monitoring/vendor/autoload.php');
        require_once(APPROOT . 'core/config.class.inc.php');

    }

    /**
     * @dataProvider GetMetricsProvider
     */
    public function testGetGetValue(array $aMetric, $aExpectedResult, $aExpectedMetrics = null)
    {
        $oOqlSelectReader = new OqlSelectReader('foo', $aMetric);

        $reflector = new \ReflectionObject($oOqlSelectReader);
        $method = $reflector->getMethod('GetObjectSet');
        $method->setAccessible(true);
        /** @var \DBObjectSet $oSet */
        $oSet = $method->invoke($oOqlSelectReader);

        $reflector = new \ReflectionObject($oSet);
        $secret = $reflector->getProperty('m_aAttToLoad');
        $secret->setAccessible(true);
        $m_aAttToLoad =  $secret->getValue($oSet);

        $method = $reflector->getMethod('_makeSelectQuery');
        $method->setAccessible(true);
        $sSQL = $method->invoke($oSet, $m_aAttToLoad);

	    $aMetrics = $oOqlSelectReader->GetMetrics();

	    if ($aExpectedMetrics !== null){
		    $this->assertNotEmpty($aMetrics);
		    $this->assertEquals(sizeof($aExpectedMetrics), sizeof($aMetrics));
		    $iIndex = 0;
		    foreach ($aMetrics as $oMetric) {
		    	/** @var MonitoringMetric $oMetric */
			    $this->assertEquals("foo", $oMetric->GetName());
			    $this->assertEquals("ordered users", $oMetric->GetDescription());
			    $this->assertEquals($aExpectedMetrics[$iIndex]['value'], $oMetric->GetValue());
			    $this->assertEquals($aExpectedMetrics[$iIndex]['labels'], $oMetric->GetLabels());
		    }
	    }

	    $this->assertEquals($aExpectedResult, $sSQL);
    }

    public function GetMetricsProvider(): array
    {
	    $sJointSelect = "SELECT
 DISTINCT `up`.`id` AS `upid`,
 `p`.`name` AS `upprofile`,
 `up`.`profileid` AS `upprofileid`,
 CAST(CONCAT(COALESCE(`p`.`name`, '')) AS CHAR) AS `upprofileid_friendlyname`,
 CAST(CONCAT(COALESCE('Link between ', ''), COALESCE(`User_userid`.`login`, ''), COALESCE(' and ', ''), COALESCE(`p`.`name`, '')) AS CHAR) AS `upfriendlyname`,
 `p`.`id` AS `pid`,
 `p`.`name` AS `pname`,
 CAST(CONCAT(COALESCE(`p`.`name`, '')) AS CHAR) AS `pfriendlyname`
 FROM 
   `priv_urp_userprofile` AS `up`
   INNER JOIN 
      `priv_urp_profiles` AS `p`
    ON `up`.`profileid` = `p`.`id`
   INNER JOIN 
      `priv_user` AS `User_userid`
    ON `up`.`userid` = `User_userid`.`id`
 WHERE (`up`.`profileid` = 1)
 ORDER BY `upfriendlyname` ASC
 ";
	    $sJointSelect2 = "SELECT
 DISTINCT `up`.`id` AS `upid`,
 `p`.`name` AS `upprofile`,
 `up`.`profileid` AS `upprofileid`,
 CAST(CONCAT(COALESCE(`p`.`name`, '')) AS CHAR) AS `upprofileid_friendlyname`,
 CAST(CONCAT(COALESCE(`up`.`userid`, '')) AS CHAR) AS `upfriendlyname`
 FROM 
   `priv_urp_userprofile` AS `up`
   INNER JOIN 
      `priv_urp_profiles` AS `p`
    ON `up`.`profileid` = `p`.`id`
 WHERE (`up`.`profileid` = 1)
 ORDER BY `upfriendlyname` ASC
 ";

	    return [
	        'oql_columns with org_id (to optimize as well)' => [
		        'aMetric' => [
			        'oql_select' => [
				        'select' => 'SELECT User WHERE id=1',
				        'labels' =>  ['firstname' => 'first_name', 'lastname' => 'last_name'],
				        'value' => 'org_id'
			        ],
			        'description' => 'ordered users',
		        ],
		        'aExpectedResult' => "SELECT
 DISTINCT `User`.`id` AS `Userid`,
 `Person_contactid`.`first_name` AS `Userfirst_name`,
 `Person_contactid_Contact`.`name` AS `Userlast_name`,
 `Person_contactid_Contact`.`org_id` AS `Userorg_id`,
 CAST(CONCAT(COALESCE(`Organization_org_id`.`name`, '')) AS CHAR) AS `Userorg_id_friendlyname`,
 COALESCE((`Organization_org_id`.`status` = 'inactive'), 0) AS `Userorg_id_obsolescence_flag`,
 CAST(CONCAT(COALESCE(`User`.`login`, '')) AS CHAR) AS `Userfriendlyname`,
 `User`.`finalclass` AS `Userfinalclass`
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
 WHERE (`User`.`id` = 1)
 ORDER BY `Userfriendlyname` ASC
 ",
		        'aExpectedMetrics' => [
		        	[ 'value' => "1", 'labels' => [ 'firstname' => 'My first name', 'lastname' => 'My last name' ] ]
		        ]
	        ],
	        'oql_columns with User.org_id (alias)' => [
		        'aMetric' => [
			        'oql_select' => [
				        'select' => 'SELECT User WHERE id=1',
				        'labels' =>  ['firstname' => 'first_name', 'lastname' => 'last_name'],
				        'value' => 'User.org_id'
			        ],
			        'description' => 'ordered users',
		        ],
		        'aExpectedResult' => "SELECT
 DISTINCT `User`.`id` AS `Userid`,
 `Person_contactid`.`first_name` AS `Userfirst_name`,
 `Person_contactid_Contact`.`name` AS `Userlast_name`,
 `Person_contactid_Contact`.`org_id` AS `Userorg_id`,
 CAST(CONCAT(COALESCE(`Organization_org_id`.`name`, '')) AS CHAR) AS `Userorg_id_friendlyname`,
 COALESCE((`Organization_org_id`.`status` = 'inactive'), 0) AS `Userorg_id_obsolescence_flag`,
 CAST(CONCAT(COALESCE(`User`.`login`, '')) AS CHAR) AS `Userfriendlyname`,
 `User`.`finalclass` AS `Userfinalclass`
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
 WHERE (`User`.`id` = 1)
 ORDER BY `Userfriendlyname` ASC
 ",
		        'aExpectedMetrics' => [
			        [ 'value' => "1", 'labels' => [ 'firstname' => 'My first name', 'lastname' => 'My last name' ] ]
		        ]
	        ],
            'oql_columns with id (not an attributedef optimizable)' => [
                'aMetric' => [
                    'oql_select' => [
                        'select' => 'SELECT User WHERE id = 1',
                        'labels' =>  ['firstname' => 'first_name', 'lastname' => 'last_name'],
	                    'value' => 'id'
                    ],
                    'description' => 'ordered users',
                ],
                'aExpectedResult' => "SELECT
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
 WHERE (`User`.`id` = 1)
 ORDER BY `Userfriendlyname` ASC
 ",
	            'aExpectedMetrics' => [
		            [ 'value' => "1", 'labels' => [ 'firstname' => 'My first name', 'lastname' => 'My last name' ] ]
	            ]
            ],
            'oql_order' => [
                'aConf' => [
                    'description' => 'ordered users',
                    'oql_select' => [
                        'select' => 'SELECT User WHERE id=1',
                        'orderby' => ['first_name' => true, 'last_name' => false],
	                    'value' => 'id'
                    ]
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
 WHERE (`User`.`id` = 1)
 ORDER BY `Userfirst_name` ASC, `Userlast_name` DESC
 ",
	            'aExpectedMetrics' => [
		            [ 'value' => "1", 'labels' => [] ]
	            ]
            ],
            'oql_limit' => [
                'aConf' => [
                    'description' => 'ordered users',
                    'oql_select' => [
                        'select' => 'SELECT User WHERE id=1',
                        'limit_count' => '1',
                        'limit_start' => '0',
	                    'value' => 'id'
                    ],
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
 WHERE (`User`.`id` = 1)
 ORDER BY `Userfriendlyname` ASC
 LIMIT 0, 1",
	            'aExpectedMetrics' => [
		            [ 'value' => "1", 'labels' => [] ]
	            ]
            ],
            /*'realife example' => [
                'aConf' => [
                    'description' => 'dismantle free instances',
                    'oql_select' => [
                        'select' => 'SELECT EventLoginUsage 
                                    JOIN User ON EventLoginUsage.user_id = User.id
                                    WHERE User.id NOT IN (
                                        SELECT User   
                                            JOIN URP_UserProfile ON URP_UserProfile.userid = User.id    
                                            JOIN URP_Profiles ON URP_UserProfile.profileid = URP_Profiles.id WHERE URP_Profiles.name="Administrator"
                                    )
                                    ',
                        'labels' => ['date' => 'date'],
	                    'value' => 'id',
                        'orderby' => ['date' => false],
                        'limit_count' => '1',
                        'limit_start' => '0',
                    ],
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
            ],*/
	        /*'jointure using fields on both sides with -> syntax (metrics make no sense)' => [
		        'aMetric' => [
			        'oql_select' => [
				        'select' => 'SELECT up, p FROM URP_UserProfile AS up JOIN URP_Profiles AS p ON up.profileid = p.id WHERE up.profileid = 1',
				        'labels' =>  [
				        	'profile' => 'p.name',
					        'name' => 'profileid->profile'
				        ],
				        'value' => 'up.profileid'
			        ],
			        'description' => 'ordered users',
		        ],
		        'aExpectedResult' => $sJointSelect,
	        ],*/
		    'jointure using fields on both sides with FROM syntax' => [
			    'aMetric' => [
				    'oql_select' => [
					    'select' => 'SELECT up, p FROM URP_UserProfile AS up JOIN URP_Profiles AS p ON up.profileid = p.id WHERE up.profileid = 1',
					    'labels' =>  [
						    'profile' => 'p.name',
						    'name' => 'up.profile'
					    ],
					    'value' => 'up.profileid'
				    ],
				    'description' => 'ordered users',
			    ],
			    'aExpectedResult' => $sJointSelect,
			    'aExpectedMetrics' => [
				    [ 'value' => "1", 'labels' => [ 'profile' => 'Administrator', 'name' => 'Administrator' ] ]
			    ]
		    ],
        ];
    }
}