<?php
/**
 * BuildConnect Logout Script
 */

require_once __DIR__ . '/includes/auth.php';

logout_user();
set_flash_message('You have been logged out successfully.', 'info');
redirect('login.php');
