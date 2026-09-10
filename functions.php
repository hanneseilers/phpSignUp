<?php
require_once __DIR__ . '/vendor/autoload.php';

// Read the SMTP settings from config/.env. SMTP_HOST empty/missing means
// "not configured" - sendPlainTextEmail() then falls back to PHP's mail().
function loadSmtpConfig($envFile) {
    $env = file_exists($envFile) ? parse_ini_file($envFile) : [];
    return [
        'host' => $env['SMTP_HOST'] ?? '',
        'port' => (int) ($env['SMTP_PORT'] ?? 587),
        'username' => $env['SMTP_USERNAME'] ?? '',
        'password' => $env['SMTP_PASSWORD'] ?? '',
        'encryption' => strtolower($env['SMTP_ENCRYPTION'] ?? 'tls'),
        'from_email' => $env['SMTP_FROM_EMAIL'] ?? 'no-reply@event.com',
        'from_name' => $env['SMTP_FROM_NAME'] ?? 'Event Team',
    ];
}

// Send a plain-text email via SMTP (through PHPMailer) when $smtpConfig has a
// host configured, otherwise fall back to PHP's built-in mail().
function sendPlainTextEmail($to, $subject, $body, array $smtpConfig) {
    if (empty($smtpConfig['host'])) {
        $headers = 'From: ' . $smtpConfig['from_name'] . ' <' . $smtpConfig['from_email'] . ">\r\n";
        return mail($to, $subject, $body, $headers);
    }

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $smtpConfig['host'];
        $mail->Port = $smtpConfig['port'];
        if (!empty($smtpConfig['username'])) {
            $mail->SMTPAuth = true;
            $mail->Username = $smtpConfig['username'];
            $mail->Password = $smtpConfig['password'];
        }
        if ($smtpConfig['encryption'] === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($smtpConfig['encryption'] === 'tls') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->setFrom($smtpConfig['from_email'], $smtpConfig['from_name']);
        $mail->addAddress($to);
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body = $body;
        return $mail->send();
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log('sendPlainTextEmail: ' . $e->getMessage());
        return false;
    }
}

