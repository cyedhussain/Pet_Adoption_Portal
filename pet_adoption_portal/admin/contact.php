<?php
require_once __DIR__ . '/dbconnect.php';
$adminId = require_admin();

// Three views: one-to-one chats with approved adopters (?view=chats&chat=ADOPTER_ID), community
// groups (?view=groups&group=GROUP_ID) and the contact-form inbox.
$view = query_param('view') === 'chats' || query_int('chat') ? 'chats'
    : (query_param('view') === 'groups' || query_int('group') ? 'groups' : 'contact');
if ($view === 'chats') {
    $threads = chat_threads();
    $threadIds = array_map('intval', array_column($threads, 'adopter_id'));
    $chatId = query_int('chat');
    if ($chatId && !in_array($chatId, $threadIds, true)) {
        flash('That conversation isn’t available. Chats open once an application is approved.', 'warning');
        redirect('admin/contact.php?view=chats');
    }
    $chatId = $chatId ?: ($threadIds[0] ?? 0);
    if ($chatId) {
        $chatAdopter = db_one("SELECT * FROM `adopter` WHERE `adopter_id` = ?", [$chatId]);
        $chatContext = chat_context($chatId);
        $chatUrl = url('admin/contact.php?view=chats&chat=' . $chatId);
        handle_chat_request($chatId, 'admin', (bool) $chatContext, $chatUrl, $adminId);
        chat_mark_read($chatId, 'admin');
        foreach ($threads as &$t) {
            if ((int) $t['adopter_id'] === $chatId) {
                $t['unread'] = 0;
            }
        }
        unset($t);
    }
}

if ($view === 'groups') {
    $groupId = query_int('group');
    $groupsUrl = 'admin/contact.php?view=groups';

    // managing groups: create, rename, add / remove members, delete (group chat messages are handled further down)
    if (is_post() && post('group_action') !== '') {
        $action = post('group_action');
        if ($action === 'create') {
            $name = post('name');
            $description = post('description');
            $errors = community_group_errors($name, $description);
            if ($errors) {
                $_SESSION['group_form'] = ['name' => $name, 'description' => $description, 'errors' => $errors];
                redirect($groupsUrl . ($groupId ? '&group=' . $groupId : '') . '&new=1');
            }
            $members = posted_adopter_ids();
            $newId = db_transaction(function () use ($name, $description, $members, $adminId) {
                db_run("INSERT INTO `community_groups` (`name`, `description`, `created_by`) VALUES (?, ?, ?)", [$name, $description, $adminId]);
                $id = db_insert_id();
                community_post($id, 'system', current_user_name() . ' created the group');
                foreach ($members as $memberId) {
                    community_add_member($id, $memberId);
                }
                return $id;
            });
            flash('“' . $name . '” created' . ($members ? ' with ' . count($members) . ' member' . (count($members) === 1 ? '' : 's') : '') . '.');
            redirect($groupsUrl . '&group=' . $newId);
        }
        $activeGroup = community_group($groupId);
        if (!$activeGroup) {
            flash('That group no longer exists.', 'warning');
            redirect($groupsUrl);
        }
        $back = $groupsUrl . '&group=' . $groupId;
        if ($action === 'update') {
            $name = post('name');
            $description = post('description');
            $errors = community_group_errors($name, $description);
            if ($errors) {
                flash(implode(' ', $errors), 'warning');
                redirect($back);
            }
            db_run("UPDATE `community_groups` SET `name` = ?, `description` = ? WHERE `group_id` = ?", [$name, $description, $groupId]);
            if ($name !== $activeGroup['name']) {
                community_post($groupId, 'system', 'The group was renamed to “' . $name . '”');
            }
            flash('Group details saved.');
        } elseif ($action === 'add_members') {
            $added = 0;
            foreach (posted_adopter_ids() as $memberId) {
                $added += community_add_member($groupId, $memberId) ? 1 : 0;
            }
            flash($added ? $added . ' member' . ($added === 1 ? '' : 's') . ' added.' : 'Pick at least one adopter to add.', $added ? 'success' : 'warning');
        } elseif ($action === 'remove_member') {
            $member = db_one("SELECT `name` FROM `adopter` WHERE `adopter_id` = ?", [(int) post('adopter_id')]);
            if ($member && community_remove_member($groupId, (int) post('adopter_id'), $member['name'] . ' was removed by the shelter')) {
                flash($member['name'] . ' was removed from the group.', 'info');
            }
        } elseif ($action === 'delete') {
            community_delete_group($groupId);
            flash('“' . $activeGroup['name'] . '” and its messages were deleted.', 'info');
            redirect($groupsUrl);
        }
        redirect($back);
    }

    $groups = community_groups();
    $groupIds = array_map('intval', array_column($groups, 'group_id'));
    if ($groupId && !in_array($groupId, $groupIds, true)) {
        flash('That group no longer exists.', 'warning');
        redirect($groupsUrl);
    }
    $groupId = $groupId ?: ($groupIds[0] ?? 0);
    $directory = community_adopter_directory();
    if ($groupId) {
        $activeGroup = community_group($groupId);
        $groupUrl = url($groupsUrl . '&group=' . $groupId);
        handle_community_request($groupId, 'admin', $groupUrl, null, $adminId);
        community_mark_read($groupId, 'admin');
        foreach ($groups as &$g) {
            if ((int) $g['group_id'] === $groupId) {
                $g['unread'] = 0;
            }
        }
        unset($g);
        $members = community_members($groupId);
        $memberIds = array_map('intval', array_column($members, 'adopter_id'));
        $nonMembers = array_values(array_filter($directory, fn($a) => !in_array((int) $a['adopter_id'], $memberIds, true)));
        $creator = $activeGroup['created_by'] ? db_value("SELECT `name` FROM `admin` WHERE `admin_id` = ?", [(int) $activeGroup['created_by']]) : null;
    }
    $groupForm = $_SESSION['group_form'] ?? ['name' => '', 'description' => '', 'errors' => []];
    unset($_SESSION['group_form']);
}
$chatUnreadTotal = chat_unread_count();
$groupUnreadTotal = community_unread_total();

