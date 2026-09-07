<!DOCTYPE html>
<html>
<head>
    <title>Registration Successful</title>
    <link rel="stylesheet" type="text/css" href="styles.css">
</head>
<body>
    <div class="container">
            
<?php
// Include the functions file
include 'functions.php';

// Get form data
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$event = $_POST['event'] ?? '';
$num_adults = $_POST['num_adults'] ?? 0;
$num_children = $_POST['num_children'] ?? 0;
$children_ages = $_POST['children_ages'] ?? '';
$comments = $_POST['comments'] ?? '';

// Load configuration from .env file
$envFile = 'config/.env';
if (file_exists($envFile)) {
    $env = parse_ini_file($envFile);
    $webdavUrl = $env['WEBDAV_URL'] ?? '';
    $webdavUser = $env['WEBDAV_USER'] ?? '';
    $webdavPass = $env['WEBDAV_PASS'] ?? '';
    $csvFile = $env['CSV_FILE'] ?? 'registrations.csv';
} else {
    die('Configuration file not found');
}

// Validate form data
$errors = [];
if (empty($name)) {
    $errors[] = 'Name is required';
}
if (empty($email)) {
    $errors[] = 'Email is required';
} elseif (!validateEmail($email)) {
    $errors[] = 'Invalid email format';
}
if (empty($event)) {
    $errors[] = 'Event is required';
} else {
    // Validate event name from events.txt
    $eventsFile = 'config/events.txt';
    $events = file($eventsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!in_array($event, $events)) {
        $errors[] = 'Invalid event selected';
    }
}

// If no errors, process the form
if (empty($errors)) {
    // Store data in CSV file via WebDAV
    $success = storeData($name, $email, $event, $num_adults, $num_children, $children_ages, $comments, $webdavUrl, $webdavUser, $webdavPass, $csvFile);
    
    if ($success) {
        // Send confirmation email
        sendConfirmationEmail($email, $name, $event);
        ?>       
            <h2>Registration Successful!</h2>
            <p>Thank you for registering for the <?php echo htmlspecialchars($event); ?> event.</p>
            <a href="index.php" class="btn">Register another person</a>
        <?php
    } else {
        ?>
            <h2>Registration Failed!</h2>
            <p>There was an error processing your registration. Please try again.</p>
            <a href="index.php" class="btn">Go back to form</a>
        <?php
    }
} else {
    // Display errors
    ?>
        <h2>Registration Errors</h2>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
        <a href="index.php" class="btn">Go back to form</a>
    <?php
}
?>

	</div>
</body>
</html>
