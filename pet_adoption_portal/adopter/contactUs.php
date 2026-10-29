<?php
require_once __DIR__ . '/dbconnect.php';
$adopterId = require_adopter();

$adopter = db_one("SELECT * FROM `adopter` WHERE `adopter_id` = ?", [$adopterId]);

// Two views: the shelter (one-to-one chat + contact form) and, for members, community groups (?view=groups&group=ID).
$myGroups = community_groups($adopterId);
$myGroupIds = array_map('intval', array_column($myGroups, 'group_id'));
$view = $myGroups && (query_param('view') === 'groups' || query_int('group')) ? 'groups' : 'shelter';

if ($view === 'groups') {
    $groupsUrl = 'adopter/contactUs.php?view=groups';
    $groupId = query_int('group');
    if ($groupId && !in_array($groupId, $myGroupIds, true)) {
        flash('You’re not a member of that group.', 'warning');
        redirect($groupsUrl);
    }
    $groupId = $groupId ?: $myGroupIds[0];
    $group = community_group($groupId);
    if (is_post() && post('group_action') === 'leave') {
        community_remove_member($groupId, $adopterId, current_user_name() . ' left the group');
        flash('You left “' . $group['name'] . '”.', 'info');
        redirect(count($myGroupIds) > 1 ? $groupsUrl : 'adopter/contactUs.php');
    }
    $groupUrl = url($groupsUrl . '&group=' . $groupId);
    handle_community_request($groupId, 'adopter', $groupUrl, $adopterId);
    community_mark_read($groupId, 'adopter', $adopterId);
    foreach ($myGroups as &$g) {
        if ((int) $g['group_id'] === $groupId) {
            $g['unread'] = 0;
        }
    }
    unset($g);
    $members = community_members($groupId);
}

// Live chat with the shelter opens once an application is approved; earlier messages stay readable.
$chatContext = chat_context($adopterId);
$canChat = (bool) $chatContext;
if ($view === 'shelter') {
    handle_chat_request($adopterId, 'adopter', $canChat, url('adopter/contactUs.php'));
}
$hasChat = $canChat || db_value("SELECT COUNT(*) FROM `chat_messages` WHERE `adopter_id` = ?", [$adopterId]);
if ($view === 'shelter' && $hasChat && !is_post()) {
    chat_mark_read($adopterId, 'adopter');
}
$groupUnread = (int) array_sum(array_column($myGroups, 'unread'));

$message = '';
$error = '';
if ($view === 'shelter' && is_post()) {
    $message = post('message');
    if (mb_strlen($message) < 5 || mb_strlen($message) > 500) {
        $error = 'Please write a message between 5 and 500 characters.';
    } else {
        $duplicate = db_value("SELECT COUNT(*) FROM `contact` WHERE `email` = ? AND `message` = ?", [$adopter['email'], $message]);
        if (!$duplicate) {
            db_run("INSERT INTO `contact` (`name`, `email`, `message`, `answer`) VALUES (?, ?, ?, '')",
                [$adopter['name'], $adopter['email'], $message]);
        }
        flash('Message sent! Our team will reply here and by email.');
        redirect('adopter/contactUs.php#messages');
    }
}

$locations = all_locations();
$messages = db_all("SELECT * FROM `contact` WHERE `email` = ? ORDER BY `timestamp` DESC", [$adopter['email']]);

$pageTitle = $view === 'groups' ? 'Community' : 'Contact us';
$activeNav = $view === 'groups' ? 'community' : 'contact';
include __DIR__ . '/header.php';
if ($view === 'groups') {
    render_page_hero('Community', 'Groups the shelter set up for adopters like you — share updates, stories and tips with each other and with our team.', '', 'Your groups', [['Dashboard', url('adopter/home.php')]]);
} else {
    render_page_hero('Messages', $canChat
        ? 'Chat with the shelter team about your approved adoption — pickup, questions, anything. Replies appear here instantly.'
        : 'Questions about a pet, an application or volunteering? Send us a message — replies appear here and in your inbox.',
        '', 'Contact the shelter', [['Dashboard', url('adopter/home.php')]]);
}
?>
<?php if ($myGroups): ?>
<div class="container">
    <nav class="tabs-pill" aria-label="Message type">
        <a class="<?= $view === 'shelter' ? 'active' : '' ?>" href="<?= e(url('adopter/contactUs.php')) ?>"<?= $view === 'shelter' ? ' aria-current="page"' : '' ?>><i class="bi bi-chat-heart"></i> Shelter<?php $shelterUnread = chat_unread_count($adopterId); if ($shelterUnread): ?><span class="nav-badge ms-1"><?= $shelterUnread ?></span><?php endif; ?></a>
        <a class="<?= $view === 'groups' ? 'active' : '' ?>" href="<?= e(url('adopter/contactUs.php?view=groups')) ?>"<?= $view === 'groups' ? ' aria-current="page"' : '' ?>><i class="bi bi-people"></i> Community<span class="n"><?= count($myGroups) ?></span><?php if ($groupUnread): ?><span class="nav-badge ms-1"><?= $groupUnread ?></span><?php endif; ?></a>
    </nav>
