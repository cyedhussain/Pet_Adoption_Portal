<?php
require_once __DIR__ . '/dbconnect.php';
logout_user();
flash('You have been logged out.', 'info');
redirect('login.php');
