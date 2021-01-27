<?php
/**
 *  @copyright   Copyright (C) 2010-2019 Combodo SARL
 *  @license     http://opensource.org/licenses/AGPL-3.0
 */

require_once(APPROOT.'application/startup.inc.php');

use Combodo\iTop\Monitoring\Controller\Controller;

$oController = new Controller(MODULESROOT.'combodo-monitoring/src/view', 'combodo-monitoring');
$oController->AllowOnlyAdmin();
$oController->SetDefaultOperation('ExposePrometheusMetrics');
$oController->setAccessTokenConfigParamId('access_token');
$oController->setAccessAuthorizedNetworkConfigParamId('authorized_network');
$oController->HandleOperation();
