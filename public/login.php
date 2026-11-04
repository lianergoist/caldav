<?php
declare(strict_types=1);
require __DIR__ . '/../lib.php';
start_session();

if (current_user() !== null) {
    header('Location: account.php');
    exit;
}

$errorMessage = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = normalize_email((string)($_POST['email'] ?? '')) ?? strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    if (!csrf_check()) {
        $errorMessage = t('msg_csrf');
    } elseif (rate_limited('login-ip:' . client_ip(), 15, 900)
        || rate_limited('login:' . $email, 10, 900)) {
        $errorMessage = t('msg_rate');
    } elseif (strlen($password) > 0 && strlen($password) <= 72 && user_verify($email, $password)) {
        session_regenerate_id(true);
        $_SESSION['user'] = $email;
        activity_touch($email, lang());
        header('Location: account.php');
        exit;
    } else {
        $errorMessage = t('login_bad');
    }
}

page_head(t('login_title'));
if ($errorMessage !== null) {
    msg($errorMessage);
}
?>
<form method="post">
    <?= csrf_field() ?>
    <label><?= th('label_email') ?> <input type="text" name="email" required maxlength="254" autocomplete="username"></label>
    <label><?= th('label_password') ?> <input type="password" name="password" required maxlength="72" autocomplete="current-password"></label>
    <button><?= th('login_title') ?></button>
</form>
<p class="small"><a href="reset.php"><?= th('link_forgot') ?></a> · <a href="register.php"><?= th('reg_title') ?></a></p>
<?php
page_foot();
