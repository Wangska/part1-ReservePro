<?php
// Mail helper for sending verification emails via PHPMailer + Mailtrap.
// You can set a Gmail address as the visible "From" so emails look like
// they come from Gmail while still going through Mailtrap's SMTP server.

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function sendVerificationEmail(string $toEmail, string $firstName, string $token): bool {
    if ($toEmail === '' || $token === '') {
        return false;
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/part1-ReservePro'), '/\\');
    if ($basePath === '.' || $basePath === '') {
        $basePath = '/part1-ReservePro';
    }
    $verifyUrl = $scheme . '://' . $host . $basePath . '/verify-email.php?token=' . urlencode($token);

    $name = $firstName !== '' ? $firstName : 'there';

    $subject = 'Verify your ReservePro account';
    $bodyText = "Hi {$name},\n\n"
              . "Thanks for creating an account on ReservePro.\n\n"
              . "Please confirm that this is your email address by clicking the link below:\n"
              . "{$verifyUrl}\n\n"
              . "If you didn’t create this account, you can ignore this email.\n\n"
              . "Best,\nReservePro";

    $bodyHtml = sprintf(
        '<p>Hi %s,</p>
        <p>Thanks for creating an account on <strong>ReservePro</strong>.</p>
        <p>Please confirm that this is your email address by clicking the button below:</p>
        <p><a href="%s" style="
            display:inline-block;
            padding:10px 18px;
            background:#D4A574;
            color:#111 !important;
            text-decoration:none;
            border-radius:6px;
            font-weight:600;
        ">Verify my email</a></p>
        <p>Or copy and paste this link into your browser:</p>
        <p><a href="%s">%s</a></p>
        <p>If you didn’t create this account, you can ignore this email.</p>
        <p>Best,<br>ReservePro</p>',
        htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8'),
        htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8')
    );

    $mail = new PHPMailer(true);

    try {
        // Mailtrap SMTP configuration (Email Testing inbox)
        $mail->isSMTP();
        $mail->Host       = 'sandbox.smtp.mailtrap.io';
        $mail->SMTPAuth   = true;
        $mail->Port       = 587;

        // TODO: Replace these with your Mailtrap SMTP credentials
        $mail->Username   = 'd248cc9f17f305';
        $mail->Password   = '2147b67e7fc8ff';

        // From / Reply-To – set this to your Gmail if you want Gmail shown as sender
        $mail->setFrom('your-gmail-address@gmail.com', 'ReservePro');
        $mail->addReplyTo('your-gmail-address@gmail.com', 'ReservePro');

        // Recipient
        $mail->addAddress($toEmail, $name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $bodyHtml;
        $mail->AltBody = $bodyText;

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Optional: log the error in development
        // error_log('Mail error: ' . $mail->ErrorInfo);
        return false;
    }
}

<?php
// Mail helper for sending verification emails via PHPMailer + Mailtrap.
// You can set a Gmail address as the visible "From" so emails look like
// they come from Gmail while still going through Mailtrap's SMTP server.

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function sendVerificationEmail($toEmail, $firstName, $token) {
    if (empty($toEmail) || empty($token)) {
        return false;
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/part1-ReservePro'), '/\\');
    if ($basePath === '.' || $basePath === '/') {
        $basePath = '/part1-ReservePro';
    }
    $verifyUrl = $scheme . '://' . $host . $basePath . '/verify-email.php?token=' . urlencode($token);

    $name = $firstName ?: 'there';

    $subject = 'Verify your ReservePro account';
    $bodyText = "Hi {$name},\n\n"
              . "Thanks for creating an account on ReservePro.\n\n"
              . "Please confirm that this is your email address by clicking the link below:\n"
              . "{$verifyUrl}\n\n"
              . "If you didn’t create this account, you can ignore this email.\n\n"
              . "Best,\nReservePro";

    $bodyHtml = '
        <p>Hi ' . htmlspecialchars($name) . ',</p>
        <p>Thanks for creating an account on <strong>ReservePro</strong>.</p>
        <p>Please confirm that this is your email address by clicking the button below:</p>
        <p><a href="' . htmlspecialchars($verifyUrl) . '" style="
            display:inline-block;
            padding:10px 18px;
            background:#D4A574;
            color:#111 !important;
            text-decoration:none;
            border-radius:6px;
            font-weight:600;
        ">Verify my email</a></p>
        <p>Or copy and paste this link into your browser:</p>
        <p><a href="' . htmlspecialchars($verifyUrl) . '">' . htmlspecialchars($verifyUrl) . '</a></p>
        <p>If you didn’t create this account, you can ignore this email.</p>
        <p>Best,<br>ReservePro</p>
    ';

    $mail = new PHPMailer(true);

    try {
        // Mailtrap SMTP configuration (Email Testing inbox)
        $mail->isSMTP();
        $mail->Host       = 'sandbox.smtp.mailtrap.io';
        $mail->SMTPAuth   = true;
        $mail->Port       = 587;

        // Replace these with your Mailtrap SMTP credentials
        $mail->Username   = 'd248cc9f17f305';
        $mail->Password   = '2147b67e7fc8ff';

        // From / Reply-To – set this to your Gmail if you want Gmail shown as sender
        $mail->setFrom('your-gmail-address@gmail.com', 'ReservePro');
        $mail->addReplyTo('your-gmail-address@gmail.com', 'ReservePro');

        // Recipient
        $mail->addAddress($toEmail, $name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $bodyHtml;
        $mail->AltBody = $bodyText;

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Optional: log the error in development
        // error_log('Mail error: ' . $mail->ErrorInfo);
        return false;
    }
}

<?php
// Mail helper for sending verification emails via PHPMailer + Mailtrap.
// You can set a Gmail address as the visible "From" so emails look like
// they come from Gmail while still going through Mailtrap's SMTP server.

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function sendVerificationEmail($toEmail, $firstName, $token) {
    if (empty($toEmail) || empty($token)) {
        return false;
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/part1-ReservePro'), '/\\');
    if ($basePath === '.' || $basePath === '/') {
        $basePath = '/part1-ReservePro';
    }
    $verifyUrl = $scheme . '://' . $host . $basePath . '/verify-email.php?token=' . urlencode($token);

    $name = $firstName ?: 'there';

    $subject = 'Verify your ReservePro account';
    $bodyText = "Hi {$name},\n\n"
              . "Thanks for creating an account on ReservePro.\n\n"
              . "Please confirm that this is your email address by clicking the link below:\n"
              . "{$verifyUrl}\n\n"
              . "If you didn’t create this account, you can ignore this email.\n\n"
              . "Best,\nReservePro";

    $bodyHtml = '
        <p>Hi ' . htmlspecialchars($name) . ',</p>
        <p>Thanks for creating an account on <strong>ReservePro</strong>.</p>
        <p>Please confirm that this is your email address by clicking the button below:</p>
        <p><a href="' . htmlspecialchars($verifyUrl) . '" style="
            display:inline-block;
            padding:10px 18px;
            background:#D4A574;
            color:#111 !important;
            text-decoration:none;
            border-radius:6px;
            font-weight:600;
        ">Verify my email</a></p>
        <p>Or copy and paste this link into your browser:</p>
        <p><a href="' . htmlspecialchars($verifyUrl) . '">' . htmlspecialchars($verifyUrl) . '</a></p>
        <p>If you didn’t create this account, you can ignore this email.</p>
        <p>Best,<br>ReservePro</p>
    ';

    $mail = new PHPMailer(true);

    try {
        // Mailtrap SMTP configuration (Email Testing inbox)
        $mail->isSMTP();
        $mail->Host       = 'sandbox.smtp.mailtrap.io';
        $mail->SMTPAuth   = true;
        $mail->Port       = 587;

        // Replace these with your Mailtrap SMTP credentials
        $mail->Username   = 'd248cc9f17f305';
        $mail->Password   = '2147b67e7fc8ff';

        // From / Reply-To – set this to your Gmail if you want Gmail shown as sender
        $mail->setFrom('your-gmail-address@gmail.com', 'ReservePro');
        $mail->addReplyTo('your-gmail-address@gmail.com', 'ReservePro');

        // Recipient
        $mail->addAddress($toEmail, $name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $bodyHtml;
        $mail->AltBody = $bodyText;

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Optional: log the error in development
        // error_log('Mail error: ' . $mail->ErrorInfo);
        return false;
    }
}

<?php
// Mail helper for sending verification emails via PHPMailer + Mailtrap.
// You can set a Gmail address as the visible "From" so emails look like
// they come from Gmail while still going through Mailtrap's SMTP server.

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

function sendVerificationEmail($toEmail, $firstName, $token) {
    if (empty($toEmail) || empty($token)) {
        return false;
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/part1-ReservePro'), '/\\');
    if ($basePath === '.' || $basePath === '/') {
        $basePath = '/part1-ReservePro';
    }
    $verifyUrl = $scheme . '://' . $host . $basePath . '/verify-email.php?token=' . urlencode($token);

    $name = $firstName ?: 'there';

    $subject = 'Verify your ReservePro account';
    $bodyText = "Hi {$name},\n\n"
              . "Thanks for creating an account on ReservePro.\n\n"
              . "Please confirm that this is your email address by clicking the link below:\n"
              . "{$verifyUrl}\n\n"
              . "If you didn’t create this account, you can ignore this email.\n\n"
              . "Best,\nReservePro";

    $bodyHtml = '
        <p>Hi ' . htmlspecialchars($name) . ',</p>
        <p>Thanks for creating an account on <strong>ReservePro</strong>.</p>
        <p>Please confirm that this is your email address by clicking the button below:</p>
        <p><a href="' . htmlspecialchars($verifyUrl) . '" style="
            display:inline-block;
            padding:10px 18px;
            background:#D4A574;
            color:#111 !important;
            text-decoration:none;
            border-radius:6px;
            font-weight:600;
        ">Verify my email</a></p>
        <p>Or copy and paste this link into your browser:</p>
        <p><a href="' . htmlspecialchars($verifyUrl) . '">' . htmlspecialchars($verifyUrl) . '</a></p>
        <p>If you didn’t create this account, you can ignore this email.</p>
        <p>Best,<br>ReservePro</p>
    ';

    $mail = new PHPMailer(true);

    try {
        // Mailtrap SMTP configuration (Email Testing inbox)
        $mail->isSMTP();
        $mail->Host       = 'sandbox.smtp.mailtrap.io';
        $mail->SMTPAuth   = true;
        $mail->Port       = 587;

        // TODO: Replace these with your Mailtrap SMTP credentials
        $mail->Username   = 'd248cc9f17f305';
        $mail->Password   = '2147b67e7fc8ff';

        // From / Reply-To – set this to your Gmail if you want Gmail shown as sender
        $mail->setFrom('your-gmail-address@gmail.com', 'ReservePro');
        $mail->addReplyTo('your-gmail-address@gmail.com', 'ReservePro');

        // Recipient
        $mail->addAddress($toEmail, $name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $bodyHtml;
        $mail->AltBody = $bodyText;

        $mail->send();
        return true;
    } catch (Exception $e) {
        // Optional: log the error in development
        // error_log('Mail error: ' . $mail->ErrorInfo);
        return false;
    }
}

<?php
// Simple mail helper for sending verification emails.
// NOTE: This uses PHP's built-in mail() function. On local XAMPP you may
// need to configure sendmail/SMTP in php.ini before emails are actually sent.

function sendVerificationEmail($toEmail, $firstName, $token) {
    if (empty($toEmail) || empty($token)) {
        return false;
    }

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/part1-ReservePro'), '/\\');
    if ($basePath === '.' || $basePath === '/') {
        $basePath = '/part1-ReservePro';
    }
    $verifyUrl = $scheme . '://' . $host . $basePath . '/verify-email.php?token=' . urlencode($token);

    $subject = 'Verify your ReservePro account';
    $name = $firstName ?: 'there';

    $message = "Hi {$name},\n\n"
             . "Thanks for creating an account on ReservePro.\n\n"
             . "Please confirm that this is your email address by clicking the link below:\n"
             . "{$verifyUrl}\n\n"
             . "If you didn’t create this account, you can ignore this email.\n\n"
             . "Best,\nReservePro";

    $headers = "From: ReservePro <no-reply@{$host}>\r\n";
    $headers .= "Reply-To: no-reply@{$host}\r\n";

    // Suppress warnings from mail() and return boolean
    return @mail($toEmail, $subject, $message, $headers);
}

?>