<?php require_once 'i18n.php'; currentLanguage(); ?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(currentLanguage()); ?>">
<head>
    <title><?php echo htmlspecialchars(t('confirmation.page_title')); ?></title>
    <link rel="stylesheet" type="text/css" href="styles.css">
</head>
<body>
    <div class="container">

<?php
// Sends a confirmation email for a registration that was already stored by
// process_form.php. The email address entered here is used only to send
// this one message - it is never written to the CSV file, a log, or any
// other storage.
require_once 'functions.php';

$registration = [
    'date' => $_POST['registration_date'] ?? '',
    'name' => $_POST['name'] ?? '',
    'event' => $_POST['event'] ?? '',
    'num_adults' => (int) ($_POST['num_adults'] ?? 0),
    'num_children' => (int) ($_POST['num_children'] ?? 0),
    'children_ages' => $_POST['children_ages'] ?? '',
    'comments' => $_POST['comments'] ?? '',
];
$email = trim($_POST['email'] ?? '');
$token = $_POST['token'] ?? '';

// The registration fields only ever arrive here as hidden fields coming from
// process_form.php's success page. They are re-validated against the same
// binding events list process_form.php uses, and the token (signed by
// process_form.php right after a successful registration) is checked so this
// endpoint can't be used to email arbitrary made-up "registration" content to
// arbitrary addresses.
$errors = [];
if (empty($registration['name'])) {
    $errors[] = t('confirmation.error_name_missing');
}
$eventValid = !empty($registration['event']) && validateEvent($registration['event'], EVENTS_FILE);
if (!$eventValid) {
    $errors[] = t('confirmation.error_invalid_event');
}

$expectedToken = buildRegistrationToken($registration);
$tokenValid = hash_equals($expectedToken, $token);
if (!$tokenValid) {
    $errors[] = t('confirmation.error_invalid_request');
}

if (empty($errors) && (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
    $errors[] = t('confirmation.error_invalid_email');
}

if (!empty($errors)) {
    ?>
        <h2><?php echo htmlspecialchars(t('confirmation.error_heading')); ?></h2>
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    <?php
    if ($tokenValid && !empty($registration['name']) && $eventValid) {
        // Registration data was genuine, only the email address needs fixing.
        renderEmailForm($registration, $token, $email);
    } else {
        ?>
        <a href="index.php" class="btn"><?php echo htmlspecialchars(t('common.back_to_form')); ?></a>
        <?php
    }
} else {
    $bodyLines = [
        t('mail.intro'),
        '',
        t('details.date') . ": " . $registration['date'],
        t('details.name') . ": " . $registration['name'],
        t('details.event') . ": " . $registration['event'],
        t('details.num_adults') . ": " . $registration['num_adults'],
        t('details.num_children') . ": " . $registration['num_children'],
    ];
    if ($registration['children_ages'] !== '') {
        $bodyLines[] = t('details.children_ages') . ": " . $registration['children_ages'];
    }
    if ($registration['comments'] !== '') {
        $bodyLines[] = t('details.comments') . ": " . $registration['comments'];
    }
    $bodyLines[] = '';
    $bodyLines[] = t('mail.regards');
    $bodyLines[] = t('mail.team');
    $body = implode("\n", $bodyLines);

    $smtpConfig = loadSmtpConfig(loadEnvConfig(ENV_FILE));
    $sent = sendPlainTextEmail($email, t('mail.subject'), $body, $smtpConfig);

    if ($sent) {
        ?>
            <h2><?php echo htmlspecialchars(t('confirmation.sent_heading')); ?></h2>
            <p><?php echo htmlspecialchars(t('confirmation.sent_message')); ?></p>
            <a href="index.php" class="btn"><?php echo htmlspecialchars(t('common.register_another')); ?></a>
        <?php
    } else {
        ?>
            <h2><?php echo htmlspecialchars(t('confirmation.failed_heading')); ?></h2>
            <p><?php echo htmlspecialchars(t('confirmation.failed_message')); ?></p>
        <?php
        renderEmailForm($registration, $token, $email);
    }
}
?>

    </div>
</body>
</html>
