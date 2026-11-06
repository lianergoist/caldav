<?php
declare(strict_types=1);
require __DIR__ . '/../lib.php';
start_session();

$token = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$tokenRow = token_get('register', $token);
$state = $tokenRow === null ? 'invalid' : 'form';

if ($tokenRow !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $state = 'expired';
    } else {
        $createResult = user_create($tokenRow['email'], (string)$tokenRow['pw_hash']);
        token_delete($token);
        if ($createResult === 'ok') {
            activity_touch($tokenRow['email'], lang());
        }
        $state = $createResult; // ok | exists | full | error
    }
}

page_head(t('ver_title'));
switch ($state) {
    case 'form':
        ?>
        <p><?= th('ver_intro') ?><strong><?= h($tokenRow['email']) ?></strong>.</p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="t" value="<?= h($token) ?>">
            <button><?= th('ver_submit') ?></button>
        </form>
        <?php
        break;
    case 'ok':
        msg(t('ver_ok'), 'ok');
        echo '<p><a href="login.php">' . th('nav_login') . '</a></p>';
        break;
    case 'exists':
        msg(t('ver_exists'));
        echo '<p><a href="login.php">' . th('nav_login') . '</a> · <a href="reset.php">' . th('link_forgot') . '</a></p>';
        break;
    case 'full':
        msg(t('reg_closed'));
        echo '<p><a href="register.php">' . th('wl_title') . '</a></p>';
        break;
    case 'expired':
        msg(t('ver_expired'));
        break;
    case 'error':
        msg(t('ver_error'));
        break;
    default:
        msg(t('ver_invalid'));
        echo '<p><a href="register.php">' . th('link_to_register') . '</a></p>';
}
page_foot();
