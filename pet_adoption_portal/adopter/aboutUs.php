<?php
// The About page is shared with the public site; aboutUs.php picks the adopter header for logged-in adopters.
require_once __DIR__ . '/dbconnect.php';
require_adopter();
require __DIR__ . '/../aboutUs.php';
