<?php
/*
 * Admin layout: sidebar + top bar + content area. Set $pageTitle and $activeNav before
 * including, then call page_end() at the bottom of the page.
 */
require_once __DIR__ . '/dbconnect.php';
$adminId = require_admin();
$LAYOUT = 'admin';
$pendingCount = (int) db_value("SELECT COUNT(*) FROM `requests` WHERE `status` = 'pending'");
$acceptedCount = (int) db_value("SELECT COUNT(*) FROM `accepted`");
$overdueCount = (int) db_value("SELECT COUNT(*) FROM `accepted` WHERE `accept_time` < NOW() - INTERVAL 3 DAY");
$unansweredCount = (int) db_value("SELECT COUNT(*) FROM `contact` WHERE `answer` = ''");
$chatUnreadCount = chat_unread_count();
$groupUnreadCount = community_unread_total();
$newAdopterCount = (int) db_value("SELECT COUNT(*) FROM `adopter` WHERE `created_at` >= NOW() - INTERVAL 7 DAY");
$navGroups = [
    'Overview'  => [
        'home'     => ['admin/home.php', 'Dashboard', 'grid-1x2', 0],
    ],
    'Adoptions' => [
        'requests' => ['admin/requests.php', 'Requests', 'inbox', $pendingCount],
        'accepted' => ['admin/accepted.php', 'Awaiting pickup', 'calendar-check', $acceptedCount],
        'adopted'  => ['admin/adopted.php', 'Adoption history', 'house-heart', 0],
    ],
    'Shelter'   => [
        'pets'       => ['admin/pets.php', 'Pets', 'heart', 0],
        'categories' => ['admin/categories.php', 'Categories', 'tags', 0],
        'locations'  => ['admin/locations.php', 'Branches', 'geo-alt', 0],
    ],
    'People'    => [
        'adopters' => ['admin/adopted.php?tab=adopters', 'Adopters', 'people', 0],
        'contact'  => [$chatUnreadCount ? 'admin/contact.php?view=chats' : 'admin/contact.php', 'Messages', 'chat-dots', $unansweredCount + $chatUnreadCount],
        'community' => ['admin/contact.php?view=groups', 'Community', 'people-fill', $groupUnreadCount],
    ],
];
$navName = current_user_name();
$notifications = array_filter([
    [$pendingCount, 'admin/requests.php', 'inbox', 'tone-warning', $pendingCount . ' adoption request' . ($pendingCount === 1 ? '' : 's'), 'Waiting for your decision'],
    [$overdueCount, 'admin/accepted.php', 'exclamation-triangle', 'tone-danger', $overdueCount . ' overdue pickup' . ($overdueCount === 1 ? '' : 's'), 'Past the 3-day pickup window'],
    [$chatUnreadCount, 'admin/contact.php?view=chats', 'chat-heart', 'tone-success', $chatUnreadCount . ' unread chat message' . ($chatUnreadCount === 1 ? '' : 's'), 'From adopters with an approved application'],
    [$groupUnreadCount, 'admin/contact.php?view=groups', 'people', 'tone-brand', $groupUnreadCount . ' new community message' . ($groupUnreadCount === 1 ? '' : 's'), 'In groups you manage'],
    [$unansweredCount, 'admin/contact.php', 'chat-dots', 'tone-info', $unansweredCount . ' unanswered message' . ($unansweredCount === 1 ? '' : 's'), 'From the contact form'],
    [$newAdopterCount, 'admin/adopted.php?tab=adopters', 'person-plus', 'tone-success', $newAdopterCount . ' new adopter' . ($newAdopterCount === 1 ? '' : 's'), 'Registered in the last 7 days'],
], fn($n) => $n[0] > 0);
$needsAttention = $pendingCount + $overdueCount + $unansweredCount + $chatUnreadCount + $groupUnreadCount;

