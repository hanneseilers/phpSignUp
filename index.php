<?php
require_once 'i18n.php';
require_once 'functions.php';
currentLanguage();
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars(currentLanguage()); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(t('form.page_title')); ?></title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="form-container">
        <form method="GET" class="lang-switch">
            <label for="lang"><?php echo htmlspecialchars(t('form.language_label')); ?></label>
            <select name="lang" id="lang" onchange="this.form.submit()">
                <?php foreach (AVAILABLE_LANGUAGES as $code): ?>
                    <option value="<?php echo htmlspecialchars($code); ?>" <?php echo $code === currentLanguage() ? 'selected' : ''; ?>><?php echo htmlspecialchars(languageName($code)); ?></option>
                <?php endforeach; ?>
            </select>
        </form>

        <h2><?php echo htmlspecialchars(t('form.heading')); ?></h2>
        <form action="process_form.php" method="POST">
            <div class="form-group">
                <label for="event_name"><?php echo htmlspecialchars(t('form.event_label')); ?></label>
                <?php
                // A ?event=... URL parameter preselects that event and locks the
                // field to it - but only if it is actually one of the configured
                // events; config/events.txt is the binding list, so an unknown
                // or missing value just falls back to the normal full dropdown.
                $events = loadEvents(EVENTS_FILE);
                $requestedEvent = is_string($_GET['event'] ?? null) ? trim($_GET['event']) : '';
                $preselectedEvent = in_array($requestedEvent, $events, true) ? $requestedEvent : '';
                ?>
                <select id="event_name" name="event" required>
                    <?php if ($preselectedEvent !== ''): ?>
                        <option value="<?php echo htmlspecialchars($preselectedEvent); ?>" selected><?php echo htmlspecialchars($preselectedEvent); ?></option>
                    <?php else: ?>
                        <option value=""><?php echo htmlspecialchars(t('form.event_placeholder')); ?></option>
                        <?php foreach ($events as $event): ?>
                            <option value='<?php echo htmlspecialchars($event); ?>'><?php echo htmlspecialchars($event); ?></option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <?php if ($preselectedEvent !== ''): ?>
                    <p class="event-preselected-note"><?php echo htmlspecialchars(t('form.event_preselected_note')); ?></p>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label for="name"><?php echo htmlspecialchars(t('form.name_label')); ?></label>
                <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars(suggestRegistrationName(NAMES_FILE)); ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="num_adults"><?php echo htmlspecialchars(t('form.num_adults_label')); ?></label>
                    <input type="number" id="num_adults" name="num_adults" min="1" max="<?php echo MAX_PARTICIPANTS; ?>" value="1" required>
                </div>
                <div class="form-group">
                    <label for="num_children"><?php echo htmlspecialchars(t('form.num_children_label')); ?></label>
                    <input type="number" id="num_children" name="num_children" min="0" max="<?php echo MAX_PARTICIPANTS; ?>" value="0">
                </div>
            </div>

            <div class="form-group">
                <label for="children_ages"><?php echo htmlspecialchars(t('form.children_ages_label')); ?></label>
                <textarea id="children_ages" name="children_ages" placeholder="<?php echo htmlspecialchars(t('form.children_ages_placeholder')); ?>"></textarea>
            </div>

            <div class="form-group">
                <label for="comments"><?php echo htmlspecialchars(t('form.comments_label')); ?></label>
                <textarea id="comments" name="comments" placeholder="<?php echo htmlspecialchars(t('form.comments_placeholder')); ?>"></textarea>
            </div>

            <button type="submit" class="submit-btn"><?php echo htmlspecialchars(t('form.submit')); ?></button>
        </form>
    </div>
</body>
</html>
