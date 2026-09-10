<?php
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
    $secretFile = __DIR__ . '/config/mail_token_secret.php';
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
function buildRegistrationToken($name, $event, $num_adults, $num_children, $children_ages, $comments) {
    $payload = implode('|', [$name, $event, $num_adults, $num_children, $children_ages, $comments]);
    return hash_hmac('sha256', $payload, getMailTokenSecret());
}

// Function to store data in CSV file via WebDAV
function storeData($name, $event, $num_adults, $num_children, $children_ages, $comments, $webdavUrl, $webdavUser, $webdavPass, $csvFile) {
    $row = buildCsvRow([
        date('Y-m-d H:i:s'),
        sanitizeCsvField($name),
        sanitizeCsvField($event),
        $num_adults,
        $num_children,
        sanitizeCsvField($children_ages),
        sanitizeCsvField($comments)
    ]);

    $authHeader = "Authorization: Basic " . base64_encode("$webdavUser:$webdavPass") . "\r\n";
    $url = rtrim($webdavUrl, '/') . '/' . $csvFile;

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

    $putContext = stream_context_create([
        'http' => [
            'method' => 'PUT',
            'header' => $authHeader . "Content-Type: text/csv\r\n",
            'content' => $existing . $row
        ]
    ]);

    $result = file_get_contents($url, false, $putContext);

    return $result !== false;
}
?>