</div>
<?php endif; ?>
<?php if ($view === 'groups'): ?>
<section class="pb-5">
    <div class="container">
        <div class="group-layout">
            <aside class="chat-list" aria-label="Your groups">
                <div class="chat-list-head"><strong>Your groups <span class="text-muted small fw-semibold"><?= count($myGroups) ?></span></strong></div>
                <?php foreach ($myGroups as $g):
                    $isActive = (int) $g['group_id'] === $groupId; ?>
                    <a class="chat-list-item<?= $isActive ? ' active' : '' ?><?= (int) $g['unread'] ? ' has-unread' : '' ?>" href="<?= e(url('adopter/contactUs.php?view=groups&group=' . (int) $g['group_id'])) ?>"<?= $isActive ? ' aria-current="true"' : '' ?>>
                        <?= group_icon_html($g) ?>
                        <span class="min-w-0 flex-grow-1">
                            <span class="d-flex justify-content-between align-items-baseline gap-2">
                                <strong class="text-truncate"><?= e($g['name']) ?></strong>
                                <small class="text-muted text-nowrap"><?= e(time_ago($g['last_at'] ?? $g['created_at'])) ?></small>
                            </span>
                            <small class="chat-list-preview"><?= e(community_preview($g, 'adopter', $adopterId)) ?></small>
                        </span>
                        <?php if ((int) $g['unread']): ?><span class="nav-badge" title="Unread"><?= (int) $g['unread'] ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </aside>

            <div class="min-w-0">
                <?php render_group_chat($group, 'adopter', [
                    'url'         => $groupUrl,
                    'subtitle'    => count($members) . ' adopter' . (count($members) === 1 ? '' : 's') . ' + the shelter team',
                    'placeholder' => 'Message ' . $group['name'] . '…',
                    'emptyText'   => 'Be the first to say hello — share how your pet is doing!',
                ], $adopterId); ?>
            </div>

            <aside class="group-panel" aria-label="Group details">
                <div class="group-panel-head">
                    <?= group_icon_html($group, true) ?>
                    <h2 class="h5 mb-1 mt-2 break-word"><?= e($group['name']) ?></h2>
                    <?php if ($group['description'] !== ''): ?><p class="small text-muted mb-0 break-word"><?= e($group['description']) ?></p><?php endif; ?>
                </div>
                <strong class="d-block mt-3 mb-2">Members <span class="text-muted small"><?= count($members) + 1 ?></span></strong>
                <ul class="member-list">
                    <li class="member-row"><span class="brand-mark member-staff-mark" aria-hidden="true"><i class="bi bi-heart-fill"></i></span>
                        <span class="min-w-0 flex-grow-1"><strong class="d-block">Shelter team</strong><small class="text-muted">Staff · runs this group</small></span></li>
                    <?php foreach ($members as $m): ?>
                        <li class="member-row">
                            <span class="avatar"><?= e(mb_strtoupper(mb_substr($m['name'], 0, 1))) ?></span>
                            <span class="min-w-0 flex-grow-1"><strong class="d-block text-truncate"><?= e($m['name']) ?><?= (int) $m['adopter_id'] === $adopterId ? ' <span class="text-muted fw-normal">(you)</span>' : '' ?></strong>
                                <small class="text-muted"><?= (int) $m['adopted_n'] ? 'Adopter family' : 'Member' ?></small></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <form method="post" class="mt-3" data-confirm="You’ll stop seeing messages from “<?= e($group['name']) ?>”. The shelter can add you back later." data-confirm-title="Leave this group?" data-confirm-ok="Leave group">
                    <?= csrf_field() ?><input type="hidden" name="group_action" value="leave">
                    <button class="btn btn-soft-danger btn-sm w-100"><i class="bi bi-box-arrow-left"></i> Leave group</button>
                </form>
            </aside>
        </div>
    </div>
