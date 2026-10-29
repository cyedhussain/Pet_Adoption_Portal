<?php
/*
 * Public layout. Set $pageTitle and $activeNav before including, then call
 * page_end() at the bottom of the page.
 */
require_once __DIR__ . '/dbconnect.php';
$LAYOUT = 'site';
// nav items: key => [href, label, icon, badge]
$navItems = [
    'home'       => [url('index.php'), 'Home', 'house-heart', 0],
    'pets'       => [url('pets.php'), 'Find a pet', 'search-heart', 0],
    'categories' => [url('categories.php'), 'Animals', 'grid', 0],
    'process'    => [url('aboutUs.php#process'), 'How it works', 'signpost-2', 0],
    'about'      => [url('aboutUs.php'), 'About us', 'people', 0],
    'contact'    => [url('contactUs.php'), 'Contact', 'chat-heart', 0],
];

if (current_role() === 'admin') {
    $headerActions = '<a class="btn btn-dark btn-sm hide-mobile" href="' . e(url('admin/home.php')) . '"><i class="bi bi-speedometer2"></i> Admin portal</a>';
    $headerMobile = '<a class="btn btn-dark w-100" href="' . e(url('admin/home.php')) . '"><i class="bi bi-speedometer2"></i> Admin portal</a>';
} elseif (current_role() === 'adopter') {
    $headerActions = '<a class="btn btn-primary btn-sm hide-mobile" href="' . e(url('adopter/home.php')) . '"><i class="bi bi-person-circle"></i> My dashboard</a>';
    $headerMobile = '<a class="btn btn-primary w-100" href="' . e(url('adopter/home.php')) . '"><i class="bi bi-person-circle"></i> My dashboard</a>';
} else {
    $headerActions = '<a class="btn btn-ghost hide-mobile" href="' . e(url('login.php')) . '">Log in</a>'
             . '<a class="btn btn-primary hide-mobile" href="' . e(url('signup.php')) . '"><i class="bi bi-heart-fill"></i> Adopt a pet</a>';
    $headerMobile = '<div class="d-grid gap-2 mt-auto"><a class="btn btn-primary btn-lg" href="' . e(url('signup.php')) . '"><i class="bi bi-heart-fill"></i> Become an adopter</a>'
                  . '<a class="btn btn-outline-primary btn-lg" href="' . e(url('login.php')) . '">Log in</a></div>';
}

render_head(($pageTitle ?? 'Home') . ' · ' . APP_NAME);
render_site_header($navItems, $activeNav ?? '', url('index.php'), $headerActions, $headerMobile);
render_flash();
?>
<main id="main">
