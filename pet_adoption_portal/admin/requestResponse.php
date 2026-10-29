<?php
require_once __DIR__ . '/dbconnect.php';
$adminId = require_admin();

/** True while a pet or adopted row still points at this image in img/pets/. */
$petId = query_int('pindex');
$adoptId = query_int('aindex');
$mode = null;
$record = null;
$pet = null;
$location = null;

if ($adoptId) {
    $record = db_one("SELECT * FROM `adopted` WHERE `adopt_id` = ?", [$adoptId]);
    $mode = 'adopted';
} elseif ($petId) {
    $pet = find_pet($petId);
    $record = db_one("SELECT * FROM `requests` WHERE `pet_id` = ? AND `status` = 'pending' ORDER BY `request_time` DESC LIMIT 1", [$petId]);
    $mode = 'pending';
    if (!$record) {
        $record = db_one("SELECT * FROM `accepted` WHERE `pet_id` = ? ORDER BY `accept_time` DESC LIMIT 1", [$petId]);
        $mode = 'accepted';
    }
}
if (!$record || ($mode !== 'adopted' && !$pet)) {
    flash('That application no longer exists. It may already have been handled.', 'warning');
    redirect($adoptId ? 'admin/adopted.php' : 'admin/requests.php');
}
if ($mode !== 'adopted') {
    $location = db_one("SELECT * FROM `locations` WHERE `location_id` = ?", [(int) $record['location_id']]);
}

// ---------------------------------------------------------------- actions

