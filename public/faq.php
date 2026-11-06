<?php
declare(strict_types=1);
require __DIR__ . '/../lib.php';
require __DIR__ . '/../legal.php';
start_session();

page_head(t('faq_title'));
legal_render('faq');
page_foot();
