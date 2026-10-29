<?php
/*
 * Adopter layout. Set $pageTitle and $activeNav before including, then call
 * page_end() at the bottom of the page.
 */
require_once __DIR__ . '/dbconnect.php';
$adopterId = require_adopter();
$LAYOUT = 'site';
$navEmail = (string) db_value("SELECT `email` FROM `adopter` WHERE `adopter_id` = ?", [$adopterId]);
$navActiveApps = (int) db_value(
    "SELECT COUNT(*) FROM `requests` WHERE `status` = 'pending' AND (`adopter_id` = ? OR (`adopter_id` IS NULL AND `email` = ?))",
    [$adopterId, $navEmail])
    + (int) db_value("SELECT COUNT(*) FROM `accepted` WHERE `adopter_id` = ? OR (`adopter_id` IS NULL AND `email` = ?)",
    [$adopterId, $navEmail]);
$navFavCount = (int) db_value("SELECT COUNT(*) FROM `favourites` WHERE `adopter_id` = ?", [$adopterId]);
$navName = current_user_name();
$navInitial = strtoupper(mb_substr($navName, 0, 1));
// nav items: key => [href, label, icon, badge]
$navItems = [
    'home'       => [url('adopter/home.php'), 'Dashboard', 'grid-1x2', 0],
    'pets'       => [url('adopter/pets.php'), 'Find a pet', 'search-heart', 0],
    'categories' => [url('adopter/categories.php'), 'Animals', 'grid', 0],
    'requested'  => [url('adopter/requested.php'), 'My adoptions', 'clipboard2-heart', $navActiveApps],
    'contact'    => [url('adopter/contactUs.php'), 'Messages', 'chat-heart', chat_unread_count($adopterId)],
];
// "Community" only appears once the shelter has added this adopter to a group
$navGroups = community_groups($adopterId);
if ($navGroups) {
    $navItems = array_slice($navItems, 0, 4, true)
        + ['community' => [url('adopter/contactUs.php?view=groups'), 'Community', 'people', (int) array_sum(array_column($navGroups, 'unread'))]]
        + $navItems;
}

$headerActions = '<a class="header-icon-btn hide-mobile" href="' . e(url('adopter/home.php#favourites')) . '" aria-label="Saved pets (' . $navFavCount . ')" title="Saved pets">'
         . '<i class="bi bi-heart"></i>' . ($navFavCount ? '<span class="nav-badge">' . $navFavCount . '</span>' : '') . '</a>'
         . '<div class="dropdown hide-mobile"><button class="user-chip" type="button" data-bs-toggle="dropdown" aria-expanded="false">'
         . '<span class="avatar">' . e($navInitial) . '</span><span class="name">' . e(excerpt($navName, 18)) . '</span><i class="bi bi-chevron-down small text-muted"></i></button>'
         . '<ul class="dropdown-menu dropdown-menu-end">'
         . '<li class="dropdown-user"><strong>' . e($navName) . '</strong><small>' . e($navEmail) . '</small></li>'
         . '<li><a class="dropdown-item" href="' . e(url('adopter/home.php')) . '"><i class="bi bi-grid-1x2"></i> Dashboard</a></li>'
         . '<li><a class="dropdown-item" href="' . e(url('adopter/requested.php')) . '"><i class="bi bi-clipboard2-heart"></i> My adoptions</a></li>'
         . '<li><a class="dropdown-item" href="' . e(url('adopter/home.php#favourites')) . '"><i class="bi bi-heart"></i> Saved pets</a></li>'
         . '<li><a class="dropdown-item' . (($activeNav ?? '') === 'about' ? ' active' : '') . '" href="' . e(url('adopter/aboutUs.php')) . '"><i class="bi bi-info-circle"></i> About us</a></li>'
         . '<li><hr class="dropdown-divider"></li>'
         . '<li><a class="dropdown-item text-danger" href="' . e(url('adopter/logout.php')) . '"><i class="bi bi-box-arrow-right text-danger"></i> Log out</a></li>'
         . '</ul></div>';
$headerMobile = '<div class="d-grid gap-2"><a class="d-flex align-items-center gap-2 fw-bold" href="' . e(url('adopter/home.php#favourites')) . '"><i class="bi bi-heart-fill text-accent"></i> Saved pets <span class="nav-badge">' . $navFavCount . '</span></a>'
              . '<a class="d-flex align-items-center gap-2 fw-bold" href="' . e(url('adopter/aboutUs.php')) . '"><i class="bi bi-info-circle text-brand"></i> About us</a></div>'
              . '<div class="mt-auto border-top pt-3 d-flex align-items-center gap-2"><span class="avatar">' . e($navInitial) . '</span>'
              . '<div class="min-w-0 flex-grow-1"><strong class="d-block text-truncate">' . e($navName) . '</strong><small class="text-muted text-truncate d-block">' . e($navEmail) . '</small></div>'
              . '<a class="btn btn-light btn-sm" href="' . e(url('adopter/logout.php')) . '">Log out</a></div>';

render_head(($pageTitle ?? 'Dashboard') . ' · ' . APP_NAME);
render_site_header($navItems, $activeNav ?? '', url('adopter/home.php'), $headerActions, $headerMobile);
render_flash();
?>
<main id="main">
