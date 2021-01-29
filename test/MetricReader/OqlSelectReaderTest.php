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
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;

class OqlSelectReaderTest extends ItopDataTestCase
{
    public function setUp()
    {
        @include_once '/home/nono/PhpstormProjects/iTop/approot.inc.php';
        parent::setUp();

        require_once(APPROOT . 'env-production/combodo-monitoring/vendor/autoload.php');
        require_once(APPROOT . 'core/config.class.inc.php');

    }

    /**
     * @dataProvider GetMetricsProvider
     */
    public function testGetGetValue(array $aMetric, $aExpectedResult)
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

        $this->assertEquals($aExpectedResult, $sSQL);
    }

    public function GetMetricsProvider(): array
    {
        return [
            'oql_columns' => [
                'aMetric' => [
                    'oql_select' => [
                        'select' => 'SELECT User',
                        'columns' =>  ['first_name', 'last_name'],
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
 WHERE 1
 ORDER BY `Userfriendlyname` ASC
 ",
            ],
            'oql_order' => [
                'aConf' => [
                    'description' => 'ordered users',
                    'oql_select' => [
                        'select' => 'SELECT User',
                        'orderby' => ['first_name' => true, 'last_name' => false],
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
 WHERE 1
 ORDER BY `Userfirst_name` ASC, `Userlast_name` DESC
 ",
            ],
            'oql_limit' => [
                'aConf' => [
                    'description' => 'ordered users',
                    'oql_select' => [
                        'select' => 'SELECT User',
                        'limit_count' => '42',
                        'limit_start' => '24',
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
 WHERE 1
 ORDER BY `Userfriendlyname` ASC
 LIMIT 24, 42",
            ],
            'realife example' => [
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
                        'columns' => ['date'],
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
            ],
        ];
    }
}