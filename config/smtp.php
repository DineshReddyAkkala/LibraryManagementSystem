<?php
$SMTP_HOST = 'smtp.gmail.com';
$SMTP_PORT = 587;
$SMTP_USERNAME = 'vmkavula@gmail.com';
$SMTP_PASSWORD = 'zxlkycjehpsylolf';
$SMTP_FROM_EMAIL = 'vmkavula@gmail.com';
$SMTP_FROM_NAME = 'Library Management System';

function smtp_send($toEmail, $toName, $subject, $body)
{
    global $SMTP_HOST, $SMTP_PORT, $SMTP_USERNAME, $SMTP_PASSWORD, $SMTP_FROM_EMAIL, $SMTP_FROM_NAME;
    $socket = fsockopen($SMTP_HOST, $SMTP_PORT, $errno, $errstr, 10);
    if (!$socket) {
        return false;
    }

    $read = function () use ($socket) {
        return fgets($socket, 515);
    };

    $write = function ($command) use ($socket) {
        fwrite($socket, $command . "\r\n");
    };

    $expect = function ($codes) use ($read) {
        $line = $read();
        if ($line === false) {
            return false;
        }
        $code = (int)substr($line, 0, 3);
        if (!in_array($code, (array)$codes, true)) {
            return false;
        }
        while (isset($line[3]) && $line[3] === '-') {
            $line = $read();
            if ($line === false) {
                return false;
            }
        }
        return true;
    };

    if (!$expect([220])) {
        fclose($socket);
        return false;
    }

    $write('EHLO localhost');
    if (!$expect([250])) {
        fclose($socket);
        return false;
    }

    $write('STARTTLS');
    if (!$expect([220])) {
        fclose($socket);
        return false;
    }

    if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
        fclose($socket);
        return false;
    }

    $write('EHLO localhost');
    if (!$expect([250])) {
        fclose($socket);
        return false;
    }

    $write('AUTH LOGIN');
    if (!$expect([334])) {
        fclose($socket);
        return false;
    }

    $write(base64_encode($SMTP_USERNAME));
    if (!$expect([334])) {
        fclose($socket);
        return false;
    }

    $write(base64_encode($SMTP_PASSWORD));
    if (!$expect([235])) {
        fclose($socket);
        return false;
    }

    $write('MAIL FROM:<' . $SMTP_FROM_EMAIL . '>');
    if (!$expect([250])) {
        fclose($socket);
        return false;
    }

    $write('RCPT TO:<' . $toEmail . '>');
    if (!$expect([250, 251])) {
        fclose($socket);
        return false;
    }

    $write('DATA');
    if (!$expect([354])) {
        fclose($socket);
        return false;
    }

    $headers = [];
    $headers[] = 'From: ' . $SMTP_FROM_NAME . ' <' . $SMTP_FROM_EMAIL . '>';
    $headers[] = 'To: ' . $toName . ' <' . $toEmail . '>';
    $headers[] = 'Subject: ' . $subject;
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=utf-8';

    $data = implode("\r\n", $headers) . "\r\n\r\n" . $body;
    $data = str_replace(["\r\n.", "\n."], ["\r\n..", "\n.."], $data);
    $write($data . "\r\n.");

    if (!$expect([250])) {
        fclose($socket);
        return false;
    }

    $write('QUIT');
    fclose($socket);
    return true;
}
