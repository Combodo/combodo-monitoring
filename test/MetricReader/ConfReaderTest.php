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

use Combodo\iTop\Monitoring\MetricReader\ConfReader;
use Combodo\iTop\Test\UnitTest\ItopTestCase;
use Config;
use ReflectionObject;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 * @backupGlobals disabled
 */
class ConfReaderTest extends ItopTestCase {
	public function setUp() {
		//@include_once '/home/nono/PhpstormProjects/iTop/approot.inc.php';
		parent::setUp();

		@require_once(APPROOT.'env-production/combodo-monitoring/vendor/autoload.php');
	}

	/**
	 * @dataProvider GetMetricsMySettingsProvider
	 */
	public function testGetGetValueMySettings(array $aMetric, $aMySetting, $aExpectedResult, ?string $sExpectedException) {
		if (is_array($aMySetting)) {
			$reflector = new ReflectionObject(\utils::GetConfig());
			$property = $reflector->getProperty('m_aSettings');
			$property->setAccessible(true);
			$m_aSettings = $property->getValue(\utils::GetConfig());

			foreach ($aMySetting as $key => $value) {
				$m_aSettings[$key] = [
					'type' => is_array($value) ? 'array' : 'string',
					'value' => $value,
					'source_of_value' => 'unknown',
				];
			}
			$property->setValue(\utils::GetConfig(), $m_aSettings);
		}

		if (null != $sExpectedException) {
			$this->expectExceptionMessageRegExp($sExpectedException);
		}

		$oOqlConfReader = new ConfReader('foo', $aMetric);

		$reflector = new ReflectionObject($oOqlConfReader);
		$method = $reflector->getMethod('GetValue');
		$method->setAccessible(true);
		$aResult = $method->invoke($oOqlConfReader, \utils::GetConfig());

		$this->assertEquals($aExpectedResult, $aResult, print_r($aResult));
	}

	public function GetMetricsMySettingsProvider() {
		return [
			'conf must be an array' => [
				'aMetric' => ['conf' => 'Not an array which is forbidden'],
				'aMySetting' => null,
				'aExpectedResult' => null,
				'sExpectedException' => '/Metric foo is not configured with a proper array/',
			],
			'MySettings nominal' => [
				'aMetric' => ['conf' => ['MySettings', 'foo']],
				'aMySetting' => ['foo' => 'bar'],
				'aExpectedResult' => 'bar',
				'sExpectedException' => null,
			],
			'MySettings with depth 1' => [
				'aMetric' => ['conf' => ['MySettings', 'foo', 'bar']],
				'aMySetting' => ['foo' => ['bar' => 'baz']],
				'aExpectedResult' => 'baz',
				'sExpectedException' => null,
			],
			'MySettings with depth 3' => [
				'aMetric' => ['conf' => ['MySettings', 'foo', 'bar', 'baz', 3]],
				'aMySetting' => ['foo' => ['bar' => ['baz' => [3 => 42]]]],
				'aExpectedResult' => 42,
				'sExpectedException' => null,
			],
			'MySettings with invalid path' => [
				'aMetric' => ['conf' => ['MySettings', 'foo', 'bar', 'baz', 3]],
				'aMySetting' => ['foo' => ['bar' => ['baz' => ['no key "3"']]]],
				'aExpectedResult' => null,
				'sExpectedException' => '/Metric foo was not found in configuration found./',
			],
			'MySettings no matching conf' => [
				'aMetric' => ['conf' => ['MySettings', 'foo']],
				'aMySetting' => null,
				'aExpectedResult' => null,
				'sExpectedException' => '/Metric foo was not found in configuration found./',
			],
		];
	}

	public function GetMetricsMyModuleProvider() {
		return [
			'MyModuleSettings nominal' => [
				'aMetric' => ['conf' => ['MyModuleSettings', 'module-name', 'foo']],
				'aMyModuleSetting' => ['foo' => 'bar'],
				'aExpectedResult' => 'bar',
				'sExpectedException' => null,
			],
			'MyModuleSettings with depth' => [
				'aMetric' => ['conf' => ['MyModuleSettings', 'module-name', 'foo', 'bar']],
				'aMyModuleSetting' => ['foo'=> ['bar' => 'baz']],
				'aExpectedResult' => 'baz',
				'sExpectedException' => null,
			],
			'MyModuleSettings no matching conf' => [
				'aMetric' => ['conf' => ['MyModuleSettings', 'module-name', 'foo']],
				'aMyModuleSetting' => null,
				'aExpectedResult' => null,
				'sExpectedException' => '/Metric foo was not found in configuration found./',
			],
		];
	}

	/**
	 * @dataProvider GetMetricsMyModuleProvider
	 */
	public function testGetGetValueMyModuleSettings(array $aMetric, $aMyModuleSetting, $aExpectedResult, ?string $sExpectedException) {
		if (is_array($aMyModuleSetting)) {
			foreach ($aMyModuleSetting as $key => $value) {
				\utils::GetConfig()->SetModuleSetting('module-name', $key, $value);
			}
		}

		if (null != $sExpectedException) {
			$this->expectExceptionMessageRegExp($sExpectedException);
		}

		$oOqlConfReader = new ConfReader('foo', $aMetric);

		$reflector = new ReflectionObject($oOqlConfReader);
		$method = $reflector->getMethod('GetValue');
		$method->setAccessible(true);
		$aResult = $method->invoke($oOqlConfReader, \utils::GetConfig());

		$this->assertEquals($aExpectedResult, $aResult);
	}

}
