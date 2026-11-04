<?php
declare(strict_types=1);
require __DIR__ . '/../lib.php';
start_session();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    do_logout();
}
header('Location: index.php');
exit;
