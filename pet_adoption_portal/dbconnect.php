<?php
/*
 * Shared bootstrap for every page: config, database, session, security and
 * the small view helpers the public, adopter and admin areas all use.
 * Always load it with require_once; admin/dbconnect.php and
 * adopter/dbconnect.php just require this file.
 */

// ---------------------------------------------------------------- config

define('APP_URL', '/pet_adoption_portal');
define('APP_NAME', 'Animal Matter To Me');
define('APP_ROOT', __DIR__);

$servername = "localhost";
$username = "root";
$password = "";
$database = "pet_adoption";

// Fill these in to send emails (Gmail needs an App Password). While they are
// empty, send_mail() skips sending instead of breaking the page.
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 465);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM', '');

define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024);

// Local time for the whole site. PHP and MySQL are both set to it below so that
// "2 min ago", chat times and "Today" agree no matter how XAMPP is configured.
define('APP_TIMEZONE', 'Asia/Karachi');
date_default_timezone_set(APP_TIMEZONE);

// ---------------------------------------------------------------- database

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $conn = mysqli_connect($servername, $username, $password, $database);
    mysqli_set_charset($conn, 'utf8mb4');
    mysqli_query($conn, "SET time_zone = '" . date('P') . "'");
} catch (mysqli_sql_exception $ex) {
    die("Unable to connect to the database. Is MySQL running and is the `pet_adoption` database imported?");
}