if (is_post()) {
    $action = post('action');
    $reason = mb_substr(post('reason'), 0, 500);
    $petName = $mode === 'adopted' ? $record['pet_name'] : $pet['name'];
    $branchLine = $location ? $location['address'] . ' (' . $location['city'] . '), ' . $location['contact'] : '';

    if ($action === 'approve' && $mode === 'pending') {
        db_transaction(function () use ($record) {
            db_run(
                "INSERT INTO `accepted` (`name`, `email`, `contact`, `cnic`, `address`, `pet_id`, `location_id`, `adopter_id`, `request_time`, `accept_time`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [$record['name'], $record['email'], $record['contact'], $record['cnic'], $record['address'],
                 (int) $record['pet_id'], (int) $record['location_id'], $record['adopter_id'] === null ? null : (int) $record['adopter_id'], $record['request_time']]
            );
            db_run("DELETE FROM `requests` WHERE `request_id` = ?", [(int) $record['request_id']]);
        });
        send_mail($record['email'], 'Your adoption request for ' . $petName . ' is approved',
            mail_template('Good news, ' . $record['name'] . '!',
                '<p>Your request to adopt <strong>' . e($petName) . '</strong> has been approved.</p>'
                . '<p>Please pick ' . e($petName) . ' up within <strong>3 days</strong> from our branch at <strong>' . e($branchLine) . '</strong>. Bring your CNIC with you.</p>',
                'View my adoptions', absolute_url('adopter/requested.php')));
        flash('Request approved. ' . $record['name'] . ' has 3 days to pick up ' . $petName . '.');
        redirect('admin/accepted.php');
    }

    if ($action === 'reject' && $mode === 'pending') {
        if ($reason === '') {
            flash('Please give a reason so the applicant knows why.', 'danger');
            redirect('admin/requestResponse.php?pindex=' . (int) $petId);
        }
        db_transaction(function () use ($record, $reason) {
            db_run("UPDATE `requests` SET `status` = 'rejected', `reason` = ?, `updated_at` = NOW() WHERE `request_id` = ?",
                [$reason, (int) $record['request_id']]);
            db_run("UPDATE `pets` SET `requested` = 0 WHERE `pet_id` = ?", [(int) $record['pet_id']]);
        });
        send_mail($record['email'], 'An update on your request for ' . $petName,
            mail_template('About your adoption request',
                '<p>Thank you for wanting to give <strong>' . e($petName) . '</strong> a home. Unfortunately we could not approve your request this time.</p>'
                . '<p><strong>Reason:</strong> ' . nl2br(e($reason)) . '</p><p>You are welcome to apply for another pet or contact us with any questions.</p>',
                'Browse pets', absolute_url('adopter/pets.php')));
        flash('Request rejected. ' . $petName . ' is available again.', 'info');
        redirect('admin/requests.php');
    }

    if ($action === 'adopted' && $mode === 'accepted') {
        db_transaction(function () use ($record, $pet) {
            db_run(
                "INSERT INTO `adopted` (`name`, `email`, `contact`, `cnic`, `address`, `pet_category`, `pet_name`, `pet_intro1`, `pet_intro2`,
                    `image`, `location_city`, `location_address`, `location_email`, `location_contact`, `adopter_id`, `pet_id`,
                    `request_time`, `accept_time`, `adopt_time`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [$record['name'], $record['email'], $record['contact'], $record['cnic'], mb_substr($record['address'], 0, 100),
                 (string) $pet['category_name'], $pet['name'], $pet['intro1'], $pet['intro2'], $pet['image'],
                 (string) $pet['city'], mb_substr((string) $pet['location_address'], 0, 100), (string) $pet['location_email'], (string) $pet['location_contact'],
                 $record['adopter_id'] === null ? null : (int) $record['adopter_id'], (int) $pet['pet_id'],
                 $record['request_time'], $record['accept_time']]
            );
            db_run("DELETE FROM `accepted` WHERE `accept_id` = ?", [(int) $record['accept_id']]);
            db_run("DELETE FROM `favourites` WHERE `pet_id` = ?", [(int) $pet['pet_id']]);
            db_run("DELETE FROM `pets` WHERE `pet_id` = ?", [(int) $pet['pet_id']]);
        });
        send_mail($record['email'], 'Welcome home, ' . $petName . '!',
            mail_template('Congratulations on your adoption!',
                '<p>Thank you for adopting <strong>' . e($petName) . '</strong>. We hope you have many happy years together.</p>'
                . '<p>If you ever need advice or help, just reply to this email or contact the branch.</p>'));
        flash($petName . ' has been marked as adopted. 🎉');
        redirect('admin/adopted.php');
    }

    if ($action === 'cancel_approval' && $mode === 'accepted') {
        if ($reason === '') {
            flash('Please give a reason so the applicant knows why.', 'danger');
            redirect('admin/requestResponse.php?pindex=' . (int) $petId);
        }
        db_transaction(function () use ($record, $reason) {
            db_run("DELETE FROM `accepted` WHERE `accept_id` = ?", [(int) $record['accept_id']]);
            db_run(
                "INSERT INTO `requests` (`name`, `email`, `contact`, `cnic`, `address`, `pet_id`, `location_id`, `adopter_id`,
                    `status`, `reason`, `request_time`, `updated_at`)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'rejected', ?, ?, NOW())",
                [$record['name'], $record['email'], $record['contact'], $record['cnic'], mb_substr($record['address'], 0, 200),
                 (int) $record['pet_id'], (int) $record['location_id'], $record['adopter_id'] === null ? null : (int) $record['adopter_id'],
                 $reason, $record['request_time']]
            );
            db_run("UPDATE `pets` SET `requested` = 0 WHERE `pet_id` = ?", [(int) $record['pet_id']]);
        });
        send_mail($record['email'], 'Your approval for ' . $petName . ' was cancelled',
            mail_template('About your adoption of ' . $petName,
                '<p>Your approved request to adopt <strong>' . e($petName) . '</strong> (approved on ' . e(format_date($record['accept_time'])) . ') has been cancelled.</p>'
                . '<p><strong>Reason:</strong> ' . nl2br(e($reason)) . '</p><p>Please contact us if you have any questions.</p>'));
        flash('Approval cancelled. ' . $petName . ' is available again.', 'info');
        redirect('admin/accepted.php');
    }

    if ($action === 'delete_adopted' && $mode === 'adopted') {
        db_run("DELETE FROM `adopted` WHERE `adopt_id` = ?", [(int) $record['adopt_id']]);
        if (!pet_image_in_use($record['image'])) {
            delete_image('pets', $record['image']);
        }
        flash('Adoption record deleted.', 'info');
        redirect('admin/adopted.php');
    }

    flash('That action is not available for this application any more.', 'warning');
    redirect_back('admin/requests.php');
}

