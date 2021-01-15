<?php
/**
 *  @copyright   Copyright (C) 2010-2019 Combodo SARL
 *  @license     http://opensource.org/licenses/AGPL-3.0
 */

require_once(APPROOT.'application/startup.inc.php');

use Combodo\iTop\Integrity\Monitoring\Controller\CombodoMonitoringController;

$oCombodoMonitoringController = new CombodoMonitoringController(MODULESROOT.'combodo-monitoring/src/view', 'combodo-monitoring');
$oCombodoMonitoringController->AllowOnlyAdmin();
$oCombodoMonitoringController->SetDefaultOperation('ExposePrometheusMetrics');
$oCombodoMonitoringController->setAccessTokenConfigParamId('access_token');
$oCombodoMonitoringController->setAccessAuthorizedNetworkConfigParamId('authorized_network');
$oCombodoMonitoringController->HandleOperation();
