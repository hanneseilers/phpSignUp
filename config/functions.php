<?php
// Helper functions for the standalone config file editor (edit.php).
//
// Deliberately self-contained: nothing here depends on the parent project's
// functions.php/i18n.php/etc., so this config/ folder can be copied out into
// its own project and keep working on its own.

// File extensions the editor is allowed to open and save.
define('EDITOR_ALLOWED_EXTENSIONS', ['txt', 'yaml', 'md']);

// Recursively list all editable files (see EDITOR_ALLOWED_EXTENSIONS) under $dir.
function getEditableFiles($dir) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && in_array($file->getExtension(), EDITOR_ALLOWED_EXTENSIONS, true)) {
            $files[] = $file->getPathname();
        }
    }
    return $files;
}

// Write $content to $file, but only if it is one of the previously listed
// $allowedFiles - callers must never pass a path taken straight from user
// input without checking it against that list first.
function saveEditableFile($file, $content, array $allowedFiles) {
    if (!in_array($file, $allowedFiles, true)) {
        return false;
    }
    return file_put_contents($file, $content) !== false;
}
?>
