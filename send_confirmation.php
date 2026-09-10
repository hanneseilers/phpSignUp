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

$registrationDate = $_POST['registration_date'] ?? '';
$name          = $_POST['name'] ?? '';
$event         = $_POST['event'] ?? '';
$num_adults    = (int) ($_POST['num_adults'] ?? 0);
$num_children  = (int) ($_POST['num_children'] ?? 0);
$children_ages = $_POST['children_ages'] ?? '';
$comments      = $_POST['comments'] ?? '';
$email         = trim($_POST['email'] ?? '');
$token         = $_POST['token'] ?? '';

// The registration fields only ever arrive here as hidden fields coming from
// process_form.php's success page. They are re-validated against the same
// binding config/events.txt list process_form.php uses, and the token
// (signed by process_form.php right after a successful registration) is
// checked so this endpoint can't be used to email arbitrary made-up
// "registration" content to arbitrary addresses.
$errors = [];
if (empty($name)) {
    $errors[] = t('confirmation.error_name_missing');
}
$eventValid = !empty($event) && validateEvent($event, 'config/events.txt');
if (!$eventValid) {
    $errors[] = t('confirmation.error_invalid_event');
}

$expectedToken = buildRegistrationToken($registrationDate, $name, $event, $num_adults, $num_children, $children_ages, $comments);
$tokenValid = hash_equals($expectedToken, $token);
if (!$tokenValid) {
    $errors[] = t('confirmation.error_invalid_request');
}

if (empty($errors) && (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))) {
    $errors[] = t('confirmation.error_invalid_email');
}

function renderEmailForm($registrationDate, $name, $event, $num_adults, $num_children, $children_ages, $comments, $token, $email = '') {
    ?>
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
            <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email); ?>">
        </div>
        <button type="submit" class="submit-btn"><?php echo htmlspecialchars(t('common.send_confirmation_button')); ?></button>
    </form>
    <?php
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
    if ($tokenValid && !empty($name) && $eventValid) {
        // Registration data was genuine, only the email address needs fixing.
        renderEmailForm($registrationDate, $name, $event, $num_adults, $num_children, $children_ages, $comments, $token, $email);
    } else {
        ?>
        <a href="index.php" class="btn"><?php echo htmlspecialchars(t('common.back_to_form')); ?></a>
        <?php
    }
} else {
    $bodyLines = [
        t('mail.intro'),
        '',
        t('details.date') . ": $registrationDate",
        t('details.name') . ": $name",
        t('details.event') . ": $event",
        t('details.num_adults') . ": $num_adults",
        t('details.num_children') . ": $num_children",
    ];
    if ($children_ages !== '') {
        $bodyLines[] = t('details.children_ages') . ": $children_ages";
    }
    if ($comments !== '') {
        $bodyLines[] = t('details.comments') . ": $comments";
    }
    $bodyLines[] = '';
    $bodyLines[] = t('mail.regards');
    $bodyLines[] = t('mail.team');
    $body = implode("\n", $bodyLines);

    $smtpConfig = loadSmtpConfig('config/.env');
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
        renderEmailForm($registrationDate, $name, $event, $num_adults, $num_children, $children_ages, $comments, $token, $email);
    }
}
?>

    </div>
</body>
</html>