/** Runs a prepared statement. Every value goes through a placeholder, never into the SQL string. */
function db_query($sql, array $params = [])
{
    global $conn;
    $stmt = mysqli_prepare($conn, $sql);
    if ($params) {
        $types = '';
        foreach ($params as $param) {
            $types .= is_int($param) ? 'i' : (is_float($param) ? 'd' : 's');
        }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    return $stmt;
}

function db_all($sql, array $params = [])
{
    $result = mysqli_stmt_get_result(db_query($sql, $params));
    return $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
}

function db_one($sql, array $params = [])
{
    $rows = db_all($sql, $params);
    return $rows[0] ?? null;
}

function db_value($sql, array $params = [])
{
    $row = db_one($sql, $params);
    return $row ? reset($row) : null;
}

/** INSERT / UPDATE / DELETE. Returns the number of affected rows. */
function db_run($sql, array $params = [])
{
    return mysqli_stmt_affected_rows(db_query($sql, $params));
}

function db_insert_id()
{
    global $conn;
    return mysqli_insert_id($conn);
}

function db_transaction(callable $work)
{
    global $conn;
    mysqli_begin_transaction($conn);
    try {
        $result = $work();
        mysqli_commit($conn);
        return $result;
    } catch (Throwable $ex) {
        mysqli_rollback($conn);
        throw $ex;
    }
}

// ---------------------------------------------------------------- request helpers

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function url($path = '')
{
    return APP_URL . '/' . ltrim($path, '/');
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect($path)
{
    header('Location: ' . (str_starts_with($path, '/') ? $path : url($path)));
    exit;
}

function redirect_back($fallback = 'index.php')
{
    $back = $_SERVER['HTTP_REFERER'] ?? '';
    if ($back !== '' && str_contains($back, APP_URL)) {
        header('Location: ' . $back);
        exit;
    }
    redirect($fallback);
}

function post($key, $default = '')
{
    return isset($_POST[$key]) && !is_array($_POST[$key]) ? trim($_POST[$key]) : $default;
}

function query_param($key, $default = '')
{
    return isset($_GET[$key]) && !is_array($_GET[$key]) ? trim($_GET[$key]) : $default;
}

function query_int($key)
{
    return (int) query_param($key, '0');
}

function is_post()
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/** Current URL with some query params replaced (null removes one). */
function current_url_with(array $changes)
{
    $params = array_merge($_GET, $changes);
    $params = array_filter($params, fn($v) => $v !== null && $v !== '');
    $path = strtok($_SERVER['REQUEST_URI'], '?');
    return $path . ($params ? '?' . http_build_query($params) : '');
}

// ---------------------------------------------------------------- flash messages

function flash($message, $type = 'success')
{
    $_SESSION['flash'][] = [$type, $message];
}

function render_flash()
{
    if (empty($_SESSION['flash'])) {
        return;
    }
    $icons = ['success' => 'check-lg', 'danger' => 'exclamation-octagon', 'warning' => 'exclamation-triangle', 'info' => 'info-lg'];
    echo '<div class="toast-stack" aria-live="polite">';
    foreach ($_SESSION['flash'] as [$type, $message]) {
        $type = isset($icons[$type]) ? $type : 'info';
        echo '<div class="toast-x toast-' . $type . '" role="' . ($type === 'danger' ? 'alert' : 'status') . '">'
           . '<span class="toast-icon"><i class="bi bi-' . $icons[$type] . '"></i></span>'
           . '<div class="toast-body">' . e($message) . '</div>'
           . '<button type="button" class="btn-close" data-toast-close aria-label="Dismiss"></button></div>';
    }
    echo '</div>';
    unset($_SESSION['flash']);
}

// ---------------------------------------------------------------- CSRF (checked automatically on every POST)

function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

if (is_post() && !hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
    flash('Your session expired. Please try again.', 'warning');
    redirect_back($_SERVER['REQUEST_URI']);
}

// ---------------------------------------------------------------- auth

function login_user($role, $id, $name)
{
    session_regenerate_id(true);
    $_SESSION['role'] = $role;
    $_SESSION['user_id'] = (int) $id;
    $_SESSION['user_name'] = $name;
}

function logout_user()
{
    $_SESSION = [];
    session_destroy();
    session_start();
}

function current_role()
{
    return $_SESSION['role'] ?? null;
}

function current_user_id()
{
    return (int) ($_SESSION['user_id'] ?? 0);
}

function current_user_name()
{
    return $_SESSION['user_name'] ?? '';
}

function require_role($role)
{
    if (current_role() !== $role) {
        flash($role === 'admin' ? 'Please log in as an administrator.' : 'Please log in to continue.', 'warning');
        redirect('login.php');
    }
    return current_user_id();
}

function require_admin()
{
    return require_role('admin');
}

function require_adopter()
{
    return require_role('adopter');
}

/** Sends a logged-in user to their own home instead of the login / signup page. */
function redirect_if_logged_in()
{
    if (current_role() === 'admin') {
        redirect('admin/home.php');
    }
    if (current_role() === 'adopter') {
        redirect('adopter/home.php');
    }
}

function hash_password($plain)
{
    return password_hash($plain, PASSWORD_DEFAULT);
}

/**
 * Checks a password against the stored value. Accounts created before hashing
 * was added still hold plain text: those log in once and are upgraded to a hash.
 */
function verify_password($plain, $stored, $table, $idColumn, $id)
{
    $isHash = password_get_info($stored)['algo'] !== null;
    $ok = $isHash ? password_verify($plain, $stored) : hash_equals($stored, $plain);
    if ($ok && (!$isHash || password_needs_rehash($stored, PASSWORD_DEFAULT))) {
        $tables = ['admin' => 'admin_id', 'adopter' => 'adopter_id'];
        if (($tables[$table] ?? null) === $idColumn) {
            db_run("UPDATE `$table` SET `password` = ? WHERE `$idColumn` = ?", [hash_password($plain), (int) $id]);
        }
    }
    return $ok;
}

// ---------------------------------------------------------------- mail

function mail_is_configured()
{
    return SMTP_USER !== '' && SMTP_PASS !== '';
}

/** Branded HTML email body. $body may contain HTML; escape user values before passing them in. */
function mail_template($heading, $body, $buttonText = '', $buttonUrl = '')
{
    $button = $buttonText !== ''
        ? '<p style="margin:28px 0"><a href="' . e($buttonUrl) . '" style="background:#e07a1f;color:#fff;padding:12px 22px;border-radius:999px;text-decoration:none;font-weight:700">' . e($buttonText) . '</a></p>'
        : '';
    return '<div style="font-family:Arial,sans-serif;background:#fbf6ee;padding:32px">'
         . '<div style="max-width:560px;margin:auto;background:#fff;border-radius:16px;padding:32px;color:#2b2118">'
         . '<p style="color:#e07a1f;font-weight:700;letter-spacing:.08em;font-size:12px;margin:0 0 8px">' . e(strtoupper(APP_NAME)) . '</p>'
         . '<h2 style="margin:0 0 16px">' . e($heading) . '</h2>'
         . '<div style="line-height:1.6;color:#5b4a3c">' . $body . '</div>' . $button
         . '<p style="font-size:12px;color:#9a8878;margin-top:32px">You are receiving this email because of your account at ' . e(APP_NAME) . '.</p>'
         . '</div></div>';
}

/** Returns true when the mail was sent. Never throws: a mail problem must not break the page. */
function send_mail($to, $subject, $html)
{
    if (!mail_is_configured()) {
        error_log("[mail skipped, SMTP not configured] to=$to subject=$subject");
        return false;
    }
    require_once APP_ROOT . '/phpmailer/src/Exception.php';
    require_once APP_ROOT . '/phpmailer/src/PHPMailer.php';
    require_once APP_ROOT . '/phpmailer/src/SMTP.php';
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = 'ssl';
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(SMTP_FROM !== '' ? SMTP_FROM : SMTP_USER, APP_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $html;
        $mail->AltBody = trim(strip_tags(str_replace(['<br>', '</p>'], "\n", $html)));
        $mail->send();
        return true;
    } catch (Throwable $ex) {
        error_log('[mail failed] ' . $ex->getMessage());
        return false;
    }
}

/** Absolute link for emails, e.g. http://localhost/pet_adoption_portal/resetPassword.php?... */
function absolute_url($path)
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url($path);
}

// ---------------------------------------------------------------- uploads

/**
 * Validates and stores an uploaded image in img/<folder>/ under a random name.
 * Returns ['file' => saved name or null, 'error' => message or null].
 * With $required = false an empty file input is fine (edit forms keep the old image).
 */
function upload_image($field, $folder, $required = true)
{
    $upload = $_FILES[$field] ?? null;
    if (!$upload || $upload['error'] === UPLOAD_ERR_NO_FILE) {
        return ['file' => null, 'error' => $required ? 'Please choose an image.' : null];
    }
    if ($upload['error'] !== UPLOAD_ERR_OK) {
        return ['file' => null, 'error' => 'The image could not be uploaded. Please try again.'];
    }
    if ($upload['size'] > MAX_UPLOAD_BYTES) {
        return ['file' => null, 'error' => 'The image is too large. The limit is 5 MB.'];
    }
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'];
    $info = @getimagesize($upload['tmp_name']);
    if (!$info || !isset($types[$info[2]])) {
        return ['file' => null, 'error' => 'Please upload a JPG, PNG, WEBP or GIF image.'];
    }
    $dir = APP_ROOT . '/img/' . $folder;
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $name = bin2hex(random_bytes(8)) . '.' . $types[$info[2]];
    if (!move_uploaded_file($upload['tmp_name'], $dir . '/' . $name)) {
        return ['file' => null, 'error' => 'The image could not be saved. Check that the img folder is writable.'];
    }
    return ['file' => $name, 'error' => null];
}

/** True while a pet or an adoption record still points at this pet image. */
function pet_image_in_use($file)
{
    return (int) db_value("SELECT COUNT(*) FROM `pets` WHERE `image` = ?", [$file])
         + (int) db_value("SELECT COUNT(*) FROM `adopted` WHERE `image` = ?", [$file]) > 0;
}

/** Deletes an old upload, only if it lives inside img/<folder>/ and nothing else uses it. */
function delete_image($folder, $file)
{
    $path = APP_ROOT . '/img/' . $folder . '/' . basename((string) $file);
    if ($file !== '' && is_file($path)) {
        @unlink($path);
    }
}

// ---------------------------------------------------------------- pets

const PET_GENDERS = ['Male', 'Female'];
const PET_SIZES = ['Small', 'Medium', 'Large'];
// age filter => [min years, max years, label]
const PET_AGE_GROUPS = [
    'baby'   => [0, 0, 'Baby (under 1)'],
    'young'  => [1, 2, 'Young (1–2 yrs)'],
    'adult'  => [3, 7, 'Adult (3–7 yrs)'],
    'senior' => [8, 99, 'Senior (8+ yrs)'],
];

function pet_image_url($file)
{
    return url('img/pets/' . rawurlencode((string) $file));
}

function category_image_url($file)
{
    return url('img/category/' . rawurlencode((string) $file));
}

function pet_age_label($age)
{
    if ($age === null || $age === '') {
        return 'Age unknown';
    }
    $age = (int) $age;
    return $age === 0 ? 'Under 1 year' : $age . ($age === 1 ? ' year' : ' years');
}

function excerpt($text, $length = 110)
{
    $text = trim((string) $text);
    return mb_strlen($text) > $length ? rtrim(mb_substr($text, 0, $length)) . '…' : $text;
}

function all_categories()
{
    return db_all("SELECT * FROM `categories` ORDER BY `name`");
}

function all_locations()
{
    return db_all("SELECT * FROM `locations` ORDER BY `city`");
}

/** Reads the search / filter values from the query string. */
function pet_filters_from_request()
{
    return [
        'q'          => query_param('q'),
        'category'   => query_int('category'),
        'location'   => query_int('location'),
        'gender'     => in_array(query_param('gender'), PET_GENDERS, true) ? query_param('gender') : '',
        'size'       => in_array(query_param('size'), PET_SIZES, true) ? query_param('size') : '',
        'age'        => isset(PET_AGE_GROUPS[query_param('age')]) ? query_param('age') : '',
        'vaccinated' => query_param('vaccinated') === '1' ? '1' : '',
        'sort'       => in_array(query_param('sort'), ['newest', 'name', 'youngest', 'oldest'], true) ? query_param('sort') : 'newest',
    ];
}

/** Pets that are still available (not requested), with category and branch details. */
function find_pets(array $f = [], $limit = 0)
{
    $where = ['p.`requested` = 0'];
    $params = [];
    if (($f['q'] ?? '') !== '') {
        $where[] = '(p.`name` LIKE ? OR p.`breed` LIKE ? OR c.`name` LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($params, $like, $like, $like);
    }
    if (!empty($f['category'])) {
        $where[] = 'p.`category_id` = ?';
        $params[] = (int) $f['category'];
    }
    if (!empty($f['location'])) {
        $where[] = 'p.`location_id` = ?';
        $params[] = (int) $f['location'];
    }
    if (($f['gender'] ?? '') !== '') {
        $where[] = 'p.`gender` = ?';
        $params[] = $f['gender'];
    }
    if (($f['size'] ?? '') !== '') {
        $where[] = 'p.`size` = ?';
        $params[] = $f['size'];
    }
    if (($f['age'] ?? '') !== '') {
        [$min, $max] = PET_AGE_GROUPS[$f['age']];
        $where[] = 'p.`age` BETWEEN ? AND ?';
        array_push($params, $min, $max);
    }
    if (($f['vaccinated'] ?? '') === '1') {
        $where[] = 'p.`vaccinated` = 1';
    }
    if (!empty($f['exclude'])) {
        $where[] = 'p.`pet_id` <> ?';
        $params[] = (int) $f['exclude'];
    }
    $order = [
        'newest'   => 'p.`created_at` DESC, p.`pet_id` DESC',
        'name'     => 'p.`name` ASC',
        'youngest' => 'p.`age` IS NULL, p.`age` ASC',
        'oldest'   => 'p.`age` DESC',
    ][$f['sort'] ?? 'newest'] ?? 'p.`pet_id` DESC';

    $sql = "SELECT p.*, c.`name` AS category_name, l.`city`, l.`address` AS location_address,
                   l.`email` AS location_email, l.`contact` AS location_contact,
                   (SELECT COUNT(*) FROM `favourites` fv WHERE fv.`pet_id` = p.`pet_id`) AS saved_count
            FROM `pets` p
            LEFT JOIN `categories` c ON c.`category_id` = p.`category_id`
            LEFT JOIN `locations` l ON l.`location_id` = p.`location_id`
            WHERE " . implode(' AND ', $where) . " ORDER BY $order";
    if ($limit > 0) {
        $sql .= ' LIMIT ' . (int) $limit;
    }
    return db_all($sql, $params);
}

/** One pet with its category and branch, whether or not it is still available. */
function find_pet($petId)
{
    return db_one(
        "SELECT p.*, c.`name` AS category_name, l.`city`, l.`address` AS location_address,
                l.`email` AS location_email, l.`contact` AS location_contact,
                (SELECT COUNT(*) FROM `favourites` fv WHERE fv.`pet_id` = p.`pet_id`) AS saved_count
         FROM `pets` p
         LEFT JOIN `categories` c ON c.`category_id` = p.`category_id`
         LEFT JOIN `locations` l ON l.`location_id` = p.`location_id`
         WHERE p.`pet_id` = ?",
        [(int) $petId]
    );
}

function pet_badges($pet)
{
    $badges = [];
    if (!empty($pet['gender'])) {
        $badges[] = '<span class="chip"><i class="bi bi-gender-' . ($pet['gender'] === 'Female' ? 'female' : 'male') . '"></i>' . e($pet['gender']) . '</span>';
    }
    $badges[] = '<span class="chip"><i class="bi bi-cake2"></i>' . e(pet_age_label($pet['age'] ?? null)) . '</span>';
    if (!empty($pet['size'])) {
        $badges[] = '<span class="chip"><i class="bi bi-arrows-angle-expand"></i>' . e($pet['size']) . '</span>';
    }
    if (!empty($pet['vaccinated'])) {
        $badges[] = '<span class="chip chip-success"><i class="bi bi-shield-check"></i>Vaccinated</span>';
    }
    return implode('', $badges);
}

/** Whole days since $datetime (0 = today). */
function days_since($datetime)
{
    if (!$datetime) {
        return 0;
    }
    return max(0, (int) floor((strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', strtotime($datetime)))) / 86400));
}

function plural($n, $word, $words = null)
{
    return $n . ' ' . ($n === 1 ? $word : ($words ?? $word . 's'));
}

/** True for pets listed in the last 7 days. */
function pet_is_new(array $pet)
{
    return !empty($pet['created_at']) && days_since($pet['created_at']) < 7;
}

/** "Listed today" / "3 days in care" — how long a pet has been waiting with us. */
function pet_care_label(array $pet)
{
    $days = days_since($pet['created_at'] ?? null);
    return $days === 0 ? 'Listed today' : plural($days, 'day') . ' in care';
}

/** Singular, lower-case animal word for a category name: "Dogs" => "dog". */
function animal_word($categoryName)
{
    $word = strtolower(trim((string) $categoryName));
    return $word === '' ? 'pet' : (str_ends_with($word, 's') ? substr($word, 0, -1) : $word);
}

/**
 * Live numbers per animal type, keyed by category_id: available, adopted (all time),
 * min_age / max_age and males / females among available pets, and branches holding them.
 */
function category_stats()
{
    $rows = db_all(
        "SELECT c.`category_id`, c.`name`,
                SUM(p.`pet_id` IS NOT NULL) AS available,
                MIN(p.`age`) AS min_age, MAX(p.`age`) AS max_age,
                SUM(p.`gender` = 'Male') AS males, SUM(p.`gender` = 'Female') AS females,
                COUNT(DISTINCT p.`location_id`) AS branches,
                (SELECT COUNT(*) FROM `adopted` a WHERE a.`pet_category` = c.`name`) AS adopted
         FROM `categories` c
         LEFT JOIN `pets` p ON p.`category_id` = c.`category_id` AND p.`requested` = 0
         GROUP BY c.`category_id`, c.`name`"
    );
    $stats = [];
    foreach ($rows as $r) {
        $stats[(int) $r['category_id']] = array_map(fn($v) => $v === null ? null : (int) $v, array_diff_key($r, ['name' => 1]));
    }
    return $stats;
}

/** "Ages 1–4 yrs" style range for category stats, or '' when no ages are known. */
function age_range_label($min, $max)
{
    if ($min === null || $max === null) {
        return '';
    }
    $fmt = fn($a) => $a === 0 ? 'under 1' : (string) $a;
    return $min === $max ? 'Age ' . $fmt($min) . ($min === 0 ? ' yr' : ' yrs') : 'Ages ' . $fmt($min) . '–' . $max . ' yrs';
}

/** Short facts line for an animal tile: adopted count, age range, gender split. */
function category_tile_facts(?array $st)
{
    if (!$st) {
        return '';
    }
    $bits = [];
    if ($st['available']) {
        $range = age_range_label($st['min_age'], $st['max_age']);
        if ($range !== '') {
            $bits[] = '<span><i class="bi bi-cake2"></i>' . e($range) . '</span>';
        }
        if ($st['males'] || $st['females']) {
            $bits[] = '<span><i class="bi bi-gender-ambiguous"></i>' . (int) $st['males'] . ' ♂ · ' . (int) $st['females'] . ' ♀</span>';
        }
    }
    $bits[] = '<span><i class="bi bi-house-heart"></i>' . (int) $st['adopted'] . ' adopted</span>';
    return '<div class="cat-facts">' . implode('', $bits) . '</div>';
}

/** Stats strip under a category hero: available, adopted, ages, genders, branches. */
function render_category_stats(?array $st, $categoryName)
{
    if (!$st) {
        return;
    }
    $word = strtolower($categoryName);
    $items = [
        ['heart-fill', 'tone-accent', (string) $st['available'], 'available now'],
        ['house-heart', 'tone-success', (string) $st['adopted'], 'adopted all time'],
        ['cake2', 'tone-sun', $st['available'] ? (age_range_label($st['min_age'], $st['max_age']) ?: 'Unknown') : '—', 'age range'],
        ['gender-ambiguous', 'tone-info', $st['available'] ? (int) $st['males'] . ' ♂ · ' . (int) $st['females'] . ' ♀' : '—', 'male · female'],
        ['geo-alt', 'tone-brand', (string) $st['branches'], $st['branches'] === 1 ? 'branch has ' . e($word) : 'branches have ' . e($word)],
    ];
    echo '<div class="cat-stats" data-animate>';
    foreach ($items as [$icon, $tone, $value, $label]) {
        echo '<div><span class="icon ' . $tone . '"><i class="bi bi-' . $icon . '"></i></span><div class="min-w-0"><strong>' . e($value) . '</strong><small>' . $label . '</small></div></div>';
    }
    echo '</div>';
}

/** onerror hook for <img>: swaps a missing photo for a soft placeholder (imgFallback() is defined in render_head()). */
function img_fallback()
{
    return ' onerror="imgFallback(this)"';
}

function gender_dot($gender)
{
    if ($gender !== 'Male' && $gender !== 'Female') {
        return '';
    }
    $female = $gender === 'Female';
    return '<span class="gender-dot ' . ($female ? 'gender-female' : 'gender-male') . '" title="' . $gender . '">'
         . '<i class="bi bi-gender-' . ($female ? 'female' : 'male') . '" aria-hidden="true"></i><span class="visually-hidden">' . $gender . '</span></span>';
}

// ---------------------------------------------------------------- pagination

/** Slices $rows for the ?page= in the URL. Returns [rows on this page, page, page count, total]. */
function paginate(array $rows, $perPage = 12)
{
    $total = count($rows);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($pages, max(1, query_int('page')));
    return [array_slice($rows, ($page - 1) * $perPage, $perPage), $page, $pages, $total];
}

function render_pagination($page, $pages, $total, $perPage, $noun = 'results')
{
    if ($total === 0) {
        return;
    }
    $from = ($page - 1) * $perPage + 1;
    $to = min($total, $page * $perPage);
    echo '<nav class="pager" aria-label="Pagination"><span class="pager-info">Showing ' . $from . '–' . $to . ' of ' . $total . ' ' . e($noun) . '</span>';
    if ($pages > 1) {
        $link = fn($n) => e(current_url_with(['page' => $n > 1 ? $n : null]));
        echo '<div class="pager-links">';
        echo $page > 1
            ? '<a href="' . $link($page - 1) . '" aria-label="Previous page"><i class="bi bi-chevron-left"></i></a>'
            : '<span class="is-disabled" aria-hidden="true"><i class="bi bi-chevron-left"></i></span>';
        $last = 0;
        for ($n = 1; $n <= $pages; $n++) {
            if ($n !== 1 && $n !== $pages && abs($n - $page) > 1) {
                continue;
            }
            if ($last && $n - $last > 1) {
                echo '<span class="gap" aria-hidden="true">…</span>';
            }
            echo $n === $page
                ? '<span class="is-current" aria-current="page">' . $n . '</span>'
                : '<a href="' . $link($n) . '">' . $n . '</a>';
            $last = $n;
        }
        echo $page < $pages
            ? '<a href="' . $link($page + 1) . '" aria-label="Next page"><i class="bi bi-chevron-right"></i></a>'
            : '<span class="is-disabled" aria-hidden="true"><i class="bi bi-chevron-right"></i></span>';
        echo '</div>';
    }
    echo '</nav>';
}

// ---------------------------------------------------------------- pet browsing

const PET_SORTS = ['newest' => 'Newest first', 'name' => 'Name A–Z', 'youngest' => 'Youngest first', 'oldest' => 'Oldest first'];

/** Radio pills for one filter. $options is value => label; '' means "any". */
function filter_pills($name, array $options, $current, $form = '')
{
    $html = '<div class="choice-pills" role="radiogroup">';
    foreach ($options as $value => $label) {
        $id = 'f-' . $name . '-' . ($value === '' ? 'any' : preg_replace('/\W/', '', (string) $value));
        $html .= '<input type="radio" name="' . e($name) . '" id="' . e($id) . '" value="' . e($value) . '"'
               . ((string) $current === (string) $value ? ' checked' : '') . ($form ? ' form="' . e($form) . '"' : '') . '>'
               . '<label for="' . e($id) . '">' . e($label) . '</label>';
    }
    return $html . '</div>';
}

/**
 * Filter sidebar (an off-canvas drawer below 992px). Every input belongs to the
 * GET form #petFilters, which the search box and sort menu in the results bar also use.
 * $action is the page the form submits to, $hidden carries params that must survive
 * (e.g. the category on a category page).
 */
function render_pet_filters(array $f, $action, array $hidden = [], $showCategory = true)
{
    $resetUrl = $action . ($hidden ? '?' . http_build_query($hidden) : '');
    echo '<aside class="filter-panel offcanvas-lg offcanvas-start" id="filterPanel" tabindex="-1" aria-labelledby="filterPanelLabel">'
       . '<div class="offcanvas-header border-bottom d-lg-none"><h2 class="h5 mb-0" id="filterPanelLabel">Filters</h2>'
       . '<button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#filterPanel" aria-label="Close filters"></button></div>'
       . '<div class="offcanvas-body d-block p-3 p-lg-0">'
       . '<form id="petFilters" class="filter-card" method="get" action="' . e($action) . '" data-autosubmit>';
    foreach ($hidden as $key => $value) {
        echo '<input type="hidden" name="' . e($key) . '" value="' . e($value) . '">';
    }
    echo '<div class="filter-card-head d-none d-lg-flex"><h2><i class="bi bi-sliders2 text-brand"></i> Filters</h2>'
       . '<a class="small fw-bold" href="' . e($resetUrl) . '">Reset</a></div>';
    if ($showCategory) {
        $animals = ['' => 'All'];
        foreach (all_categories() as $c) {
            $animals[(int) $c['category_id']] = $c['name'];
        }
        echo '<div class="filter-group"><span class="filter-group-title">Animal</span>' . filter_pills('category', $animals, $f['category'] ?: '') . '</div>';
    }
    echo '<div class="filter-group"><label class="filter-group-title" for="f-location">Branch</label><select class="form-select" id="f-location" name="location"><option value="">Any branch</option>';
    foreach (all_locations() as $l) {
        echo '<option value="' . (int) $l['location_id'] . '"' . ((int) $f['location'] === (int) $l['location_id'] ? ' selected' : '') . '>' . e($l['city']) . '</option>';
    }
    echo '</select></div>';
    $ages = ['' => 'Any'];
    foreach (PET_AGE_GROUPS as $key => $group) {
        $ages[$key] = $group[2];
    }
    echo '<div class="filter-group"><span class="filter-group-title">Age</span>' . filter_pills('age', $ages, $f['age']) . '</div>';
    echo '<div class="filter-group"><span class="filter-group-title">Gender</span>' . filter_pills('gender', ['' => 'Any'] + array_combine(PET_GENDERS, PET_GENDERS), $f['gender']) . '</div>';
    echo '<div class="filter-group"><span class="filter-group-title">Size</span>' . filter_pills('size', ['' => 'Any'] + array_combine(PET_SIZES, PET_SIZES), $f['size']) . '</div>';
    echo '<div class="filter-group"><label class="filter-switch form-switch" for="f-vaccinated"><span><i class="bi bi-shield-check text-brand"></i> Vaccinated only</span>'
       . '<input class="form-check-input" type="checkbox" role="switch" id="f-vaccinated" name="vaccinated" value="1"' . ($f['vaccinated'] === '1' ? ' checked' : '') . '></label></div>';
    echo '<div class="filter-actions"><a class="btn btn-light" href="' . e($resetUrl) . '">Reset</a><button class="btn btn-primary" type="submit">Show pets</button></div>';
    echo '</form></div></aside>';
}

/** Removable chips for the filters that are switched on. */
function active_filter_chips(array $f, $showCategory = true)
{
    $chips = [];
    $remove = fn($key) => e(current_url_with([$key => null, 'page' => null]));
    if ($f['q'] !== '') {
        $chips[] = ['q', '“' . $f['q'] . '”'];
    }
    if ($showCategory && $f['category']) {
        foreach (all_categories() as $c) {
            if ((int) $c['category_id'] === (int) $f['category']) {
                $chips[] = ['category', $c['name']];
            }
        }
    }
    if ($f['location']) {
        foreach (all_locations() as $l) {
            if ((int) $l['location_id'] === (int) $f['location']) {
                $chips[] = ['location', $l['city']];
            }
        }
    }
    if ($f['age'] !== '') {
        $chips[] = ['age', PET_AGE_GROUPS[$f['age']][2]];
    }
    if ($f['gender'] !== '') {
        $chips[] = ['gender', $f['gender']];
    }
    if ($f['size'] !== '') {
        $chips[] = ['size', $f['size']];
    }
    if ($f['vaccinated'] === '1') {
        $chips[] = ['vaccinated', 'Vaccinated'];
    }
    $html = '';
    foreach ($chips as [$key, $label]) {
        $html .= '<a class="filter-chip" href="' . $remove($key) . '" aria-label="Remove filter ' . e($label) . '">' . e($label) . '<i class="bi bi-x-lg"></i></a>';
    }
    return [$html, count($chips)];
}

/**
 * The whole "browse pets" experience: filter sidebar, search + sort bar, result count,
 * active filter chips, a paginated card grid and an empty state.
 * Options: hidden, showCategory, detailUrl (callable pet => url), favouriteIds, emptyText, metaLink (HTML), noun.
 */
function render_pet_browser(array $pets, array $f, $action, array $options = [])
{
    $hidden = $options['hidden'] ?? [];
    $showCategory = $options['showCategory'] ?? true;
    $noun = $options['noun'] ?? 'pets';
    [$chipsHtml, $chipCount] = active_filter_chips($f, $showCategory);
    [$pagePets, $page, $pages, $total] = paginate($pets, 12);

    echo '<div class="browse-layout">';
    render_pet_filters($f, $action, $hidden, $showCategory);
    echo '<div class="browse-main min-w-0">';
    echo '<div class="results-bar">'
       . '<div class="results-search"><i class="bi bi-search" aria-hidden="true"></i>'
       . '<label class="visually-hidden" for="petSearch">Search pets</label>'
       . '<input class="form-control" type="search" id="petSearch" name="q" form="petFilters" value="' . e($f['q']) . '" placeholder="Search by name, breed or animal…"></div>'
       . '<div class="results-sort"><label class="visually-hidden" for="petSort">Sort by</label><select class="form-select" id="petSort" name="sort" form="petFilters" onchange="this.form.submit()">';
    foreach (PET_SORTS as $key => $label) {
        echo '<option value="' . $key . '"' . ($f['sort'] === $key ? ' selected' : '') . '>' . $label . '</option>';
    }
    echo '</select><button class="btn btn-outline-primary filter-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#filterPanel" aria-controls="filterPanel">'
       . '<i class="bi bi-sliders2"></i> Filters' . ($chipCount ? ' <span class="nav-badge">' . $chipCount . '</span>' : '') . '</button></div></div>';
    echo '<div class="results-meta"><div class="d-flex flex-wrap align-items-center gap-2"><span><strong>' . $total . '</strong> '
       . e($total === 1 ? rtrim($noun, 's') : $noun) . ' waiting for a home</span>' . $chipsHtml . '</div>'
       . ($options['metaLink'] ?? '') . '</div>';
    if (!$pets) {
        $hasFilters = $chipCount > 0;
        render_empty_state('bi-search-heart', $hasFilters ? 'No pets match those filters' : 'Nothing here… yet',
            $options['emptyText'] ?? 'Try removing a filter or searching for something else.',
            $hasFilters ? 'Clear all filters' : '', $action . ($hidden ? '?' . http_build_query($hidden) : ''));
    } else {
        render_pet_grid($pagePets, $options['detailUrl'], $options['favouriteIds'] ?? null);
        render_pagination($page, $pages, $total, 12, $noun);
    }
    echo '</div></div>';
}

/**
 * One pet card. $detailUrl is where the card links. Pass $isFavourite (true/false)
 * on adopter pages to show the heart button; leave it null on public pages.
 */
function render_pet_card(array $pet, $detailUrl, $isFavourite = null)
{
    $requested = (int) ($pet['requested'] ?? 0) !== 0;
    $meta = array_filter([$pet['breed'] ?? '', pet_age_label($pet['age'] ?? null)]);
    echo '<article class="pet-card">';
    echo '<div class="pet-card-media">'
       . '<img src="' . e(pet_image_url($pet['image'])) . '" alt="' . e($pet['name']) . (!empty($pet['breed']) ? ', ' . e($pet['breed']) : '') . '" loading="lazy" decoding="async"' . img_fallback() . '>'
       . '<div class="pet-card-badges">'
       . (!empty($pet['category_name']) ? '<span class="pet-badge">' . e($pet['category_name']) . '</span>' : '')
       . (pet_is_new($pet) && !$requested ? '<span class="pet-badge pet-badge-new"><i class="bi bi-stars"></i>New</span>' : '')
       . ($requested ? '<span class="pet-badge pet-badge-warn"><i class="bi bi-hourglass-split"></i>Application pending</span>' : '')
       . '</div>'
       . (!empty($pet['city']) ? '<span class="pet-card-city"><i class="bi bi-geo-alt-fill"></i>' . e($pet['city']) . '</span>' : '')
       . '</div>';
    if ($isFavourite !== null) {
        echo '<form method="post" class="fav-form">' . csrf_field()
           . '<input type="hidden" name="fav_toggle" value="' . (int) $pet['pet_id'] . '">'
           . '<button class="fav-btn' . ($isFavourite ? ' is-active' : '') . '" aria-label="' . ($isFavourite ? 'Remove ' : 'Save ') . e($pet['name']) . ($isFavourite ? ' from favourites' : ' to favourites') . '" aria-pressed="' . ($isFavourite ? 'true' : 'false') . '">'
           . '<i class="bi bi-heart' . ($isFavourite ? '-fill' : '') . '"></i></button></form>';
    }
    echo '<div class="pet-card-body">'
       . '<div class="pet-card-head"><h3 class="pet-card-title"><a href="' . e($detailUrl) . '">' . e($pet['name']) . '</a></h3>' . gender_dot($pet['gender'] ?? '') . '</div>'
       . '<p class="pet-card-meta">' . e(implode(' · ', $meta)) . '</p>'
       . '<div class="chips">'
       . (!empty($pet['size']) ? '<span class="chip"><i class="bi bi-arrows-angle-expand"></i>' . e($pet['size']) . '</span>' : '')
       . (!empty($pet['vaccinated']) ? '<span class="chip chip-success"><i class="bi bi-shield-check"></i>Vaccinated</span>' : '<span class="chip"><i class="bi bi-shield"></i>Vaccination due</span>')
       . (!empty($pet['saved_count']) ? '<span class="chip chip-love" title="Saved by ' . plural((int) $pet['saved_count'], 'adopter') . '"><i class="bi bi-heart-fill"></i>' . (int) $pet['saved_count'] . ' saved</span>' : '')
       . '</div>'
       . '<div class="pet-card-foot"><span class="small fw-semibold text-muted" title="' . ($requested ? 'Reserved for now' : 'Ready to adopt') . '"><i class="bi bi-clock-history"></i> ' . e(pet_care_label($pet)) . '</span>'
       . '<span class="pet-card-go" aria-hidden="true">Meet ' . e(excerpt($pet['name'], 14)) . ' <i class="bi bi-arrow-right"></i></span></div>'
       . '</div></article>';
}

/** Grid of cards, or a friendly empty state. $detailUrl is a callable(pet) => url. */
function render_pet_grid(array $pets, callable $detailUrl, ?array $favouriteIds = null, $emptyText = 'No pets match your search yet.')
{
    if (!$pets) {
        render_empty_state('bi-search-heart', 'Nothing here… yet', $emptyText);
        return;
    }
    echo '<div class="pet-grid">';
    foreach ($pets as $pet) {
        $fav = $favouriteIds === null ? null : in_array((int) $pet['pet_id'], $favouriteIds, true);
        render_pet_card($pet, $detailUrl($pet), $fav);
    }
    echo '</div>';
}

function render_empty_state($icon, $title, $text, $buttonText = '', $buttonUrl = '', $compact = false)
{
    echo '<div class="empty-state' . ($compact ? ' compact' : '') . '"><div class="empty-icon" aria-hidden="true"><i class="bi ' . e($icon) . '"></i></div>'
       . '<h3>' . e($title) . '</h3><p>' . e($text) . '</p>'
       . ($buttonText !== '' ? '<a class="btn btn-primary" href="' . e($buttonUrl) . '">' . e($buttonText) . '</a>' : '')
       . '</div>';
}

/**
 * Page header used at the top of most pages: title + intro on the left, a framed photo on the right.
 * $crumbs is a list of [label, url]; the page title is added as the last crumb. Pass $image = '' for a compact header.
 */
function render_page_hero($title, $subtitle = '', $image = 'img/4.jpg', $eyebrow = '', array $crumbs = [])
{
    echo '<section class="page-hero' . ($image === '' ? ' compact' : '') . '"><div class="container"><div class="page-hero-grid">';
    echo '<div class="reveal">';
    if ($crumbs) {
        echo '<nav class="breadcrumbs" aria-label="Breadcrumb">';
        foreach ($crumbs as [$label, $href]) {
            echo '<a href="' . e($href) . '">' . e($label) . '</a><i class="bi bi-chevron-right sep" aria-hidden="true"></i>';
        }
        echo '<span class="current" aria-current="page">' . e($title) . '</span></nav>';
    }
    echo ($eyebrow !== '' ? '<span class="eyebrow">' . e($eyebrow) . '</span>' : '')
       . '<h1 class="break-word">' . e($title) . '</h1>'
       . ($subtitle !== '' ? '<p class="lead-text">' . e($subtitle) . '</p>' : '')
       . '</div>';
    if ($image !== '') {
        $imageUrl = url(implode('/', array_map('rawurlencode', explode('/', $image))));
        echo '<div class="page-hero-media reveal reveal-2"><img src="' . e($imageUrl) . '" alt="" fetchpriority="high"' . img_fallback() . '></div>';
    }
    echo '</div></div></section>';
}

/**
 * Pet profile layout shared by the public and adopter pet pages: photo + story on the
 * left, a sticky adoption panel on the right. Options: crumbs ([label, url] list),
 * favHtml (heart button form), actionsHtml (the adoption call to action), minimal (true for an
 * adopted record, which has no age / size details).
 */
function render_pet_profile(array $pet, array $o = [])
{
    $requested = (int) ($pet['requested'] ?? 0) !== 0;
    $minimal = !empty($o['minimal']);
    echo '<nav class="breadcrumbs" aria-label="Breadcrumb">';
    foreach ($o['crumbs'] ?? [] as [$label, $href]) {
        echo '<a href="' . e($href) . '">' . e($label) . '</a><i class="bi bi-chevron-right sep" aria-hidden="true"></i>';
    }
    echo '<span class="current" aria-current="page">' . e($pet['name']) . '</span></nav>';

    echo '<div class="pet-layout"><div class="pet-media min-w-0">';
    echo '<div class="pet-gallery reveal"><img src="' . e(pet_image_url($pet['image'])) . '" alt="' . e($pet['name']) . '" fetchpriority="high"' . img_fallback() . '>'
       . '<div class="pet-card-badges">' . (!empty($pet['category_name']) ? '<span class="pet-badge">' . e($pet['category_name']) . '</span>' : '')
       . ($requested && !$minimal ? '<span class="pet-badge pet-badge-warn"><i class="bi bi-hourglass-split"></i>Application pending</span>' : '') . '</div>'
       . ($o['favHtml'] ?? '') . '</div></div><div class="pet-stories min-w-0">';
    echo '<div class="story-card" data-animate><span class="eyebrow">Personality</span><h2>Meet ' . e($pet['name']) . '</h2><p>' . nl2br(e($pet['intro1'])) . '</p></div>';
    echo '<div class="story-card" data-animate><span class="eyebrow">Health &amp; home</span><h2>Good to know</h2>';
    if (!$minimal) {
        echo '<ul class="health-list">'
           . '<li class="' . ($pet['vaccinated'] ? 'yes' : 'no') . '"><i class="bi bi-' . ($pet['vaccinated'] ? 'shield-check' : 'shield') . '"></i>' . ($pet['vaccinated'] ? 'Fully vaccinated' : 'Vaccination not complete yet') . '</li>'
           . '<li class="yes"><i class="bi bi-geo-alt"></i>Lives at our ' . e($pet['city'] ?: 'shelter') . ' branch</li>'
           . '</ul>';
    }
    echo '<p>' . ($pet['intro2'] !== '' ? nl2br(e($pet['intro2'])) : 'Ask our team about ' . e($pet['name']) . '’s routine, health history and the kind of home they would love.') . '</p></div>';
    if (!$minimal) {
        // Everything an adopter needs, taken from the signup, application and pickup rules.
        $branch = $pet['city'] ? 'our ' . e($pet['city']) . ' branch' : 'the branch';
        echo '<div class="story-card" data-animate><span class="eyebrow">Before you apply</span><h2>Adoption checklist</h2>'
           . '<ul class="check-list">'
           . '<li><i class="bi bi-person-check"></i><div><strong>A free adopter account</strong>Your name, email and a password — it takes a minute.</div></li>'
           . '<li><i class="bi bi-person-vcard"></i><div><strong>Your 13-digit CNIC</strong>Asked once when you sign up. One account per CNIC.</div></li>'
           . '<li><i class="bi bi-telephone"></i><div><strong>A phone number and home address</strong>Filled in on the short application form.</div></li>'
           . '<li><i class="bi bi-calendar-check"></i><div><strong>Time to collect ' . e($pet['name']) . '</strong>Pick up from ' . $branch . ' within 3 days of approval, and bring your CNIC.</div></li>'
           . '</ul></div>';
    }
    echo '</div>';

    echo '<aside class="pet-aside min-w-0"><div class="adopt-panel reveal reveal-2">';
    echo '<span class="eyebrow">' . e(trim(($pet['category_name'] ?: 'Pet') . ($pet['city'] ? ' · ' . $pet['city'] : ''))) . '</span>';
    echo '<div class="d-flex align-items-center gap-2"><h1>' . e($pet['name']) . '</h1>' . gender_dot($pet['gender'] ?? '') . '</div>';
    if (($pet['breed'] ?? '') !== '') {
        echo '<p class="breed">' . e($pet['breed']) . '</p>';
    }
    if (!$minimal) {
        $facts = [
            ['cake2', 'Age', pet_age_label($pet['age'])],
            ['gender-ambiguous', 'Gender', $pet['gender'] ?: 'Unknown'],
            ['arrows-angle-expand', 'Size', $pet['size'] ?: 'Unknown'],
            ['shield-check', 'Vaccinated', $pet['vaccinated'] ? 'Yes' : 'Not yet'],
        ];
        echo '<div class="fact-grid">';
        foreach ($facts as [$icon, $label, $value]) {
            echo '<div class="fact"><i class="bi bi-' . $icon . '"></i><div class="min-w-0"><span>' . $label . '</span><strong>' . e($value) . '</strong></div></div>';
        }
        echo '</div>';
        $saved = (int) ($pet['saved_count'] ?? 0);
        $everApplied = $requested || (int) db_value("SELECT COUNT(*) FROM `requests` WHERE `pet_id` = ?", [(int) $pet['pet_id']]) > 0;
        echo '<ul class="pet-status">'
           . '<li><i class="bi bi-clock-history"></i><span>' . (days_since($pet['created_at'] ?? null) === 0 ? 'Listed today' : 'With us for <strong>' . plural(days_since($pet['created_at']), 'day') . '</strong>')
           . (!empty($pet['created_at']) ? ' <small>since ' . e(format_date($pet['created_at'])) . '</small>' : '') . '</span></li>'
           . '<li><i class="bi bi-heart' . ($saved ? '-fill text-accent' : '') . '"></i><span>' . ($saved ? 'Saved by <strong>' . plural($saved, 'adopter') . '</strong>' : 'Not saved by anyone yet') . '</span></li>'
           . '<li><i class="bi bi-' . ($requested ? 'hourglass-split' : 'envelope-open-heart') . '"></i><span>'
           . ($requested ? '<strong>Application in progress</strong>' : ($everApplied ? '<strong>Open for applications</strong>' : '<strong>No applications yet</strong> — be the first')) . '</span></li>'
           . '</ul>';
    } else {
        echo '<div class="my-4"></div>';
    }
    echo $o['actionsHtml'] ?? '';
    if (!$minimal) {
        echo '<div class="mini-steps-head">What happens next</div><ol class="mini-steps">'
           . '<li><span class="n">1</span><div><strong>Apply online</strong>A short form — your name, email and CNIC come from your account. ' . e($pet['name']) . ' is reserved while we review it.</div></li>'
           . '<li><span class="n">2</span><div><strong>The shelter reviews it</strong>We email you with the decision, and you can follow it in My adoptions.</div></li>'
           . '<li><span class="n">3</span><div><strong>Approved? Pick up within 3 days</strong>Collect ' . e($pet['name']) . ' from the branch with your CNIC.</div></li>'
           . '<li><span class="n">4</span><div><strong>Adopted</strong>The shelter marks the adoption complete — welcome home!</div></li>'
           . '</ol>';
    }
    echo '</div>';
    if (!empty($pet['city'])) {
        echo '<div class="info-panel mt-3" data-animate><h3><i class="bi bi-geo-alt-fill text-accent"></i> Meet ' . e($pet['name']) . ' at our ' . e($pet['city']) . ' branch</h3>'
           . '<div class="info-row"><i class="bi bi-pin-map"></i><div><small>Address</small>' . e($pet['location_address']) . '</div></div>'
           . '<div class="info-row"><i class="bi bi-telephone"></i><div><small>Phone</small><a href="tel:' . e($pet['location_contact']) . '">' . e($pet['location_contact']) . '</a></div></div>'
           . '<div class="info-row"><i class="bi bi-envelope"></i><div><small>Email</small><a href="mailto:' . e($pet['location_email']) . '">' . e($pet['location_email']) . '</a></div></div>';
        if (!$minimal && !empty($pet['location_id'])) {
            $others = (int) db_value("SELECT COUNT(*) FROM `pets` WHERE `location_id` = ? AND `requested` = 0 AND `pet_id` <> ?",
                [(int) $pet['location_id'], (int) $pet['pet_id']]);
            $browse = url((current_role() === 'adopter' ? 'adopter/' : '') . 'pets.php?location=' . (int) $pet['location_id']);
            echo '<div class="branch-more">' . ($others
                ? '<span><strong>' . plural($others, 'other pet') . '</strong> also waiting at this branch</span><a class="btn btn-sm btn-outline-primary" href="' . e($browse) . '">Browse them <i class="bi bi-arrow-right"></i></a>'
                : '<span>' . e($pet['name']) . ' is the only pet available at this branch right now.</span>') . '</div>';
        }
        echo '</div>';
    }
    echo '</aside></div>';
}

/** Photo side of the login / signup / password screens, with a live "pets waiting" card. */
function render_auth_art($eyebrow, $title, $text, $image = 'img/6.jpg')
{
    $waiting = find_pets([], 3);
    $count = (int) db_value("SELECT COUNT(*) FROM `pets` WHERE `requested` = 0");
    echo '<div class="auth-art"><img src="' . e(url($image)) . '" alt="" fetchpriority="high">'
       . '<span class="eyebrow eyebrow-light">' . e($eyebrow) . '</span><h2>' . e($title) . '</h2><p>' . e($text) . '</p>';
    if ($waiting) {
        echo '<div class="auth-live"><div class="stack">';
        foreach ($waiting as $pet) {
            echo '<img src="' . e(pet_image_url($pet['image'])) . '" alt="' . e($pet['name']) . '"' . img_fallback() . '>';
        }
        echo '</div><div><strong>' . $count . ' ' . ($count === 1 ? 'pet is' : 'pets are') . ' waiting</strong><small>' . e(implode(', ', array_column($waiting, 'name'))) . ($count > 3 ? ' and more' : '') . '</small></div></div>';
    }
    echo '</div>';
}

/** The four adoption steps. $light = true for the white-card variant used on light backgrounds. */
function render_adoption_steps($light = false)
{
    $steps = [
        ['search-heart', 'Find your match', 'Browse by animal, branch, age or size and save the pets you love to your favourites.'],
        ['pencil-square', 'Apply online', 'Create a free account and send a short adoption application in a couple of minutes.'],
        ['patch-check', 'Get approved', 'Our team reviews every application and emails you. Follow each step in your dashboard.'],
        ['house-heart', 'Welcome home', 'Pick your new family member up from the branch within 3 days of approval.'],
    ];
    echo '<ol class="steps' . ($light ? ' steps-light' : '') . '">';
    foreach ($steps as $i => [$icon, $title, $text]) {
        echo '<li data-animate><span class="step-num" aria-hidden="true">0' . ($i + 1) . '</span><span class="step-icon" aria-hidden="true"><i class="bi bi-' . $icon . '"></i></span>'
           . '<h3>' . e($title) . '</h3><p>' . e($text) . '</p></li>';
    }
    echo '</ol>';
}

// ---------------------------------------------------------------- page chrome

/** <!doctype> through <body>. Every header.php starts with this. */
function render_head($title, $bodyClass = 'site')
{
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>' . e($title) . '</title>'
       . '<meta name="description" content="' . e(APP_NAME) . ' connects rescued cats, dogs and rabbits with loving homes. Browse pets, apply online and follow your adoption.">'
       . '<meta name="theme-color" content="#12614f">'
       . '<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22%3E%3Crect width=%22100%22 height=%22100%22 rx=%2228%22 fill=%22%2312614f%22/%3E%3Ctext x=%2250%22 y=%2268%22 font-size=%2252%22 text-anchor=%22middle%22%3E%F0%9F%90%BE%3C/text%3E%3C/svg%3E">'
       . '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">'
       . '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">'
       . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
       . '<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500..800&family=Figtree:wght@400..800&display=swap" rel="stylesheet">'
       // ?v= changes whenever style.css does, so browsers never keep an old copy after a deploy
       . '<link href="' . e(url('assets/style.css?v=' . filemtime(APP_ROOT . '/assets/style.css'))) . '" rel="stylesheet">'
       . '<script>document.documentElement.classList.add("js");'
       . 'function imgFallback(i){i.onerror=null;i.classList.add("img-missing");i.alt=i.alt||"Photo coming soon";'
       . 'i.src="data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 120 120\'%3E%3Crect width=\'120\' height=\'120\' fill=\'%23eff8f4\'/%3E%3Cg fill=\'%23b9e2d4\'%3E%3Cellipse cx=\'60\' cy=\'74\' rx=\'17\' ry=\'14\'/%3E%3Cellipse cx=\'40\' cy=\'54\' rx=\'7\' ry=\'9\'/%3E%3Cellipse cx=\'53\' cy=\'45\' rx=\'7\' ry=\'9\'/%3E%3Cellipse cx=\'67\' cy=\'45\' rx=\'7\' ry=\'9\'/%3E%3Cellipse cx=\'80\' cy=\'54\' rx=\'7\' ry=\'9\'/%3E%3C/g%3E%3C/svg%3E"}</script>'
       . '</head><body class="' . e($bodyClass) . '"><a class="skip-link" href="#main">Skip to content</a>'
       // refraction map for the liquid glass surfaces (.lg::before uses filter: url(#lg-distort))
       . '<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false"><filter id="lg-distort" x="0%" y="0%" width="100%" height="100%">'
       . '<feTurbulence type="fractalNoise" baseFrequency="0.008 0.012" numOctaves="2" seed="7" result="noise"/>'
       . '<feGaussianBlur in="noise" stdDeviation="2.5" result="map"/>'
       . '<feDisplacementMap in="SourceGraphic" in2="map" scale="55" xChannelSelector="R" yChannelSelector="G"/></filter></svg>';
}

/**
 * Public / adopter top bar with a mobile drawer.
 * $items: key => [url, label, icon, badge count]. $actionsHtml goes on the right (account buttons).
 */
function render_site_header(array $items, $activeNav, $homeUrl, $actionsHtml, $mobileFooterHtml = '')
{
    echo '<header class="site-header"><div class="container lg">'
       . '<a class="brand" href="' . e($homeUrl) . '" aria-label="' . e(APP_NAME) . ' home"><span class="brand-mark"><i class="bi bi-heart-fill"></i></span>'
       . '<span class="brand-name">Animal Matter<small>to me · since 2010</small></span></a>';
    echo '<nav class="main-nav" aria-label="Main">';
    foreach ($items as $key => [$href, $label, $icon, $badge]) {
        $active = $activeNav === $key;
        echo '<a href="' . e($href) . '"' . ($active ? ' class="active" aria-current="page"' : '') . '>' . e($label)
           . ($badge ? '<span class="nav-badge">' . (int) $badge . '</span>' : '') . '</a>';
    }
    echo '<span class="nav-lens" aria-hidden="true"></span></nav><div class="header-actions">' . $actionsHtml . '</div>'
       . '<button class="header-icon-btn nav-toggle" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileNav" aria-controls="mobileNav" aria-label="Open menu"><i class="bi bi-list"></i></button>'
       . '</div></header>';
    echo '<div class="offcanvas offcanvas-end mobile-nav" tabindex="-1" id="mobileNav" aria-labelledby="mobileNavLabel">'
       . '<div class="offcanvas-header"><span class="brand" id="mobileNavLabel"><span class="brand-mark"><i class="bi bi-heart-fill"></i></span><span class="brand-name">Animal Matter<small>to me</small></span></span>'
       . '<button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close menu"></button></div>'
       . '<div class="offcanvas-body d-flex flex-column gap-4"><nav class="mobile-nav-links" aria-label="Mobile">';
    foreach ($items as $key => [$href, $label, $icon, $badge]) {
        echo '<a href="' . e($href) . '"' . ($activeNav === $key ? ' class="active" aria-current="page"' : '') . '><i class="bi bi-' . e($icon) . '"></i>' . e($label)
           . ($badge ? '<span class="nav-badge">' . (int) $badge . '</span>' : '') . '</a>';
    }
    echo '</nav>' . $mobileFooterHtml . '</div></div>';
}

// ---------------------------------------------------------------- favourites

function favourite_ids($adopterId)
{
    return array_map('intval', array_column(
        db_all("SELECT `pet_id` FROM `favourites` WHERE `adopter_id` = ?", [(int) $adopterId]), 'pet_id'));
}

/** Call near the top of any adopter page that shows heart buttons. Handles the POST and redirects back. */
function handle_favourite_toggle($adopterId)
{
    if (!is_post() || post('fav_toggle') === '') {
        return;
    }
    $petId = (int) post('fav_toggle');
    if (db_run("DELETE FROM `favourites` WHERE `adopter_id` = ? AND `pet_id` = ?", [(int) $adopterId, $petId]) === 0
        && db_value("SELECT COUNT(*) FROM `pets` WHERE `pet_id` = ?", [$petId])) {
        db_run("INSERT INTO `favourites` (`adopter_id`, `pet_id`) VALUES (?, ?)", [(int) $adopterId, $petId]);
        flash('Saved to your favourites.');
    } else {
        flash('Removed from your favourites.', 'info');
    }
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

function favourite_pets($adopterId)
{
    return db_all(
        "SELECT p.*, c.`name` AS category_name, l.`city`,
                (SELECT COUNT(*) FROM `favourites` fv WHERE fv.`pet_id` = p.`pet_id`) AS saved_count
         FROM `favourites` f
         JOIN `pets` p ON p.`pet_id` = f.`pet_id`
         LEFT JOIN `categories` c ON c.`category_id` = p.`category_id`
         LEFT JOIN `locations` l ON l.`location_id` = p.`location_id`
         WHERE f.`adopter_id` = ? ORDER BY f.`created_at` DESC",
        [(int) $adopterId]
    );
}

// ---------------------------------------------------------------- adoption status tracker

const STAGE_LABELS = [
    'pending'   => ['Under review', 'warning', 'hourglass-split'],
    'accepted'  => ['Approved – ready for pickup', 'info', 'calendar-check'],
    'adopted'   => ['Adopted', 'success', 'house-heart'],
    'rejected'  => ['Not approved', 'danger', 'x-circle'],
    'cancelled' => ['Cancelled by you', 'secondary', 'slash-circle'],
];

/**
 * Every application an adopter has made, newest first, normalised to one shape:
 * stage, pet_id, pet_name, image, city, request_time, accept_time, adopt_time,
 * updated_at, reason, request_id. Older rows have no adopter_id, so they match on email.
 */
function adopter_applications($adopterId, $email)
{
    $apps = [];
    $rows = db_all(
        "SELECT r.*, COALESCE(p.`name`, ad.`pet_name`) AS pet_name, COALESCE(p.`image`, ad.`image`) AS image, l.`city`,
                l.`address` AS location_address, l.`contact` AS location_contact
         FROM `requests` r
         LEFT JOIN `pets` p ON p.`pet_id` = r.`pet_id`
         LEFT JOIN `adopted` ad ON ad.`pet_id` = r.`pet_id`
         LEFT JOIN `locations` l ON l.`location_id` = r.`location_id`
         WHERE r.`adopter_id` = ? OR (r.`adopter_id` IS NULL AND r.`email` = ?)",
        [(int) $adopterId, $email]
    );
    foreach ($rows as $r) {
        $apps[] = ['stage' => $r['status'], 'pet_id' => $r['pet_id'], 'pet_name' => $r['pet_name'] ?? 'A pet',
                   'image' => $r['image'] ?? '', 'city' => $r['city'] ?? '', 'request_time' => $r['request_time'],
                   'accept_time' => null, 'adopt_time' => null, 'updated_at' => $r['updated_at'],
                   'reason' => $r['reason'], 'request_id' => $r['request_id'],
                   'branch_address' => $r['location_address'] ?? '', 'branch_phone' => $r['location_contact'] ?? ''];
    }
    $rows = db_all(
        "SELECT a.*, p.`name` AS pet_name, p.`image`, l.`city`, l.`address` AS location_address, l.`contact` AS location_contact
         FROM `accepted` a
         LEFT JOIN `pets` p ON p.`pet_id` = a.`pet_id`
         LEFT JOIN `locations` l ON l.`location_id` = a.`location_id`
         WHERE a.`adopter_id` = ? OR (a.`adopter_id` IS NULL AND a.`email` = ?)",
        [(int) $adopterId, $email]
    );
    foreach ($rows as $r) {
        $apps[] = ['stage' => 'accepted', 'pet_id' => $r['pet_id'], 'pet_name' => $r['pet_name'] ?? 'A pet',
                   'image' => $r['image'] ?? '', 'city' => $r['city'] ?? '', 'request_time' => $r['request_time'],
                   'accept_time' => $r['accept_time'], 'adopt_time' => null, 'updated_at' => $r['accept_time'],
                   'reason' => '', 'request_id' => null,
                   'pickup' => trim(($r['location_address'] ?? '') . ' · ' . ($r['location_contact'] ?? ''), ' ·'),
                   'branch_address' => $r['location_address'] ?? '', 'branch_phone' => $r['location_contact'] ?? ''];
    }
    $rows = db_all(
        "SELECT * FROM `adopted` WHERE `adopter_id` = ? OR (`adopter_id` IS NULL AND `email` = ?)",
        [(int) $adopterId, $email]
    );
    foreach ($rows as $r) {
        $apps[] = ['stage' => 'adopted', 'pet_id' => $r['pet_id'], 'pet_name' => $r['pet_name'],
                   'image' => $r['image'], 'city' => $r['location_city'], 'request_time' => $r['request_time'],
                   'accept_time' => $r['accept_time'], 'adopt_time' => $r['adopt_time'], 'updated_at' => $r['adopt_time'],
                   'reason' => '', 'request_id' => null,
                   'branch_address' => $r['location_address'], 'branch_phone' => $r['location_contact']];
    }
    usort($apps, fn($a, $b) => strcmp($b['updated_at'] ?? $b['request_time'], $a['updated_at'] ?? $a['request_time']));
    return $apps;
}

function time_ago($datetime)
{
    $seconds = time() - strtotime($datetime);
    if ($seconds < 3600) {
        return max(1, (int) floor($seconds / 60)) . ' min ago';
    }
    if ($seconds < 86400) {
        return (int) floor($seconds / 3600) . ' h ago';
    }
    $days = (int) floor($seconds / 86400);
    return $days === 1 ? 'yesterday' : $days . ' days ago';
}

function format_date($datetime, $withTime = false)
{
    if (!$datetime) {
        return '';
    }
    return date($withTime ? 'M j, Y · g:i A' : 'M j, Y', strtotime($datetime));
}

function stage_badge($stage)
{
    [$label, $tone, $icon] = STAGE_LABELS[$stage] ?? [ucfirst($stage), 'secondary', 'circle'];
    return '<span class="status-pill status-' . e($tone) . '"><i class="bi bi-' . $icon . '"></i>' . e($label) . '</span>';
}

/** Card with the Requested → Approved → Adopted timeline for one application. $actions is extra HTML (buttons). */
function render_application_card(array $app, $actions = '')
{
    $stage = $app['stage'];
    $stopped = in_array($stage, ['rejected', 'cancelled'], true);
    $steps = [
        ['Requested', $app['request_time'], true],
        [$stopped ? 'Closed' : 'Approved', $stopped ? $app['updated_at'] : $app['accept_time'],
            $stopped || in_array($stage, ['accepted', 'adopted'], true)],
        ['Adopted', $app['adopt_time'], $stage === 'adopted'],
    ];
    $current = ['pending' => 1, 'accepted' => 2, 'adopted' => 3, 'rejected' => 2, 'cancelled' => 2][$stage] ?? 1;

    echo '<article class="app-card' . ($stopped ? ' is-stopped' : '') . '" data-animate>';
    echo '<img class="app-card-img" src="' . e(pet_image_url($app['image'])) . '" alt="' . e($app['pet_name']) . '" loading="lazy"' . img_fallback() . '>';
    echo '<div class="app-card-body"><div class="app-card-title">'
       . '<div class="min-w-0"><h3>' . e($app['pet_name']) . '</h3>'
       . '<small class="text-muted fw-semibold">' . ($app['city'] ? '<i class="bi bi-geo-alt"></i> ' . e($app['city']) . ' branch · ' : '')
       . 'Applied ' . e(format_date($app['request_time'])) . '</small>'
       . '</div>' . stage_badge($stage) . '</div>';
    echo '<ol class="tracker" aria-label="Application progress">';
    foreach ($steps as $i => [$label, $time, $done]) {
        $class = $done ? ($stopped && $i === 1 ? 'is-stopped' : 'is-done') : (!$stopped && $i === $current ? 'is-next' : '');
        echo '<li class="' . $class . '"><span class="tracker-dot" aria-hidden="true"></span><span class="tracker-label">' . e($label) . '</span>'
           . '<span class="tracker-time">' . e($done ? format_date($time) : ($stopped ? '—' : 'Waiting')) . '</span></li>';
    }
    echo '</ol>';
    $phone = $app['branch_phone'] ?? '';
    $branchLine = trim(($app['city'] ? $app['city'] . ' branch' : '') . (($app['branch_address'] ?? '') !== '' ? ' · ' . $app['branch_address'] : ''), ' ·');
    $facts = [];
    if ($stage === 'pending') {
        $waited = days_since($app['request_time']);
        $facts[] = ['hourglass-split', $waited === 0 ? 'Sent today' : 'Waiting ' . plural($waited, 'day')];
        echo '<p class="note"><i class="bi bi-signpost-2"></i><span><strong>Next step:</strong> the shelter reviews your application and emails you the decision. '
           . 'Nothing to do for now — you can cancel any time while it’s under review.</span></p>';
    } elseif ($stage === 'accepted') {
        $deadline = strtotime($app['accept_time'] . ' +3 days');
        $open = time() <= $deadline;
        $left = (int) round((strtotime(date('Y-m-d', $deadline)) - strtotime('today')) / 86400); // calendar days, DST-safe
        $facts[] = ['calendar-event', 'Pick up by ' . date('M j', $deadline)];
        $facts[] = !$open ? ['exclamation-triangle', 'Pickup window ended'] : ($left > 0 ? ['alarm', plural($left, 'day') . ' left'] : ['alarm', 'Last day today']);
        echo '<p class="note ' . ($open ? 'note-info' : 'note-warning') . '"><i class="bi bi-calendar-check"></i><span><strong>Next step:</strong> '
           . ($open
               ? 'pick ' . e($app['pet_name']) . ' up from the ' . e($app['city']) . ' branch by <strong>' . e(format_date(date('Y-m-d H:i:s', $deadline))) . '</strong> and bring your CNIC.'
               : 'the 3-day pickup window has passed. Please call the ' . e($app['city']) . ' branch as soon as possible — an approval that isn’t collected can be cancelled.')
           . (!empty($app['branch_address']) ? ' <span class="d-block mt-1"><i class="bi bi-geo-alt"></i> ' . e($app['branch_address']) . '</span>' : '') . '</span></p>';
    } elseif ($stage === 'rejected') {
        echo '<p class="note note-danger"><i class="bi bi-chat-left-text"></i><span>'
           . ($app['reason'] !== '' ? '<strong>Reason from the shelter:</strong> ' . e($app['reason']) : 'The shelter did not give a reason. You can contact us for details.')
           . ' <span class="d-block mt-1"><strong>Next step:</strong> you’re welcome to apply for another pet at any time.</span></span></p>';
    } elseif ($stage === 'cancelled') {
        echo '<p class="note"><i class="bi bi-slash-circle"></i><span>You cancelled this application, so ' . e($app['pet_name']) . ' went back on the list. You can apply again whenever you’re ready.</span></p>';
    } elseif ($stage === 'adopted') {
        $took = $app['adopt_time'] ? days_since($app['request_time']) - days_since($app['adopt_time']) : null;
        if ($took !== null) {
            $facts[] = ['stopwatch', $took === 0 ? 'Home the same day' : 'Home in ' . plural($took, 'day')];
        }
        echo '<p class="note note-success"><i class="bi bi-house-heart"></i><span>Welcome home, ' . e($app['pet_name']) . '! Thank you for adopting.</span></p>';
    }
    if (in_array($stage, ['pending', 'accepted'], true) && ($branchLine !== '' || $phone !== '')) {
        $facts[] = ['telephone', $phone !== '' ? '<a href="tel:' . e($phone) . '">' . e($phone) . '</a>' : e($branchLine), true];
    }
    if ($facts) {
        echo '<ul class="app-facts">';
        foreach ($facts as $f) {
            echo '<li><i class="bi bi-' . $f[0] . '"></i>' . (!empty($f[2]) ? $f[1] : e($f[1])) . '</li>';
        }
        echo '</ul>';
    }
    echo $actions . '</div></article>';
}

// ---------------------------------------------------------------- one-to-one chat (shelter ↔ adopter)

/*
 * Each adopter has one conversation with the shelter team. It opens once one of their
 * applications is approved (a row in `accepted`) and stays open after the adoption, so
 * pickup and aftercare can be arranged. sender is 'admin' or 'adopter'; read_at is set
 * when the other side has the conversation open. Pages call handle_chat_request() before
 * any output and render_chat() where the window goes; page_end() wires up live updates.
 */
const CHAT_MAX_LENGTH = 1000;

/** The adopter's approved and adopted pets, newest first. A non-empty list means the chat is open. */
function chat_context($adopterId)
{
    $email = (string) db_value("SELECT `email` FROM `adopter` WHERE `adopter_id` = ?", [(int) $adopterId]);
    return db_all(
        "SELECT 'accepted' AS stage, a.`pet_id`, p.`name` AS pet_name, p.`image`, l.`city`, a.`accept_time` AS happened_at
         FROM `accepted` a
         LEFT JOIN `pets` p ON p.`pet_id` = a.`pet_id`
         LEFT JOIN `locations` l ON l.`location_id` = a.`location_id`
         WHERE a.`adopter_id` = ? OR (a.`adopter_id` IS NULL AND a.`email` = ?)
         UNION ALL
         SELECT 'adopted', d.`pet_id`, d.`pet_name`, d.`image`, d.`location_city`, d.`adopt_time`
         FROM `adopted` d
         WHERE d.`adopter_id` = ? OR (d.`adopter_id` IS NULL AND d.`email` = ?)
         ORDER BY happened_at DESC",
        [(int) $adopterId, $email, (int) $adopterId, $email]
    );
}

/** Adopter account behind an application row. Older rows have no adopter_id, so fall back to the email. */
function adopter_id_for(array $row)
{
    if (!empty($row['adopter_id'])) {
        return (int) $row['adopter_id'];
    }
    return (int) db_value("SELECT `adopter_id` FROM `adopter` WHERE `email` = ? LIMIT 1", [(string) ($row['email'] ?? '')]);
}

function chat_messages($adopterId, $afterId = 0)
{
    return db_all("SELECT * FROM `chat_messages` WHERE `adopter_id` = ? AND `chat_id` > ? ORDER BY `chat_id`", [(int) $adopterId, (int) $afterId]);
}

function chat_send($adopterId, $sender, $body, $adminId = null)
{
    db_run("INSERT INTO `chat_messages` (`adopter_id`, `sender`, `admin_id`, `body`) VALUES (?, ?, ?, ?)",
        [(int) $adopterId, $sender, $adminId === null ? null : (int) $adminId, $body]);
    return db_insert_id();
}

/** Marks what the other side sent as read, because $readerRole has the conversation open. */
function chat_mark_read($adopterId, $readerRole)
{
    db_run("UPDATE `chat_messages` SET `read_at` = NOW() WHERE `adopter_id` = ? AND `sender` <> ? AND `read_at` IS NULL",
        [(int) $adopterId, $readerRole]);
}

/** Unread shelter messages for one adopter, or — with no adopter — unread adopter messages for the shelter. */
function chat_unread_count($adopterId = null)
{
    if ($adopterId === null) {
        return (int) db_value("SELECT COUNT(*) FROM `chat_messages` WHERE `sender` = 'adopter' AND `read_at` IS NULL");
    }
    return (int) db_value("SELECT COUNT(*) FROM `chat_messages` WHERE `adopter_id` = ? AND `sender` = 'admin' AND `read_at` IS NULL", [(int) $adopterId]);
}

/** Newest of $viewerRole's own messages that the other side has read (drives the "Seen" ticks). */
function chat_seen_upto($adopterId, $viewerRole)
{
    return (int) db_value("SELECT COALESCE(MAX(`chat_id`), 0) FROM `chat_messages` WHERE `adopter_id` = ? AND `sender` = ? AND `read_at` IS NOT NULL",
        [(int) $adopterId, $viewerRole]);
}

/**
 * Conversation list for the shelter: adopters with an open chat (approved or adopted) or with
 * past messages. Conversations with messages come first, latest activity on top.
 */
function chat_threads()
{
    return db_all(
        "SELECT ad.`adopter_id`, ad.`name`, ad.`email`, m.`last_id`, COALESCE(m.`unread`, 0) AS unread,
                lm.`body` AS last_body, lm.`sender` AS last_sender, lm.`created_at` AS last_at,
                (SELECT COUNT(*) FROM `accepted` a WHERE a.`adopter_id` = ad.`adopter_id` OR (a.`adopter_id` IS NULL AND a.`email` = ad.`email`)) AS accepted_n,
                (SELECT COUNT(*) FROM `adopted` d WHERE d.`adopter_id` = ad.`adopter_id` OR (d.`adopter_id` IS NULL AND d.`email` = ad.`email`)) AS adopted_n
         FROM `adopter` ad
         LEFT JOIN (SELECT `adopter_id`, MAX(`chat_id`) AS last_id, SUM(`sender` = 'adopter' AND `read_at` IS NULL) AS unread
                    FROM `chat_messages` GROUP BY `adopter_id`) m ON m.`adopter_id` = ad.`adopter_id`
         LEFT JOIN `chat_messages` lm ON lm.`chat_id` = m.`last_id`
         HAVING accepted_n + adopted_n > 0 OR last_id IS NOT NULL
         ORDER BY (last_id IS NULL), last_at DESC, ad.`name`"
    );
}

function chat_day_label($timestamp)
{
    $day = date('Y-m-d', $timestamp);
    if ($day === date('Y-m-d')) {
        return 'Today';
    }
    if ($day === date('Y-m-d', strtotime('-1 day'))) {
        return 'Yesterday';
    }
    return date($day >= date('Y-m-d', strtotime('-6 days')) ? 'l' : 'D, M j, Y', $timestamp);
}

/** One message as the JSON the live chat script expects (also used to render it server-side). */
function chat_message_json(array $m, $viewerRole)
{
    $ts = strtotime($m['created_at']);
    return ['id' => (int) $m['chat_id'], 'mine' => $m['sender'] === $viewerRole, 'body' => $m['body'],
            'time' => date('g:i A', $ts), 'full' => date('D, M j, Y · g:i A', $ts), 'day' => chat_day_label($ts)];
}

/**
 * One message row. $seenUpto drives the Sent / Seen ticks (null = no ticks, as in groups).
 * Group messages carry name / sender / staff / system: other people's names are shown above
 * the first bubble of a run, and system notes ("Ali joined") render as a centred line.
 */
function chat_bubble_html(array $msg, $seenUpto, $prevSender = null)
{
    if (!empty($msg['system'])) {
        return '<div class="chat-system" data-id="' . $msg['id'] . '"><span title="' . e($msg['full']) . '">' . e($msg['body']) . '</span></div>';
    }
    $ticks = $msg['mine'] && $seenUpto !== null;
    $seen = $ticks && $msg['id'] <= $seenUpto;
    $sender = $msg['sender'] ?? '';
    $nameLine = !$msg['mine'] && !empty($msg['name']) && $sender !== $prevSender
        ? '<div class="chat-sender">' . e($msg['name']) . (!empty($msg['staff']) ? ' <span class="chat-staff">Staff</span>' : '') . '</div>'
        : '';
    return '<div class="chat-msg ' . ($msg['mine'] ? 'mine' : 'theirs') . '" data-id="' . $msg['id'] . '" data-sender="' . e($sender) . '">'
         . $nameLine . '<div class="chat-bubble">' . e($msg['body']) . '</div>'
         . '<div class="chat-meta"><time title="' . e($msg['full']) . '">' . e($msg['time']) . '</time>'
         . ($ticks ? '<span class="chat-tick' . ($seen ? ' is-seen' : '') . '" title="' . ($seen ? 'Seen' : 'Sent') . '"><i class="bi bi-check2' . ($seen ? '-all' : '') . '"></i></span>' : '')
         . '</div></div>';
}

/**
 * Serves the chat endpoints for one conversation, then exits. Call it before any output.
 *   GET  ?chat_poll=1&after=ID[&visible=1] → JSON {messages, seen}
 *   POST chat_body=…                       → JSON {message} for the live script, or a redirect for a plain form post
 * Other requests are left alone. $pageUrl is the page that shows this conversation.
 */
function handle_chat_request($adopterId, $viewerRole, $canSend, $pageUrl, $adminId = null)
{
    $fromScript = ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';
    if (query_param('chat_poll') === '1') {
        if (query_param('visible') === '1') {
            chat_mark_read($adopterId, $viewerRole);
        }
        chat_poll_respond(array_map(fn($m) => chat_message_json($m, $viewerRole), chat_messages($adopterId, query_int('after'))),
            chat_seen_upto($adopterId, $viewerRole));
    }
    if (!is_post() || !isset($_POST['chat_body'])) {
        return;
    }
    $body = trim(str_replace("\r\n", "\n", (string) $_POST['chat_body']));
    $error = chat_body_error($body, $canSend);
    $message = null;
    if ($error === '') {
        // email the adopter about the first unread shelter message only, not every message
        $notify = $viewerRole === 'admin' && chat_unread_count($adopterId) === 0;
        $id = chat_send($adopterId, $viewerRole, $body, $adminId);
        $message = chat_message_json(db_one("SELECT * FROM `chat_messages` WHERE `chat_id` = ?", [$id]), $viewerRole);
        if ($notify) {
            $adopter = db_one("SELECT `name`, `email` FROM `adopter` WHERE `adopter_id` = ?", [(int) $adopterId]);
            if ($adopter) {
                send_mail($adopter['email'], 'New message from ' . APP_NAME,
                    mail_template('You have a new message, ' . $adopter['name'],
                        '<p>The shelter team wrote to you:</p><blockquote style="border-left:3px solid #12614f;margin:0;padding:.4em 1em;color:#36423e">' . nl2br(e(excerpt($body, 400))) . '</blockquote>',
                        'Open the chat', absolute_url('adopter/contactUs.php#chat')));
            }
        }
    }
    chat_respond($fromScript, $error, $message, $pageUrl);
}

function chat_body_error($body, $canSend)
{
    if (!$canSend) {
        return 'This chat is closed, so the message was not sent.';
    }
    if ($body === '') {
        return 'Write a message first.';
    }
    return mb_strlen($body) > CHAT_MAX_LENGTH ? 'Messages can be up to ' . CHAT_MAX_LENGTH . ' characters.' : '';
}

/** Answers a chat POST: JSON for the live script, otherwise a flash + redirect back to the chat. Exits. */
function chat_respond($fromScript, $error, $message, $pageUrl)
{
    if ($fromScript) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($error === '' ? 200 : 422);
        echo json_encode($error === '' ? ['message' => $message] : ['error' => $error], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($error !== '') {
        flash($error, 'warning');
    }
    header('Location: ' . $pageUrl . '#chat');
    exit;
}

/** JSON for a poll request: new messages after ?after=, plus the Seen marker when ticks are used. Exits. */
function chat_poll_respond(array $messages, $seen = null)
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['messages' => $messages] + ($seen === null ? [] : ['seen' => $seen]), JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * The chat window for one conversation. Options:
 *   title, subtitle, avatar (letter; empty shows the shelter logo), context (chat_context rows),
 *   canSend, closedText, emptyText, placeholder, url (the page; polls and posts go here).
 */
function render_chat($adopterId, $viewerRole, array $o)
{
    $context = '';
    foreach ($o['context'] ?? [] as $c) {
        $label = $c['stage'] === 'accepted'
            ? 'Approved · pick up' . ($c['city'] ? ' in ' . $c['city'] : '')
            : 'Adopted ' . format_date($c['happened_at']);
        $context .= '<span class="chat-pet"><img src="' . e(pet_image_url($c['image'] ?? '')) . '" alt=""' . img_fallback() . '>'
                  . '<span><strong>' . e($c['pet_name'] ?? 'A pet') . '</strong><small>' . e($label) . '</small></span></span>';
    }
    $icon = ($o['avatar'] ?? '') !== ''
        ? '<span class="avatar avatar-lg">' . e($o['avatar']) . '</span>'
        : '<span class="brand-mark chat-shelter-mark" aria-hidden="true"><i class="bi bi-heart-fill"></i></span>';
    render_chat_window($o + [
        'messages' => array_map(fn($m) => chat_message_json($m, $viewerRole), chat_messages($adopterId)),
        'seen'     => chat_seen_upto($adopterId, $viewerRole),
        'icon'     => $icon,
        'contextHtml' => $context,
    ]);
}

/**
 * The chat window shared by one-to-one chats and community groups. Options:
 *   messages (chat JSON rows), seen (int for ticks, null for none), url, title, subtitle,
 *   icon (HTML), contextHtml, canSend, emptyText, placeholder, closedText.
 */
function render_chat_window(array $o)
{
    $messages = $o['messages'];
    $lastId = $messages ? end($messages)['id'] : 0;
    $seen = $o['seen'];

    echo '<section class="chat" id="chat" data-chat data-url="' . e($o['url']) . '" data-last-id="' . $lastId . '"'
       . ($seen === null ? ' data-ticks="0"' : ' data-seen="' . (int) $seen . '"') . ' aria-label="Chat: ' . e($o['title']) . '">';
    echo '<header class="chat-head">' . $o['icon']
       . '<div class="min-w-0 flex-grow-1"><strong class="d-block text-truncate">' . e($o['title']) . '</strong><small class="text-muted d-block text-truncate">' . e($o['subtitle'] ?? '') . '</small></div>'
       . '<span class="chat-live" title="New messages appear automatically"><span class="dot"></span>Live</span></header>';
    if (($o['contextHtml'] ?? '') !== '') {
        echo '<div class="chat-context">' . $o['contextHtml'] . '</div>';
    }

    echo '<div class="chat-scroll" role="log" aria-live="polite" aria-label="Messages">';
    if (!$messages) {
        echo '<div class="chat-empty"><span class="icon"><i class="bi bi-chat-heart"></i></span><strong>No messages yet</strong><span>' . e($o['emptyText'] ?? 'Say hello to start the conversation.') . '</span></div>';
    }
    $day = null;
    $prevSender = null;
    foreach ($messages as $m) {
        if ($m['day'] !== $day) {
            $day = $m['day'];
            $prevSender = null;
            echo '<div class="chat-day"><span>' . e($day) . '</span></div>';
        }
        echo chat_bubble_html($m, $seen, $prevSender);
        $prevSender = !empty($m['system']) ? null : ($m['sender'] ?? null);
    }
    echo '</div>';

    if (!empty($o['canSend'])) {
        echo '<form class="chat-compose" method="post" action="' . e($o['url']) . '">' . csrf_field()
           . '<label class="visually-hidden" for="chatInput">Message</label>'
           . '<textarea class="form-control" id="chatInput" name="chat_body" rows="1" maxlength="' . CHAT_MAX_LENGTH . '" placeholder="' . e($o['placeholder'] ?? 'Write a message…') . '" required></textarea>'
           . '<button class="btn btn-primary chat-send" type="submit" aria-label="Send message"><i class="bi bi-send-fill"></i></button>'
           . '<p class="chat-error" role="alert" hidden></p>'
           . '<p class="chat-hint">Enter to send · Shift + Enter for a new line</p></form>';
    } else {
        echo '<div class="chat-closed"><i class="bi bi-lock"></i><span>' . e($o['closedText'] ?? 'This chat is closed.') . '</span></div>';
    }
    echo '</section>';
}

// ---------------------------------------------------------------- community groups

/*
 * Group chats the shelter creates and adds adopters to. Admins see and post in every group
 * (shown as "Shelter team"); adopters only in groups they belong to. Unread is tracked with
 * community_members.last_read_id (per adopter) and community_groups.admin_last_read_id (shared
 * by the shelter). sender = 'system' rows are notes such as "Ali joined the group".
 */
const GROUP_NAME_MAX = 60;
const GROUP_DESCRIPTION_MAX = 200;
const GROUP_TONES = ['#12614f', '#e2622f', '#7b5cf0', '#2563c9', '#b7791f', '#119174', '#c9372c'];

// adopted_n / accepted_n for an `adopter a` row, so lists can say how far each person got
const ADOPTER_PROGRESS_SQL = "(SELECT COUNT(*) FROM `adopted` d WHERE d.`adopter_id` = a.`adopter_id` OR (d.`adopter_id` IS NULL AND d.`email` = a.`email`)) AS adopted_n,
    (SELECT COUNT(*) FROM `accepted` x WHERE x.`adopter_id` = a.`adopter_id` OR (x.`adopter_id` IS NULL AND x.`email` = a.`email`)) AS accepted_n";

function group_icon_html(array $group, $large = false)
{
    $words = preg_split('/\s+/', trim($group['name']));
    $initials = mb_strtoupper(mb_substr($words[0] ?? '#', 0, 1) . (isset($words[1]) ? mb_substr($words[1], 0, 1) : ''));
    return '<span class="group-icon' . ($large ? ' group-icon-lg' : '') . '" style="--tone:' . GROUP_TONES[(int) $group['group_id'] % count(GROUP_TONES)] . '" aria-hidden="true">' . e($initials) . '</span>';
}

function adopter_progress_pill(array $row)
{
    if ((int) $row['adopted_n']) {
        return '<span class="status-pill status-success">Adopted</span>';
    }
    return (int) $row['accepted_n'] ? '<span class="status-pill status-info">Awaiting pickup</span>' : '<span class="status-pill status-secondary">Adopter</span>';
}

/**
 * Groups with member count, last message and unread count, latest activity first.
 * With $adopterId: only that adopter's groups and their unread; without: every group, unread for the shelter.
 */
function community_groups($adopterId = null)
{
    if ($adopterId === null) {
        $join = '';
        $params = [];
        $unread = "m.`message_id` > g.`admin_last_read_id` AND m.`sender` = 'adopter'";
    } else {
        $join = 'JOIN `community_members` me ON me.`group_id` = g.`group_id` AND me.`adopter_id` = ?';
        $params = [(int) $adopterId];
        $unread = "m.`message_id` > me.`last_read_id` AND m.`sender` <> 'system' AND NOT (m.`sender` = 'adopter' AND m.`adopter_id` = me.`adopter_id`)";
    }
    return db_all(
        "SELECT g.*,
                (SELECT COUNT(*) FROM `community_members` cm WHERE cm.`group_id` = g.`group_id`) AS member_count,
                (SELECT COUNT(*) FROM `community_messages` m WHERE m.`group_id` = g.`group_id` AND $unread) AS unread,
                lm.`body` AS last_body, lm.`sender` AS last_sender, lm.`adopter_id` AS last_adopter_id, lm.`created_at` AS last_at, la.`name` AS last_name
         FROM `community_groups` g $join
         LEFT JOIN `community_messages` lm ON lm.`message_id` = (SELECT MAX(`message_id`) FROM `community_messages` WHERE `group_id` = g.`group_id`)
         LEFT JOIN `adopter` la ON la.`adopter_id` = lm.`adopter_id`
         ORDER BY COALESCE(lm.`created_at`, g.`created_at`) DESC, g.`group_id` DESC",
        $params
    );
}

function community_unread_total($adopterId = null)
{
    return (int) array_sum(array_column(community_groups($adopterId), 'unread'));
}

function community_group($groupId)
{
    return db_one("SELECT * FROM `community_groups` WHERE `group_id` = ?", [(int) $groupId]);
}

function community_members($groupId)
{
    return db_all(
        "SELECT a.`adopter_id`, a.`name`, a.`email`, cm.`added_at`, " . ADOPTER_PROGRESS_SQL . "
         FROM `community_members` cm JOIN `adopter` a ON a.`adopter_id` = cm.`adopter_id`
         WHERE cm.`group_id` = ? ORDER BY a.`name`",
        [(int) $groupId]
    );
}

/** Every adopter account, for the shelter's member picker. */
function community_adopter_directory()
{
    return db_all("SELECT a.`adopter_id`, a.`name`, a.`email`, a.`created_at`, " . ADOPTER_PROGRESS_SQL . " FROM `adopter` a ORDER BY a.`name`");
}

/** Adopter ids from a posted checkbox list (members[]), limited to real accounts. */
function posted_adopter_ids($field = 'members')
{
    $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST[$field] ?? [])))));
    if (!$ids) {
        return [];
    }
    $rows = db_all("SELECT `adopter_id` FROM `adopter` WHERE `adopter_id` IN (" . implode(',', array_fill(0, count($ids), '?')) . ")", $ids);
    return array_map('intval', array_column($rows, 'adopter_id'));
}

function community_post($groupId, $sender, $body, $adopterId = null, $adminId = null)
{
    db_run("INSERT INTO `community_messages` (`group_id`, `sender`, `adopter_id`, `admin_id`, `body`) VALUES (?, ?, ?, ?, ?)",
        [(int) $groupId, $sender, $adopterId === null ? null : (int) $adopterId, $adminId === null ? null : (int) $adminId, $body]);
    return db_insert_id();
}

/** Adds an adopter (history stays visible, but starts as read). Returns false if they were already a member. */
function community_add_member($groupId, $adopterId)
{
    $added = db_run(
        "INSERT IGNORE INTO `community_members` (`group_id`, `adopter_id`, `last_read_id`)
         SELECT ?, ?, COALESCE(MAX(`message_id`), 0) FROM `community_messages` WHERE `group_id` = ?",
        [(int) $groupId, (int) $adopterId, (int) $groupId]
    );
    if ($added) {
        $name = (string) db_value("SELECT `name` FROM `adopter` WHERE `adopter_id` = ?", [(int) $adopterId]);
        community_post($groupId, 'system', $name . ' joined the group');
    }
    return (bool) $added;
}

function community_remove_member($groupId, $adopterId, $note)
{
    if (db_run("DELETE FROM `community_members` WHERE `group_id` = ? AND `adopter_id` = ?", [(int) $groupId, (int) $adopterId])) {
        community_post($groupId, 'system', $note);
        return true;
    }
    return false;
}

function community_delete_group($groupId)
{
    db_transaction(function () use ($groupId) {
        db_run("DELETE FROM `community_messages` WHERE `group_id` = ?", [(int) $groupId]);
        db_run("DELETE FROM `community_members` WHERE `group_id` = ?", [(int) $groupId]);
        db_run("DELETE FROM `community_groups` WHERE `group_id` = ?", [(int) $groupId]);
    });
}

/** Validation messages for a group name + description (empty when fine). */
function community_group_errors($name, $description)
{
    $errors = [];
    if (mb_strlen($name) < 3 || mb_strlen($name) > GROUP_NAME_MAX) {
        $errors[] = 'Group names need 3–' . GROUP_NAME_MAX . ' characters.';
    }
    if (mb_strlen($description) > GROUP_DESCRIPTION_MAX) {
        $errors[] = 'The description can be up to ' . GROUP_DESCRIPTION_MAX . ' characters.';
    }
    return $errors;
}

function community_messages($groupId, $afterId = 0)
{
    return db_all(
        "SELECT m.*, a.`name` AS adopter_name FROM `community_messages` m
         LEFT JOIN `adopter` a ON a.`adopter_id` = m.`adopter_id`
         WHERE m.`group_id` = ? AND m.`message_id` > ? ORDER BY m.`message_id`",
        [(int) $groupId, (int) $afterId]
    );
}

function community_mark_read($groupId, $viewerRole, $adopterId = null)
{
    $max = (int) db_value("SELECT COALESCE(MAX(`message_id`), 0) FROM `community_messages` WHERE `group_id` = ?", [(int) $groupId]);
    if ($viewerRole === 'admin') {
        db_run("UPDATE `community_groups` SET `admin_last_read_id` = ? WHERE `group_id` = ? AND `admin_last_read_id` < ?", [$max, (int) $groupId, $max]);
    } else {
        db_run("UPDATE `community_members` SET `last_read_id` = ? WHERE `group_id` = ? AND `adopter_id` = ? AND `last_read_id` < ?",
            [$max, (int) $groupId, (int) $adopterId, $max]);
    }
}

function community_message_json(array $m, $viewerRole, $viewerAdopterId = 0)
{
    $ts = strtotime($m['created_at']);
    $system = $m['sender'] === 'system';
    $mine = $viewerRole === 'admin' ? $m['sender'] === 'admin' : ($m['sender'] === 'adopter' && (int) $m['adopter_id'] === (int) $viewerAdopterId);
    return ['id' => (int) $m['message_id'], 'mine' => $mine, 'body' => $m['body'],
            'time' => date('g:i A', $ts), 'full' => date('D, M j, Y · g:i A', $ts), 'day' => chat_day_label($ts),
            'system' => $system, 'staff' => $m['sender'] === 'admin',
            'name' => $system ? '' : ($m['sender'] === 'admin' ? 'Shelter team' : ($m['adopter_name'] ?? 'Former member')),
            'sender' => $system ? 'system' : ($m['sender'] === 'admin' ? 'admin' : 'adopter:' . (int) $m['adopter_id'])];
}

/**
 * Live endpoints for one group (same protocol as handle_chat_request, no Seen ticks). Call before
 * any output, after checking the viewer may see the group. Exits for poll / chat_body requests.
 */
function handle_community_request($groupId, $viewerRole, $pageUrl, $adopterId = null, $adminId = null)
{
    if (query_param('chat_poll') === '1') {
        if (query_param('visible') === '1') {
            community_mark_read($groupId, $viewerRole, $adopterId);
        }
        chat_poll_respond(array_map(fn($m) => community_message_json($m, $viewerRole, $adopterId),
            community_messages($groupId, query_int('after'))));
    }
    if (!is_post() || !isset($_POST['chat_body'])) {
        return;
    }
    $body = trim(str_replace("\r\n", "\n", (string) $_POST['chat_body']));
    $error = chat_body_error($body, true);
    $message = null;
    if ($error === '') {
        $id = community_post($groupId, $viewerRole, $body, $viewerRole === 'adopter' ? $adopterId : null, $viewerRole === 'admin' ? $adminId : null);
        $message = community_message_json(community_messages($groupId, $id - 1)[0], $viewerRole, $adopterId);
    }
    chat_respond(($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch', $error, $message, $pageUrl);
}

/** Group chat window. $o: url, subtitle, placeholder, emptyText (title and icon come from the group). */
function render_group_chat(array $group, $viewerRole, array $o, $adopterId = 0)
{
    render_chat_window($o + [
        'messages' => array_map(fn($m) => community_message_json($m, $viewerRole, $adopterId), community_messages($group['group_id'])),
        'seen'     => null,
        'icon'     => group_icon_html($group, true),
        'title'    => $group['name'],
        'canSend'  => true,
    ]);
}

/** Last-message line for a group in a list: "You: …", "Ali: …" or a system note. */
function community_preview(array $g, $viewerRole, $adopterId = 0)
{
    if ($g['last_body'] === null) {
        return 'No messages yet';
    }
    $text = excerpt(preg_replace('/\s+/', ' ', $g['last_body']), 60);
    if ($g['last_sender'] === 'system') {
        return $text;
    }
    $mine = $viewerRole === 'admin' ? $g['last_sender'] === 'admin' : ($g['last_sender'] === 'adopter' && (int) $g['last_adopter_id'] === (int) $adopterId);
    $who = $mine ? 'You' : ($g['last_sender'] === 'admin' ? 'Shelter team' : ($g['last_name'] ?? 'Former member'));
    return $who . ': ' . $text;
}

/** Searchable checkbox list of adopters (members[]) used by the create / add-members dialogs. */
function render_member_picker(array $adopters, $idPrefix)
{
    if (!$adopters) {
        echo '<p class="text-muted small mb-0">Everyone with an account is already in this group.</p>';
        return;
    }
    echo '<div class="member-picker"><div class="input-icon mb-2"><i class="bi bi-search" aria-hidden="true"></i>'
       . '<input class="form-control form-control-sm" type="search" placeholder="Search adopters…" aria-label="Search adopters" data-filter-list="#' . e($idPrefix) . '-list"></div>'
       . '<div class="picker-list" id="' . e($idPrefix) . '-list">';
    foreach ($adopters as $a) {
        $id = $idPrefix . '-' . (int) $a['adopter_id'];
        echo '<label class="picker-item" for="' . e($id) . '" data-filter-text="' . e(mb_strtolower($a['name'] . ' ' . $a['email'])) . '">'
           . '<input class="form-check-input" type="checkbox" name="members[]" value="' . (int) $a['adopter_id'] . '" id="' . e($id) . '">'
           . '<span class="avatar">' . e(mb_strtoupper(mb_substr($a['name'], 0, 1))) . '</span>'
           . '<span class="min-w-0 flex-grow-1"><strong class="d-block text-truncate">' . e($a['name']) . '</strong><small class="text-muted d-block text-truncate">' . e($a['email']) . '</small></span>'
           . adopter_progress_pill($a) . '</label>';
    }
    echo '</div></div>';
}

// ---------------------------------------------------------------- layout

/** Closes the page opened by a header.php: footer (public / adopter) or the admin shell, plus the shared scripts. */
function page_end()
{
    global $LAYOUT;
    if (($LAYOUT ?? 'site') === 'admin') {
        echo '</div></main></div>';
    } else {
        $isAdopter = current_role() === 'adopter';
        $home = $isAdopter ? 'adopter/' : '';
        echo '</main><footer class="site-footer"><div class="container position-relative">';
        echo '<div class="footer-cta"><div><h3>' . ($isAdopter ? 'Someone new arrives every week.' : 'Ready to change a life?') . '</h3>'
           . '<p>' . ($isAdopter ? 'Check back often — and keep your favourites saved.' : 'Create a free account and start your adoption today.') . '</p></div>'
           . '<a class="btn btn-accent btn-lg" href="' . e(url($isAdopter ? 'adopter/pets.php' : (current_role() ? 'pets.php' : 'signup.php'))) . '">'
           . ($isAdopter || current_role() ? 'Browse pets' : 'Become an adopter') . ' <i class="bi bi-arrow-right"></i></a></div>';
        echo '<div class="row g-5">'
           . '<div class="col-lg-4"><a class="brand" href="' . e(url($isAdopter ? 'adopter/home.php' : 'index.php')) . '"><span class="brand-mark"><i class="bi bi-heart-fill"></i></span><span class="brand-name">Animal Matter<small>to me · since 2010</small></span></a>'
           . '<p class="mt-3">Rescued animals, responsible adoption and a second chance at a loving home. Every pet is health-checked before they meet you.</p></div>'
           . '<div class="col-6 col-lg-2"><h4>Adopt</h4><ul>'
           . '<li><a href="' . e(url($home . 'pets.php')) . '">Browse pets</a></li>'
           . '<li><a href="' . e(url($home . 'categories.php')) . '">Categories</a></li>'
           . '<li><a href="' . e(url($isAdopter ? 'adopter/requested.php' : 'aboutUs.php#process')) . '">' . ($isAdopter ? 'My adoptions' : 'How it works') . '</a></li></ul></div>'
           . '<div class="col-6 col-lg-2"><h4>Shelter</h4><ul>'
           . '<li><a href="' . e(url($home . 'aboutUs.php')) . '">About us</a></li>'
           . '<li><a href="' . e(url($home . 'contactUs.php')) . '">Contact</a></li>'
           . (current_role() ? '' : '<li><a href="' . e(url('login.php')) . '">Log in</a></li>') . '</ul></div>'
           . '<div class="col-lg-4"><h4>Visit a branch</h4><ul>';
        foreach (all_locations() as $l) {
            echo '<li class="footer-branch"><strong>' . e($l['city']) . '</strong><span>' . e($l['address']) . ' · <a href="tel:' . e($l['contact']) . '">' . e($l['contact']) . '</a></span></li>';
        }
        echo '</ul></div></div><div class="footer-bottom"><span>© ' . date('Y') . ' ' . e(APP_NAME) . '. Made with care for animals.</span>'
           . '<span><i class="bi bi-heart-fill text-accent"></i> Adopt, don’t shop.</span></div></div></footer>';
    }
    // Shared confirm dialog: any <form data-confirm="Question?"> asks before submitting.
    // data-confirm-ok sets the button text, data-confirm-tone="neutral" uses the brand colour instead of red.
    echo '<div class="modal fade confirm-modal" id="confirmModal" tabindex="-1" aria-labelledby="confirmTitle" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">'
       . '<div class="modal-body"><div class="confirm-icon"><i class="bi bi-exclamation-triangle"></i></div><h2 id="confirmTitle">Are you sure?</h2><p id="confirmText"></p></div>'
       . '<div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-danger" id="confirmOk">Confirm</button></div>'
       . '</div></div></div>';
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>';
    echo <<<'JS'
<script>
(function () {
    // toasts: dismiss on click, auto-hide after 6s
    document.querySelectorAll('.toast-x').forEach(function (t) {
        var close = function () { t.classList.add('is-leaving'); setTimeout(function () { t.remove(); }, 250); };
        t.querySelector('[data-toast-close]').addEventListener('click', close);
        setTimeout(close, 6000);
    });
    // header shadow once the page scrolls
    var header = document.querySelector('.site-header');
    if (header) {
        var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 8); };
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
    }
    // glass droplet in the main nav: rests on the current page, glides to the hovered link
    document.querySelectorAll('.main-nav').forEach(function (nav) {
        var lens = nav.querySelector('.nav-lens');
        if (!lens) { return; }
        var links = nav.querySelectorAll('a'), active = nav.querySelector('a.active'), shown = null;
        nav.classList.add('has-lens');
        var moveTo = function (link, instant) {
            links.forEach(function (l) { l.classList.toggle('is-lensed', l === link); });
            if (!link) { lens.style.opacity = 0; shown = null; return; }
            if (shown && shown !== link && !instant) {
                lens.classList.remove('is-moving'); void lens.offsetWidth; lens.classList.add('is-moving');
            }
            if (instant) { lens.style.transition = 'none'; }
            lens.style.width = link.offsetWidth + 'px';
            lens.style.transform = 'translateX(' + link.offsetLeft + 'px)';
            lens.style.opacity = 1;
            if (instant) { lens.offsetWidth; lens.style.transition = ''; }
            shown = link;
        };
        links.forEach(function (l) {
            l.addEventListener('mouseenter', function () { moveTo(l); });
            l.addEventListener('focus', function () { moveTo(l); });
        });
        nav.addEventListener('mouseleave', function () { moveTo(active); });
        window.addEventListener('resize', function () { moveTo(shown || active, true); });
        if (document.fonts && document.fonts.ready) { document.fonts.ready.then(function () { moveTo(active, true); }); }
        moveTo(active, true);
    });
    // fade sections in as they scroll into view
    var animated = document.querySelectorAll('[data-animate]');
    if ('IntersectionObserver' in window && animated.length) {
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) { if (en.isIntersecting) { en.target.classList.add('is-visible'); io.unobserve(en.target); } });
        }, { rootMargin: '0px 0px -8% 0px' });
        animated.forEach(function (el, i) { el.style.transitionDelay = (i % 4) * 70 + 'ms'; io.observe(el); });
    } else {
        animated.forEach(function (el) { el.classList.add('is-visible'); });
    }
    // styled confirmation for destructive actions
    var modalEl = document.getElementById('confirmModal'), pending = null;
    var modal = window.bootstrap ? bootstrap.Modal.getOrCreateInstance(modalEl) : null;
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (ev) {
            if (form.dataset.confirmed) { return; }
            ev.preventDefault();
            if (!modal) { if (confirm(form.dataset.confirm)) { form.dataset.confirmed = '1'; form.submit(); } return; }
            pending = form;
            var neutral = form.dataset.confirmTone === 'neutral';
            modalEl.classList.toggle('is-neutral', neutral);
            modalEl.querySelector('.confirm-icon i').className = 'bi bi-' + (neutral ? 'question-circle' : 'exclamation-triangle');
            document.getElementById('confirmTitle').textContent = form.dataset.confirmTitle || 'Are you sure?';
            document.getElementById('confirmText').textContent = form.dataset.confirm;
            var ok = document.getElementById('confirmOk');
            ok.textContent = form.dataset.confirmOk || 'Confirm';
            ok.className = 'btn ' + (neutral ? 'btn-primary' : 'btn-danger');
            modal.show();
        });
    });
    document.getElementById('confirmOk').addEventListener('click', function () {
        if (!pending) { return; }
        pending.dataset.confirmed = '1';
        this.disabled = true;
        var f = pending; pending = null;
        if (f.requestSubmit) { f.requestSubmit(); } else { f.submit(); }
    });
    modalEl.addEventListener('hidden.bs.modal', function () { pending = null; document.getElementById('confirmOk').disabled = false; });
    // live image preview for file inputs
    document.querySelectorAll('input[type=file][data-preview]').forEach(function (input) {
        input.addEventListener('change', function () {
            var img = document.querySelector(input.dataset.preview);
            if (img && input.files[0]) { img.src = URL.createObjectURL(input.files[0]); img.classList.remove('d-none'); }
        });
    });
    // show / hide password buttons
    document.querySelectorAll('[data-toggle-password]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = document.getElementById(btn.dataset.togglePassword), show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-pressed', show ? 'true' : 'false');
            btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            btn.firstElementChild.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    });
    // character counters: <textarea maxlength data-count="#id">
    document.querySelectorAll('[data-count]').forEach(function (field) {
        var out = document.querySelector(field.dataset.count);
        var update = function () { out.textContent = field.value.length + ' / ' + field.maxLength; };
        update();
        field.addEventListener('input', update);
    });
    // pet filters apply instantly on wide screens; on phones the drawer's button applies them
    document.querySelectorAll('form[data-autosubmit]').forEach(function (form) {
        form.addEventListener('change', function () { if (matchMedia('(min-width: 992px)').matches) { form.submit(); } });
    });
    // live chat (render_chat): send without reloading, poll for new messages and "Seen" ticks
    document.querySelectorAll('[data-chat]').forEach(function (chat) {
        var scroller = chat.querySelector('.chat-scroll'), form = chat.querySelector('.chat-compose');
        var input = form && form.querySelector('textarea'), errorBox = form && form.querySelector('.chat-error');
        var url = chat.dataset.url, lastId = +chat.dataset.lastId || 0, seen = +chat.dataset.seen || 0, busy = false;
        var ticks = chat.dataset.ticks !== '0';
        var days = chat.querySelectorAll('.chat-day'), lastDay = days.length ? days[days.length - 1].textContent : '';
        // who wrote the last bubble, so a run of messages from one person shows their name once (groups)
        var lastRow = scroller.lastElementChild;
        var lastSender = lastRow && lastRow.classList.contains('chat-msg') ? (lastRow.dataset.sender || '') : null;
        var nearBottom = function () { return scroller.scrollHeight - scroller.scrollTop - scroller.clientHeight < 90; };
        var toBottom = function () { scroller.scrollTop = scroller.scrollHeight; };
        var withParams = function (q) { return url + (url.indexOf('?') < 0 ? '?' : '&') + q; };
        var showError = function (text) { if (errorBox) { errorBox.textContent = text; errorBox.hidden = !text; } };
        var tick = function (row) {
            var t = row.querySelector('.chat-tick');
            if (!t) { return; }
            var isSeen = +row.dataset.id <= seen;
            t.classList.toggle('is-seen', isSeen);
            t.title = isSeen ? 'Seen' : 'Sent';
            t.firstElementChild.className = isSeen ? 'bi bi-check2-all' : 'bi bi-check2';
        };
        var add = function (m) {
            if (scroller.querySelector('[data-id="' + m.id + '"]')) { return; }
            var empty = scroller.querySelector('.chat-empty');
            if (empty) { empty.remove(); }
            if (m.day !== lastDay) {
                var d = document.createElement('div'), s = document.createElement('span');
                d.className = 'chat-day'; s.textContent = m.day; d.appendChild(s); scroller.appendChild(d); lastDay = m.day; lastSender = null;
            }
            lastId = Math.max(lastId, m.id);
            if (m.system) {
                var note = document.createElement('div'), text = document.createElement('span');
                note.className = 'chat-system is-new'; note.dataset.id = m.id; text.textContent = m.body; text.title = m.full;
                note.appendChild(text); scroller.appendChild(note); lastSender = null;
                return;
            }
            var row = document.createElement('div'), bubble = document.createElement('div'), meta = document.createElement('div'), time = document.createElement('time');
            row.className = 'chat-msg is-new ' + (m.mine ? 'mine' : 'theirs'); row.dataset.id = m.id; row.dataset.sender = m.sender || '';
            if (!m.mine && m.name && (m.sender || '') !== lastSender) {
                var who = document.createElement('div'); who.className = 'chat-sender'; who.textContent = m.name;
                if (m.staff) { var badge = document.createElement('span'); badge.className = 'chat-staff'; badge.textContent = 'Staff'; who.appendChild(document.createTextNode(' ')); who.appendChild(badge); }
                row.appendChild(who);
            }
            bubble.className = 'chat-bubble'; bubble.textContent = m.body;
            meta.className = 'chat-meta'; time.textContent = m.time; time.title = m.full; meta.appendChild(time);
            if (m.mine && ticks) {
                var t = document.createElement('span'); t.className = 'chat-tick'; t.innerHTML = '<i class="bi bi-check2"></i>'; meta.appendChild(t);
            }
            row.appendChild(bubble); row.appendChild(meta); scroller.appendChild(row); tick(row);
            lastSender = m.sender || '';
        };
        var poll = function () {
            if (busy) { return; }
            busy = true;
            var stick = nearBottom();
            fetch(withParams('chat_poll=1&after=' + lastId + (document.visibilityState === 'visible' ? '&visible=1' : '')), { headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin', cache: 'no-store' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    (data.messages || []).forEach(add);
                    seen = Math.max(seen, data.seen || 0);
                    scroller.querySelectorAll('.chat-msg.mine').forEach(tick);
                    if (stick && data.messages && data.messages.length) { toBottom(); }
                })
                .catch(function () {})
                .then(function () { busy = false; });
        };
        toBottom();
        setInterval(function () { if (document.visibilityState === 'visible') { poll(); } }, 4000);
        document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') { poll(); } });
        if (!form) { return; }
        var grow = function () { input.style.height = 'auto'; input.style.height = Math.min(input.scrollHeight, 150) + 'px'; };
        input.addEventListener('input', function () { grow(); showError(''); });
        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' && !ev.shiftKey && !ev.isComposing) { ev.preventDefault(); if (form.requestSubmit) { form.requestSubmit(); } else { form.dispatchEvent(new Event('submit', { cancelable: true })); } }
        });
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            if (!input.value.trim()) { input.focus(); return; }
            var button = form.querySelector('.chat-send');
            button.disabled = true;
            fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'fetch' }, credentials: 'same-origin' })
                .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
                .then(function (res) {
                    if (res.ok && res.data.message) { add(res.data.message); input.value = ''; grow(); showError(''); toBottom(); }
                    else { showError(res.data.error || 'The message could not be sent.'); }
                })
                .catch(function () { showError('The message could not be sent. Check your connection, or reload the page if you were away for a while.'); })
                .then(function () { button.disabled = false; input.focus(); });
        });
    });
    // <input data-filter-list="#list"> hides [data-filter-text] items in #list that don't match
    document.querySelectorAll('[data-filter-list]').forEach(function (input) {
        var list = document.querySelector(input.dataset.filterList);
        input.addEventListener('input', function () {
            var q = input.value.trim().toLowerCase();
            list.querySelectorAll('[data-filter-text]').forEach(function (item) { item.hidden = q !== '' && item.dataset.filterText.indexOf(q) < 0; });
        });
    });
    // dialogs marked data-autoshow open on load (e.g. to show validation errors again)
    document.querySelectorAll('.modal[data-autoshow]').forEach(function (m) { if (window.bootstrap) { bootstrap.Modal.getOrCreateInstance(m).show(); } });
    // "/" focuses the admin search
    var adminSearch = document.getElementById('adminSearch');
    if (adminSearch) {
        document.addEventListener('keydown', function (ev) {
            if (ev.key === '/' && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName)) { ev.preventDefault(); adminSearch.focus(); }
        });
    }
})();
</script>
JS;
    echo '</body></html>';
}
