<?php
declare(strict_types=1);
require __DIR__ . '/../lib.php';
start_session();

$token = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$errorMessage = null;
$showConfirmation = false;

/* ----- trin 2: nyt kodeord via link fra mail ----- */
if ($token !== '') {
    $tokenRow = token_get('reset', $token);
    if ($tokenRow !== null && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $newPassword = (string)($_POST['new'] ?? '');
        $newPasswordRepeat = (string)($_POST['new2'] ?? '');
        if (!csrf_check()) {
            $errorMessage = t('msg_csrf_reload');
        } elseif ($newPassword !== $newPasswordRepeat) {
            $errorMessage = t('pw_mismatch');
        } elseif (($passwordError = validate_password($newPassword)) !== null) {
            $errorMessage = $passwordError;
        } elseif (user_set_hash($tokenRow['email'], make_hash($newPassword))) {
            token_delete($token);
            $showConfirmation = true;
        } else {
            $errorMessage = t('acc_change_fail');
        }
    }
    page_head(t('rst_new_title'));
    if ($showConfirmation) {
        msg(t('acc_changed'), 'ok');
        echo '<p><a href="login.php">' . th('nav_login') . '</a></p>';
    } elseif ($tokenRow === null) {
        msg(t('rst_invalid'));
        echo '<p><a href="reset.php">' . th('rst_new_link') . '</a></p>';
    } else {
        if ($errorMessage !== null) {
            msg($errorMessage);
        }
        ?>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="t" value="<?= h($token) ?>">
            <label><?= th('label_new') ?> <input type="password" name="new" required minlength="<?= (int)cfg('min_password') ?>" maxlength="72" autocomplete="new-password"></label>
            <label><?= th('label_new2') ?> <input type="password" name="new2" required maxlength="72" autocomplete="new-password"></label>
            <button><?= th('rst_save') ?></button>
        </form>
        <?php
    }
    page_foot();
    exit;
}

/* ----- trin 1: bed om link ----- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errorMessage = t('msg_csrf');
    } elseif (rate_limited('reset:' . client_ip(), 5, 3600)) {
        $errorMessage = t('msg_rate_ip');
    } else {
        $email = normalize_email((string)($_POST['email'] ?? ''));
        if ($email !== null && isset(htpasswd_load()[$email]) && mail_allowed($email)) {
            $tokenLifetime = (int)cfg('reset_ttl');
            $newToken = token_create('reset', $email, null, $tokenLifetime);
            send_mail($email, t('mail_reset_subject'),
                t('mail_reset_body', intdiv($tokenLifetime, 60),
                    cfg('base_url') . "/reset.php?t=$newToken&lang=" . lang()));
        }
        $showConfirmation = true; // samme svar uanset om adressen findes
    }
}

page_head(t('link_forgot'));
if ($showConfirmation) {
    msg(t('rst_sent'), 'ok');
} else {
    if ($errorMessage !== null) {
        msg($errorMessage);
    }
    ?>
    <form method="post">
        <?= csrf_field() ?>
        <label><?= th('label_email') ?> <input type="email" name="email" required maxlength="254" autocomplete="email"></label>
        <button><?= th('rst_send') ?></button>
    </form>
    <?php
}
page_foot();