$tab = in_array(query_param('tab'), ['unanswered', 'answered', 'all'], true) ? query_param('tab') : 'unanswered';
$counts = [
    'unanswered' => (int) db_value("SELECT COUNT(*) FROM `contact` WHERE `answer` = ''"),
    'answered'   => (int) db_value("SELECT COUNT(*) FROM `contact` WHERE `answer` <> ''"),
];
$counts['all'] = $counts['unanswered'] + $counts['answered'];
$where = ['unanswered' => "WHERE `answer` = ''", 'answered' => "WHERE `answer` <> ''", 'all' => ''][$tab];
$messages = db_all("SELECT * FROM `contact` $where ORDER BY `timestamp` " . ($tab === 'unanswered' ? 'ASC' : 'DESC'));
$inbox = db_one(
    "SELECT MIN(CASE WHEN `answer` = '' THEN `timestamp` END) AS oldest,
        SUM(`timestamp` >= NOW() - INTERVAL 7 DAY) AS week,
        SUM(`answer` = '' AND `timestamp` < NOW() - INTERVAL 3 DAY) AS over3,
        COUNT(DISTINCT `email`) AS senders
     FROM `contact`"
);
$daysWaiting = fn($ts) => (int) floor((time() - strtotime($ts)) / 86400);

$pageTitle = $view === 'groups' ? 'Community' : 'Messages';
$activeNav = $view === 'groups' ? 'community' : 'contact';
include __DIR__ . '/header.php';
?>
<div class="admin-page-head">
    <div>
        <h1><?= $view === 'groups' ? 'Community' : 'Messages' ?></h1>
        <p><?= [
            'chats'   => 'One-to-one chats with adopters whose application you approved. New messages appear automatically.',
            'groups'  => 'Group chats you create for adopters — a place for them to share updates, tips and questions with each other and with your team.',
            'contact' => 'Questions sent through the contact form. Replies are emailed and shown on the adopter’s Messages page.',
        ][$view] ?></p>
    </div>
    <nav class="seg-tabs" aria-label="Message type">
        <a class="<?= $view === 'chats' ? 'active' : '' ?>" href="<?= e(url('admin/contact.php?view=chats')) ?>"<?= $view === 'chats' ? ' aria-current="page"' : '' ?>><i class="bi bi-chat-heart"></i> Live chats<?php if ($chatUnreadTotal): ?> <span class="nav-badge"><?= $chatUnreadTotal ?></span><?php endif; ?></a>
        <a class="<?= $view === 'groups' ? 'active' : '' ?>" href="<?= e(url('admin/contact.php?view=groups')) ?>"<?= $view === 'groups' ? ' aria-current="page"' : '' ?>><i class="bi bi-people"></i> Community<?php if ($groupUnreadTotal): ?> <span class="nav-badge"><?= $groupUnreadTotal ?></span><?php endif; ?></a>
        <a class="<?= $view === 'contact' ? 'active' : '' ?>" href="<?= e(url('admin/contact.php')) ?>"<?= $view === 'contact' ? ' aria-current="page"' : '' ?>><i class="bi bi-envelope"></i> Contact form <span class="n"><?= $counts['unanswered'] ?></span></a>
    </nav>
