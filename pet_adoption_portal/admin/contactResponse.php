<?php
// POST-only handler for replying to and deleting contact messages. Always returns to contact.php.
require_once __DIR__ . '/dbconnect.php';
$adminId = require_admin();

$tab = in_array(post('tab'), ['unanswered', 'answered', 'all'], true) ? post('tab') : 'unanswered';
if (!is_post()) {
    redirect('admin/contact.php');
}

$message = db_one("SELECT * FROM `contact` WHERE `contact_id` = ?", [(int) post('contact_id')]);
if (!$message) {
    flash('That message no longer exists.', 'warning');
    redirect('admin/contact.php?tab=' . $tab);
}

if (post('action') === 'delete') {
    db_run("DELETE FROM `contact` WHERE `contact_id` = ?", [(int) $message['contact_id']]);
    flash('Message deleted.', 'info');
    redirect('admin/contact.php?tab=' . $tab);
}

if (post('action') === 'reply') {
    $response = mb_substr(post('response'), 0, 500);
    if ($response === '') {
        flash('Write a reply before sending.', 'danger');
        redirect('admin/contact.php?tab=' . $tab);
    }
    db_run("UPDATE `contact` SET `answer` = ? WHERE `contact_id` = ?", [$response, (int) $message['contact_id']]);
    $sent = send_mail($message['email'], 'Re: your message to ' . APP_NAME,
        mail_template('Hi ' . $message['name'] . ', we replied to your message',
            '<p style="padding:12px 16px;background:#fbf6ee;border-radius:10px;color:#7c6b5d"><em>' . nl2br(e($message['message'])) . '</em></p>'
            . '<p>' . nl2br(e($response)) . '</p>'));
    flash($sent ? 'Reply sent to ' . $message['email'] . '.' : 'Reply saved. (Email is not configured, so it was not emailed.)');
    redirect('admin/contact.php?tab=' . $tab);
}

redirect('admin/contact.php?tab=' . $tab);