// Function to generate a random name from the names.txt file
function getRandomName($namesFile) {
    $names = file($namesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (empty($names)) {
        return "No names available";
    }
    return $names[array_rand($names)];
}

// Load the list of allowed events from config/events.txt
function loadEvents($eventsFile) {
    return file_exists($eventsFile) ? file($eventsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
}

// Function to validate event name against the configured event list
function validateEvent($event, $eventsFile) {
    return in_array($event, loadEvents($eventsFile), true);
}

// Prefix a leading single quote onto values that would otherwise be read as a
// formula (=, +, -, @) or spreadsheet-specific control char by Excel/LibreOffice
// when the CSV is opened later, per the standard CSV-injection mitigation.
function sanitizeCsvField($value) {
    $value = (string) $value;
    if ($value !== '' && strpbrk($value[0], "=+-@\t\r") !== false) {
        return "'" . $value;
    }
    return $value;
}

// Header row written once, the first time the CSV file is created. Fixed to
// German regardless of the submitter's UI language, since the file is read
// by the organizer, not the person registering, and must stay consistent
// once written.
function csvHeaderRow() {
    return buildCsvRow(['Datum', 'Name', 'Angebot', 'Anzahl Erwachsene', 'Anzahl Kinder', 'Alter der Kinder', 'Kommentare']);
}

// Function to turn a registration into a properly escaped CSV row
function buildCsvRow(array $fields) {
    $stream = fopen('php://temp', 'r+');
    fputcsv($stream, $fields);
    rewind($stream);
    $row = stream_get_contents($stream);
    fclose($stream);
    return $row;
}

// Get (creating on first use) the secret used to sign the registration data
// that travels as hidden fields from process_form.php to send_confirmation.php,
// so that endpoint can tell a genuine post-registration request from a forged one.
function getMailTokenSecret() {
    $secretFile = __DIR__ . '/mail_token_secret.php';
    if (!file_exists($secretFile)) {
        $secret = bin2hex(random_bytes(32));
        // Write to a unique temp file and rename it into place: rename() is
        // atomic on the same filesystem, so if two requests race to create
        // the secret on its very first use, both end up agreeing on whichever
        // one's rename happened last instead of one silently overwriting the
        // other mid-write.
        $tmpFile = $secretFile . '.' . bin2hex(random_bytes(4)) . '.tmp';
        file_put_contents($tmpFile, "<?php\nreturn " . var_export($secret, true) . ";\n", LOCK_EX);
        if (!@rename($tmpFile, $secretFile)) {
            @unlink($tmpFile);
        }
    }
    return require $secretFile;
}

// Compute the signature for a set of registration fields
function buildRegistrationToken($registrationDate, $name, $event, $num_adults, $num_children, $children_ages, $comments) {
    $payload = implode('|', [$registrationDate, $name, $event, $num_adults, $num_children, $children_ages, $comments]);
    return hash_hmac('sha256', $payload, getMailTokenSecret());
}

// Try to acquire an exclusive WebDAV lock (RFC4918) on $url, retrying briefly
// if another request currently holds it (HTTP 423 Locked). Returns the lock
// token (e.g. "<opaquelocktoken:...>"), to be sent back in an `If` header, or
// null if no lock could be obtained - either the server doesn't support
// WebDAV locking at all, or it stayed locked for the whole retry window.
function acquireWebdavLock($url, $authHeader, $maxAttempts = 5, $retryDelayMicroseconds = 200000) {
    $body = '<?xml version="1.0" encoding="utf-8"?>'
        . '<D:lockinfo xmlns:D="DAV:">'
        . '<D:lockscope><D:exclusive/></D:lockscope>'
        . '<D:locktype><D:write/></D:locktype>'
        . '<D:owner><D:href>phpSignUp registration form</D:href></D:owner>'
        . '</D:lockinfo>';

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        $context = stream_context_create([
            'http' => [
                'method' => 'LOCK',
                'header' => $authHeader
                    . "Content-Type: text/xml; charset=\"utf-8\"\r\n"
                    . "Timeout: Second-30\r\n"
                    . "Depth: 0\r\n",
                'content' => $body,
                'ignore_errors' => true
            ]
        ]);

        $response = @file_get_contents($url, false, $context);
        $statusLine = $http_response_header[0] ?? '';

        if ($response !== false && preg_match('#^HTTP/\S+\s+2\d\d#', $statusLine)) {
            foreach ($http_response_header as $header) {
                if (stripos($header, 'Lock-Token:') === 0) {
                    return trim(substr($header, strlen('Lock-Token:')));
                }
            }
            if (preg_match('#<(?:[a-zA-Z0-9]+:)?locktoken>\s*<(?:[a-zA-Z0-9]+:)?href>\s*([^<\s]+)#i', (string) $response, $matches)) {
                return '<' . trim($matches[1]) . '>';
            }
            return null; // 2xx but no token we can find - treat as "no lock"
        }

        $isLocked = (bool) preg_match('#^HTTP/\S+\s+423#', $statusLine);
        if (!$isLocked || $attempt === $maxAttempts) {
            return null; // unsupported/other error, or retries exhausted
        }
        usleep($retryDelayMicroseconds);
    }

    return null;
}

// Release a lock previously obtained from acquireWebdavLock().
function releaseWebdavLock($url, $authHeader, $lockToken) {
    $context = stream_context_create([
        'http' => [
            'method' => 'UNLOCK',
            'header' => $authHeader . "Lock-Token: {$lockToken}\r\n",
            'ignore_errors' => true
        ]
    ]);
    @file_get_contents($url, false, $context);
}

// Function to store data in CSV file via WebDAV
function storeData($registrationDate, $name, $event, $num_adults, $num_children, $children_ages, $comments, $webdavUrl, $webdavUser, $webdavPass, $csvFile) {
    $row = buildCsvRow([
        $registrationDate,
        sanitizeCsvField($name),
        sanitizeCsvField($event),
        $num_adults,
        $num_children,
        sanitizeCsvField($children_ages),
        sanitizeCsvField($comments)
    ]);

    $authHeader = "Authorization: Basic " . base64_encode("$webdavUser:$webdavPass") . "\r\n";
    $url = rtrim($webdavUrl, '/') . '/' . $csvFile;

    // Hold an exclusive WebDAV lock across the GET-then-PUT append below so
    // two concurrent submissions can't both read the same "current" content
    // and each PUT back a version that drops the other's row. Best-effort:
    // if the server doesn't support WebDAV locking, we still proceed without
    // one rather than blocking registrations entirely.
    $lockToken = acquireWebdavLock($url, $authHeader);
    $ifHeader = $lockToken !== null ? "If: ({$lockToken})\r\n" : '';

    // Fetch the existing CSV so new rows are appended instead of overwriting it.
    // A missing file (404) is expected on the very first registration, so we
    // only keep the response body when the GET actually succeeded (2xx).
    $getContext = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => $authHeader,
            'ignore_errors' => true
        ]
    ]);
    $existing = @file_get_contents($url, false, $getContext);
    $statusLine = $http_response_header[0] ?? '';
    $statusOk = (bool) preg_match('#^HTTP/\S+\s+2\d\d#', $statusLine);
    if ($existing === false || !$statusOk) {
        $existing = '';
    }
    if ($existing === '') {
        $existing = csvHeaderRow();
    }

    $putContext = stream_context_create([
        'http' => [
            'method' => 'PUT',
            'header' => $authHeader . $ifHeader . "Content-Type: text/csv\r\n",
            'content' => $existing . $row
        ]
    ]);

    $result = file_get_contents($url, false, $putContext);

    if ($lockToken !== null) {
        releaseWebdavLock($url, $authHeader, $lockToken);
    }

    return $result !== false;
}
?>