// ---------------------------------------------------------------- view data

if ($mode === 'adopted') {
    $view = [
        'name' => $record['pet_name'], 'image' => $record['image'], 'category' => $record['pet_category'],
        'intro1' => $record['pet_intro1'], 'intro2' => $record['pet_intro2'],
        'city' => $record['location_city'], 'address' => $record['location_address'],
        'branch_email' => $record['location_email'], 'branch_contact' => $record['location_contact'],
    ];
} else {
    $view = [
        'name' => $pet['name'], 'image' => $pet['image'], 'category' => $pet['category_name'],
        'intro1' => $pet['intro1'], 'intro2' => $pet['intro2'],
        'city' => $location['city'] ?? $pet['city'], 'address' => $location['address'] ?? $pet['location_address'],
        'branch_email' => $location['email'] ?? $pet['location_email'], 'branch_contact' => $location['contact'] ?? $pet['location_contact'],
    ];
}

$history = $mode === 'adopted' ? [] : db_all(
    "SELECT * FROM `requests` WHERE `pet_id` = ? AND `status` <> 'pending' ORDER BY COALESCE(`updated_at`, `request_time`) DESC LIMIT 5",
    [(int) $petId]
);
$applicantApps = $record['adopter_id'] !== null
    ? (int) db_value("SELECT COUNT(*) FROM `adopted` WHERE `adopter_id` = ?", [(int) $record['adopter_id']])
    : 0;