render_head(($pageTitle ?? 'Admin') . ' · Admin · ' . APP_NAME, 'admin-body');
render_flash();
?>
<div class="admin-shell">
    <aside class="admin-sidebar offcanvas-lg offcanvas-start" id="adminSidebar" tabindex="-1" aria-label="Admin navigation">
        <div class="offcanvas-body">
            <div class="d-flex align-items-start justify-content-between">
                <a class="brand" href="<?= e(url('admin/home.php')) ?>"><span class="brand-mark"><i class="bi bi-heart-fill"></i></span><span class="brand-name">Animal Matter<small>Admin portal</small></span></a>
                <button type="button" class="btn-close d-lg-none mt-2" data-bs-dismiss="offcanvas" data-bs-target="#adminSidebar" aria-label="Close menu"></button>
            </div>
            <nav aria-label="Admin">
                <?php foreach ($navGroups as $group => $items): ?>
                    <div class="sidebar-label"><?= e($group) ?></div>
                    <?php foreach ($items as $key => [$href, $label, $icon, $count]): ?>
                        <a class="sidebar-link<?= ($activeNav ?? '') === $key ? ' active' : '' ?>" href="<?= e(url($href)) ?>"<?= ($activeNav ?? '') === $key ? ' aria-current="page"' : '' ?>>
                            <i class="bi bi-<?= $icon ?>"></i><?= e($label) ?>
                            <?php if ($count): ?><span class="badge-count"><?= $count ?></span><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                <div class="sidebar-label">Website</div>
                <a class="sidebar-link" href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i>View public site</a>
            </nav>
            <div class="sidebar-card mt-4">
                <strong><?= $needsAttention ? $needsAttention . ' item' . ($needsAttention === 1 ? '' : 's') . ' need you' : 'All caught up' ?></strong>
                <?= $needsAttention ? 'Requests, overdue pickups and messages waiting for a reply.' : 'No pending requests, overdue pickups or unanswered messages.' ?>
                <?php if ($pendingCount): ?><a class="btn btn-accent btn-sm w-100" href="<?= e(url('admin/requests.php')) ?>">Review requests</a><?php endif; ?>
            </div>
            <div class="sidebar-user">
                <span class="avatar avatar-brand"><?= e(strtoupper(mb_substr($navName, 0, 1))) ?></span>
                <div class="flex-grow-1 min-w-0"><strong class="text-truncate d-block"><?= e($navName) ?></strong><small>Administrator</small></div>
                <a href="<?= e(url('admin/logout.php')) ?>" class="btn btn-icon btn-sm btn-light" title="Log out" aria-label="Log out"><i class="bi bi-box-arrow-right"></i></a>
            </div>
        </div>
    </aside>
    <main class="admin-main" id="main">
        <header class="admin-topbar lg">
            <button class="topbar-btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminSidebar" aria-controls="adminSidebar" aria-label="Open menu"><i class="bi bi-list"></i></button>
            <div class="crumbs"><a href="<?= e(url('admin/home.php')) ?>">Admin</a><i class="bi bi-chevron-right small" aria-hidden="true"></i><strong><?= e($pageTitle ?? 'Dashboard') ?></strong></div>
            <form class="admin-search" method="get" action="<?= e(url('admin/pets.php')) ?>" role="search">
                <i class="bi bi-search" aria-hidden="true"></i>
                <label class="visually-hidden" for="adminSearch">Search pets</label>
                <input class="form-control" type="search" id="adminSearch" name="q" placeholder="Search pets by name or breed…" value="<?= ($activeNav ?? '') === 'pets' ? e(query_param('q')) : '' ?>">
                <kbd aria-hidden="true">/</kbd>
            </form>
            <div class="dropdown">
                <button class="topbar-btn" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Notifications<?= $notifications ? ' (' . count($notifications) . ')' : '' ?>">
                    <i class="bi bi-bell"></i><?php if ($needsAttention): ?><span class="dot"></span><?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end notif-menu">
                    <div class="notif-head">Notifications <span class="text-muted small fw-semibold"><?= count($notifications) ?> new</span></div>
                    <?php if (!$notifications): ?>
                        <div class="notif-empty"><i class="bi bi-check2-circle text-success fs-4 d-block mb-1"></i>You’re all caught up.</div>
                    <?php endif; ?>
                    <?php foreach ($notifications as [, $href, $icon, $tone, $title, $sub]): ?>
                        <a class="notif-item" href="<?= e(url($href)) ?>"><span class="icon <?= $tone ?>"><i class="bi bi-<?= $icon ?>"></i></span><span><strong><?= e($title) ?></strong><small><?= e($sub) ?></small></span></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php if (!in_array($activeNav ?? '', ['pets', 'categories'], true)): ?><a class="btn btn-primary d-none d-md-inline-flex" href="<?= e(url('admin/pets.php?add=1')) ?>"><i class="bi bi-plus-lg"></i> Add pet</a><?php endif; ?>
            <div class="dropdown">
                <button class="topbar-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Account menu"><span class="avatar avatar-brand" style="width:30px;height:30px;font-size:.78rem"><?= e(strtoupper(mb_substr($navName, 0, 1))) ?></span></button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li class="dropdown-user"><strong><?= e($navName) ?></strong><small>Administrator</small></li>
                    <li><a class="dropdown-item" href="<?= e(url('admin/pets.php?add=1')) ?>"><i class="bi bi-plus-lg"></i> Add a pet</a></li>
                    <li><a class="dropdown-item" href="<?= e(url('index.php')) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right"></i> View public site</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= e(url('admin/logout.php')) ?>"><i class="bi bi-box-arrow-right text-danger"></i> Log out</a></li>
                </ul>
            </div>
        </header>
        <div class="admin-content">