</section>
<?php else: ?>
<?php if ($hasChat): ?>
<section class="pb-4">
    <div class="container">
        <?php render_chat($adopterId, 'adopter', [
            'title'       => 'Shelter team',
            'subtitle'    => APP_NAME . ' · staff reply here directly',
            'context'     => $chatContext,
            'canSend'     => $canChat,
            'url'         => url('adopter/contactUs.php'),
            'placeholder' => 'Ask about pickup, your pet, anything…',
            'emptyText'   => 'Your application was approved — say hello to arrange pickup or ask anything about your new friend.',
            'closedText'  => 'This chat closed because you no longer have an approved application. Use the form below to reach us.',
        ]); ?>
    </div>
</section>
<?php endif; ?>
<section class="pb-5">
    <div class="container">
        <?php if (!$hasChat): ?>
            <div class="chat-locked mb-4" data-animate>
                <span class="icon"><i class="bi bi-chat-heart"></i></span>
                <div><strong>Live chat with the shelter unlocks after approval</strong>
                    <span>As soon as one of your applications is approved you can chat one-to-one with our team here to arrange pickup.</span></div>
                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('adopter/requested.php')) ?>">My adoptions</a>
            </div>
        <?php endif; ?>
        <div class="row g-4 g-lg-5">
            <div class="col-lg-5">
                <div class="form-card" data-animate>
                    <span class="eyebrow"><?= $hasChat ? 'Contact form' : 'New message' ?></span>
                    <h2><?= $hasChat ? 'Something else?' : 'How can we help?' ?></h2>
                    <p class="text-muted">You’re writing as <strong class="break-word"><?= e($adopter['name']) ?></strong> (<span class="break-word"><?= e($adopter['email']) ?></span>).</p>
                    <form method="post" novalidate>
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label" for="message">Message</label>
                            <textarea class="form-control<?= $error ? ' is-invalid' : '' ?>" id="message" name="message" rows="6" maxlength="500" placeholder="Write your message…" data-count="#messageCount" required><?= e($message) ?></textarea>
                            <div class="invalid-feedback"><?= e($error) ?></div>
                            <div class="form-text d-flex justify-content-between"><span>Between 5 and 500 characters.</span><span class="char-count" id="messageCount"></span></div>
                        </div>
                        <button class="btn btn-primary btn-lg w-100" type="submit"><i class="bi bi-send"></i> Send message</button>
                    </form>
                </div>
                <h3 class="h5 mt-4 mb-3">Or visit a branch</h3>
                <div class="d-grid gap-2">
                    <?php foreach ($locations as $l): ?>
                        <div class="contact-tile">
                            <i class="bi bi-geo-alt"></i>
                            <div>
                                <h4><?= e($l['city']) ?></h4>
                                <p><?= e($l['address']) ?></p>
                                <p><a href="tel:<?= e($l['contact']) ?>"><?= e($l['contact']) ?></a> · <a href="mailto:<?= e($l['email']) ?>"><?= e($l['email']) ?></a></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-lg-7" id="messages">
                <div class="d-flex justify-content-between align-items-end mb-3">
                    <div><span class="eyebrow">Your conversations</span><h2 class="mb-0 h3">Message history</h2></div>
                    <span class="text-muted fw-semibold"><?= count($messages) ?> total</span>
                </div>
                <?php if (!$messages): ?>
                    <?php render_empty_state('bi-chat-dots', 'No messages yet', 'Messages you send and the shelter’s replies will appear here.'); ?>
                <?php else: ?>
                    <div class="thread">
                        <?php foreach ($messages as $m): ?>
                            <article class="message-card" data-animate>
                                <div class="message-meta">
                                    <small class="text-muted fw-semibold"><i class="bi bi-clock"></i> <?= e(format_date($m['timestamp'], true)) ?></small>
                                    <?php if ($m['answer'] === ''): ?>
                                        <span class="status-pill status-warning"><i class="bi bi-hourglass-split"></i>Awaiting reply</span>
                                    <?php else: ?>
                                        <span class="status-pill status-success"><i class="bi bi-check2-circle"></i>Answered</span>
                                    <?php endif; ?>
                                </div>
                                <p class="message-body"><?= nl2br(e($m['message'])) ?></p>
                                <?php if ($m['answer'] !== ''): ?>
                                    <div class="message-reply"><strong class="d-block small mb-1"><i class="bi bi-reply-fill"></i> Reply from the shelter</strong><?= nl2br(e($m['answer'])) ?></div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>
<?php page_end(); ?>