</div>

<?php if ($view === 'chats'): ?>
    <?php if (!$threads): ?>
        <?php render_empty_state('bi-chat-heart', 'No chats yet', 'A chat opens with an adopter as soon as you approve one of their applications.', 'Review requests', url('admin/requests.php')); ?>
    <?php else: ?>
        <div class="chat-layout">
            <aside class="chat-list" aria-label="Conversations">
                <div class="chat-list-head"><strong>Conversations</strong><span class="text-muted small fw-semibold"><?= count($threads) ?></span></div>
                <?php foreach ($threads as $t):
                    $isActive = (int) $t['adopter_id'] === $chatId;
                    $preview = $t['last_body'] !== null
                        ? ($t['last_sender'] === 'admin' ? 'You: ' : '') . excerpt(preg_replace('/\s+/', ' ', $t['last_body']), 60)
                        : 'No messages yet — say hello';
                    [$tagLabel, $tagTone] = (int) $t['accepted_n'] ? ['Awaiting pickup', 'status-info'] : ((int) $t['adopted_n'] ? ['Adopted', 'status-success'] : ['Closed', 'status-secondary']); ?>
                    <a class="chat-list-item<?= $isActive ? ' active' : '' ?><?= (int) $t['unread'] ? ' has-unread' : '' ?>" href="<?= e(url('admin/contact.php?view=chats&chat=' . (int) $t['adopter_id'])) ?>"<?= $isActive ? ' aria-current="true"' : '' ?>>
                        <span class="avatar"><?= e(strtoupper(mb_substr($t['name'], 0, 1))) ?></span>
                        <span class="min-w-0 flex-grow-1">
                            <span class="d-flex justify-content-between align-items-baseline gap-2">
                                <strong class="text-truncate"><?= e($t['name']) ?></strong>
                                <?php if ($t['last_at']): ?><small class="text-muted text-nowrap"><?= e(time_ago($t['last_at'])) ?></small><?php endif; ?>
                            </span>
                            <small class="chat-list-preview"><?= e($preview) ?></small>
                            <span class="status-pill <?= $tagTone ?> chat-list-tag"><?= e($tagLabel) ?></span>
                        </span>
                        <?php if ((int) $t['unread']): ?><span class="nav-badge" title="Unread"><?= (int) $t['unread'] ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </aside>
            <div class="min-w-0">
                <?php render_chat($chatId, 'admin', [
                    'title'       => $chatAdopter['name'],
                    'subtitle'    => $chatAdopter['email'] . ' · adopter since ' . format_date($chatAdopter['created_at']),
                    'avatar'      => strtoupper(mb_substr($chatAdopter['name'], 0, 1)),
                    'context'     => $chatContext,
                    'canSend'     => (bool) $chatContext,
                    'url'         => $chatUrl,
                    'placeholder' => 'Write to ' . $chatAdopter['name'] . '…',
                    'emptyText'   => 'Say hello — let ' . $chatAdopter['name'] . ' know when and where to pick up.',
                    'closedText'  => $chatAdopter['name'] . ' has no approved application right now, so this chat is read-only.',
                ]); ?>
            </div>
        </div>
    <?php endif; ?>
