<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

class MailService
{
    public function sendPasswordResetOtp(
        string $email,
        string $fullName,
        string $otp
    ): void {

        $mail = new PHPMailer(true);

        try {
            // SMTP configuration
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;

            // CHANGE THESE
            $mail->Username = 'sakshipawarr19@gmail.com';
            $mail->Password = 'nwjs ckzd iozn juyb';//app passwod

            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            // Sender
            $mail->setFrom(
                'okispldevlopmentteam@gmail.com',
                'RepoMaster'
            );

            // Receiver
            $mail->addAddress($email, $fullName);

            // Email content
            $mail->isHTML(true);
            $mail->Subject = 'RepoMaster Password Reset OTP';

            $mail->Body = "
                <div style='font-family: Arial, sans-serif;'>
                    <h2>RepoMaster Password Reset</h2>

                    <p>Hello <strong>" . htmlspecialchars($fullName) . "</strong>,</p>

                    <p>
                        Your OTP for resetting your RepoMaster password is:
                    </p>

                    <h1 style='letter-spacing: 5px;'>
                        {$otp}
                    </h1>

                    <p>
                        This OTP is valid for <strong>5 minutes</strong>.
                    </p>

                    <p>
                        If you did not request a password reset,
                        please ignore this email.
                    </p>

                    <p>Regards,<br>RepoMaster Team</p>
                </div>
            ";

            $mail->AltBody =
                "Your RepoMaster password reset OTP is {$otp}. "
                . "This OTP is valid for 5 minutes.";

            $mail->send();

        } catch (Exception $e) {
            throw new RuntimeException(
                'Unable to send OTP email: ' . $mail->ErrorInfo
            );
        }
    }
}