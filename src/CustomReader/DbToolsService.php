<?php

namespace Combodo\iTop\Monitoring\CustomReader;

use Combodo\iTop\DBTools\Service\DBToolsUtils;

class DbToolsService {
	const DB_ANALYZE_CHECK_FILE = 'data/dbAnalyzeCheck.log';

	private $oProperty;

    public function GetDBTablesInfo(int $iDbAnalyzeFrequencyInMinutes) {
		if ($this->IsAnalyzeImplementedInITop()){
			if (! $this->IsAnalyzeRequired($iDbAnalyzeFrequencyInMinutes)){
				$this->DisableAnalyze();
			}
		}

        return DBToolsUtils::GetDBTablesInfo();
    }

	public function IsAnalyzeImplementedInITop() : bool {
		try{
			$oReflectionClass = new \ReflectionClass(DBToolsUtils::class);
			$this->oProperty = $oReflectionClass->getProperty('bAnalyzed');
			$this->oProperty->setAccessible(true);
			return true;
		} catch(\Exception $e){
			\IssueLog::Debug("Analyze may not be implemented in current iTop version", null, [ 'message' => $e->getMessage()] );
		}

		return false;
	}

	public function DisableAnalyze() : void {
		$this->oProperty->setValue(true);
	}

	public function GetDbAnalyzeFrequencyFile() {
		return APPROOT.self::DB_ANALYZE_CHECK_FILE;
	}

	public function IsAnalyzeRequired(int $iDbAnalyzeFrequencyInMinutes) : bool {
		$sFile = $this->GetDbAnalyzeFrequencyFile();

		if (is_file($sFile)){
			$sPreviousAnalyzeTimestamp = file_get_contents($sFile);
		} else {
			$sPreviousAnalyzeTimestamp = false;
		}
		$iNow = strtotime('now');
		if (false !== $sPreviousAnalyzeTimestamp){
			$fLastBackupAgeInMinutes = ($iNow - $sPreviousAnalyzeTimestamp) / 60;
			if ($iDbAnalyzeFrequencyInMinutes > $fLastBackupAgeInMinutes){
				return false;
			}
		}

		//schedule next analyze
		@unlink($sFile);
		file_put_contents($sFile, $iNow);
		return true;
	}
}
