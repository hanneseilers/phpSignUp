<?php
// Simple text file editor for .txt, .yaml and .md files. Self-contained:
// everything it needs lives in this config/ folder (functions.php,
// styles.css), so the folder can be copied into its own project and keep
// working there on its own, independently of the rest of this project.
require_once __DIR__ . '/functions.php';

$editorDir = __DIR__;
$files = getEditableFiles($editorDir);

$selectedFile = $_GET['file'] ?? '';
$content = '';
if ($selectedFile !== '' && in_array($selectedFile, $files, true)) {
    $content = file_get_contents($selectedFile);
}

$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file'], $_POST['content'])) {
    $file = $_POST['file'];
    $content = $_POST['content'];

    if (saveEditableFile($file, $content, $files)) {
        $selectedFile = $file;
        $message = ['type' => 'success', 'text' => 'Datei erfolgreich gespeichert.'];
    } else {
        $message = ['type' => 'error', 'text' => 'Fehler: Ungültige Datei.'];
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Konfigurationseditor</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <div class="form-container">
        <h1>Konfigurationseditor</h1>

        <?php if ($message !== null): ?>
            <div class="message <?php echo htmlspecialchars($message['type']); ?>">
                <?php echo htmlspecialchars($message['text']); ?>
            </div>
        <?php endif; ?>

        <div class="file-list">
            <form method="GET">
                <label for="file">Datei auswählen:</label>
                <select name="file" id="file" onchange="this.form.submit()">
                    <option value="">-- Datei auswählen --</option>
                    <?php foreach ($files as $file): ?>
                        <option value="<?php echo htmlspecialchars($file); ?>" <?php echo $selectedFile === $file ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(basename($file)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if ($selectedFile !== ''): ?>
            <div class="editor">
                <form method="POST">
                    <input type="hidden" name="file" value="<?php echo htmlspecialchars($selectedFile); ?>">
                    <textarea name="content" rows="30" cols="80"><?php echo htmlspecialchars($content); ?></textarea>
                    <br>
                    <input type="submit" value="Datei speichern" class="save-btn">
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
