<?php
declare(strict_types=1);
require __DIR__ . '/../lib.php';
start_session();

/*
 * Tre tilstande:
 *  - invitation: ?invite=<token> fra ventelistens mail. Adressen er allerede bekræftet, så kontoen
 *    oprettes med det samme på den plads, der er holdt til personen.
 *  - venteliste: alle pladser er optaget (eller lovet væk til ventelisten). Formularen tager kun en
 *    e-mailadresse, og personen skal bekræfte via mail (waitlist.php), før de kommer på listen.
 *  - almindelig tilmelding med bekræftelsesmail (verify.php).
 */

$errorMessage = null;
$confirmation = null;   // tekst, der vises i stedet for formularen

$inviteToken = (string)($_GET['invite'] ?? $_POST['invite'] ?? '');
$invite = $inviteToken !== '' ? token_get('invite', $inviteToken) : null;
$inviteInvalid = $inviteToken !== '' && $invite === null;
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

if ($invite !== null) {
    $mode = 'invite';
} else {
    $mode = registration_open() ? 'register' : 'waitlist';
}

if ($isPost && !csrf_check()) {
    $errorMessage = t('msg_csrf');
} elseif ($isPost && !empty($_POST['website'])) {
    $confirmation = $mode === 'waitlist' ? t('wl_sent') : t('reg_sent'); // honeypot: lad som om det lykkedes
} elseif ($isPost && $mode === 'invite') {
    $email = (string)$invite['email'];
    $password = (string)($_POST['password'] ?? '');
    if (rate_limited('reg:' . client_ip(), 5, 3600)) {
        $errorMessage = t('msg_rate_ip');
    } elseif (empty($_POST['accept'])) {
        $errorMessage = t('reg_accept_needed');
    } elseif ($password !== (string)($_POST['password2'] ?? '')) {
        $errorMessage = t('pw_mismatch');
    } elseif (($passwordError = validate_password($password)) !== null) {
        $errorMessage = $passwordError;
    } else {
        $createResult = user_create($email, make_hash($password), true);
        if ($createResult === 'ok' || $createResult === 'exists') {
            waitlist_remove($email);
            if ($createResult === 'ok') {
                activity_touch($email, lang());
            }
            $confirmation = $createResult === 'ok' ? t('ver_ok') : t('ver_exists');
        } else {
            $errorMessage = t('ver_error');
        }
    }
} elseif ($isPost && rate_limited('reg:' . client_ip(), 5, 3600)) {
    $errorMessage = t('msg_rate_ip');
} elseif ($isPost && $mode === 'waitlist') {
    $email = normalize_email((string)($_POST['email'] ?? ''));
    if ($email === null) {
        $errorMessage = t('reg_invalid_email');
    } else {
        if (mail_allowed($email)) {
            if (isset(htpasswd_load()[$email])) {
                send_mail($email, t('mail_exists_subject'),
                    t('mail_exists_body', cfg('base_url') . '/reset.php?lang=' . lang()));
            } else {
                $tokenLifetime = (int)cfg('register_ttl');
                $token = token_create('waitlist', $email, null, $tokenLifetime);
                send_mail($email, t('mail_wl_confirm_subject'),
                    t('mail_wl_confirm_body', intdiv($tokenLifetime, 3600),
                        cfg('base_url') . "/waitlist.php?t=$token&lang=" . lang()));
            }
        }
        $confirmation = t('wl_sent'); // samme svar uanset hvad, så man ikke kan se, hvem der står på listen
    }
} elseif ($isPost) {
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
            $confirmation = t('reg_sent');
        } else {
            if (mail_allowed($email)) {
                $tokenLifetime = (int)cfg('register_ttl');
                $token = token_create('register', $email, make_hash($password), $tokenLifetime);
                send_mail($email, t('mail_confirm_subject'),
                    t('mail_confirm_body', intdiv($tokenLifetime, 3600),
                        cfg('base_url') . "/verify.php?t=$token&lang=" . lang()));
            }
            $confirmation = t('reg_sent');
        }
    }
}

page_head($mode === 'waitlist' ? t('wl_title') : t('reg_title'));
if ($confirmation !== null) {
    msg($confirmation, 'ok');
    if ($mode === 'invite') {
        echo '<p><a href="login.php">' . th('nav_login') . '</a></p>';
    }
    page_foot();
    exit;
}
if ($inviteInvalid) {
    msg(t('inv_invalid'));
}
if ($errorMessage !== null) {
    msg($errorMessage);
}
$honeypot = '<div class="hp" aria-hidden="true"><label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';
$acceptBox = '<label class="check"><input type="checkbox" name="accept" value="1" required>'
    . '<span>' . th('reg_accept_pre') . '<a href="terms.php" target="_blank" rel="noopener">' . th('reg_accept_terms') . '</a>'
    . th('reg_accept_mid') . '<a href="privacy.php" target="_blank" rel="noopener">' . th('reg_accept_privacy') . '</a>'
    . th('reg_accept_post') . '</span></label>';
$minPassword = (int)cfg('min_password');

if ($mode === 'waitlist') {
    ?>
    <p><?= th('wl_full_intro', (int)cfg('max_users')) ?></p>
    <form method="post" autocomplete="on">
        <?= csrf_field() ?>
        <label><?= th('label_email') ?> <input type="email" name="email" required maxlength="254" autocomplete="email"></label>
        <?= $honeypot ?>
        <button><?= th('wl_submit') ?></button>
    </form>
    <p class="small"><?= th('wl_privacy_pre') ?><a href="privacy.php"><?= th('reg_accept_privacy') ?></a><?= th('reg_accept_post') ?></p>
    <p class="small"><?= th('reg_already') ?> <a href="login.php"><?= th('nav_login') ?></a> · <a href="reset.php"><?= th('link_forgot') ?></a></p>
    <?php
} elseif ($mode === 'invite') {
    ?>
    <p><?= th('inv_intro') ?><strong><?= h((string)$invite['email']) ?></strong>.</p>
    <form method="post" autocomplete="on">
        <?= csrf_field() ?>
        <input type="hidden" name="invite" value="<?= h($inviteToken) ?>">
        <input type="hidden" name="username" value="<?= h((string)$invite['email']) ?>" autocomplete="username">
        <label><?= th('label_password') ?> <input type="password" name="password" required minlength="<?= $minPassword ?>" maxlength="72" autocomplete="new-password"></label>
        <label><?= th('label_password2') ?> <input type="password" name="password2" required maxlength="72" autocomplete="new-password"></label>
        <?= $acceptBox ?>
        <button><?= th('reg_submit') ?></button>
    </form>
    <?php
} else {
    ?>
    <p><?= th('reg_intro') ?></p>
    <form method="post" autocomplete="on">
        <?= csrf_field() ?>
        <label><?= th('label_email') ?> <input type="email" name="email" required maxlength="254" autocomplete="email"></label>
        <label><?= th('label_password') ?> <input type="password" name="password" required minlength="<?= $minPassword ?>" maxlength="72" autocomplete="new-password"></label>
        <label><?= th('label_password2') ?> <input type="password" name="password2" required maxlength="72" autocomplete="new-password"></label>
        <?= $honeypot ?>
        <?= $acceptBox ?>
        <button><?= th('reg_submit') ?></button>
    </form>
    <p class="small"><?= th('reg_already') ?> <a href="login.php"><?= th('nav_login') ?></a> · <a href="reset.php"><?= th('link_forgot') ?></a></p>
    <?php
}
page_foot();