// Applicant history: every other application by the same person (adopter_id, or email for older rows).
$who = $record['adopter_id'] !== null ? [(int) $record['adopter_id'], $record['email']] : [-1, $record['email']];
$selfKey = ['pending' => ['requests', $record['request_id'] ?? 0], 'accepted' => ['accepted', $record['accept_id'] ?? 0], 'adopted' => ['adopted', $record['adopt_id'] ?? 0]][$mode];
$applicantHistory = [];
foreach (db_all(
    "SELECT x.`request_id` AS id, x.`status`, x.`reason`, x.`request_time`, COALESCE(x.`updated_at`, x.`request_time`) AS at,
            COALESCE(p.`name`, d.`pet_name`) AS pet_name, x.`pet_id`
     FROM `requests` x LEFT JOIN `pets` p ON p.`pet_id` = x.`pet_id` LEFT JOIN `adopted` d ON d.`pet_id` = x.`pet_id`
     WHERE x.`adopter_id` = ? OR x.`email` = ?", $who) as $h) {
    if (!($selfKey[0] === 'requests' && (int) $h['id'] === (int) $selfKey[1])) {
        $applicantHistory[] = $h + ['stage' => $h['status'], 'link' => $h['status'] === 'pending' ? 'admin/requestResponse.php?pindex=' . (int) $h['pet_id'] : null];
    }
}
foreach (db_all(
    "SELECT x.`accept_id` AS id, x.`request_time`, x.`accept_time` AS at, p.`name` AS pet_name, x.`pet_id`
     FROM `accepted` x LEFT JOIN `pets` p ON p.`pet_id` = x.`pet_id` WHERE x.`adopter_id` = ? OR x.`email` = ?", $who) as $h) {
    if (!($selfKey[0] === 'accepted' && (int) $h['id'] === (int) $selfKey[1])) {
        $applicantHistory[] = $h + ['stage' => 'accepted', 'reason' => '', 'link' => 'admin/requestResponse.php?pindex=' . (int) $h['pet_id']];
    }
}
foreach (db_all(
    "SELECT x.`adopt_id` AS id, x.`request_time`, x.`adopt_time` AS at, x.`pet_name` FROM `adopted` x WHERE x.`adopter_id` = ? OR x.`email` = ?", $who) as $h) {
    if (!($selfKey[0] === 'adopted' && (int) $h['id'] === (int) $selfKey[1])) {
        $applicantHistory[] = $h + ['stage' => 'adopted', 'reason' => '', 'link' => 'admin/requestResponse.php?aindex=' . (int) $h['id']];
    }
}
usort($applicantHistory, fn($a, $b) => strcmp($b['at'], $a['at']));
$applicantAccount = $record['adopter_id'] !== null
    ? db_one("SELECT `created_at`, (SELECT COUNT(*) FROM `favourites` f WHERE f.`adopter_id` = a.`adopter_id`) AS saves FROM `adopter` a WHERE a.`adopter_id` = ?", [(int) $record['adopter_id']])
    : null;
$outcomeCounts = array_count_values(array_column($applicantHistory, 'stage'));
// Admin wording for outcomes (STAGE_LABELS is written for adopters).
$outcomeLabels = ['pending' => ['Pending', 'warning'], 'accepted' => ['Awaiting pickup', 'info'], 'adopted' => ['Adopted', 'success'],
                  'rejected' => ['Not approved', 'danger'], 'cancelled' => ['Withdrawn', 'secondary']];

// Pet insights (not for completed adoptions: the pet row is gone by then).
$petInsights = null;
if ($pet) {
    $petInsights = db_one(
        "SELECT DATEDIFF(NOW(), p.`created_at`) AS in_care,
            (SELECT COUNT(*) FROM `favourites` f WHERE f.`pet_id` = p.`pet_id`) AS saves,
            (SELECT COUNT(*) FROM `requests` r WHERE r.`pet_id` = p.`pet_id` AND r.`status` <> 'pending') AS earlier
         FROM `pets` p WHERE p.`pet_id` = ?", [(int) $pet['pet_id']]);
}
$durationLabel = function ($from, $to) {
    if (!$from || !$to) {
        return '—';
    }
    $days = (strtotime($to) - strtotime($from)) / 86400;
    return $days < 1 ? max(1, (int) round($days * 24)) . ' h' : rtrim(rtrim(number_format($days, 1), '0'), '.') . ' days';
};

$steps = [
    ['Requested', $record['request_time'], true],
    ['Approved', $record['accept_time'] ?? null, $mode !== 'pending'],
    ['Adopted', $record['adopt_time'] ?? null, $mode === 'adopted'],
];
$stage = ['pending' => 'pending', 'accepted' => 'accepted', 'adopted' => 'adopted'][$mode];
$backUrl = ['pending' => 'admin/requests.php', 'accepted' => 'admin/accepted.php', 'adopted' => 'admin/adopted.php'][$mode];

$pageTitle = 'Application · ' . $view['name'];
$activeNav = ['pending' => 'requests', 'accepted' => 'accepted', 'adopted' => 'adopted'][$mode];
include __DIR__ . '/header.php';
?>
<div class="admin-page-head">
    <div>
        <a class="back" href="<?= e(url($backUrl)) ?>"><i class="bi bi-arrow-left"></i> Back to <?= e(['pending' => 'requests', 'accepted' => 'awaiting pickup', 'adopted' => 'adoption history'][$mode]) ?></a>
        <h1><?= e($record['name']) ?> <i class="bi bi-arrow-right text-muted fs-4"></i> <?= e($view['name']) ?></h1>
        <p>Application received <?= e(format_date($record['request_time'], true)) ?> · <?= e(time_ago($record['request_time'])) ?></p>
    </div>
    <?= stage_badge($stage) ?>
</div>

<div class="row g-3">
    <div class="col-lg-8 d-flex flex-column gap-3">
        <div class="panel h-auto">
            <div class="panel-head"><h2>Decision</h2></div>
            <ol class="tracker mt-0">
                <?php foreach ($steps as $i => [$label, $time, $done]): ?>
                    <li class="<?= $done ? 'is-done' : ($i === array_sum(array_column($steps, 2)) ? 'is-next' : '') ?>">
                        <span class="tracker-dot" aria-hidden="true"></span><span class="tracker-label"><?= e($label) ?></span>
                        <span class="tracker-time"><?= e($done ? format_date($time) : 'Waiting') ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
            <?php if ($mode === 'accepted'): ?>
                <?php $daysLeft = 3 - (int) floor((time() - strtotime($record['accept_time'])) / 86400); ?>
                <p class="note <?= $daysLeft < 0 ? 'note-danger' : 'note-info' ?> mb-3">
                    <i class="bi bi-clock"></i>
                    <span><?= $daysLeft >= 0 ? 'Pickup window: ' . $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') . ' left.' : 'The 3-day pickup window ended ' . abs($daysLeft) . ' day' . (abs($daysLeft) === 1 ? '' : 's') . ' ago.' ?></span>
                </p>
            <?php endif; ?>
            <?php if ($mode === 'pending'): ?>
                <p class="text-muted">Approving reserves <?= e($view['name']) ?> for <?= e($record['name']) ?> and gives them 3 days to pick up. Rejecting makes <?= e($view['name']) ?> available again. The applicant is emailed either way.</p>
                <div class="d-flex flex-wrap gap-2">
                    <form method="post" data-confirm="<?= e($record['name']) ?> will be emailed and has 3 days to pick up <?= e($view['name']) ?>." data-confirm-title="Approve this request?" data-confirm-ok="Approve" data-confirm-tone="neutral">
                        <?= csrf_field() ?><input type="hidden" name="action" value="approve">
                        <button class="btn btn-primary"><i class="bi bi-check2-circle"></i> Approve request</button>
                    </form>
                    <button class="btn btn-soft-danger" data-bs-toggle="modal" data-bs-target="#reasonModal"><i class="bi bi-x-circle"></i> Reject…</button>
                </div>
            <?php elseif ($mode === 'accepted'): ?>
                <p class="text-muted">Once <?= e($record['name']) ?> has collected <?= e($view['name']) ?>, mark the adoption complete. This moves the pet to the adoption history and removes the listing.</p>
                <div class="d-flex flex-wrap gap-2">
                    <form method="post" data-confirm="<?= e($view['name']) ?> will be removed from the listings and moved to the adoption history." data-confirm-title="Mark as adopted?" data-confirm-ok="Mark adopted" data-confirm-tone="neutral">
                        <?= csrf_field() ?><input type="hidden" name="action" value="adopted">
                        <button class="btn btn-primary"><i class="bi bi-house-heart"></i> Mark as adopted</button>
                    </form>
                    <button class="btn btn-soft-danger" data-bs-toggle="modal" data-bs-target="#reasonModal"><i class="bi bi-slash-circle"></i> Cancel approval…</button>
                    <?php if ($chatAdopterId = adopter_id_for($record)): ?>
                        <a class="btn btn-light ms-lg-auto" href="<?= e(url('admin/contact.php?view=chats&chat=' . $chatAdopterId)) ?>"><i class="bi bi-chat-heart"></i> Chat with <?= e($record['name']) ?></a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p class="text-muted">This adoption is complete. Deleting the record removes it from your history and statistics.</p>
                <?php if ($chatAdopterId = adopter_id_for($record)): ?>
                    <a class="btn btn-light mb-2" href="<?= e(url('admin/contact.php?view=chats&chat=' . $chatAdopterId)) ?>"><i class="bi bi-chat-heart"></i> Chat with <?= e($record['name']) ?></a>
                <?php endif; ?>
                <form method="post" data-confirm="The adoption record for <?= e($view['name']) ?> will be removed from your history and statistics. This can’t be undone." data-confirm-title="Delete this record?" data-confirm-ok="Delete record">
                    <?= csrf_field() ?><input type="hidden" name="action" value="delete_adopted">
                    <button class="btn btn-soft-danger"><i class="bi bi-trash"></i> Delete record</button>
                </form>
            <?php endif; ?>
        </div>

        <div class="panel h-auto">
            <div class="panel-head"><h2>Applicant</h2>
                <?php if ($applicantApps): ?><span class="status-pill status-success"><i class="bi bi-house-heart"></i><?= $applicantApps ?> previous adoption<?= $applicantApps === 1 ? '' : 's' ?></span><?php endif; ?>
            </div>
            <div class="d-flex align-items-center gap-3 mb-3">
                <span class="avatar avatar-lg"><?= e(strtoupper(mb_substr($record['name'], 0, 1))) ?></span>
                <div class="min-w-0"><strong class="d-block break-word"><?= e($record['name']) ?></strong><a class="small" href="mailto:<?= e($record['email']) ?>"><?= e($record['email']) ?></a></div>
            </div>
            <dl class="detail-list">
                <dt>Phone</dt><dd><a href="tel:<?= e($record['contact']) ?>"><?= e($record['contact']) ?></a></dd>
                <dt>CNIC</dt><dd><?= e($record['cnic']) ?></dd>
                <dt>Address</dt><dd><?= e($record['address']) ?></dd>
                <dt>Requested</dt><dd><?= e(format_date($record['request_time'], true)) ?></dd>
                <?php if (!empty($record['accept_time'])): ?><dt>Approved</dt><dd><?= e(format_date($record['accept_time'], true)) ?></dd><?php endif; ?>
                <?php if (!empty($record['adopt_time'])): ?><dt>Adopted</dt><dd><?= e(format_date($record['adopt_time'], true)) ?></dd><?php endif; ?>
            </dl>
        </div>

        <div class="panel h-auto">
            <div class="panel-head"><div><h2>Applicant history</h2><p><?= $applicantAccount ? 'Member since ' . e(format_date($applicantAccount['created_at'])) . ' · ' . e(time_ago($applicantAccount['created_at'])) . ' · ' . (int) $applicantAccount['saves'] . ' saved pet' . ((int) $applicantAccount['saves'] === 1 ? '' : 's') : 'Not linked to an adopter account — matched by email' ?></p></div></div>
            <div class="history-summary">
                <?php foreach (['adopted' => 'Adopted', 'accepted' => 'Awaiting pickup', 'pending' => 'Other pending', 'rejected' => 'Not approved', 'cancelled' => 'Withdrawn'] as $k => $label): ?>
                    <div class="<?= ($outcomeCounts[$k] ?? 0) ? 'has-' . $outcomeLabels[$k][1] : '' ?>"><strong><?= (int) ($outcomeCounts[$k] ?? 0) ?></strong><span><?= $label ?></span></div>
                <?php endforeach; ?>
            </div>
            <?php if (!$applicantHistory): ?>
                <p class="note note-info mb-0 mt-3"><i class="bi bi-stars"></i><span>This is <?= e($record['name']) ?>’s first application.</span></p>
            <?php else: ?>
                <ul class="attention-list mt-3">
                    <?php foreach (array_slice($applicantHistory, 0, 8) as $h): [$olabel, $otone] = $outcomeLabels[$h['stage']] ?? [ucfirst($h['stage']), 'secondary']; ?>
                        <li>
                            <span class="avatar avatar-brand"><i class="bi bi-heart"></i></span>
                            <div class="text"><strong><?= e($h['pet_name'] ?? 'A pet') ?></strong><small>Applied <?= e(format_date($h['request_time'])) ?><?= $h['stage'] !== 'pending' ? ' · updated ' . e(format_date($h['at'])) : '' ?><?= ($h['reason'] ?? '') !== '' ? ' · ' . e(excerpt($h['reason'], 70)) : '' ?></small></div>
                            <span class="status-pill status-<?= $otone ?>"><?= e($olabel) ?></span>
                            <?php if ($h['link']): ?><a class="btn btn-sm btn-light" href="<?= e(url($h['link'])) ?>">Open</a><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if (count($applicantHistory) > 8): ?><p class="small text-muted mt-2 mb-0">Showing the 8 most recent of <?= count($applicantHistory) ?>.</p><?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($history): ?>
            <div class="panel h-auto">
                <div class="panel-head"><h2>Earlier applications for <?= e($view['name']) ?></h2></div>
                <ul class="attention-list">
                    <?php foreach ($history as $h): ?>
                        <li>
                            <span class="avatar"><?= e(strtoupper(mb_substr($h['name'], 0, 1))) ?></span>
                            <div class="text"><strong><?= e($h['name']) ?></strong><small><?= e(format_date($h['updated_at'] ?? $h['request_time'])) ?><?= $h['reason'] !== '' ? ' · ' . e(excerpt($h['reason'], 90)) : '' ?></small></div>
                            <?= str_replace('Cancelled by you', 'Withdrawn by applicant', stage_badge($h['status'])) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="panel h-auto p-2" style="position:sticky;top:88px">
            <img class="w-100 rounded-4" style="aspect-ratio:4/3;object-fit:cover" src="<?= e(pet_image_url($view['image'])) ?>" alt="<?= e($view['name']) ?>"<?= img_fallback() ?>>
            <div class="p-3">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div class="min-w-0">
                        <h2 class="h4 mb-0 break-word"><?= e($view['name']) ?></h2>
                        <p class="text-muted fw-semibold mb-2"><?= e(trim(($view['category'] ?? '') . (!empty($pet['breed']) ? ' · ' . $pet['breed'] : ''), ' ·')) ?></p>
                    </div>
                    <?php if ($pet): ?><a class="btn btn-sm btn-light flex-none" href="<?= e(url('admin/petView.php?pindex=' . (int) $pet['pet_id'])) ?>">Profile</a><?php endif; ?>
                </div>
                <?php if ($pet): ?><div class="chips mb-3"><?= pet_badges($pet) ?></div><?php endif; ?>
                <p class="small text-muted break-word"><?= e(excerpt($view['intro1'], 220)) ?></p>
                <?php if ($petInsights): ?>
                    <div class="mini-stats">
                        <div><strong><?= (int) $petInsights['in_care'] ?>d</strong><span>In care</span></div>
                        <div><strong><?= (int) $petInsights['saves'] ?></strong><span>Saved by</span></div>
                        <div><strong><?= (int) $petInsights['earlier'] ?></strong><span>Earlier apps</span></div>
                    </div>
                <?php elseif ($mode === 'adopted'): ?>
                    <div class="mini-stats">
                        <div><strong><?= e($durationLabel($record['request_time'], $record['accept_time'])) ?></strong><span>To approve</span></div>
                        <div><strong><?= e($durationLabel($record['accept_time'], $record['adopt_time'])) ?></strong><span>To pick up</span></div>
                        <div><strong><?= e($durationLabel($record['request_time'], $record['adopt_time'])) ?></strong><span>In total</span></div>
                    </div>
                <?php endif; ?>
                <div class="info-row"><i class="bi bi-geo-alt"></i><div><small>Branch</small><?= e($view['address']) ?> (<?= e($view['city']) ?>)</div></div>
                <div class="info-row"><i class="bi bi-telephone"></i><div><small>Branch contact</small><?= e($view['branch_contact']) ?> · <?= e($view['branch_email']) ?></div></div>
            </div>
        </div>
    </div>
</div>

<?php if ($mode === 'pending' || $mode === 'accepted'): ?>
<div class="modal fade" id="reasonModal" tabindex="-1" aria-labelledby="reasonModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $mode === 'pending' ? 'reject' : 'cancel_approval' ?>">
            <div class="modal-header">
                <h2 class="modal-title h5" id="reasonModalLabel"><?= $mode === 'pending' ? 'Reject this request' : 'Cancel this approval' ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="form-label" for="reason">Reason (shared with <?= e($record['name']) ?>)</label>
                <textarea class="form-control" id="reason" name="reason" rows="4" maxlength="500" required data-count="#reasonCount"
                    placeholder="<?= $mode === 'pending' ? 'e.g. We are looking for a home with a garden for this dog.' : 'e.g. The pet was not collected within 3 days.' ?>"></textarea>
                <div class="form-text d-flex justify-content-between"><span><?= e($view['name']) ?> will become available for other adopters again.</span><span class="char-count" id="reasonCount"></span></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Keep it</button>
                <button class="btn btn-danger"><?= $mode === 'pending' ? 'Reject request' : 'Cancel approval' ?></button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php page_end(); ?>
