<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include AfricasTalking SDK
use AfricasTalking\SDK\AfricasTalking;

require_once __DIR__ . '/../vendor/autoload.php';

// Define BASE_URL if it's not already defined
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $script_name = str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
    // Go up two directories from /includes/
    $base_path = rtrim(rtrim(dirname($script_name), '/\\'), '/\\') . '/';
    define('BASE_URL', $protocol . $host . $base_path);
}

class NotificationService
{

    public function __construct()
    {
        // In case a DB connection is needed in the future
    }

    /**
     * Send email using PHPMailer
     */
    private function sendEmail($to, $subject, $body, $plainText = '')
    {
        $mail = new PHPMailer(true);
        try {
            // Server settings from user-provided file
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'infofonepo@gmail.com'; // From reference
            $mail->Password = 'zaoxwuezfjpglwjb'; // From reference
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;

            // Recipients
            $mail->setFrom('infofonepo@gmail.com', 'MBIMS'); // Changed to MBIMS
            $mail->addAddress($to);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;

            if (!empty($plainText)) {
                $mail->AltBody = $plainText;
            }

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Email could not be sent. Error: {$mail->ErrorInfo}");
            return false;
        }
    }

    /**
     * Send SMS using AfricasTalking
     */
    private function sendSMS($phoneNumber, $message)
    {
        // AfricasTalking credentials from user-provided file
        $username = "Iot_project";
        $apiKey = "atsk_6ccbe2174a56e50490d59c73c1f7177fc02e47c2cdecb5343b67e6680bc321677b10c4bd";

        // Format phone number
        if (!preg_match('/^\+/', $phoneNumber)) {
            $phoneNumber = '+250' . ltrim($phoneNumber, '0');
        }

        // Truncate message if too long
        if (strlen($message) > 160) {
            $message = substr($message, 0, 157) . '...';
        }

        try {
            $AT = new AfricasTalking($username, $apiKey);
            $sms = $AT->sms();

            $result = $sms->send([
                'to' => $phoneNumber,
                'message' => $message
            ]);

            if ($result['status'] == 'success' && !empty($result['data']->SMSMessageData->Recipients)) {
                $recipient = $result['data']->SMSMessageData->Recipients[0];
                if ($recipient->status == 'Success') {
                    return true;
                }
            }

            error_log("SMS could not be sent. Status: " . json_encode($result));
            return false;

        } catch (Exception $e) {
            error_log("SMS error: " . $e->getMessage());
            return false;
        }
    }

    private function getNewUserWelcomeEmailTemplate($fullName, $email, $password)
    {
        $loginUrl = BASE_URL;

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Welcome to MBIMS</title>
        </head>
        <body style="margin: 0; padding: 0; font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4;">
            <table align="center" border="0" cellpadding="0" cellspacing="0" width="600" style="border-collapse: collapse; margin: 20px auto; border: 1px solid #ddd;">
                <!-- Header -->
                <tr>
                    <td align="center" bgcolor="#007bff" style="padding: 30px 20px; color: #ffffff; font-size: 28px; font-weight: bold;">
                        Welcome to MBIMS!
                    </td>
                </tr>
                <!-- Body -->
                <tr>
                    <td bgcolor="#ffffff" style="padding: 40px 30px;">
                        <h2 style="color: #333333; margin-top: 0;">Your Account is Ready!</h2>
                        <p>Hello <strong>{$fullName}</strong>,</p>
                        <p>An account has been created for you on the MBIMS platform. You can now log in using the following credentials:</p>
                        
                        <table border="0" cellpadding="10" cellspacing="0" width="100%" style="border-collapse: collapse; border: 1px solid #eee; margin-top: 20px; margin-bottom: 20px;">
                            <tr>
                                <td style="background-color: #f9f9f9; width: 120px;"><strong>Email:</strong></td>
                                <td><a href="mailto:{$email}" style="color: #007bff;">{$email}</a></td>
                            </tr>
                            <tr>
                                <td style="background-color: #f9f9f9; width: 120px;"><strong>Password:</strong></td>
                                <td>{$password}</td>
                            </tr>
                        </table>
                        
                        <p>For security reasons, we strongly recommend that you change your password after your first login.</p>
                        
                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-top: 30px;">
                            <tr>
                                <td align="center">
                                    <a href="{$loginUrl}" style="background-color: #28a745; color: #ffffff; padding: 15px 25px; text-decoration: none; border-radius: 5px; display: inline-block;">
                                        Login to Your Account
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <!-- Footer -->
                <tr>
                    <td bgcolor="#f4f4f4" style="padding: 20px 30px; text-align: center; color: #777; font-size: 12px;">
                        <p>You received this email because an account was created for you on MBIMS.</p>
                        <p>&copy; " . date('Y') . " MBIMS. All Rights Reserved.</p>
                    </td>
                </tr>
            </table>
        </body>
        </html>
HTML;
    }

    public function sendNewUserWelcomeNotification($userData)
    {
        $fullName = $userData['first_name'] . ' ' . $userData['last_name'];
        $email = $userData['email'];
        $phone = $userData['phone'];
        $password = $userData['password'];

        // Send Email
        if (!empty($email)) {
            $subject = "Welcome to MBIMS - Your Account Is Ready";
            $htmlMessage = $this->getNewUserWelcomeEmailTemplate($fullName, $email, $password);
            $plainTextMessage = "Hello {$fullName},\n\nYour account for MBIMS has been created.\n\nLogin with:\nEmail: {$email}\nPassword: {$password}\n\nPlease change your password after you log in.\n\nThanks,\nThe MBIMS Team";
            $this->sendEmail($email, $subject, $htmlMessage, $plainTextMessage);
        }

        // Send SMS
        if (!empty($phone)) {
            $smsMessage = "Welcome to MBIMS. Your account is created. Email: {$email}, Password: {$password}. Please change your password after first login.";
            $this->sendSMS($phone, $smsMessage);
        }
    }
}