<?php elseif ($view === 'groups'): ?>
    <?php if (!$groups): ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="bi bi-people"></i></div>
            <h3>Start your first community group</h3>
            <p>Bring adopters together — for example everyone who adopted a cat, or this month’s new families. Members can chat with each other and with your team.</p>
            <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#groupModal"><i class="bi bi-plus-lg"></i> Create a group</button>
        </div>
    <?php else: ?>
        <div class="group-layout">
            <aside class="chat-list" aria-label="Groups">
                <div class="chat-list-head">
                    <strong>Groups <span class="text-muted small fw-semibold"><?= count($groups) ?></span></strong>
                    <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#groupModal"><i class="bi bi-plus-lg"></i> New group</button>
                </div>
                <?php foreach ($groups as $g):
                    $isActive = (int) $g['group_id'] === $groupId; ?>
                    <a class="chat-list-item<?= $isActive ? ' active' : '' ?><?= (int) $g['unread'] ? ' has-unread' : '' ?>" href="<?= e(url('admin/contact.php?view=groups&group=' . (int) $g['group_id'])) ?>"<?= $isActive ? ' aria-current="true"' : '' ?>>
                        <?= group_icon_html($g) ?>
                        <span class="min-w-0 flex-grow-1">
                            <span class="d-flex justify-content-between align-items-baseline gap-2">
                                <strong class="text-truncate"><?= e($g['name']) ?></strong>
                                <small class="text-muted text-nowrap"><?= e(time_ago($g['last_at'] ?? $g['created_at'])) ?></small>
                            </span>
                            <small class="chat-list-preview"><?= e(community_preview($g, 'admin')) ?></small>
                            <span class="status-pill status-secondary chat-list-tag"><i class="bi bi-people"></i><?= (int) $g['member_count'] ?> member<?= (int) $g['member_count'] === 1 ? '' : 's' ?></span>
                        </span>
                        <?php if ((int) $g['unread']): ?><span class="nav-badge" title="Unread"><?= (int) $g['unread'] ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </aside>

            <div class="min-w-0">
                <?php render_group_chat($activeGroup, 'admin', [
                    'url'         => $groupUrl,
                    'subtitle'    => count($members) . ' member' . (count($members) === 1 ? '' : 's') . ' · you post as Shelter team',
                    'placeholder' => 'Message ' . $activeGroup['name'] . '…',
                    'emptyText'   => $members ? 'Kick things off — welcome everyone and say what this group is for.' : 'Add a few adopters, then welcome them here.',
                ]); ?>
            </div>

            <aside class="group-panel" aria-label="Group details">
                <div class="group-panel-head">
                    <?= group_icon_html($activeGroup, true) ?>
                    <h2 class="h5 mb-1 mt-2 break-word"><?= e($activeGroup['name']) ?></h2>
                    <?php if ($activeGroup['description'] !== ''): ?><p class="small text-muted mb-1 break-word"><?= e($activeGroup['description']) ?></p><?php endif; ?>
                    <small class="text-muted">Created <?= e(format_date($activeGroup['created_at'])) ?><?= $creator ? ' by ' . e($creator) : '' ?></small>
                    <div class="d-flex gap-2 justify-content-center mt-3">
                        <button class="btn btn-light btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#groupEditModal"><i class="bi bi-pencil"></i> Edit</button>
                        <form method="post" data-confirm="“<?= e($activeGroup['name']) ?>” and all of its messages will be deleted for everyone. This can’t be undone." data-confirm-title="Delete this group?" data-confirm-ok="Delete group">
                            <?= csrf_field() ?><input type="hidden" name="group_action" value="delete">
                            <button class="btn btn-soft-danger btn-sm"><i class="bi bi-trash"></i> Delete</button>
                        </form>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3 mb-2">
                    <strong>Members <span class="text-muted small"><?= count($members) ?></span></strong>
                    <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#addMembersModal"<?= $nonMembers ? '' : ' disabled title="Every adopter is already a member"' ?>><i class="bi bi-person-plus"></i> Add</button>
                </div>
                <ul class="member-list">
                    <li class="member-row"><span class="brand-mark member-staff-mark" aria-hidden="true"><i class="bi bi-heart-fill"></i></span>
                        <span class="min-w-0 flex-grow-1"><strong class="d-block">Shelter team</strong><small class="text-muted">All admins · can see every group</small></span></li>
                    <?php foreach ($members as $m): ?>
                        <li class="member-row">
                            <span class="avatar"><?= e(mb_strtoupper(mb_substr($m['name'], 0, 1))) ?></span>
                            <span class="min-w-0 flex-grow-1"><strong class="d-block text-truncate"><?= e($m['name']) ?></strong><small class="text-muted d-block text-truncate"><?= e($m['email']) ?></small><span class="d-block mt-1"><?= adopter_progress_pill($m) ?></span></span>
                            <form class="flex-none" method="post" data-confirm="<?= e($m['name']) ?> will be removed from “<?= e($activeGroup['name']) ?>” and won’t see its messages any more." data-confirm-title="Remove member?" data-confirm-ok="Remove">
                                <?= csrf_field() ?><input type="hidden" name="group_action" value="remove_member"><input type="hidden" name="adopter_id" value="<?= (int) $m['adopter_id'] ?>">
                                <button class="btn btn-icon btn-sm btn-light" title="Remove <?= e($m['name']) ?>" aria-label="Remove <?= e($m['name']) ?>"><i class="bi bi-x-lg"></i></button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                    <?php if (!$members): ?><li class="text-muted small px-1 py-2">No adopters yet — add some to get the conversation going.</li><?php endif; ?>
                </ul>
            </aside>
        </div>

        <div class="modal fade" id="groupEditModal" tabindex="-1" aria-labelledby="groupEditTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered"><form class="modal-content" method="post">
                <div class="modal-header"><h2 class="modal-title h5" id="groupEditTitle">Edit group</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <?= csrf_field() ?><input type="hidden" name="group_action" value="update">
                    <label class="form-label" for="editGroupName">Name</label>
                    <input class="form-control mb-3" id="editGroupName" name="name" maxlength="<?= GROUP_NAME_MAX ?>" minlength="3" required value="<?= e($activeGroup['name']) ?>">
                    <label class="form-label" for="editGroupDescription">Description <span class="text-muted fw-normal">(optional)</span></label>
                    <textarea class="form-control" id="editGroupDescription" name="description" rows="3" maxlength="<?= GROUP_DESCRIPTION_MAX ?>"><?= e($activeGroup['description']) ?></textarea>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary">Save</button></div>
            </form></div>
        </div>

        <div class="modal fade" id="addMembersModal" tabindex="-1" aria-labelledby="addMembersTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><form class="modal-content" method="post">
                <div class="modal-header"><h2 class="modal-title h5" id="addMembersTitle">Add members to <?= e($activeGroup['name']) ?></h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <?= csrf_field() ?><input type="hidden" name="group_action" value="add_members">
                    <p class="small text-muted">New members can read earlier messages. Everyone in the group sees their name.</p>
                    <?php render_member_picker($nonMembers, 'add'); ?>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"<?= $nonMembers ? '' : ' disabled' ?>><i class="bi bi-person-plus"></i> Add selected</button></div>
            </form></div>
        </div>
    <?php endif; ?>

    <div class="modal fade" id="groupModal" tabindex="-1" aria-labelledby="groupModalTitle" aria-hidden="true"<?= query_param('new') === '1' ? ' data-autoshow' : '' ?>>
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><form class="modal-content" method="post" novalidate>
            <div class="modal-header"><h2 class="modal-title h5" id="groupModalTitle">New community group</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <?= csrf_field() ?><input type="hidden" name="group_action" value="create">
                <?php foreach ($groupForm['errors'] as $err): ?><div class="alert alert-warning py-2 small"><?= e($err) ?></div><?php endforeach; ?>
                <label class="form-label" for="groupName">Group name</label>
                <input class="form-control mb-3" id="groupName" name="name" maxlength="<?= GROUP_NAME_MAX ?>" required placeholder="e.g. Cat parents of Lefke" value="<?= e($groupForm['name']) ?>">
                <label class="form-label" for="groupDescription">Description <span class="text-muted fw-normal">(optional)</span></label>
                <textarea class="form-control mb-3" id="groupDescription" name="description" rows="2" maxlength="<?= GROUP_DESCRIPTION_MAX ?>" placeholder="What is this group for?"><?= e($groupForm['description']) ?></textarea>
                <label class="form-label d-block">Members</label>
                <?php render_member_picker($directory, 'new'); ?>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary"><i class="bi bi-people"></i> Create group</button></div>
        </form></div>
    </div>
