<?php
// Mail helper for sending verification emails via PHPMailer + Mailtrap.

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
    // Local logo file to embed in the email so it shows up in Mailtrap
    $logoPath  = __DIR__ . '/../background image/asd.webp';
    $logoCid   = 'reservepro-logo';

    $name = $firstName !== '' ? $firstName : 'there';

    $subject = 'Verify your ReservePro account';

    // Plain-text fallback
    $bodyText = "Hi {$name},\n\n"
              . "Thanks for creating an account on ReservePro.\n\n"
              . "Please confirm that this is your email address by clicking the link below:\n"
              . "{$verifyUrl}\n\n"
              . "If you didn’t create this account, you can ignore this email.\n\n"
              . "Best,\nReservePro";

    // Beautiful HTML layout with logo and styled card
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeVerifyUrl = htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8');

    $bodyHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>Verify your ReservePro account</title>
  </head>
  <body style="margin:0; padding:0; background-color:#0B1120; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">
    <!-- Make the email visually larger and wider so it fills more of Mailtrap's preview -->
    <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="background-color:#0B1120; padding:8px 0;">
      <tr>
        <td align="center">
          <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="max-width:760px; background:#020617; border-radius:18px; border:1px solid #1F2937; overflow:hidden; box-shadow:0 20px 60px rgba(0,0,0,0.75);">
            <tr>
              <td style="padding:18px 26px 0 26px; text-align:left;">
                <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                  <tr>
                    <td style="vertical-align:middle;">
                      <img src="cid:$logoCid" alt="ReservePro" width="40" height="40" style="border-radius:999px; display:block;">
                    </td>
                    <td style="vertical-align:middle; padding-left:10px;">
                      <div style="font-size:18px; font-weight:700; color:#F9FAFB;">ReservePro</div>
                      <div style="font-size:12px; color:#9CA3AF;">Stay reservations made simple</div>
                    </td>
                  </tr>
                </table>
              </td>
            </tr>
            <tr>
              <td style="padding:22px 26px 10px 26px; text-align:left;">
                <h1 style="margin:0 0 8px 0; font-size:26px; line-height:1.35; color:#F9FAFB;">Confirm your email</h1>
                <p style="margin:0 0 4px 0; font-size:16px; line-height:1.6; color:#E5E7EB;">
                  Hi $safeName,
                </p>
                <p style="margin:0 0 10px 0; font-size:16px; line-height:1.6; color:#E5E7EB;">
                  Thanks for creating an account on <strong style="color:#FBBF77;">ReservePro</strong>.
                </p>
                <p style="margin:0 0 12px 0; font-size:14px; line-height:1.6; color:#9CA3AF;">
                  Please confirm that this is your email address by clicking the button below:
                </p>
                <p style="margin:0 0 16px 0;">
                  <a href="$safeVerifyUrl" style="
                    display:inline-block;
                    padding:12px 26px;
                    background:linear-gradient(135deg,#D4A574,#B8935F);
                    color:#111827 !important;
                    text-decoration:none;
                    border-radius:999px;
                    font-size:15px;
                    font-weight:700;
                    box-shadow:0 8px 20px rgba(212,165,116,0.45);
                  ">Verify my email</a>
                </p>
                <p style="margin:0 0 6px 0; font-size:12px; line-height:1.6; color:#6B7280;">
                  Or copy and paste this link into your browser:
                </p>
                <p style="margin:0 0 12px 0; font-size:12px; line-height:1.6; color:#9CA3AF; word-break:break-all;">
                  <a href="$safeVerifyUrl" style="color:#FBBF77; text-decoration:none;">$safeVerifyUrl</a>
                </p>
                <p style="margin:0; font-size:11px; line-height:1.5; color:#6B7280;">
                  If you didn’t create this account, you can safely ignore this email.
                </p>
              </td>
            </tr>
            <tr>
              <td style="padding:12px 26px 18px 26px; border-top:1px solid #111827; text-align:left;">
                <p style="margin:0; font-size:11px; line-height:1.5; color:#4B5563;">
                  © ReservePro. This is a system email for account verification.
                </p>
              </td>
            </tr>
          </table>
        </td>
      </tr>
    </table>
  </body>
</html>
HTML;

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

        // Embed local logo image so it is visible in Mailtrap and other clients
        if (is_readable($logoPath)) {
            $mail->addEmbeddedImage($logoPath, $logoCid, 'reservepro-logo.webp');
        }

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

