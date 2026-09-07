<?php
// Process form data and store in CSV file via WebDAV

// Load environment variables from .env file
$env_file = __DIR__ . '/.env';
if (file_exists($env_file)) {
    $env = parse_ini_file($env_file);
    $webdav_url = $env['WEBDAV_URL'] ?? '';
    $webdav_username = $env['WEBDAV_USERNAME'] ?? '';
    $webdav_password = $env['WEBDAV_PASSWORD'] ?? '';
    $webdav_file_path = $env['WEBDAV_FILE_PATH'] ?? 'event_registrations.csv';
} else {
    // Fallback to hardcoded values if .env file doesn't exist
    $webdav_url = 'https://your-nextcloud-instance.com/remote.php/webdav/';
    $webdav_username = 'your_username';
    $webdav_password = 'your_password';
    $webdav_file_path = 'event_registrations.csv';
}

// Split WebDAV URL into base URL and file path
$webdav_base_url = rtrim($webdav_url, '/');
$webdav_file_name = basename($webdav_file_path);
$webdav_folder_path = dirname($webdav_file_path);
if ($webdav_folder_path === '.') {
    $webdav_folder_path = '';
}

// Validate WebDAV connection
function validateWebDAVConnection($base_url, $username, $password) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $base_url);
    curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PROPFIND");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Depth: 0',
        'Content-Type: application/xml'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $http_code == 207; // PROPFIND success
}

// Validate WebDAV connection
if (!validateWebDAVConnection($webdav_base_url, $webdav_username, $webdav_password)) {
    die('Error: Could not connect to Nextcloud instance. Please check your credentials and URL.');
}

// Get form data
$event_name = $_POST['event_name'] ?? '';
$name = $_POST['name'] ?? '';
$num_adults = $_POST['num_adults'] ?? '0';
$num_children = $_POST['num_children'] ?? '0';
$children_ages = $_POST['children_ages'] ?? '';
$comments = $_POST['comments'] ?? '';

// Validate data
if (empty($event_name) || empty($name)) {
    die('Error: Event name and name are required.');
}

// Prepare CSV data
$data = [
    $event_name,
    $name,
    $num_adults,
    $num_children,
    $children_ages,
    $comments,
    date('Y-m-d H:i:s') // Add timestamp
];

// Convert to CSV format
$csv_line = '"' . implode('","', array_map(function($field) {
    return str_replace('"', '""', $field); // Escape quotes
}, $data)) . "\"\n";

// Function to download file via WebDAV
function downloadFromWebDAV($base_url, $username, $password, $folder_path, $filename) {
    $full_url = $base_url;
    if (!empty($folder_path)) {
        $full_url .= '/' . ltrim($folder_path, '/');
    }
    $full_url .= '/' . $filename;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $full_url);
    curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $http_code == 200 ? $response : false;
}

// Function to upload file via WebDAV
function uploadToWebDAV($base_url, $username, $password, $folder_path, $filename, $content) {
    // Construct full URL
    $full_url = $base_url;
    if (!empty($folder_path)) {
        $full_url .= '/' . ltrim($folder_path, '/');
    }
    $full_url .= '/' . $filename;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $full_url);
    curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: text/csv',
        'Content-Length: ' . strlen($content)
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $http_code;
}

// Check if CSV file exists in WebDAV and download it
$csv_content = downloadFromWebDAV($webdav_base_url, $webdav_username, $webdav_password, $webdav_folder_path, $webdav_file_name);

// If file doesn't exist, create with header
if ($csv_content === false) {
    $csv_content = "Event Name,Name,Number of Adults,Number of Children,Ages of Children,Comments,Timestamp\n";
}

// Append new data to CSV content
$csv_content .= $csv_line;

// Upload updated content back to WebDAV
$http_code = uploadToWebDAV($webdav_base_url, $webdav_username, $webdav_password, $webdav_folder_path, $webdav_file_name, $csv_content);

if ($http_code == 200 || $http_code == 201) {
    echo "<h2>Registration Successful!</h2>";
    echo "<p>Your registration has been saved to the Nextcloud instance.</p>";
    echo "<h3>Registered Data:</h3>";
    echo "<ul>";
    echo "<li><strong>Event Name:</strong> " . htmlspecialchars($event_name) . "</li>";
    echo "<li><strong>Name:</strong> " . htmlspecialchars($name) . "</li>";
    echo "<li><strong>Number of Adults:</strong> " . htmlspecialchars($num_adults) . "</li>";
    echo "<li><strong>Number of Children:</strong> " . htmlspecialchars($num_children) . "</li>";
    echo "<li><strong>Ages of Children:</strong> " . htmlspecialchars($children_ages) . "</li>";
    echo "<li><strong>Comments:</strong> " . htmlspecialchars($comments) . "</li>";
    echo "<li><strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "</li>";
    echo "</ul>";
    
    // Email input field for confirmation
    echo "<div style='margin-top: 20px;'>";
    echo "<p><strong>Get a confirmation email:</strong></p>";
    echo "<form method='post' action='send_confirmation.php' style='display: inline;'>";
    echo "<input type='email' name='email' placeholder='Enter your email address' required style='padding: 5px; margin-right: 10px;'>";
    echo "<input type='hidden' name='event_name' value='" . htmlspecialchars($event_name) . "'>";
    echo "<input type='hidden' name='name' value='" . htmlspecialchars($name) . "'>";
    echo "<input type='hidden' name='num_adults' value='" . htmlspecialchars($num_adults) . "'>";
    echo "<input type='hidden' name='num_children' value='" . htmlspecialchars($num_children) . "'>";
    echo "<input type='hidden' name='children_ages' value='" . htmlspecialchars($children_ages) . "'>";
    echo "<input type='hidden' name='comments' value='" . htmlspecialchars($comments) . "'>";
    echo "<button type='submit' style='padding: 5px 10px; background: #007cba; color: white; border: none; cursor: pointer;'>Send Confirmation</button>";
    echo "</form>";
    echo "</div>";
    
    echo "<p><a href='event_form.php'>Register for another event</a></p>";
} else {
    echo "<h2>Registration Error!</h2>";
    echo "<p>There was an error saving your registration to the Nextcloud instance.</p>";
    echo "<p>Please try again or contact support.</p>";
}
?>
?>
?>
?>

if ($http_code == 200 || $http_code == 201) {
    echo "<h2>Registration Successful!</h2>";
    echo "<p>Your registration has been saved to the Nextcloud instance.</p>";
    echo "<p><a href='event_form.php'>Register for another event</a></p>";
} else {
    echo "<h2>Registration Error!</h2>";
    echo "<p>There was an error saving your registration to the Nextcloud instance.</p>";
    echo "<p>Please try again or contact support.</p>";
}
?>