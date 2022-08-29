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

use Combodo\iTop\Monitoring\Controller\Controller;
use Combodo\iTop\Monitoring\Model\MonitoringMetric;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @backupGlobals disabled
 */
class ControllerTest extends ItopDataTestCase
{
    /** @var Controller */
    private $monitoringController;

    protected function setUp(): void
    {
        //require_once '/home/nono/PhpstormProjects/iTop/approot.inc.php';
        parent::setUp();

        require_once APPROOT.'core/config.class.inc.php';
        require_once APPROOT.'env-production/combodo-monitoring/src/Controller/Controller.php';
        require_once APPROOT.'env-production/combodo-monitoring/src/Model/MonitoringMetric.php';

        if (!defined('MODULESROOT')) {
            define('MODULESROOT', APPROOT.'env-production/');
        }

        $this->monitoringController = new Controller(MODULESROOT.'combodo-monitoring/src/view');
    }

    /**
     * @dataProvider ReadMetricsProvider
     *
     * @param array $aMetricConf
     * @param array $aExpectedMetrics
     */
    public function testReadMetrics($aMetricConf, $aExpectedMetricFields)
    {
        $aExpectedMetrics = null;

        foreach ($aExpectedMetricFields as $aPerMetricValues) {
            $aLabel = 4 == count($aPerMetricValues) ? $aPerMetricValues[3] : [];
            $aExpectedMetric = new MonitoringMetric($aPerMetricValues[0], $aPerMetricValues[1], $aPerMetricValues[2], $aLabel);
            break;
        }

        $aMetrics = $this->monitoringController->ReadMetrics($aMetricConf);

        if ($aExpectedMetric == null){
            $this->assertEquals(0, sizeof($aMetrics), var_export($aMetrics, true));
        } else{
            $this->assertEquals(1, sizeof($aMetrics), var_export($aMetrics, true));
            $this->assertEquals(
                $aExpectedMetric,
                $aMetrics[0]
            );
        }
    }

    public function ReadMetricsProvider()
    {
        return [
           'conf MySettings' => [
                ['access_mode' => [
                        'description' => 'access mode',
                        'conf' => ['MySettings', 'access_mode'],
                        'static_labels' => ['labelname1' => 'labelvalue1'],
                    ],
                ],
                [['access_mode', 'access mode', '3', ['labelname1' => 'labelvalue1']]],
            ],
            'conf MySettings not found' => [
                ['access_message1' => [
                        'description' => 'access message',
                        'conf' => ['MySettings', 'access_message2'],
                    ],
                ],
                [],
            ],
            'conf MyModuleSettings' => [
                ['retention_count' => [
                        'description' => 'retention count',
                        'conf' => ['MyModuleSettings', 'itop-backup', 'retention_count'],
                    ],
                ],
                [['retention_count', 'retention count', '5']],
            ],
            'conf MyModuleSettings not found' => [
                ['access_message2' => [
                        'description' => 'retention count',
                        'conf' => ['MyModuleSettings', 'itop-backup2', 'retention_count'],
                    ],
                ],
                [],
            ],
            'conf MyModuleSettings not found2' => [
                ['access_message3' => [
                        'description' => 'retention count',
                        'conf' => ['MyModuleSettings', 'itop-backup', 'retention_count2'],
                    ],
                ],
                [],
            ],

            'conf MyModuleSettings2' => [
                ['itop_backup_weekdays_count' => [
                        'description' => 'User authorized quota',
                        'conf' => ['MyModuleSettings', 'itop-backup', 'week_days'],
                    ],
                ],
                [['itop_backup_weekdays_count', 'User authorized quota', 'monday, tuesday, wednesday, thursday, friday']],
            ],
            'oql_count with 2 labels, 1 not trimmed' => [
                ['itop_user_count' => [
                        'description' => 'Nb of users',
                        'oql_count' => [
                            'select' => 'SELECT User WHERE id=1',
                        ],
                        'static_labels' => ['labelname2' => 'labelvalue2', 'name with space at the end     ' => 'foo'],
                    ],
                ],
                [['itop_user_count', 'Nb of users', '1', ['labelname2' => 'labelvalue2', 'name with space at the end     ' => 'foo']]],
            ],
            'no_description_in_oql_metric' => [
                ['itop_user_nodescription_count' => [
                        'oql_count' => [
                            'select' => 'SELECT User',
                        ],
                    ],
                ],
                [],
            ],
            'conf_without_description' => [
                ['itop_user_quota_count' => [
                        'conf' => 'ee.rrr',
                    ],
                ],
                [],
            ],
            'no_metric' => [
                ['xxx' => ['itop_user_count' => [
                            'oql_count' => [
                                'select' => 'SELECT User',
                            ],
                        ],
                    ],
                ],
                [],
            ],
        ];
    }

