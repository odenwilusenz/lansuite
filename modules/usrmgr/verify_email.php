<?php

$stepParameter = $_GET['step'] ?? 0;
switch ($stepParameter) {
    default:
        $dsp->NewContent(t('Email-Adresse verifizieren'), '');

        $verificationCode = (string) ($_GET['verification_code'] ?? '');
        $user_data = $database->queryWithOnlyFirstRow('SELECT userid, fcode FROM %prefix%user WHERE fcode = ?', [$verificationCode]);
        if (!is_array($user_data) || $user_data['fcode'] === '' || $verificationCode === '') {
            $func->error(t('Der von dir Übermittelte Verifizierungscode ist inkorrekt! Bitte prüfe, ob du die URL komplett aus der Benachrichtigungs-Mail kopiert hast.'));
            break;
        }

        if (empty($cfg['signon_autopw'])) {
            $database->query('UPDATE %prefix%user SET email_verified = 1, fcode = \'\' WHERE fcode = ?', [$verificationCode]);
            $func->confirmation(t('Deine Email-Adresse wurde erfolgreich verifiziert. Vielen Dank!'), 'index.php');
            break;
        }

        $newPassword = (string) ($_POST['new_password'] ?? '');
        $newPasswordRepeat = (string) ($_POST['new_password2'] ?? '');
        if ($newPassword !== '' || $newPasswordRepeat !== '') {
            if ($newPassword === '' || $newPassword !== $newPasswordRepeat) {
                $func->error(t('Die Passwörter stimmen nicht überein oder sind leer.'), NO_LINK);
            } else {
                $database->query(
                    "UPDATE %prefix%user SET password = ?, email_verified = 1, fcode = '' WHERE fcode = ?",
                    [md5($newPassword), $verificationCode]
                );
                $func->confirmation(t('Deine Email-Adresse wurde erfolgreich verifiziert und dein Passwort wurde gespeichert.'), 'index.php');
                break;
            }
        }
        $dsp->SetForm('index.php?mod=usrmgr&action=verify_email&verification_code=' . urlencode($verificationCode));
        $dsp->AddSingleRow(t('Bitte lege ein Passwort fest. Damit bestätigst du auch deine E-Mail-Adresse.'));
        $dsp->AddPasswordRow('new_password', t('Neues Passwort'), '', '');
        $dsp->AddPasswordRow('new_password2', t('Neues Passwort wiederholen'), '', '');
        $dsp->AddFormSubmitRow(t('Passwort setzen'));
        break;

    case 2:
        $mail = new \LanSuite\Module\Mail\Mail();
        $usrmgr = new \LanSuite\Module\UsrMgr\UserManager($mail);
        if ($usrmgr->SendVerificationEmail($_GET['userid'])) {
            $func->confirmation(t('Die Verifikations-Email ist versandt worden.'));
        }
        break;
}
