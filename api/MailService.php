<?php
declare(strict_types=1);

final class MailService
{
    public static function send(string $to, string $subject, string $html, string $text = ''): bool
    {
        $from = env('EMAIL_FROM', 'paduamusical@gmail.com');

        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: Hotspot PIX <' . $from . '>',
            'Reply-To: ' . $from,
            'X-Mailer: PHP/' . phpversion(),
        ];

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $sent = mail($to, $encodedSubject, $html, implode("\r\n", $headers));

        if (!$sent) {
            error_log('MailService: Falha ao enviar e-mail para ' . $to);
            return false;
        }

        return true;
    }
}
