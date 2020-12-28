<?php
/**
 *  @copyright   Copyright (C) 2010-2019 Combodo SARL
 *  @license     http://opensource.org/licenses/AGPL-3.0
 */

namespace Combodo\iTop\Integrity\Monitoring;

use Combodo\iTop\Integrity\Monitoring\Controller\CombodoMonitoringController;

require_once '../approot.inc.php';
require_once APPROOT.'application/application.inc.php';
require_once APPROOT.'application/webpage.class.inc.php';
#require_once(APPROOT.'/application/ajaxwebpage.class.inc.php');
require_once APPROOT.'application/startup.inc.php';
require_once APPROOT.'application/loginwebpage.class.inc.php';

$oAjaxOperationsController = new CombodoMonitoringController(MODULESROOT.'combodo-monitoring/src/view', 'combodo-monitoring');
$oAjaxOperationsController->DisableInDemoMode();
$oAjaxOperationsController->AllowOnlyAdmin();
// Allow parallel execution
session_write_close();

$oAjaxOperationsController->HandleOperation();
