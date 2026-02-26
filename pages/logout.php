<?php
declare(strict_types=1);

require_login();
logout_user();
set_flash('success', 'You have been logged out.');
redirect(url('index.php'));
