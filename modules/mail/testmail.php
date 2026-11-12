<?php

if (($auth['type'] ?? 0) < \LS_AUTH_TYPE_ADMIN) {
    $func->information('ACCESS_DENIED', NO_LINK);
    return;
}

$mail = new \LanSuite\Module\Mail\Mail();
$stepParameter = $_GET['step'] ?? 0;
$recipient = trim(stripslashes((string) ($_POST['to'] ?? ($cfg['sys_party_mail'] ?? ''))));

if ((int) $stepParameter === 2) {
    $sent = $mail->create_inet_mail(
        '',
        $recipient,
        t('LanSuite Testmail'),
        t('Dies ist eine Testmail aus LanSuite. Der Versand hat funktioniert.'),
        $cfg['sys_party_mail'] ?? ''
    );
    if ($sent) {
        $func->confirmation(t('Die Testmail wurde an %1 gesendet.', $recipient), NO_LINK);
    } else {
        $detail = $mail->technicalError !== '' ? '<br />' . htmlspecialchars($mail->technicalError, ENT_QUOTES, 'UTF-8') : '';
        $func->error($mail->error . $detail, NO_LINK);
    }
}

$dsp->NewContent(t('Testmail'), t('Sendet eine Internet-Mail mit den aktuellen SMTP- oder PHP-mail()-Einstellungen.'));
$dsp->SetForm('index.php?mod=mail&action=testmail&step=2');
$dsp->AddTextFieldRow('to', t('Empfänger'), htmlspecialchars($recipient, ENT_QUOTES, 'UTF-8'), '');
$dsp->AddFormSubmitRow(t('Testmail senden'));
$dsp->AddSingleRow(t('Absender ist die Kontaktmail der Lanparty. Zugangsdaten werden nicht angezeigt.'));
