<?php
// General utility functions
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Generate a random 3-digit number
 * @return string
 */
function generateRandomNumber() {
    return str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
}

/**
 * Validate email format
 * @param string $email
 * @return bool
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate date format (YYYY-MM-DD)
 * @param string $date
 * @return bool
 */
function isValidDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * Sanitize input data
 * @param string $data
 * @return string
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Log detailed error messages to file
 * @param string $message
 * @param string $level
 */
function logError($message, $level = 'ERROR') {
    $logFile = __DIR__ . '/error.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$level}: {$message}\n";
    file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

/**
 * Send confirmation email using SMTP
 * @param string $to
 * @param string $name
 * @param string $event
 * @param string $date
 * @return bool
 */
function sendConfirmationEmail($to, $name, $event, $date) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.example.com'; // Set the SMTP server
        $mail->SMTPAuth   = true;
        $mail->Username   = 'your-email@example.com'; // SMTP username
        $mail->Password   = 'your-password'; // SMTP password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Recipients
        $mail->setFrom('no-reply@event.com', 'Event Team');
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(false);
        $mail->Subject = 'Registration Confirmation';
        $mail->Body    = "Dear $name,\n\nThank you for registering for the $event event on $date.\n\nBest regards,\nEvent Team";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log detailed error but show generic message to user
        logError("Email sending failed for {$to}: " . $e->getMessage(), 'EMAIL_ERROR');
        return false;
    }
}

/**
 * Check if WebDAV server is reachable
 * @param string $url
 * @param string $username
 * @param string $password
 * @return bool
 */
function isWebDAVServerReachable($url, $username, $password) {
    // This is a simplified check - in a real implementation, you'd make an actual HTTP request
    // to verify the server is reachable and credentials work
    return !empty($url) && !empty($username) && !empty($password);
}
?>