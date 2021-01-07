<?php

use \Combodo\iTop\Integrity\Monitoring\Controller\CombodoMonitoringMetric;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;
use \Combodo\iTop\Integrity\Monitoring\Controller\CombodoMonitoringController;

class CombodoMonitoringControllerTest extends ItopDataTestCase {
    /** @var CombodoMonitoringController $monitoringController */
    private $monitoringController;

    public function setUp()
    {
        @include_once '/home/combodo/workspace/iTop/approot.inc.php';
        parent::setUp();

        require_once(APPROOT . 'core/config.class.inc.php');
        require_once(APPROOT . 'env-production/combodo-monitoring/src/Controller/CombodoMonitoringController.php');
        require_once(APPROOT . 'env-production/combodo-monitoring/src/Controller/CombodoMonitoringMetric.php');

        if (!defined('MODULESROOT'))
        {
            define('MODULESROOT', APPROOT.'env-production/');
        }

        $this->monitoringController = new CombodoMonitoringController(MODULESROOT.'combodo-monitoring/src/view');
    }


    public function testConfigReading(){
        $aParams = $this->monitoringController->readConf(__DIR__ . '/' . MONITORING_CONFIG_FILE);

        $aExpected = ['metrics' =>
            [   'itop_user_count' =>
                    [
                        'description' => 'Nb of users',
                        'oql_count' => 'SELECT User',
                        'label' => 'labelname1,labelvalue1'
                    ],
                'itop_user_groupby_count' =>
                    [
                        'description' => 'Nb of users per profile',
                        'oql_count' => 'SELECT URP_UserProfile',
                        'oql_groupby' => 'type,Type',
                        'label' => 'labelname2,labelvalue2'
                    ],
                'itop_user_quota_count' =>
                    [
                        'description' => 'User authorized quota',
                        'conf' => 'eeii'
                    ],
                'itop_backup_retention_count' =>
                    [
                        'description' => 'description test',
                        'conf' => ['MyModuleSettings', 'itop-backup', 'retention_count'],
                        'label' => 'labelname3,labelvalue3'
                    ]
            ]
        ];

        $this->assertArrayHasKey('metrics', $aParams);

        $aMetrics = $aParams['metrics'];

        $this->assertArrayHasKey('itop_user_count', $aMetrics);
        $this->assertArrayHasKey('itop_user_groupby_count', $aMetrics);
        $this->assertArrayHasKey('itop_user_quota_count', $aMetrics);
        $this->assertArrayHasKey('itop_backup_retention_count', $aMetrics);

        $sExpected = json_encode($aExpected);
        $sArray = json_encode($aParams);
        $this->assertEquals($sExpected, $sArray);
    }

    /**
     * @dataProvider GetMonitoringConf
     * @param array $sInitConf
     * @param array $aExpectedMetrics
     */
        public function testReadMetrics($sInitConf, $aExpectedMetricFields) {
        $aExpectedMetrics=[];
        foreach ($aExpectedMetricFields as $aPerMetricValues){
            $aLabel = count($aPerMetricValues)==4 ? $aPerMetricValues[3] : [];
            $aExpectedMetrics[] = new CombodoMonitoringMetric($aPerMetricValues[0], $aPerMetricValues[1], $aPerMetricValues[2], $aLabel);
        }
        $aMetrics = $this->monitoringController->readMetrics($sInitConf);
        $this->assertEquals(
            $aExpectedMetrics,
            $aMetrics);
    }

    public function GetMonitoringConf() {
        return [
           'conf MySettings' => [
                ['metrics' =>
                    ['access_mode' =>
                        [
                            'description' => 'access mode',
                            'conf' => [ 'MySettings', 'access_mode'],
                            'label' => 'labelname1,labelvalue1'
                        ]
                    ]
                ],
                [["access_mode", 'access mode', "3", ["labelname1" => "labelvalue1"]]]
            ],
            'conf MySettings not found' => [
                ['metrics' =>
                    ['access_message' =>
                        [
                            'description' => 'access message',
                            'conf' => [ 'MySettings', 'access_message2']
                        ]
                    ]
                ],
                []
            ],
            'conf MyModuleSettings' => [
                ['metrics' =>
                    ['retention_count' =>
                        [
                            'description' => 'retention count',
                            'conf' => [ 'MyModuleSettings', 'itop-backup', 'retention_count']
                        ]
                    ]
                ],
                [["retention_count", 'retention count', "5"]]
            ],
            'conf MyModuleSettings not found' => [
                ['metrics' =>
                    ['access_message' =>
                        [
                            'description' => 'retention count',
                            'conf' => [ 'MyModuleSettings', 'itop-backup2', 'retention_count']
                        ]
                    ]
                ],
                []
            ],
            'conf MyModuleSettings not found2' => [
                ['metrics' =>
                    ['access_message' =>
                        [
                            'description' => 'retention count',
                            'conf' => [ 'MyModuleSettings', 'itop-backup', 'retention_count2']
                        ]
                    ]
                ],
                []
            ],
            /*'conf addons' => [
                ['metrics' =>
                    ['user_rights' =>
                        [
                            'description' => 'user rights',
                            'conf' => [ 'MyModules', 'addons', 'user rights']
                        ]
                    ]
                ],
                [["user_rights", 'user rights', "addons/userrights/userrightsprofile.class.inc.php"]]
            ],
            'conf addons not found' => [
                ['metrics' =>
                    ['user_rights' =>
                        [
                            'description' => 'user rights',
                            'conf' => [ 'MyModules', 'addons2', 'user ri    ghts']
                        ]
                    ]
                ],
                []
            ],
            'conf addons not found2' => [
                ['metrics' =>
                    ['user_rights' =>
                        [
                            'description' => 'user rights',
                            'conf' => [ 'MyModules', 'addons', 'user rights2']
                        ]
                    ]
                ],
                []
            ],*/
            'conf MyModuleSettings2' => [
                ['metrics' =>
                    ['itop_backup_weekdays_count' =>
                        [
                            'description' => 'User authorized quota',
                            'conf' => [ 'MyModuleSettings', 'itop-backup', 'week_days']
                        ]
                    ]
                ],
                [["itop_backup_weekdays_count", 'User authorized quota', "monday, tuesday, wednesday, thursday, friday"]]
            ],
            'oql_count' => [
                ['metrics' =>
                    ['itop_user_count' =>
                        [
                            'description' => 'Nb of users',
                            'oql_count' => 'SELECT User',
                            'label' => ' labelname2 , labelvalue2 '
                        ]
                    ]
                ],
                [["itop_user_count", 'Nb of users', "1", ["labelname2" => "labelvalue2"]]]
            ],
            'oql_label' => [
                ['metrics' =>
                    ['itop_user_count' =>
                        [
                            'description' => 'Nb of URP_UserProfile par type',
                            'oql_count' => 'SELECT URP_UserProfile JOIN URP_Profiles AS URP_Profiles_profileid ON URP_UserProfile.profileid = URP_Profiles_profileid.id',
                            'oql_groupby' => 'profile, URP_Profiles_profileid.friendlyname'
                        ]
                    ]
                ],
                [["itop_user_count", 'Nb of URP_UserProfile par type', "1", ["profile" => "Administrator"]]]
            ],
            'no_description_in_oql_metric' => [
                ['metrics' =>
                    ['itop_user_count' =>
                        [
                            'oql_count' => 'SELECT User'
                        ]
                    ]
                ],
                []
            ],
            'conf_without_description' => [
                ['metrics' =>
                    ['itop_user_quota_count' =>
                        [
                            'conf' => 'ee.rrr'
                        ]
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
            ]
        ];
    }
}
