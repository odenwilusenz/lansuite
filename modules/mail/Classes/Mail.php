<?php

namespace LanSuite\Module\Mail;

use PHPMailer\PHPMailer\Exception as PhpMailerException;
use PHPMailer\PHPMailer\PHPMailer;

class Mail
{
    /**
     * Message safe to show to any user. No SMTP details.
     *
     * @var string
     */
    public $error;

    /**
     * Redacted technical detail. Only the admin test mail may display this.
     *
     * @var string
     */
    public $technicalError = '';

    /**
     * @param int $from_userid
     * @param int $to_userid
     * @param string $subject_text
     * @param string $msgbody_text
     * @return bool
     */
    public function create_mail($from_userid, $to_userid, $subject_text, $msgbody_text)
    {
        global $database, $cfg;

        if ($from_userid == "") {
            $this->error = t('Sys-Mail Fehler: Kein Absender angegeben');
            return false;
        }
        if ($to_userid == "") {
            $this->error = t('Sys-Mail Fehler: Kein Empfänger angegeben');
            return false;
        }

        $database->query("
          INSERT INTO %prefix%mail_messages
          SET
            mail_status = 'active',
            des_status = 'new',
            fromUserID = ?,
            toUserID = ?,
            Subject= ?,
            msgbody= ?,
            tx_date= NOW()", [$from_userid, $to_userid, $subject_text, $msgbody_text]);
        $this->error = 'OK';

        // Send Info-Mail to receiver
        if ($cfg['sys_internet']) {
            $row = $database->queryWithOnlyFirstRow('SELECT u.username, u.email, u.lsmail_alert FROM %prefix%user AS u WHERE u.userid = ?', [$to_userid]);
            if ($row['lsmail_alert']) {
                $this->create_inet_mail($row['username'], $row['email'], t('Benachrichtigung: Neue LS-Mail'), t('Du hast eine neue Lansuite-Mail erhalten. Diese Benachrichtigung kkannst du im System unter "Meine Einstellungen" deaktivieren'));
            }
        }

        $this->error = 'OK';

        return true;
    }

    /**
     * @param int $to_userid
     * @param string $subject_text
     * @param string $msgbody_text
     * @return bool
     */
    public function create_sys_mail($to_userid, $subject_text, $msgbody_text)
    {
        if ($this->create_mail("0", $to_userid, $subject_text, $msgbody_text)) {
            return true;
        }

        return false;
    }

    /**
     * @param string $to_user_name
     * @param string $to_user_email
     * @param string $subject_text
     * @param string $msgbody_text
     * @param string $from
     * @return bool
     */
    public function create_inet_mail($to_user_name, $to_user_email, $subject_text, $msgbody_text, $from = '')
    {
        global $cfg;

        $this->technicalError = '';

        if (!$from) {
            $from = $cfg['sys_party_mail'] ?? '';
        }
        $from = trim((string) $from);

        if (empty($cfg['sys_internet'])) {
            return $this->failMail(
                t('Um Internet-Mails zu versenden, muss sich Lansuite im Internet-Modus befinden'),
                'Internet-Modus ist aus'
            );
        }

        if ($from === '' || filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
            return $this->failMail(
                t('Es ist keine gültige Absender-Adresse konfiguriert. Bitte setze die Kontaktmail der Lanparty.'),
                'Absender fehlt oder ist ungültig'
            );
        }

        $to_user_email = trim((string) $to_user_email);
        if ($to_user_email === '' || filter_var($to_user_email, FILTER_VALIDATE_EMAIL) === false) {
            return $this->failMail(
                t('Die E-Mail konnte nicht gesendet werden. Die Empfängeradresse ist ungültig.'),
                'Empfängeradresse ist ungültig'
            );
        }

        $charset = !empty($cfg['mail_utf8']) ? PHPMailer::CHARSET_UTF8 : 'ISO-8859-1';
        if ($charset === 'ISO-8859-1') {
            $subject_text = mb_convert_encoding($subject_text, 'ISO-8859-1');
            $msgbody_text = mb_convert_encoding($msgbody_text, 'ISO-8859-1');
        }
        $msgbody_text = str_replace("\r", '', (string) $msgbody_text);

        $phpmailer = new PHPMailer(true);
        try {
            $phpmailer->CharSet = $charset;
            $phpmailer->Timeout = 20;
            $phpmailer->setFrom($from);
            $phpmailer->addAddress($to_user_email, (string) $to_user_name);
            $phpmailer->Subject = (string) $subject_text;
            $phpmailer->Body = $msgbody_text;
            $phpmailer->isHTML(false);

            if (!empty($cfg['mail_use_smtp'])) {
                $storedPassword = (string) ($cfg['mail_smtp_pass'] ?? '');
                $password = SmtpSecret::decrypt($storedPassword);
                if ($storedPassword !== '' && str_starts_with($storedPassword, 'enc1:') && $password === '') {
                    return $this->failMail(
                        t('Das gespeicherte SMTP-Passwort konnte nicht gelesen werden.'),
                        'SMTP-Passwort konnte nicht entschlüsselt werden'
                    );
                }

                $phpmailer->isSMTP();
                $phpmailer->Host = (string) ($cfg['mail_smtp_host'] ?? '');
                $port = (int) ($cfg['mail_smtp_port'] ?? 587);
                $phpmailer->Port = $port > 0 ? $port : 587;

                $encryption = strtolower((string) ($cfg['mail_smtp_encryption'] ?? 'tls'));
                if ($encryption === 'ssl') {
                    $phpmailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                } elseif ($encryption === 'tls') {
                    $phpmailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                } else {
                    $phpmailer->SMTPSecure = '';
                    $phpmailer->SMTPAutoTLS = false;
                }

                $username = (string) ($cfg['mail_smtp_user'] ?? '');
                if ($username !== '') {
                    $phpmailer->SMTPAuth = true;
                    $phpmailer->Username = $username;
                    $phpmailer->Password = $password;
                }
            } else {
                $phpmailer->isMail();
            }

            $phpmailer->send();
            $this->error = '';

            return true;
        } catch (PhpMailerException $e) {
            $detail = SmtpSecret::redact($phpmailer->ErrorInfo !== '' ? $phpmailer->ErrorInfo : $e->getMessage());

            return $this->failMail(
                t('Die E-Mail konnte nicht gesendet werden. Bitte versuche es später erneut oder wende dich an die Organisation.'),
                $detail
            );
        }
    }

    private function failMail(string $userMessage, string $technicalDetail): bool
    {
        global $func;

        $safeDetail = SmtpSecret::redact($technicalDetail);
        $this->error = $userMessage;
        $this->technicalError = $safeDetail;
        if (isset($func)) {
            $func->log_event('Mailversand fehlgeschlagen: ' . $safeDetail, 3, 'Mail');
        }

        return false;
    }
}
