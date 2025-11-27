<?php

namespace Combodo\iTop\Monitoring\CustomReader;

use Combodo\iTop\DBTools\Service\DBToolsUtils;

class DbToolsService
{
	public const DB_ANALYZE_CHECK_FILE = 'data/dbAnalyzeCheck.log';

	private $oProperty;

	public function GetDBTablesInfo(int $iDbAnalyzeFrequencyInMinutes, $bMockGetDBTablesInfoCall = false)
	{
		if ($this->IsAnalyzeImplementedInITop()) {
			if (! $this->IsAnalyzeRequired($iDbAnalyzeFrequencyInMinutes)) {
				$this->DisableAnalyze();
			}
		}

		if ($bMockGetDBTablesInfoCall) {
			\IssueLog::Warning("testing environment: not calling DBToolsUtils::GetDBTablesInfo");
		}

		return DBToolsUtils::GetDBTablesInfo();
	}

	public function IsAnalyzeImplementedInITop(): bool
	{
		try {
			$oReflectionClass = new \ReflectionClass(DBToolsUtils::class);
			$this->oProperty = $oReflectionClass->getProperty('bAnalyzed');
			$this->oProperty->setAccessible(true);
			return true;
		} catch (\Exception $e) {
			\IssueLog::Debug("Analyze may not be implemented in current iTop version", null, [ 'message' => $e->getMessage()]);
		}

		return false;
	}

	public function DisableAnalyze(): void
	{
		$this->oProperty->setValue(true);
	}

	public function GetDbAnalyzeFrequencyFile()
	{
		return APPROOT.self::DB_ANALYZE_CHECK_FILE;
	}

	public function IsAnalyzeRequired(int $iDbAnalyzeFrequencyInMinutes): bool
	{
		$sFile = $this->GetDbAnalyzeFrequencyFile();

		$iNow = strtotime('now');
		if (is_file($sFile)) {
			if (false == $iNow) {
				//issue with timestamp: no analysis until next retry
				return false;
			}

			$sPreviousAnalyzeTimestamp = (int) file_get_contents($sFile);
			if (false !== $sPreviousAnalyzeTimestamp) {
				$fLastBackupAgeInMinutes = ($iNow - $sPreviousAnalyzeTimestamp) / 60;
				if ($iDbAnalyzeFrequencyInMinutes > $fLastBackupAgeInMinutes) {
					return false;
				}
			}

			//schedule next analyze
			@unlink($sFile);
		}

		file_put_contents($sFile, $iNow);
		return true;
	}
}
