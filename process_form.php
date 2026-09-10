<?php require_once 'i18n.php'; currentLanguage(); ?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(currentLanguage()); ?>">
<head>
    <title><?php echo htmlspecialchars(t('registration.page_title')); ?></title>
    <link rel="stylesheet" type="text/css" href="styles.css">
</head>
<body>
    <div class="container">

<?php
// Include the functions file
include 'functions.php';

// Get form data
$name = $_POST['name'] ?? '';
$event = $_POST['event'] ?? '';
$num_adults = (int) ($_POST['num_adults'] ?? 0);
$num_children = (int) ($_POST['num_children'] ?? 0);
$children_ages = $_POST['children_ages'] ?? '';
$comments = $_POST['comments'] ?? '';

// Load configuration from .env file
$envFile = 'config/.env';
if (file_exists($envFile)) {
    $env = parse_ini_file($envFile);
    $webdavUrl = $env['WEBDAV_URL'] ?? '';
    $webdavUser = $env['WEBDAV_USERNAME'] ?? '';
    $webdavPass = $env['WEBDAV_PASSWORD'] ?? '';
    $csvFile = $env['WEBDAV_FILE_PATH'] ?? 'registrations.csv';
} else {
    die('Configuration file not found');
}

// Validate form data
$errors = [];
if (empty($name)) {
    $errors[] = t('registration.error_name_required');
}
if (empty($event)) {
    $errors[] = t('registration.error_event_required');
} elseif (!validateEvent($event, 'config/events.txt')) {
    $errors[] = t('registration.error_invalid_event');
}

// If no errors, process the form
if (empty($errors)) {
    // Store data in CSV file via WebDAV
    $success = storeData($name, $event, $num_adults, $num_children, $children_ages, $comments, $webdavUrl, $webdavUser, $webdavPass, $csvFile);

    if ($success) {
        // Sign the registration fields so send_confirmation.php can verify
        // the confirmation-email request really came from this registration.
        $token = buildRegistrationToken($name, $event, $num_adults, $num_children, $children_ages, $comments);
        ?>
            <h2><?php echo htmlspecialchars(t('registration.success_heading')); ?></h2>
            <p><?php echo htmlspecialchars(t('registration.success_message', ['event' => $event])); ?></p>

            <h3><?php echo htmlspecialchars(t('registration.confirmation_heading')); ?></h3>
            <p><?php echo htmlspecialchars(t('registration.confirmation_intro')); ?></p>
            <form action="send_confirmation.php" method="POST">
                <input type="hidden" name="name" value="<?php echo htmlspecialchars($name); ?>">
                <input type="hidden" name="event" value="<?php echo htmlspecialchars($event); ?>">
                <input type="hidden" name="num_adults" value="<?php echo (int) $num_adults; ?>">
                <input type="hidden" name="num_children" value="<?php echo (int) $num_children; ?>">
                <input type="hidden" name="children_ages" value="<?php echo htmlspecialchars($children_ages); ?>">
                <input type="hidden" name="comments" value="<?php echo htmlspecialchars($comments); ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <div class="form-group">
                    <label for="email"><?php echo htmlspecialchars(t('common.email_label')); ?></label>
                    <input type="email" id="email" name="email" placeholder="you@example.com">
                </div>
                <button type="submit" class="submit-btn"><?php echo htmlspecialchars(t('common.send_confirmation_button')); ?></button>
            </form>

            <a href="index.php" class="btn"><?php echo htmlspecialchars(t('common.register_another')); ?></a>
        <?php
    } else {
        ?>
            <h2><?php echo htmlspecialchars(t('registration.failure_heading')); ?></h2>
            <p><?php echo htmlspecialchars(t('registration.failure_message')); ?></p>
            <a href="index.php" class="btn"><?php echo htmlspecialchars(t('common.back_to_form')); ?></a>
        <?php
    }
} else {
    // Display errors
    ?>
        <h2><?php echo htmlspecialchars(t('registration.errors_heading')); ?></h2>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
        <a href="index.php" class="btn"><?php echo htmlspecialchars(t('common.back_to_form')); ?></a>
    <?php
}
?>

	</div>
</body>
</html>
