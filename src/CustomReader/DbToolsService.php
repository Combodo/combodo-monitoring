<?php

namespace Combodo\iTop\Monitoring\CustomReader;

use Combodo\iTop\DBTools\Service\DBToolsUtils;

class DbToolsService {
    public function GetDBTablesInfo() {
        return DBToolsUtils::GetDBTablesInfo();
    }
}
