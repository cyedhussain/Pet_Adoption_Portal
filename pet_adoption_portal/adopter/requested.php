<?php
require_once __DIR__ . '/dbconnect.php';
$adopterId = require_adopter();

$adopter = db_one("SELECT * FROM `adopter` WHERE `adopter_id` = ?", [$adopterId]);
$applications = adopter_applications($adopterId, $adopter['email']);

// tab => [label, stages shown, empty-state text]
$tabs = [
    'all'      => ['All', ['pending', 'accepted', 'adopted', 'rejected', 'cancelled'], 'You haven’t applied to adopt a pet yet.'],
    'pending'  => ['Under review', ['pending'], 'No applications are waiting for review.'],
    'accepted' => ['Approved', ['accepted'], 'No approved applications waiting for pickup.'],
    'adopted'  => ['Adopted', ['adopted'], 'You haven’t completed an adoption yet.'],
    'closed'   => ['Not approved', ['rejected', 'cancelled'], 'Nothing here — none of your applications were declined or cancelled.'],
];
$tab = isset($tabs[query_param('tab')]) ? query_param('tab') : 'all';
$counts = [];
foreach ($tabs as $key => [, $stages]) {
    $counts[$key] = count(array_filter($applications, fn($a) => in_array($a['stage'], $stages, true)));
}
$shown = array_filter($applications, fn($a) => in_array($a['stage'], $tabs[$tab][1], true));
$chatUnread = chat_unread_count($adopterId);

$pageTitle = 'My adoptions';
$activeNav = 'requested';
include __DIR__ . '/header.php';
render_page_hero('My adoptions', 'Follow every application from request to pick-up. We’ll also email you whenever something changes.', '', 'Application tracker', [['Dashboard', url('adopter/home.php')]]);
?>
<section class="pb-5">
    <div class="container" style="max-width:1000px">
        <nav class="tabs-pill" aria-label="Filter applications">
            <?php foreach ($tabs as $key => [$label]): ?>
                <a class="<?= $tab === $key ? 'active' : '' ?>" href="<?= e(url('adopter/requested.php' . ($key === 'all' ? '' : '?tab=' . $key))) ?>"<?= $tab === $key ? ' aria-current="page"' : '' ?>><?= e($label) ?><span class="n"><?= $counts[$key] ?></span></a>
            <?php endforeach; ?>
        </nav>

        <?php if (!$shown): ?>
            <?php render_empty_state('bi-clipboard2-heart', $tab === 'all' ? 'No applications yet' : 'Nothing to show', $tabs[$tab][2], 'Find a pet', url('adopter/pets.php')); ?>
        <?php else: ?>
            <div class="app-list">
                <?php foreach ($shown as $app):
                    $actions = '<div class="d-flex flex-wrap gap-2">';
                    if ($app['pet_id'] && in_array($app['stage'], ['pending', 'accepted', 'adopted'], true)) {
                        $actions .= '<a class="btn btn-sm btn-outline-primary" href="' . e(url('adopter/petInfo.php?pindex=' . (int) $app['pet_id'])) . '"><i class="bi bi-eye"></i> View ' . e(excerpt($app['pet_name'], 16)) . '</a>';
                    }
                    if ($app['stage'] === 'pending') {
                        $actions .= '<form method="post" action="' . e(url('adopter/removeRequest.php')) . '" data-confirm="Cancel your application for ' . e($app['pet_name']) . '? The pet will become available to other adopters." data-confirm-title="Cancel application?" data-confirm-ok="Yes, cancel it">'
                                  . csrf_field() . '<input type="hidden" name="request_id" value="' . (int) $app['request_id'] . '">'
                                  . '<button class="btn btn-sm btn-soft-danger"><i class="bi bi-x-lg"></i> Cancel application</button></form>';
                    }
                    if (in_array($app['stage'], ['rejected', 'cancelled'], true)) {
                        $actions .= '<a class="btn btn-sm btn-outline-primary" href="' . e(url('adopter/pets.php')) . '"><i class="bi bi-search-heart"></i> Find another friend</a>';
                    }
                    if (in_array($app['stage'], ['accepted', 'adopted'], true)) {
                        $actions .= '<a class="btn btn-sm ' . ($app['stage'] === 'accepted' ? 'btn-primary' : 'btn-light') . '" href="' . e(url('adopter/contactUs.php#chat')) . '"><i class="bi bi-chat-heart"></i> Chat with the shelter'
                                  . ($chatUnread ? ' <span class="nav-badge">' . $chatUnread . '</span>' : '') . '</a>';
                    }
                    $actions .= '</div>';
                    render_application_card($app, $actions);
                endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php page_end(); ?>
