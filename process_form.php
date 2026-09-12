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
$registration = [
    'date' => date('Y-m-d H:i:s'),
    'name' => $_POST['name'] ?? '',
    'event' => $_POST['event'] ?? '',
    'num_adults' => (int) ($_POST['num_adults'] ?? 0),
    'num_children' => (int) ($_POST['num_children'] ?? 0),
    'children_ages' => $_POST['children_ages'] ?? '',
    'comments' => $_POST['comments'] ?? '',
];

// Load configuration from .env file
$env = loadEnvConfig(ENV_FILE);

if (empty($env)) {
    ?>
        <h2><?php echo htmlspecialchars(t('registration.failure_heading')); ?></h2>
        <p><?php echo htmlspecialchars(t('registration.error_config_missing')); ?></p>
        <a href="index.php" class="btn"><?php echo htmlspecialchars(t('common.back_to_form')); ?></a>
    <?php
} else {
    $webdavConfig = loadWebdavConfig($env);

    // Validate form data
    $errors = [];
    if (empty($registration['name'])) {
        $errors[] = t('registration.error_name_required');
    }
    if (empty($registration['event'])) {
        $errors[] = t('registration.error_event_required');
    } elseif (!validateEvent($registration['event'], EVENTS_FILE)) {
        $errors[] = t('registration.error_invalid_event');
    }
    if ($registration['num_adults'] < 1) {
        $errors[] = t('registration.error_min_adults');
    } elseif ($registration['num_adults'] > MAX_PARTICIPANTS) {
        $errors[] = t('registration.error_max_adults', ['max' => MAX_PARTICIPANTS]);
    }
    if ($registration['num_children'] > MAX_PARTICIPANTS) {
        $errors[] = t('registration.error_max_children', ['max' => MAX_PARTICIPANTS]);
    }

    // If no errors, process the form
    if (empty($errors)) {
        // Store data in CSV file via WebDAV
        $success = storeData($registration, $webdavConfig);

        if ($success) {
            // Sign the registration fields so send_confirmation.php can verify
            // the confirmation-email request really came from this registration.
            $token = buildRegistrationToken($registration);
            ?>
                <h2><?php echo htmlspecialchars(t('registration.success_heading')); ?></h2>
                <p><?php echo htmlspecialchars(t('registration.success_message', ['event' => $registration['event']])); ?></p>

                <h3><?php echo htmlspecialchars(t('registration.details_heading')); ?></h3>
                <ul class="registration-details">
                    <li><strong><?php echo htmlspecialchars(t('details.date')); ?>:</strong> <?php echo htmlspecialchars($registration['date']); ?></li>
                    <li><strong><?php echo htmlspecialchars(t('details.name')); ?>:</strong> <?php echo htmlspecialchars($registration['name']); ?></li>
                    <li><strong><?php echo htmlspecialchars(t('details.event')); ?>:</strong> <?php echo htmlspecialchars($registration['event']); ?></li>
                    <li><strong><?php echo htmlspecialchars(t('details.num_adults')); ?>:</strong> <?php echo (int) $registration['num_adults']; ?></li>
                    <li><strong><?php echo htmlspecialchars(t('details.num_children')); ?>:</strong> <?php echo (int) $registration['num_children']; ?></li>
                    <?php if ($registration['children_ages'] !== ''): ?>
                        <li><strong><?php echo htmlspecialchars(t('details.children_ages')); ?>:</strong> <?php echo htmlspecialchars($registration['children_ages']); ?></li>
                    <?php endif; ?>
                    <?php if ($registration['comments'] !== ''): ?>
                        <li><strong><?php echo htmlspecialchars(t('details.comments')); ?>:</strong> <?php echo htmlspecialchars($registration['comments']); ?></li>
                    <?php endif; ?>
                </ul>

                <h3><?php echo htmlspecialchars(t('registration.confirmation_heading')); ?></h3>
                <p><?php echo htmlspecialchars(t('registration.confirmation_intro')); ?></p>
                <?php renderEmailForm($registration, $token, '', false); ?>

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
}
?>

	</div>
</body>
</html>
