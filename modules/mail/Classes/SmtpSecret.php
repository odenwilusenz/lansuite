<?php

namespace LanSuite\Module\Mail;

use LanSuite\Module\Install\Install;

/**
 * Encrypts the SMTP password for the config table.
 * The key lives in inc/base/config.php, which is not stored in git.
 */
class SmtpSecret
{
    private const PREFIX = 'enc1:';

    private const CIPHER = 'aes-256-gcm';

    /**
     * Create [mail] secret in the gitignored config file when it is missing.
     */
    public static function ensureKey(): bool
    {
        global $config;

        if (!empty($config['mail']['secret'])) {
            return true;
        }

        $config['mail']['secret'] = 'k' . bin2hex(random_bytes(32));
        $install = new Install();

        return $install->WriteConfig() !== false;
    }

    public static function encrypt(string $plaintext): string
    {
        $key = self::rawKey();
        if ($key === null) {
            return '';
        }

        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plaintext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($cipher === false || $tag === '') {
            return '';
        }

        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    /**
     * Values without the enc1 prefix are legacy plaintext and are returned unchanged.
     */
    public static function decrypt(string $stored): string
    {
        if ($stored === '' || !str_starts_with($stored, self::PREFIX)) {
            return $stored;
        }

        $key = self::rawKey();
        if ($key === null) {
            return '';
        }

        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 29) {
            return '';
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);

        return $plain === false ? '' : $plain;
    }

    /**
     * Remove the SMTP password and the config secret from a message before it is logged.
     */
    public static function redact(string $message): string
    {
        global $cfg, $config;

        $secrets = [];
        if (!empty($config['mail']['secret'])) {
            $secrets[] = (string) $config['mail']['secret'];
        }
        if (!empty($cfg['mail_smtp_pass'])) {
            $stored = (string) $cfg['mail_smtp_pass'];
            $secrets[] = $stored;
            $plain = self::decrypt($stored);
            if ($plain !== '' && $plain !== $stored) {
                $secrets[] = $plain;
            } elseif ($plain !== '' && !str_starts_with($stored, self::PREFIX)) {
                $secrets[] = $plain;
            }
        }

        foreach ($secrets as $secret) {
            if ($secret !== '') {
                $message = str_replace($secret, '[redacted]', $message);
            }
        }

        return $message;
    }

    private static function rawKey(): ?string
    {
        global $config;

        $secret = (string) ($config['mail']['secret'] ?? '');
        if ($secret === '') {
            return null;
        }

        return hash('sha256', $secret, true);
    }
}
