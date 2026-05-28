<?php
namespace MarketingAgent\Service;

use Exception;

class SmtpService {
    /**
     * Sends an email via SMTP socket connection.
     */
    public static function send(
        string $host,
        int $port,
        ?string $username,
        ?string $password,
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $subject,
        string $body
    ): void {
        // Resolve security prefix
        $security = '';
        if ($port === 465) {
            $security = 'ssl://';
        }

        // Open socket connection
        $socket = @fsockopen($security . $host, $port, $errno, $errstr, 15);
        if (!$socket) {
            throw new Exception("Could not connect to SMTP server {$host}:{$port} - Error: {$errstr} ({$errno})");
        }

        // Helper to read and validate SMTP response codes
        $expect = function(int $code) use ($socket) {
            $response = '';
            while ($str = fgets($socket, 515)) {
                $response .= $str;
                // SMTP multi-line response terminates when index 3 is space
                if (substr($str, 3, 1) === ' ') {
                    break;
                }
            }
            if (substr($response, 0, 3) !== (string)$code) {
                throw new Exception("SMTP Protocol Error: Expected {$code}, got: " . trim($response));
            }
            return $response;
        };

        // Welcome code (220)
        $expect(220);

        // Send EHLO
        fwrite($socket, "EHLO " . gethostname() . "\r\n");
        $ehloResponse = $expect(250);

        // Handle STARTTLS on port 587 or if server indicates support
        if ($port === 587 || ($port !== 465 && strpos($ehloResponse, 'STARTTLS') !== false)) {
            fwrite($socket, "STARTTLS\r\n");
            $expect(220);

            // Enable crypto on existing socket
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception("Failed to enable TLS encryption on SMTP socket.");
            }

            // Resend EHLO over encrypted socket
            fwrite($socket, "EHLO " . gethostname() . "\r\n");
            $expect(250);
        }

        // Authenticate if credentials are provided
        if (!empty($username) && !empty($password)) {
            fwrite($socket, "AUTH LOGIN\r\n");
            $expect(334);

            fwrite($socket, base64_encode($username) . "\r\n");
            $expect(334);

            fwrite($socket, base64_encode($password) . "\r\n");
            $expect(235);
        }

        // MAIL FROM
        fwrite($socket, "MAIL FROM:<{$fromEmail}>\r\n");
        $expect(250);

        // RCPT TO
        fwrite($socket, "RCPT TO:<{$toEmail}>\r\n");
        $expect(250);

        // DATA
        fwrite($socket, "DATA\r\n");
        $expect(354);

        // Build email headers
        $boundary = '----=' . md5(uniqid(microtime(), true));
        $headers = [
            "MIME-Version: 1.0",
            "Content-Type: text/plain; charset=UTF-8",
            "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$fromEmail}>",
            "To: <{$toEmail}>",
            "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
            "Date: " . date('r'),
            "Message-ID: <" . md5(uniqid(microtime(), true)) . "@" . $host . ">",
            "",
            $body
        ];

        $message = implode("\r\n", $headers);
        
        // Escape lone dots on a line according to RFC 5321 section 4.5.2
        $message = str_replace("\r\n.", "\r\n..", $message);

        // Write message body and finish data sequence with a dot
        fwrite($socket, $message . "\r\n.\r\n");
        $expect(250);

        // Send QUIT and close
        fwrite($socket, "QUIT\r\n");
        fclose($socket);
    }
}