<?php else: ?>

<nav class="seg-tabs mb-3" aria-label="Filter messages">
    <?php foreach (['unanswered' => 'Unanswered', 'answered' => 'Answered', 'all' => 'All'] as $key => $label): ?>
        <a class="<?= $tab === $key ? 'active' : '' ?>" href="<?= e(url('admin/contact.php?tab=' . $key)) ?>"<?= $tab === $key ? ' aria-current="page"' : '' ?>><?= $label ?> <span class="n"><?= $counts[$key] ?></span></a>
    <?php endforeach; ?>
</nav>

<div class="admin-strip">
    <div class="<?= $counts['unanswered'] ? 'is-warn' : '' ?>"><span class="lbl">Unanswered</span><strong><?= $counts['unanswered'] ?></strong><small>of <?= $counts['all'] ?> message<?= $counts['all'] === 1 ? '' : 's' ?></small></div>
    <div class="<?= $inbox['oldest'] && $daysWaiting($inbox['oldest']) > 3 ? 'is-danger' : '' ?>"><span class="lbl">Oldest unanswered</span><strong><?= $inbox['oldest'] ? $daysWaiting($inbox['oldest']) . ' day' . ($daysWaiting($inbox['oldest']) === 1 ? '' : 's') : '—' ?></strong><small><?= $inbox['oldest'] ? 'since ' . e(format_date($inbox['oldest'])) : 'inbox zero' ?></small></div>
    <div><span class="lbl">Waiting 3+ days</span><strong><?= (int) $inbox['over3'] ?></strong><small>reply to these first</small></div>
    <div><span class="lbl">Received this week</span><strong><?= (int) $inbox['week'] ?></strong><small>from <?= (int) $inbox['senders'] ?> sender<?= (int) $inbox['senders'] === 1 ? '' : 's' ?> all-time</small></div>
