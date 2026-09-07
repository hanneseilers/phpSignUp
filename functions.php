<?php
// Function to generate a random name from the names.txt file
function getRandomName($namesFile) {
    $names = file($namesFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (empty($names)) {
        return "No names available";
    }
    return $names[array_rand($names)];
}

// Function to validate email format
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Function to validate event name
function validateEvent($event, $eventsFile) {
    $events = file($eventsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    return in_array($event, $events);
}

// Function to store data in CSV file via WebDAV
function storeData($name, $email, $event, $num_adults, $num_children, $children_ages, $comments, $webdavUrl, $webdavUser, $webdavPass, $csvFile) {
    // Create CSV row
    $data = [
        date('Y-m-d H:i:s'),
        $name,
        $email,
        $event,
        $num_adults,
        $num_children,
        $children_ages,
        $comments
    ];
    
    // Connect to WebDAV and store data
    $context = stream_context_create([
        'http' => [
            'method' => 'PUT',
            'header' => "Authorization: Basic " . base64_encode("$webdavUser:$webdavPass") . "\r\n" .
                        "Content-Type: text/csv\r\n",
            'content' => implode(',', $data) . "\n"
        ]
    ]);
    
    $url = $webdavUrl . '/' . $csvFile;
    $result = file_get_contents($url, false, $context);
    
    return $result !== false;
}

// Function to send confirmation email
function sendConfirmationEmail($email, $name, $event) {
    $subject = "Registration Confirmation";
    $message = "Dear $name,\n\nThank you for registering for the $event event.\n\nBest regards,\nEvent Team";
    $headers = "From: no-reply@event.com";
    
    return mail($email, $subject, $message, $headers);
}
?>