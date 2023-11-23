<?php

namespace Combodo\iTop\Monitoring\Test\CustomReader;

use Combodo\iTop\Monitoring\CustomReader\DbToolsService;
use Combodo\iTop\Monitoring\CustomReader\ItopDbBigTableReader;
use Combodo\iTop\Monitoring\Model\MonitoringMetric;
use Combodo\iTop\Test\UnitTest\ItopDataTestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @backupGlobals disabled
 */
class ItopDbBigTableReaderTest extends ItopDataTestCase
{
    protected function setUp(): void
    {
        $this->RequireOnceItopFile('approot.inc.php');
        parent::setUp();

        $this->RequireOnceItopFile('/env-production/combodo-monitoring/vendor/autoload.php');
        $this->RequireOnceItopFile('/core/config.class.inc.php');
    }

    protected function tearDown(): void
    {
    }

    public function testThresholds()
    {
        $oItopDbBigTableReader = new ItopDbBigTableReader('', []);
        $this->assertEquals(100000, $oItopDbBigTableReader->GetObjectCountThreshold('table_name'));
        $this->assertEquals(250, $oItopDbBigTableReader->GetDiskSpaceThreshold('table_name'));
    }

    public function testThresholdsWithOverallConf()
    {
        $oItopDbBigTableReader = new ItopDbBigTableReader('',
            [
                'default_objectcount_threshold' => 500000,
                'default_diskspace_threshold' => 500,
            ],
        );
        $this->assertEquals(500000, $oItopDbBigTableReader->GetObjectCountThreshold('table_name'));
        $this->assertEquals(500, $oItopDbBigTableReader->GetDiskSpaceThreshold('table_name'));
    }

    public function testThresholdsWithOverallConfAndTableConfForObjects()
    {
        $oItopDbBigTableReader = new ItopDbBigTableReader('',
            [
                'default_objectcount_threshold' => 500000,
                'default_diskspace_threshold' => 500,
                'table_name' => [
                    'objectcount_threshold' => 700000,
                ]
            ],
        );
        $this->assertEquals(500000, $oItopDbBigTableReader->GetObjectCountThreshold('any_other_table_name'));
        $this->assertEquals(500, $oItopDbBigTableReader->GetDiskSpaceThreshold('any_other_table_name'));
        $this->assertEquals(700000, $oItopDbBigTableReader->GetObjectCountThreshold('table_name'));
        $this->assertEquals(500, $oItopDbBigTableReader->GetDiskSpaceThreshold('table_name'));
    }

    public function testThresholdsWithOverallConfAndTableConfForDiskSpace()
    {
        $oItopDbBigTableReader = new ItopDbBigTableReader('',
            [
                'default_objectcount_threshold' => 500000,
                'default_diskspace_threshold' => 500,
                'table_name' => [
                    'diskspace_threshold' => 700,
                ]
            ],
        );
        $this->assertEquals(500000, $oItopDbBigTableReader->GetObjectCountThreshold('any_other_table_name'));
        $this->assertEquals(500, $oItopDbBigTableReader->GetDiskSpaceThreshold('any_other_table_name'));
        $this->assertEquals(500000, $oItopDbBigTableReader->GetObjectCountThreshold('table_name'));
        $this->assertEquals(700, $oItopDbBigTableReader->GetDiskSpaceThreshold('table_name'));
    }