    /**
     * @group cbd-monitoring-ci
     * test separated from provider to fix f...ing error: Test was run in child process and ended unexpectedly
     **/
    public function testReadMetrics_OqlGroupByWithDynamicLabels()
    {
        $aUseCase = [
            [
                'itop_user_count' => [
                    'description' => 'Nb of URP_UserProfile par type',
                    'oql_groupby' => [
                        'select' => 'SELECT URP_UserProfile JOIN URP_Profiles ON URP_UserProfile.profileid =URP_Profiles.id WHERE URP_Profiles.id=1',
                        'groupby' => ['profile' => 'URP_UserProfile.profile'],
                    ],
                ],
            ],
            [['itop_user_count', 'Nb of URP_UserProfile par type', '1', ['profile' => 'Administrator']]],
        ];

        $this->testReadMetrics($aUseCase[0], $aUseCase[1]);
    }

    public function testConfSubArray()
    {
        $confFile = \utils::GetConfigFilePath();
        $sContent = (is_null($confFile)) ? '' : file_get_contents($confFile);
        if (false === strpos($sContent, 'authent-ldap')) {
            $this->markTestSkipped();
        } else {
            //['conf authent-ldap sub array']
            $useCase = [
                ['itop_authent-ldap' => [
                        'description' => 'ldap option 17',
                        'conf' => ['MyModuleSettings', 'authent-ldap', 'options', '17'],
                    ],
                ],
                [['itop_authent-ldap', 'ldap option 17', '3']],
            ];

            $this->testReadMetrics($useCase[0], $useCase[1]);
        }
    }

