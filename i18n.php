<?php
// Minimal YAML-backed translation helper.
//
// Language files live in translations/<code>.yaml. The active language is
// picked from a `lang` request parameter (persisted in a cookie so it
// survives the multi-page registration flow) or, failing that, the cookie
// set by a previous request, falling back to DEFAULT_LANGUAGE.
require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

define('DEFAULT_LANGUAGE', 'de');
define('AVAILABLE_LANGUAGES', ['de', 'en']);

/**
 * Resolve the language for this request. Must be called before any output
 * is sent, since selecting a language via ?lang= sets a cookie.
 * @return string
 */
function currentLanguage() {
    static $lang = null;
    if ($lang !== null) {
        return $lang;
    }

    $requested = $_GET['lang'] ?? $_POST['lang'] ?? null;
    if (is_string($requested) && in_array($requested, AVAILABLE_LANGUAGES, true)) {
        $lang = $requested;
        if (!headers_sent()) {
            setcookie('lang', $lang, time() + 60 * 60 * 24 * 365, '/');
        }
        return $lang;
    }

    $cookieLang = $_COOKIE['lang'] ?? null;
    if (is_string($cookieLang) && in_array($cookieLang, AVAILABLE_LANGUAGES, true)) {
        $lang = $cookieLang;
        return $lang;
    }

    $lang = DEFAULT_LANGUAGE;
    return $lang;
}

/**
 * Load (and cache) the translation map for a language.
 * @param string $lang
 * @return array
 */
function loadTranslations($lang) {
    static $cache = [];
    if (isset($cache[$lang])) {
        return $cache[$lang];
    }

    $file = __DIR__ . "/translations/{$lang}.yaml";
    if (!file_exists($file)) {
        $file = __DIR__ . '/translations/' . DEFAULT_LANGUAGE . '.yaml';
    }

    $data = file_exists($file) ? Yaml::parseFile($file) : [];
    $cache[$lang] = is_array($data) ? $data : [];
    return $cache[$lang];
}

/**
 * Look up a dot-notated key ("form.submit") inside a nested translation array.
 * @param array $data
 * @param string $key
 * @return string|null
 */
function translationValue($data, $key) {
    $value = $data;
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }
    return is_string($value) ? $value : null;
}

/**
 * Translate a key for the current request's language, with optional
 * {placeholder} substitution. Falls back to the key itself when a
 * translation is missing, so a gap is easy to spot instead of failing silently.
 * @param string $key
 * @param array $vars
 * @return string
 */
function t($key, $vars = []) {
    $value = translationValue(loadTranslations(currentLanguage()), $key);
    if ($value === null) {
        return $key;
    }
    foreach ($vars as $name => $replacement) {
        $value = str_replace('{' . $name . '}', (string) $replacement, $value);
    }
    return $value;
}

/**
 * Native display name of a language, read from its own translation file.
 * @param string $lang
 * @return string
 */
function languageName($lang) {
    return translationValue(loadTranslations($lang), 'language_name') ?? strtoupper($lang);
}
