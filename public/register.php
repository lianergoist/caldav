<?php
declare(strict_types=1);
require __DIR__ . '/../lib.php';
start_session();

$errorMessage = null;
$showConfirmation = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $errorMessage = t('msg_csrf');
    } elseif (!empty($_POST['website'])) {
        $showConfirmation = true; // honeypot: lad som om det lykkedes
    } elseif (rate_limited('reg:' . client_ip(), 5, 3600)) {
        $errorMessage = t('msg_rate_ip');
    } else {
        $email = normalize_email((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $passwordRepeat = (string)($_POST['password2'] ?? '');
        if (empty($_POST['accept'])) {
            $errorMessage = t('reg_accept_needed');
        } elseif ($email === null) {
            $errorMessage = t('reg_invalid_email');
        } elseif ($password !== $passwordRepeat) {
            $errorMessage = t('pw_mismatch');
        } elseif (($passwordError = validate_password($password)) !== null) {
            $errorMessage = $passwordError;
        } else {
            $users = htpasswd_load();
            if (isset($users[$email])) {
                if (mail_allowed($email)) {
                    send_mail($email, t('mail_exists_subject'),
                        t('mail_exists_body', cfg('base_url') . '/reset.php?lang=' . lang()));
                }
                $showConfirmation = true;
            } elseif (count($users) >= (int)cfg('max_users')) {
                $errorMessage = t('reg_closed');
            } else {
                if (mail_allowed($email)) {
                    $tokenLifetime = (int)cfg('register_ttl');
                    $token = token_create('register', $email, make_hash($password), $tokenLifetime);
                    send_mail($email, t('mail_confirm_subject'),
                        t('mail_confirm_body', intdiv($tokenLifetime, 3600),
                            cfg('base_url') . "/verify.php?t=$token&lang=" . lang()));
                }
                $showConfirmation = true;
            }
        }
    }
}

page_head(t('reg_title'));
if ($showConfirmation) {
    msg(t('reg_sent'), 'ok');
} else {
    if ($errorMessage !== null) {
        msg($errorMessage);
    }
    ?>
    <p><?= th('reg_intro') ?></p>
    <form method="post" autocomplete="on">
        <?= csrf_field() ?>
        <label><?= th('label_email') ?> <input type="email" name="email" required maxlength="254" autocomplete="email"></label>
        <label><?= th('label_password') ?> <input type="password" name="password" required minlength="<?= (int)cfg('min_password') ?>" maxlength="72" autocomplete="new-password"></label>
        <label><?= th('label_password2') ?> <input type="password" name="password2" required maxlength="72" autocomplete="new-password"></label>
        <div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <label class="check"><input type="checkbox" name="accept" value="1" required>
            <span><?= th('reg_accept_pre') ?><a href="terms.php" target="_blank" rel="noopener"><?= th('reg_accept_terms') ?></a><?= th('reg_accept_mid') ?><a href="privacy.php" target="_blank" rel="noopener"><?= th('reg_accept_privacy') ?></a><?= th('reg_accept_post') ?></span></label>
        <button><?= th('reg_submit') ?></button>
    </form>
    <p class="small"><?= th('reg_already') ?> <a href="login.php"><?= th('nav_login') ?></a> · <a href="reset.php"><?= th('link_forgot') ?></a></p>
    <?php
}
page_foot();
