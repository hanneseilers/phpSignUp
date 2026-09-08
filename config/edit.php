<?php
// Simple text file editor for .txt, .yaml, and .md files

// Define the directory where files are stored
$editorDir = __DIR__ . '';

// Create the editor directory if it doesn't exist
if (!is_dir($editorDir)) {
    mkdir($editorDir, 0755, true);
}

// Get list of files with .txt, .yaml, or .md extensions
$files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($editorDir));
foreach ($iterator as $file) {
    if ($file->isFile() && in_array($file->getExtension(), ['txt', 'yaml', 'md'])) {
        $files[] = $file->getPathname();
    }
}

// Handle file selection
$selectedFile = $_GET['file'] ?? '';
$content = '';

if (!empty($selectedFile) && in_array($selectedFile, $files)) {
    $content = file_get_contents($selectedFile);
}

// Handle saving
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['file']) && isset($_POST['content'])) {
    $file = $_POST['file'];
    $content = $_POST['content'];
    
    if (in_array($file, $files)) {
        file_put_contents($file, $content);
        $message = "File saved successfully!";
    } else {
        $message = "Error: Invalid file.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Konfigurationseditor</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        .file-list { margin-bottom: 20px; }
        .editor { display: flex; flex-direction: column; }
        textarea { flex: 1; padding: 10px; font-family: monospace; }
        .save-btn { padding: 10px 20px; background: #007cba; color: white; border: none; cursor: pointer; }
        .save-btn:hover { background: #005a87; }
        .message { padding: 10px; margin: 10px 0; }
        .success { background: #d4edda; color: #155724; }
        .error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="form-container">
        <h1>Simple Text Editor</h1>
        
        <?php if (isset($message)): ?>
            <div class="message <?php echo strpos($message, 'successfully') !== false ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="file-list">
            <form method="GET">
                <label for="file">Select File:</label>
                <select name="file" id="file" onchange="this.form.submit()">
                    <option value="">-- Choose a file --</option>
                    <?php foreach ($files as $file): ?>
                        <option value="<?php echo htmlspecialchars($file); ?>" <?php echo $selectedFile === $file ? 'selected' : ''; ?>>
                            <?php echo basename($file); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        
        <?php if (!empty($selectedFile)): ?>
            <div class="editor">
                <form method="POST">
                    <input type="hidden" name="file" value="<?php echo htmlspecialchars($selectedFile); ?>">
                    <textarea name="content" rows="30" cols="80"><?php echo htmlspecialchars($content); ?></textarea>
                    <br><br>
                    <input type="submit" value="Save File" class="save-btn">
                </form>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
