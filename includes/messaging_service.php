<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Include AfricasTalking SDK
use AfricasTalking\SDK\AfricasTalking;

require_once '../../vendor/autoload.php';

class MessagingService {
    private $conn;
    
    public function __construct() {
        $this->conn = getDBConnection();
    }
    
    /**
     * Send email using PHPMailer
     */
    private function sendEmail($to, $subject, $body, $plainText = '') {
        $mail = new PHPMailer(true);
        try {
            // Server settings (same as test_email.php)
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'infofonepo@gmail.com';
            $mail->Password = 'zaoxwuezfjpglwjb';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;
            
            // Recipients
            $mail->setFrom('infofonepo@gmail.com', 'Advocate Management System');
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
    private function sendSMS($phoneNumber, $message) {
        // AfricasTalking credentials (same as test_sms.php)
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
    
    /**
     * Generate payment confirmation email template
     */
    private function getPaymentConfirmationEmailTemplate($clientName, $amount, $transactionId, $paymentMethod, $invoiceDescription) {
        $formattedAmount = number_format($amount, 2);
        $currentDate = date('F j, Y \a\t g:i A');
        $currentYear = date('Y');
        
        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Payment Confirmation</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    margin: 0;
                    padding: 0;
                    background-color: #f4f4f4;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                    background-color: #ffffff;
                }
                .header {
                    background-color: #28a745;
                    padding: 30px 20px;
                    color: white;
                    text-align: center;
                    border-radius: 8px 8px 0 0;
                }
                .header h2 {
                    margin: 0;
                    font-size: 28px;
                    font-weight: bold;
                }
                .content {
                    padding: 30px 20px;
                    background-color: #ffffff;
                    border: 1px solid #e0e0e0;
                    border-top: none;
                }
                .success-icon {
                    text-align: center;
                    margin-bottom: 20px;
                }
                .success-icon .checkmark {
                    display: inline-block;
                    width: 60px;
                    height: 60px;
                    background-color: #28a745;
                    border-radius: 50%;
                    position: relative;
                    margin-bottom: 15px;
                }
                .success-icon .checkmark::after {
                    content: '✓';
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    color: white;
                    font-size: 30px;
                    font-weight: bold;
                }
                .message {
                    background-color: #f8f9fa;
                    padding: 20px;
                    border-left: 4px solid #28a745;
                    margin-bottom: 25px;
                    border-radius: 0 4px 4px 0;
                }
                .message h3 {
                    margin-top: 0;
                    color: #28a745;
                    font-size: 24px;
                }
                .payment-details {
                    background-color: #e8f5e8;
                    padding: 20px;
                    border-radius: 8px;
                    margin-bottom: 25px;
                    border: 1px solid #c3e6cb;
                }
                .payment-details h4 {
                    margin-top: 0;
                    color: #28a745;
                    font-size: 18px;
                    border-bottom: 2px solid #28a745;
                    padding-bottom: 10px;
                }
                .detail-row {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-bottom: 12px;
                    padding: 8px 0;
                    border-bottom: 1px solid #d4edda;
                }
                .detail-row:last-child {
                    border-bottom: none;
                    margin-bottom: 0;
                }
                .detail-label {
                    font-weight: bold;
                    color: #495057;
                    flex: 1;
                }
                .detail-value {
                    color: #212529;
                    font-weight: 500;
                    text-align: right;
                    flex: 1;
                }
                .amount {
                    font-size: 28px;
                    font-weight: bold;
                    color: #28a745;
                }
                .status-completed {
                    color: #28a745;
                    font-weight: bold;
                    background-color: #d4edda;
                    padding: 4px 12px;
                    border-radius: 20px;
                    font-size: 12px;
                    text-transform: uppercase;
                }
                .footer {
                    text-align: center;
                    margin-top: 30px;
                    padding-top: 20px;
                    border-top: 2px solid #e9ecef;
                    font-size: 12px;
                    color: #6c757d;
                    line-height: 1.4;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>Payment Confirmation</h2>
                    <p>Advocate Management System</p>
                </div>
                
                <div class="content">
                    <div class="success-icon">
                        <div class="checkmark"></div>
                    </div>
                    
                    <div class="message">
                        <h3>Payment Successful!</h3>
                        <p>Dear <strong>{$clientName}</strong>,</p>
                        <p>We are pleased to confirm that your payment has been processed successfully.</p>
                    </div>
                    
                    <div class="payment-details">
                        <h4>Transaction Details</h4>
                        <div class="detail-row">
                            <span class="detail-label">Amount Paid:</span>
                            <span class="detail-value amount">$$formattedAmount</span>
                        </div>
                        
                        <div class="detail-row">
                            <span class="detail-label">Payment Method:</span>
                            <span class="detail-value">{$paymentMethod}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Invoice:</span>
                            <span class="detail-value">{$invoiceDescription}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Date & Time:</span>
                            <span class="detail-value">{$currentDate}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Status:</span>
                            <span class="detail-value"><span class="status-completed">Completed</span></span>
                        </div>
                    </div>
                    
                    <p style="text-align: center; font-size: 16px; color: #28a745; font-weight: bold;">
                        Thank you for choosing our services!
                    </p>
                </div>
                
                <div class="footer">
                    <p><strong>This is an automated message from your Advocate Management System.</strong></p>
                    <p>&copy; {$currentYear} Advocate Management System. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
HTML;
    }
    
    /**
     * Send payment confirmation notifications (email + SMS)
     */
    public function sendPaymentConfirmation($transactionId) {
        try {
            error_log("=== SENDING PAYMENT CONFIRMATION ===");
            error_log("Transaction ID: " . $transactionId);
            
            // Get transaction and user details
            $stmt = $this->conn->prepare("
                SELECT 
                    pt.transaction_id,
                    pt.amount,
                    pt.payment_method,
                    pt.gateway_transaction_id,
                    b.billing_id,
                    b.description as invoice_description,
                    u.full_name,
                    u.email,
                    u.phone
                FROM payment_transactions pt
                JOIN billings b ON pt.billing_id = b.billing_id
                JOIN client_profiles cp ON pt.client_id = cp.client_id
                JOIN users u ON cp.user_id = u.user_id
                WHERE pt.transaction_id = ? AND pt.status = 'completed'
            ");
            
            $stmt->bind_param("i", $transactionId);
            $stmt->execute();
            $result = $stmt->get_result()->fetch_assoc();
            
            if (!$result) {
                error_log("Transaction not found or not completed");
                return ['success' => false, 'message' => 'Transaction not found or not completed'];
            }
            
            error_log("User data found: " . json_encode($result));
            
            $clientName = $result['full_name'];
            $clientEmail = $result['email'];
            $clientPhone = $result['phone'];
            $amount = $result['amount'];
            $paymentMethod = ucfirst($result['payment_method']);
            $invoiceDescription = $result['invoice_description'];
            $transactionRef = $result['gateway_transaction_id'] ?? 'TXN-' . str_pad($transactionId, 8, '0', STR_PAD_LEFT);
            
            $emailSent = false;
            $smsSent = false;
            
            // Send Email
            if (!empty($clientEmail)) {
                error_log("Sending email to: " . $clientEmail);
                
                $subject = "Payment Confirmation - Advocate Management System";
                $htmlMessage = $this->getPaymentConfirmationEmailTemplate(
                    $clientName, 
                    $amount, 
                    $transactionRef, 
                    $paymentMethod,
                    $invoiceDescription
                );
                
                $plainTextMessage = "
Payment Confirmation

Dear {$clientName},

Your payment has been processed successfully.

Transaction Details:
- Amount: $" . number_format($amount, 2) . "
- Payment Method: {$paymentMethod}
- Invoice: {$invoiceDescription}
- Date: " . date('F j, Y \a\t g:i A') . "
- Status: COMPLETED

Thank you for your payment!

Best regards,
Advocate Management System
                ";
                
                $emailSent = $this->sendEmail($clientEmail, $subject, $htmlMessage, $plainTextMessage);
                error_log("Email sent: " . ($emailSent ? 'YES' : 'NO'));
            }
            
            // Send SMS
            if (!empty($clientPhone)) {
                error_log("Sending SMS to: " . $clientPhone);
                
                $smsMessage = "Hello {$clientName}! Your payment of $" . number_format($amount, 2) . " has been processed successfully. Thank you! - Advocate Management System";
                
                $smsSent = $this->sendSMS($clientPhone, $smsMessage);
                error_log("SMS sent: " . ($smsSent ? 'YES' : 'NO'));
            }
            
            return [
                'success' => true,
                'email_sent' => $emailSent,
                'sms_sent' => $smsSent,
                'message' => 'Payment confirmation notifications processed'
            ];
            
                } catch (Exception $e) {
            error_log("Error sending payment confirmation: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error sending notifications: ' . $e->getMessage()
            ];
        }
    }
}
?>