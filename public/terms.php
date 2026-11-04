<?php
declare(strict_types=1);
require __DIR__ . '/../lib.php';
require __DIR__ . '/../legal.php';
start_session();

page_head(t('terms_title'));
legal_render('terms');
page_foot();
