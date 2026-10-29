<?php
require_once __DIR__ . '/dbconnect.php';
$adopterId = require_adopter();

if (!is_post()) {
    redirect('adopter/requested.php');
}

$email = db_value("SELECT `email` FROM `adopter` WHERE `adopter_id` = ?", [$adopterId]);
$request = db_one(
    "SELECT r.*, p.`name` AS pet_name FROM `requests` r LEFT JOIN `pets` p ON p.`pet_id` = r.`pet_id`
     WHERE r.`request_id` = ? AND r.`status` = 'pending'
       AND (r.`adopter_id` = ? OR (r.`adopter_id` IS NULL AND r.`email` = ?))",
    [(int) post('request_id'), $adopterId, $email]);

if (!$request) {
    flash('That application can no longer be cancelled.', 'warning');
    redirect('adopter/requested.php');
}

db_transaction(function () use ($request) {
    db_run("UPDATE `requests` SET `status` = 'cancelled', `updated_at` = NOW() WHERE `request_id` = ?", [(int) $request['request_id']]);
    db_run("UPDATE `pets` SET `requested` = 0 WHERE `pet_id` = ?", [(int) $request['pet_id']]);
});

flash('Your application for ' . ($request['pet_name'] ?? 'the pet') . ' was cancelled.', 'info');
redirect('adopter/requested.php');
