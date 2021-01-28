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
     * @dataProvider ReadMetricsProvider
     * @param array $aMetricConf
     * @param array $aExpectedMetrics
     */
    public function testReadMetrics($aMetricConf, $aExpectedMetricFields) {
        $aExpectedMetrics=[];

        foreach ($aExpectedMetricFields as $aPerMetricValues){
            $aLabel = count($aPerMetricValues) == 4 ? $aPerMetricValues[3] : [];
            $aExpectedMetrics[] = new MonitoringMetric($aPerMetricValues[0], $aPerMetricValues[1], $aPerMetricValues[2], $aLabel);
        }

        $aMetrics = $this->monitoringController->ReadMetrics($aMetricConf);

        $this->assertEquals(
            $aExpectedMetrics,
            $aMetrics
        );
    }

    public function ReadMetricsProvider() {
        return [
           'conf MySettings' => [
                ['access_mode' =>
                    [
                        'description' => 'access mode',
                        'conf' => [ 'MySettings', 'access_mode'],
                        'label' => ['labelname1' => 'labelvalue1']
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

            'conf MyModuleSettings2' => [
                ['itop_backup_weekdays_count' =>
                    [
                        'description' => 'User authorized quota',
                        'conf' => [ 'MyModuleSettings', 'itop-backup', 'week_days']
                    ]
                ],
                [["itop_backup_weekdays_count", 'User authorized quota', "monday, tuesday, wednesday, thursday, friday"]]
            ],

            'conf itop-standard-email-synchro sub array' => [
                ['itop_standard_email_synchro' =>
                    [
                        'description' => 'ticket logs',
                        'conf' => [ 'MyModuleSettings', 'itop-standard-email-synchro', 'ticket_log', 'Incident']
                    ]
                ],
                [["itop_standard_email_synchro", 'ticket logs', "public_log"]]
            ],
            'oql_count with 2 labels, 1 not trimmed' => [
                ['itop_user_count' =>
                    [
                        'description' => 'Nb of users',
                        'oql_count' => [
                            'select' => 'SELECT User WHERE id=1',
                        ],
                        'label' => ['labelname2' => 'labelvalue2', 'name with space at the end     ' =>'foo']
                    ]
                ],
                [["itop_user_count", 'Nb of users', "1", ["labelname2" => "labelvalue2", 'name with space at the end     ' =>'foo']]]
            ],
            'oql_groupby with labels' => [
                ['itop_user_count' =>
                    [
                        'description' => 'Nb of URP_UserProfile par type',
                        'oql_groupby' => [
                            'select' => 'SELECT URP_UserProfile JOIN URP_Profiles AS URP_Profiles_profileid ON URP_UserProfile.profileid = URP_Profiles_profileid.id WHERE URP_UserProfile.userid=1',
                            'groupby' => ['profile' => 'URP_Profiles_profileid.friendlyname'],
                        ]
                    ]
                ],
                [["itop_user_count", 'Nb of URP_UserProfile par type', "1", ["profile" => "Administrator"]]]
            ],
            'no_description_in_oql_metric' => [
                ['itop_user_count' =>
                    [
                        'oql_count' => [
                            'select' => 'SELECT User',
                        ]
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
                            'oql_count' => [
                                'select' => 'SELECT User',
                            ]
                        ]
                    ]
                ],
                []
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

        $oConfigMock = $this->createMock(\Config::class);
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