    public function GetMetricsProvider()
    {
        return [
            'object_threshold_reached' => [
                'aOutput' => [
                    [
                        'table_name' => 'object_threshold_reached',
                        'table_rows' => '500000',
                        'data_length_mb' => '0.01562500',
                        'index_length_mb' => '0.00000000',
                        'total_length_mb' => '0.01562500',
                    ]
                ],
                'aExpectedMetrics' => [
                    [
                        'itop_big_table_objectcount',
                        'itop tables that reach (configurable) 100000 objects.',
                        '500000',
                        ['table' => 'object_threshold_reached', 'toto' => 'titi']
                    ],
                ],
            ],
            'diskspace_threshold_reached' => [
                'aOutput' => [
                    [
                        'table_name' => 'diskspace_threshold_reached',
                        'table_rows' => '50000',
                        'data_length_mb' => '0.01562500',
                        'index_length_mb' => '0.00000000',
                        'total_length_mb' => '300.01562500',
                    ]
                ],
                'aExpectedMetrics' => [
                    [
                        'itop_big_table_diskspace_in_megabytes',
                        'itop tables that reach (configurable) 250 mb in disk space.',
                        '300',
                        ['table' => 'diskspace_threshold_reached', 'toto' => 'titi']
                    ],
                ],
            ],
            'both_threshold_reached' => [
                'aOutput' => [
                    [
                        'table_name' => 'both_threshold_reached',
                        'table_rows' => '500000',
                        'data_length_mb' => '0.01562500',
                        'index_length_mb' => '0.00000000',
                        'total_length_mb' => '300.01562500',
                    ]
                ],
                'aExpectedMetrics' => [
                    [
                        'itop_big_table_objectcount',
                        'itop tables that reach (configurable) 100000 objects.',
                        '500000',
                        ['table' => 'both_threshold_reached', 'toto' => 'titi']
                    ],
                    [
                        'itop_big_table_diskspace_in_megabytes',
                        'itop tables that reach (configurable) 250 mb in disk space.',
                        '300',
                        ['table' => 'both_threshold_reached', 'toto' => 'titi']
                    ],
                ],
            ],
            'both_threshold_reached_inconsistentdata1' => [
                'aOutput' => [
                    [
                        'table_rows' => '500000',
                        'data_length_mb' => '0.01562500',
                        'index_length_mb' => '0.00000000',
                        'total_length_mb' => '300.01562500',
                    ]
                ],
                'aExpectedMetrics' => [
                ],
            ],
            'both_threshold_reached_inconsistentdata2' => [
                'aOutput' => [
                    [
                        'table_name' => 'both_threshold_reached',
                        'data_length_mb' => '0.01562500',
                        'index_length_mb' => '0.00000000',
                        'total_length_mb' => '300.01562500',
                    ]
                ],
                'aExpectedMetrics' => [
                ],
            ],
            'both_threshold_reached_inconsistentdata3' => [
                'aOutput' => [
                    [
                        'table_name' => 'both_threshold_reached',
                        'table_rows' => '500000',
                        'data_length_mb' => '0.01562500',
                        'index_length_mb' => '0.00000000',
                    ]
                ],
                'aExpectedMetrics' => [
                ],
	            'db_analyze_frequency_in_minutes' => 24 *60
            ],
        ];
    }

    /**
     * @dataProvider GetMetricsProvider
     */
    public function testGetMetrics($aOutput, $aExpectedMetrics, $iDbAnalyzeFrequencyConf = -1)
    {
        $aLabels = ['toto' => 'titi'];
        $oDbToolsService = $this->createMock(DbToolsService::class);
	    $aMetricConf = ['static_labels' => $aLabels];
		if ($iDbAnalyzeFrequencyConf !== -1){
			$aMetricConf['db_analyze_frequency_in_minutes'] = $iDbAnalyzeFrequencyConf;
		}
	    $oItopDbBigTableReader = new ItopDbBigTableReader('', $aMetricConf, $oDbToolsService);

        $oDbToolsService->expects(self::exactly(1))
            ->method('GetDBTablesInfo')
            ->with(($iDbAnalyzeFrequencyConf !== -1) ? $iDbAnalyzeFrequencyConf : 6*60)
            ->willReturn(
                $aOutput
            );
        $oMetrics = $oItopDbBigTableReader->GetMetrics();
        $this->assertEquals(sizeof($aExpectedMetrics), sizeof($oMetrics), var_export($oMetrics, true));

        $i=0;
        foreach ($aExpectedMetrics as $aCurrentMetricInfo) {
            /* MonitoringMetric $oMetric */
            $oMetric = $oMetrics[$i];

            $this->assertEquals($aCurrentMetricInfo[0], $oMetric->GetName(), $oMetric);
            $this->assertEquals($aCurrentMetricInfo[1], $oMetric->GetDescription());
            $this->assertEquals($aCurrentMetricInfo[2], $oMetric->GetValue());
            $this->assertEquals($aCurrentMetricInfo[3], $oMetric->GetLabels());
            $i++;
        }
    }
}