</div>

<?php if (!$messages): ?>
    <?php if ($tab === 'unanswered'): ?>
        <?php render_empty_state('bi-check2-all', 'Inbox zero', 'Every message has been answered.', 'See answered messages', url('admin/contact.php?tab=answered')); ?>
    <?php else: ?>
        <?php render_empty_state('bi-chat-dots', 'No messages here', 'Messages from the contact form will appear here.'); ?>
    <?php endif; ?>
<?php endif; ?>

<div class="thread" style="max-width:920px">
    <?php foreach ($messages as $m): ?>
        <article class="message-card">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div class="d-flex align-items-center gap-3 min-w-0">
                    <span class="avatar avatar-lg"><?= e(strtoupper(mb_substr($m['name'], 0, 1))) ?></span>
                    <div class="min-w-0">
                        <strong class="d-block break-word"><?= e($m['name']) ?></strong>
                        <small class="text-muted"><a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a> · <?= e(format_date($m['timestamp'], true)) ?> · <?= e(time_ago($m['timestamp'])) ?></small>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <?php $waited = $daysWaiting($m['timestamp']); ?>
                    <?= $m['answer'] === ''
                        ? '<span class="status-pill ' . ($waited > 3 ? 'status-danger' : 'status-warning') . '" title="Waiting since ' . e(format_date($m['timestamp'], true)) . '"><i class="bi bi-hourglass-split"></i>' . ($waited < 1 ? 'Waiting since today' : 'Waiting ' . $waited . ' day' . ($waited === 1 ? '' : 's')) . '</span>'
                        : '<span class="status-pill status-success"><i class="bi bi-check2"></i>Answered</span>' ?>
                    <form method="post" action="<?= e(url('admin/contactResponse.php')) ?>" data-confirm="The message from <?= e($m['name']) ?> will be deleted. This can’t be undone." data-confirm-title="Delete message?" data-confirm-ok="Delete">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="contact_id" value="<?= (int) $m['contact_id'] ?>">
                        <input type="hidden" name="tab" value="<?= e($tab) ?>">
                        <button class="btn btn-icon btn-sm btn-light" title="Delete message" aria-label="Delete message from <?= e($m['name']) ?>"><i class="bi bi-trash"></i></button>
                    </form>
                </div>
            </div>
            <p class="message-body mt-3"><?= nl2br(e($m['message'])) ?></p>
            <?php if ($m['answer'] !== ''): ?>
                <div class="message-reply">
                    <strong class="d-block small mb-1"><i class="bi bi-reply-fill"></i> Your reply</strong>
                    <?= nl2br(e($m['answer'])) ?>
                </div>
            <?php else: ?>
                <form class="mt-3" method="post" action="<?= e(url('admin/contactResponse.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reply">
                    <input type="hidden" name="contact_id" value="<?= (int) $m['contact_id'] ?>">
                    <input type="hidden" name="tab" value="<?= e($tab) ?>">
                    <label class="visually-hidden" for="reply-<?= (int) $m['contact_id'] ?>">Reply to <?= e($m['name']) ?></label>
                    <textarea class="form-control mb-2" id="reply-<?= (int) $m['contact_id'] ?>" name="response" rows="3" maxlength="500" required placeholder="Write a reply to <?= e($m['name']) ?>…" data-count="#count-<?= (int) $m['contact_id'] ?>"></textarea>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <small class="text-muted"><?= mail_is_configured() ? 'The reply is emailed to ' . e($m['email']) . '.' : 'Email is not configured — the reply is saved and shown on their Messages page.' ?> <span class="char-count" id="count-<?= (int) $m['contact_id'] ?>"></span></small>
                        <button class="btn btn-primary btn-sm"><i class="bi bi-send"></i> Send reply</button>
                    </div>
                </form>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php page_end(); ?>
