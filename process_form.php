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
if ($num_adults < 1) {
    $errors[] = t('registration.error_min_adults');
}

// If no errors, process the form
if (empty($errors)) {
    // Fixed once here so the value shown to the user, the value emailed
    // later, and the value actually written to the CSV are all identical.
    $registrationDate = date('Y-m-d H:i:s');

    // Store data in CSV file via WebDAV
    $success = storeData($registrationDate, $name, $event, $num_adults, $num_children, $children_ages, $comments, $webdavUrl, $webdavUser, $webdavPass, $csvFile);

    if ($success) {
        // Sign the registration fields so send_confirmation.php can verify
        // the confirmation-email request really came from this registration.
        $token = buildRegistrationToken($registrationDate, $name, $event, $num_adults, $num_children, $children_ages, $comments);
        ?>
            <h2><?php echo htmlspecialchars(t('registration.success_heading')); ?></h2>
            <p><?php echo htmlspecialchars(t('registration.success_message', ['event' => $event])); ?></p>

            <h3><?php echo htmlspecialchars(t('registration.details_heading')); ?></h3>
            <ul class="registration-details">
                <li><strong><?php echo htmlspecialchars(t('details.date')); ?>:</strong> <?php echo htmlspecialchars($registrationDate); ?></li>
                <li><strong><?php echo htmlspecialchars(t('details.name')); ?>:</strong> <?php echo htmlspecialchars($name); ?></li>
                <li><strong><?php echo htmlspecialchars(t('details.event')); ?>:</strong> <?php echo htmlspecialchars($event); ?></li>
                <li><strong><?php echo htmlspecialchars(t('details.num_adults')); ?>:</strong> <?php echo (int) $num_adults; ?></li>
                <li><strong><?php echo htmlspecialchars(t('details.num_children')); ?>:</strong> <?php echo (int) $num_children; ?></li>
                <?php if ($children_ages !== ''): ?>
                    <li><strong><?php echo htmlspecialchars(t('details.children_ages')); ?>:</strong> <?php echo htmlspecialchars($children_ages); ?></li>
                <?php endif; ?>
                <?php if ($comments !== ''): ?>
                    <li><strong><?php echo htmlspecialchars(t('details.comments')); ?>:</strong> <?php echo htmlspecialchars($comments); ?></li>
                <?php endif; ?>
            </ul>

            <h3><?php echo htmlspecialchars(t('registration.confirmation_heading')); ?></h3>
            <p><?php echo htmlspecialchars(t('registration.confirmation_intro')); ?></p>
            <form action="send_confirmation.php" method="POST">
                <input type="hidden" name="registration_date" value="<?php echo htmlspecialchars($registrationDate); ?>">
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

            <p><a href="index.php" class="btn">&larr; <?php echo htmlspecialchars(t('common.register_another')); ?></a></p>
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
