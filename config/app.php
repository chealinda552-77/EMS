<?php
declare(strict_types=1);

define('APP_NAME', 'Employee Fingerprint Management');
define('APP_TIMEZONE', getenv('APP_TIMEZONE') ?: 'UTC');

date_default_timezone_set(APP_TIMEZONE);
