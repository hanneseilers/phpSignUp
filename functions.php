<?php
require_once __DIR__ . '/vendor/autoload.php';

// Central config file locations, resolved relative to this file so they don't
// depend on the include caller's current working directory.
define('EVENTS_FILE', __DIR__ . '/config/events.txt');
define('NAMES_FILE', __DIR__ . '/config/names.txt');
define('ENV_FILE', __DIR__ . '/config/.env');

// Sanity cap on the head-count fields - generous enough for any real
// registration, low enough to keep a single bad/malicious submission from
// bloating the CSV.
define('MAX_PARTICIPANTS', 50);

// Timeout (seconds) applied to every WebDAV HTTP request, so an unreachable
// or slow WebDAV server can't hang a registration request indefinitely.
define('WEBDAV_TIMEOUT_SECONDS', 10);

// Parse a config/.env-style file into a key => value array, or [] if it
// doesn't exist. Shared by loadSmtpConfig() and loadWebdavConfig().
function loadEnvConfig($envFile) {
    return file_exists($envFile) ? parse_ini_file($envFile) : [];
}

// Read the SMTP settings from an already-loaded .env array. SMTP_HOST
// empty/missing means "not configured" - sendPlainTextEmail() then falls
// back to PHP's mail().
function loadSmtpConfig(array $env) {
    return [
        'host' => $env['SMTP_HOST'] ?? '',
        'port' => (int) ($env['SMTP_PORT'] ?? 465),
        'username' => $env['SMTP_USERNAME'] ?? '',
        'password' => $env['SMTP_PASSWORD'] ?? '',
        'encryption' => strtolower($env['SMTP_ENCRYPTION'] ?? 'ssl'),
        'from_email' => $env['SMTP_FROM_EMAIL'] ?? 'no-reply@event.com',
        'from_name' => $env['SMTP_FROM_NAME'] ?? 'Event Team',
    ];
}

// Read the WebDAV settings from an already-loaded .env array.
function loadWebdavConfig(array $env) {
    return [
        'url' => $env['WEBDAV_URL'] ?? '',
        'username' => $env['WEBDAV_USERNAME'] ?? '',
        'password' => $env['WEBDAV_PASSWORD'] ?? '',
        'csv_file' => $env['WEBDAV_FILE_PATH'] ?? 'registrations.csv',
    ];
}

// Send a plain-text email via SMTP (through PHPMailer) when $smtpConfig has a
// host configured, otherwise fall back to PHP's built-in mail().
function sendPlainTextEmail($to, $subject, $body, array $smtpConfig) {
    if (empty($smtpConfig['host'])) {
        // Declare UTF-8 explicitly and RFC 2047-encode the subject - unlike
        // PHPMailer below, mail() doesn't infer or apply either on its own,
        // which otherwise turns every umlaut into mojibake for the recipient.
        $headers = 'From: ' . $smtpConfig['from_name'] . ' <' . $smtpConfig['from_email'] . ">\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n";
        return mail($to, mb_encode_mimeheader($subject, 'UTF-8'), $body, $headers);
    }

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $smtpConfig['host'];
        $mail->Port = $smtpConfig['port'];
        $mail->CharSet = \PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
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

// Pick a random name from the names.txt file, or '' if none are available.
function getRandomName($namesFile) {
    $names = file($namesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return empty($names) ? '' : $names[array_rand($names)];
}

// Suggest a random "<name>_<3-digit-number>" placeholder value for the
// registration form's name field.
function suggestRegistrationName($namesFile) {
    $name = getRandomName($namesFile);
    if ($name === '') {
        return '';
    }
    $number = str_pad((string) rand(1, 999), 3, '0', STR_PAD_LEFT);
    return $name . '_' . $number;
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

// Compute the signature for a registration's fields.
function buildRegistrationToken(array $registration) {
    $payload = implode('|', [
        $registration['date'],
        $registration['name'],
        $registration['event'],
        $registration['num_adults'],
        $registration['num_children'],
        $registration['children_ages'],
        $registration['comments'],
    ]);
    return hash_hmac('sha256', $payload, getMailTokenSecret());
}

// Echo the hidden <input> fields carrying a signed registration + token,
// shared by process_form.php's success page and send_confirmation.php's forms.
function renderRegistrationHiddenFields(array $registration, $token) {
    ?>
    <input type="hidden" name="registration_date" value="<?php echo htmlspecialchars($registration['date']); ?>">
    <input type="hidden" name="name" value="<?php echo htmlspecialchars($registration['name']); ?>">
    <input type="hidden" name="event" value="<?php echo htmlspecialchars($registration['event']); ?>">
    <input type="hidden" name="num_adults" value="<?php echo (int) $registration['num_adults']; ?>">
    <input type="hidden" name="num_children" value="<?php echo (int) $registration['num_children']; ?>">
    <input type="hidden" name="children_ages" value="<?php echo htmlspecialchars($registration['children_ages']); ?>">
    <input type="hidden" name="comments" value="<?php echo htmlspecialchars($registration['comments']); ?>">
    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
    <?php
}

// Render the "enter email to receive a confirmation" form for a signed
// registration. $required controls whether the browser enforces an email
// being entered (used on the retry paths, once the visitor already opted in).
function renderEmailForm(array $registration, $token, $email = '', $required = true) {
    ?>
    <form action="send_confirmation.php" method="POST">
        <?php renderRegistrationHiddenFields($registration, $token); ?>
        <div class="form-group">
            <label for="email"><?php echo htmlspecialchars(t('common.email_label')); ?></label>
            <input type="email" id="email" name="email"<?php echo $required ? ' required' : ''; ?> placeholder="you@example.com" value="<?php echo htmlspecialchars($email); ?>">
        </div>
        <button type="submit" class="submit-btn"><?php echo htmlspecialchars(t('common.send_confirmation_button')); ?></button>
    </form>
    <?php
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
                'timeout' => WEBDAV_TIMEOUT_SECONDS,
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
            'timeout' => WEBDAV_TIMEOUT_SECONDS,
            'ignore_errors' => true
        ]
    ]);
    @file_get_contents($url, false, $context);
}

// Function to store a registration in the CSV file via WebDAV
function storeData(array $registration, array $webdavConfig) {
    $row = buildCsvRow([
        $registration['date'],
        sanitizeCsvField($registration['name']),
        sanitizeCsvField($registration['event']),
        $registration['num_adults'],
        $registration['num_children'],
        sanitizeCsvField($registration['children_ages']),
        sanitizeCsvField($registration['comments'])
    ]);

    $authHeader = "Authorization: Basic " . base64_encode($webdavConfig['username'] . ':' . $webdavConfig['password']) . "\r\n";
    $url = rtrim($webdavConfig['url'], '/') . '/' . $webdavConfig['csv_file'];

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
            'timeout' => WEBDAV_TIMEOUT_SECONDS,
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
            'content' => $existing . $row,
            'timeout' => WEBDAV_TIMEOUT_SECONDS
        ]
    ]);

    $result = file_get_contents($url, false, $putContext);

    if ($lockToken !== null) {
        releaseWebdavLock($url, $authHeader, $lockToken);
    }

    return $result !== false;
}
?>
