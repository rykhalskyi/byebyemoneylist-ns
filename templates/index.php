<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript(OCA\ByeByeMoneyList\AppInfo\Application::APP_ID, OCA\ByeByeMoneyList\AppInfo\Application::APP_ID . '-main');
Util::addStyle(OCA\ByeByeMoneyList\AppInfo\Application::APP_ID, OCA\ByeByeMoneyList\AppInfo\Application::APP_ID . '-main');

?>

<div id="byebyemoneylist"></div>
