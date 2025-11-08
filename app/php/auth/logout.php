<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/session.php';
Session::start();
Session::logout();
header('Location: /siged/public/index.php?action=login');
