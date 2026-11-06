<?php
declare(strict_types=1);
require __DIR__ . '/../lib.php';
require __DIR__ . '/../home_layout.php';   // knaptekster og login-note deles med forsiden
start_session();

$user = require_login();
$errorMessage = null;
$deleted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $current = (string)($_POST['current'] ?? '');
    if (!csrf_check()) {
        $errorMessage = t('msg_csrf');
    } elseif (rate_limited('acct:' . $user, 10, 900)) {
        $errorMessage = t('msg_rate');
    } elseif (strlen($current) === 0 || strlen($current) > 72 || !user_verify($user, $current)) {
        $errorMessage = t('acc_current_wrong');
    } elseif ($action === 'delete') {
        if (empty($_POST['confirm'])) {
            $errorMessage = t('acc_confirm_needed');
        } elseif (!radicale_delete_data($user, $current)) {
            $errorMessage = t('acc_del_data_fail');
        } elseif (!user_remove($user)) {
            $errorMessage = t('acc_del_acct_fail');
        } else {
            activity_forget($user);
            do_logout();
            $deleted = true;
            try {
                waitlist_send_invites(); // den ledige plads går til den næste på ventelisten
            } catch (Throwable $e) {
                error_log('caldav waitlist: ' . $e->getMessage()); // cron prøver igen inden for en time
            }
        }
    }
}

if ($deleted) {
    page_head(t('acc_deleted_title'));
    msg(t('acc_deleted'), 'ok');
    echo '<p><a href="index.php">' . th('link_home') . '</a></p>';
    page_foot();
    exit;
}

page_head(t('acc_title'));
if ($errorMessage !== null) {
    msg($errorMessage);
}
$url = rtrim((string)cfg('radicale_url'), '/') . '/' . $user . '/'; // @ er gyldigt i en URL-sti; brugernavnet er valideret i normalize_email()
?>
<div class="actions">
	<a class="btn btn--slate" href="<?= h(rtrim((string)cfg('radicale_url'), '/')) ?>/.web/"><?= h(home_t('radicale_button')) ?></a>
    <a class="btn btn--slate" href="<?= h(HOME_NOTES) ?>"><?= h(home_t('notes_button')) ?></a>
</div>
<?= home_notes_hint() ?>

<h2><?= th('acc_conn') ?></h2>
<p><?= th('acc_username') ?>: <strong><?= h($user) ?></strong><br>
<?= th('acc_server') ?>: <code>https://<?= h((string)parse_url((string)cfg('radicale_url'), PHP_URL_HOST)) ?></code><p>
<span class="small"><?= sprintf(th('acc_server_alt'), '<br><code>' . h(rtrim((string)cfg('radicale_url'), '/')) . '/</code>') ?></span></p>
<!--
<br>
<?= th('acc_collection') ?>: <code><?= h($url) ?></code></p>
-->


<h2><?= th('acc_del_h') ?></h2>
<p><?= th('acc_del_text') ?></p>
<form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <label><?= th('label_password') ?> <input type="password" name="current" required maxlength="72" autocomplete="current-password"></label>
    <label class="check"><input type="checkbox" name="confirm" value="1"> <?= th('acc_del_check') ?></label>
    <button class="danger"><?= th('acc_del_btn') ?></button>
</form>
<?php
page_foot();
