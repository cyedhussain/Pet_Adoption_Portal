<?php
// Pets in one category, with the "Add pet" form locked to that category. The list itself lives in pets.php.
require_once __DIR__ . '/dbconnect.php';
$adminId = require_admin();

$lockedCategory = db_one("SELECT * FROM `categories` WHERE `category_id` = ?", [query_int('cindex')]);
if (!$lockedCategory) {
    flash('That category no longer exists.', 'warning');
    redirect('admin/categories.php');
}
require __DIR__ . '/pets.php';
