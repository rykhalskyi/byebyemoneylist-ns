<?php

declare(strict_types=1);

use OCP\Util;

Util::addTranslations(OCA\ByeByeMoneyList\AppInfo\Application::APP_ID);
Util::addScript(OCA\ByeByeMoneyList\AppInfo\Application::APP_ID, OCA\ByeByeMoneyList\AppInfo\Application::APP_ID . '-main');
Util::addStyle(OCA\ByeByeMoneyList\AppInfo\Application::APP_ID, OCA\ByeByeMoneyList\AppInfo\Application::APP_ID . '-main');

?>

<div id="byebyemoneylist" data-version="<?php p($_['version']); ?>"></div>
