<?php

use The\Route;

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/additionalenv.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/Routes/web.php';
new Route($route);
if ((session_status() === PHP_SESSION_ACTIVE) && isset($_SESSION['user_id'])) {
    session_regenerate_id();
}
