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
        parent::setUp();

        $this->RequireOnceItopFile('core/config.class.inc.php');
        $this->RequireOnceItopFile('env-production/combodo-monitoring/vendor/autoload.php');

        if (!defined('MODULESROOT')) {
            define('MODULESROOT', APPROOT.'env-production/');
        }

        $this->monitoringController = new Controller(MODULESROOT.'combodo-monitoring/src/view');
    }

    /**
     * @dataProvider ReadMetricsProvider
     *
     * @group cbd-monitoring-ci
     * test separated from provider to fix f...ing error: Test was run in child process and ended unexpectedly
     *
     * @param array $aMetricConf
     * @param array $aExpectedMetrics
     */
    public function testReadSingleMetrics(array $aMetricConf, int $iExpectedMetricCount, ?string $sExpectedName=null,
	    ?string $sExpectedDesc=null, ?int $iExpectedValue=666, ?array $aExpectedLabels=null)
    {
        $aMetrics = $this->monitoringController->ReadMetrics($aMetricConf);

	    $this->assertEquals($iExpectedMetricCount, count($aMetrics), var_export($aMetrics, true));
	    if ($iExpectedMetricCount != 0){
		    $oExpectedMetric = new MonitoringMetric($sExpectedName, $sExpectedDesc, $iExpectedValue, $aExpectedLabels);

            $this->assertEquals(1, count($aMetrics), var_export($aMetrics, true));
            $this->assertEquals(
                $oExpectedMetric,
                $aMetrics[0]
            );
        }
    }

    public function ReadMetricsProvider()
    {
        return [
           'metric from conf path in MySettings->access_mode' => [
	           'aMetricConf' => [
				   'access_mode' => [
                        'description' => 'access mode',
                        'conf' => ['MySettings', 'access_mode'],
                        'static_labels' => ['labelname1' => 'labelvalue1'],
                    ],
                ],
	           'iExpectedMetricCount' => 1,
	           'sExpectedName' => 'access_mode',
	           'sExpectedDesc' => 'access mode',
	           'iExpectedValue' => 3,
	           'aExpectedLabels' => ['labelname1' => 'labelvalue1'],
            ],
            'metric from conf path unfound MySettings->access_message2' => [
	            'aMetricConf' => [
					'access_message1' => [
                        'description' => 'access message',
                        'conf' => ['MySettings', 'access_message2'],
                    ],
                ],
	            'iExpectedMetricCount' => 0,
            ],
            'conf MyModuleSettings MyModuleSetting->itop-backup->retention_count without static labels' => [
	            'aMetricConf' => ['retention_count' => [
                        'description' => 'retention count',
                        'conf' => ['MyModuleSettings', 'itop-backup', 'retention_count'],
                    ],
                ],
	            'iExpectedMetricCount' => 1,
	            'sExpectedName' => 'retention_count',
	            'sExpectedDesc' => 'retention count',
	            'iExpectedValue' => 5,
	            'aExpectedLabels' => [],
            ],
            'conf MyModuleSettings not found MyModuleSettings->itop-backup2->retention_count' => [
	            'aMetricConf' => ['access_message2' => [
                        'description' => 'retention count',
                        'conf' => ['MyModuleSettings', 'itop-backup2', 'retention_count'],
                    ],
                ],
	            'iExpectedMetricCount' => 0,
            ],
            'conf MyModuleSettings not found MyModuleSettings->itop-backup->retention_count2' => [
	            'aMetricConf' => ['access_message3' => [
                        'description' => 'retention count',
                        'conf' => ['MyModuleSettings', 'itop-backup', 'retention_count2'],
                    ],
                ],
	            'iExpectedMetricCount' => 0,
            ],
            'oql_count with 2 labels, 1 not trimmed' => [
	            'aMetricConf' => ['itop_user_count' => [
                        'description' => 'Nb of users',
                        'oql_count' => [
                            'select' => 'SELECT User WHERE id=1',
                        ],
                        'static_labels' => ['labelname2' => 'labelvalue2', 'name with space at the end     ' => 'foo'],
                    ],
                ],
	            'iExpectedMetricCount' => 1,
	            'sExpectedName' => 'itop_user_count',
	            'sExpectedDesc' => 'Nb of users',
	            'iExpectedValue' => 1,
	            'aExpectedLabels' => ['labelname2' => 'labelvalue2', 'name with space at the end     ' => 'foo']
            ],
            'no_description_in_oql_metric' => [
	            'aMetricConf' => ['itop_user_nodescription_count' => [
                        'oql_count' => [
                            'select' => 'SELECT User',
                        ],
                    ],
                ],
	            'iExpectedMetricCount' => 0,
            ],
            'conf_without_description' => [
	            'aMetricConf' => ['itop_user_quota_count' => [
                        'conf' => 'ee.rrr',
                    ],
                ],
	            'iExpectedMetricCount' => 0,
            ],
            'no_metric returned' => [
	            'aMetricConf' =>
		            ['xxx' => ['itop_user_count' => [
                            'oql_count' => [
                                'select' => 'SELECT User',
                            ],
                        ],
                    ],
                ],
	            'iExpectedMetricCount' => 0,
            ],
        ];
    }

    /**
     * @group cbd-monitoring-ci
     * test separated from provider to fix f...ing error: Test was run in child process and ended unexpectedly
     **/
    public function testReadMetrics_OqlGroupByWithDynamicLabels()
    {
        $aConf = [
                'itop_user_count' => [
                    'description' => 'Nb of URP_UserProfile par type',
                    'oql_groupby' => [
                        'select' => 'SELECT URP_UserProfile JOIN URP_Profiles ON URP_UserProfile.profileid =URP_Profiles.id WHERE URP_Profiles.id=1',
                        'groupby' => ['profile' => 'URP_UserProfile.profile'],
                    ],
                ]
            ];

        $this->testReadSingleMetrics($aConf, 1, 'itop_user_count',
	        'Nb of URP_UserProfile par type', 1, ['profile' => 'Administrator']);
    }

    public function testConfSubArray()
    {
        $confFile = \utils::GetConfigFilePath();
        $sContent = (is_null($confFile)) ? '' : file_get_contents($confFile);
        if (false === strpos($sContent, 'authent-ldap')) {
            $this->markTestSkipped();
        } else {
	        $aConf = [
				'itop_authent-ldap' => [
			        'description' => 'ldap option 17',
			        'conf' => ['MyModuleSettings', 'authent-ldap', 'options', '17'],
	            ]
	        ];

			$this->testReadSingleMetrics($aConf, 1, 'itop_authent-ldap',
		        'ldap option 17', 3, []);
        }
    }

    /**
     * @dataProvider ReadMetricConfProvider
     */
    public function testReadMetricConf(string $sCollection, array $aConf, $aExpectedResult, ?string $sExpectedException)
    {
        if (null !== $sExpectedException) {
	        if (method_exists($this, 'expectExceptionMessageRegExp')){
		        $this->expectExceptionMessageRegExp($sExpectedException);
	        } else {
		        $this->expectExceptionMessageMatches($sExpectedException);
	        }
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

	public function testCheckIp(){
		$this->assertEquals(true, $this->monitoringController->CheckIpFunction("192.168.48.3", ['192.168.0.1/16']));
		$this->assertEquals(false, $this->monitoringController->CheckIpFunction("192.168.48.3", ['192.168.0.1/24']));
	}
}
