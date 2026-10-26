<?php
/**
 * User Logout Script - SavorBite Online Food Ordering System
 */
require_once __DIR__ . '/includes/auth.php';

log_out_user();

header("Location: login.php?logged_out=1");
exit();
