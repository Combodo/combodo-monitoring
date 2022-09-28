<?php
/**
 *  @copyright   Copyright (C) 2010-2019 Combodo SARL
 *  @license     http://opensource.org/licenses/AGPL-3.0
 */

require_once(APPROOT.'application/startup.inc.php');

use Combodo\iTop\Monitoring\Controller\Controller;

$oCombodoMonitoringController = new Controller(MODULESROOT.'combodo-monitoring/src/view', 'combodo-monitoring');
$oCombodoMonitoringController->AllowOnlyAdmin();
$oCombodoMonitoringController->SetDefaultOperation('Status');
$oCombodoMonitoringController->HandleOperation();
