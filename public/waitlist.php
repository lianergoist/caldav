<?php
declare(strict_types=1);
require __DIR__ . '/../lib.php';
start_session();

/*
 * Ventelisten:
 *  ?t=<token>      bekræft, at du vil på listen (linket fra mailen, som register.php sender)
 *  ?leave=<token>  meld dig af listen (linket i mailene, når du står på listen)
 * Begge dele kræver et klik på en knap (POST), så mailprogrammer, der åbner links af sig selv,
 * ikke kommer til at gøre det.
 */

$confirmToken = (string)($_GET['t'] ?? $_POST['t'] ?? '');
$leaveToken = (string)($_GET['leave'] ?? $_POST['leave'] ?? '');
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
$tokenRow = $confirmToken !== '' ? token_get('waitlist', $confirmToken) : null;

page_head(t('wl_title'));

if ($leaveToken !== '') {
    if ($isPost && csrf_check()) {
        if (waitlist_leave($leaveToken)) {
            msg(t('wl_left'), 'ok');
        } else {
            msg(t('wl_invalid'));
        }
    } else {
        ?>
        <p><?= th('wl_leave_intro') ?></p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="leave" value="<?= h($leaveToken) ?>">
            <button class="danger"><?= th('wl_leave_submit') ?></button>
        </form>
        <?php
    }
} elseif ($tokenRow === null) {
    msg(t('wl_invalid'));
    echo '<p><a href="register.php">' . th('link_to_register') . '</a></p>';
} elseif ($isPost && csrf_check()) {
    $email = (string)$tokenRow['email'];
    token_delete($confirmToken);
    if (isset(htpasswd_load()[$email])) {
        msg(t('ver_exists'));
        echo '<p><a href="login.php">' . th('nav_login') . '</a></p>';
    } else {
        [$status, $position] = waitlist_add($email, lang());
        if ($status === 'added') {
            $leave = waitlist_new_leave_token($email);
            send_mail($email, t('mail_wl_added_subject'), t('mail_wl_added_body', $position,
                cfg('base_url') . "/waitlist.php?leave=$leave&lang=" . lang()));
            msg(t('wl_added', $position), 'ok');
        } else {
            msg(t('wl_already', $position), 'ok');
        }
        waitlist_send_invites(); // er der allerede en ledig plads, kommer invitationen med det samme
    }
} else {
    ?>
    <p><?= th('wl_confirm_intro') ?><strong><?= h((string)$tokenRow['email']) ?></strong>.</p>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="t" value="<?= h($confirmToken) ?>">
        <button><?= th('wl_confirm_submit') ?></button>
    </form>
    <?php
}
page_foot();