    /**
     * @dataProvider ReadMetricConfProvider
     */
    public function testReadMetricConf(string $sCollection, array $aConf, $aExpectedResult, ?string $sExpectedException)
    {
        if (null !== $sExpectedException) {
            $this->expectExceptionMessageRegExp($sExpectedException);
        }

        $oConfigMock = $this->createMock(\Config::class);
        $oConfigMock->expects($this->any())
            ->method('GetModuleSetting')
            ->willReturn($aConf);

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
                'sExpectedException' => null,
            ],
            'missing collection' => [
                'sCollection' => 'foo',
                'aConf' => ['bar' => []],
                'aExpectedResult' => null,
                'sExpectedException' => '/Collection "foo" not found/',
            ],
        ];
    }

    /**
     * @dataProvider RemoveDuplicatesProvider
     */
    public function testRemoveDuplicates(array $aDuplicateMetricFields, array $aExpectedMetricFields)
    {
        $aMetrics = $this->BuildMetricArray($aDuplicateMetricFields);
        $aExpectedMetrics = $this->BuildMetricArray($aExpectedMetricFields);

        $this->assertEquals($aExpectedMetrics,
            $this->monitoringController->RemoveDuplicates($aMetrics));
    }

    public function BuildMetricArray(array $aDuplicateMetricFields): array
    {
        $aMetrics = [];
        if (0 === sizeof($aDuplicateMetricFields)) {
            return $aMetrics;
        }

        foreach ($aDuplicateMetricFields as $aFields) {
            $aMetrics[] = new MonitoringMetric(
                $aFields['name'],
                '',
                $aFields['value'],
                $aFields['labels']
            );
        }

        return $aMetrics;
    }

    public function RemoveDuplicatesProvider()
    {
        return [
            'empty' => [
                'aDuplicateMetricFields' => [], 'aExpectedMetricFields' => [],
            ],
            'one metric' => [
                'aDuplicateMetricFields' => [
                    '0' => [
                        'name' => 'toto',
                        'labels' => [
                            'ga' => 'bu',
                            'zo' => 'meu',
                        ],
                        'value' => '1',
                    ],
                ],
                'aExpectedMetricFields' => [
                    '0' => [
                        'name' => 'toto',
                        'labels' => [
                            'ga' => 'bu',
                            'zo' => 'meu',
                        ],
                        'value' => '1',
                    ],
                ],
            ],
            'one metric with no labels' => [
                'aDuplicateMetricFields' => [
                    '0' => [
                        'name' => 'toto',
                        'labels' => [],
                        'value' => '1',
                    ],
                ],
                'aExpectedMetricFields' => [
                    '0' => [
                        'name' => 'toto',
                        'labels' => [],
                        'value' => '1',
                    ],
                ],
            ],
            'no removal : one additional label' => [
                'aDuplicateMetricFields' => [
                    '0' => [
                        'name' => 'toto',
                        'labels' => [
                            'ga' => 'bu',
                            'zo' => 'meu',
                        ],
                        'value' => '1',
                    ],
                    '1' => [
                        'name' => 'toto',
                        'labels' => [
                            'ga' => 'bu',
                            'zo' => 'meu',
                            'zo2' => 'meu2',
                        ],
                        'value' => '1',
                    ],
                ],
                'aExpectedMetricFields' => [
                    '0' => [
                        'name' => 'toto',
                        'labels' => [
                            'ga' => 'bu',
                            'zo' => 'meu',
                        ],
                        'value' => '1',
                    ],
                    '1' => [
                        'name' => 'toto',
                        'labels' => [
                            'ga' => 'bu',
                            'zo' => 'meu',
                            'zo2' => 'meu2',
                        ],
                        'value' => '1',
                    ],
                ],
            ],
            'no removal : distinct names' => [
                'aDuplicateMetricFields' => [
                    '0' => [
                        'name' => 'toto',
                        'labels' => [
                            'ga' => 'bu',
                            'zo' => 'meu',
                        ],
                        'value' => '1',
                    ],
                    '1' => [
                        'name' => 'toto2',
                        'labels' => [
                            'ga' => 'bu',
                            'zo' => 'meu',
                        ],
                        'value' => '1',
                    ],
                ],
                'aExpectedMetricFields' => [
                    '0' => [
                        'name' => 'toto',
                        'labels' => [
                            'ga' => 'bu',
                            'zo' => 'meu',
                        ],
                        'value' => '1',
                    ],
                    '1' => [
                        'name' => 'toto2',
                        'labels' => [
                            'ga' => 'bu',
                            'zo' => 'meu',
                        ],
                        'value' => '1',
                    ],
                ],
            ],
            'removal' => [
                'aDuplicateMetricFields' => [
                    '0' => [
                        'name' => 'toto',
                        'labels' => [
                            'ga' => 'bu',
                            'zo' => 'meu',
                        ],
                        'value' => '1',
                    ],
                    '1' => [
                        'name' => 'toto',
                        'labels' => [
                            'ga' => 'bu',
                            'zo' => 'meu',
                        ],
                        'value' => '2',
                    ],
                ],
                'aExpectedMetricFields' => [
                    '0' => [
                        'name' => 'toto',
                        'labels' => [
                            'ga' => 'bu',
                            'zo' => 'meu',
                        ],
                        'value' => '1',
                    ],
                ],
            ],
        ];
    }
}
