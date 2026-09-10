# PHP Event Registration Form

A simple web-based event registration form with WebDAV storage.

## Features
- User registration form with name, event, adult/child counts and comments
- WebDAV storage for registration data (appends to a CSV file)
- CSV file handling with proper escaping
- Form validation and sanitization
- Optional confirmation email: after a successful registration you may enter
  an email address to receive the registration details by mail. That address
  is used only to send this one message and is never stored.
- Multi-language UI (German/English), switchable in the form; translations
  live as YAML files under `translations/`

## Installation

1. Clone or download the project files
2. Install dependencies:
   ```bash
   composer install
   ```

## Configuration

1. Copy `config/.env.example` to `config/.env` and fill in your WebDAV settings:
   - `WEBDAV_URL` - WebDAV server URL
   - `WEBDAV_USERNAME` - WebDAV username
   - `WEBDAV_PASSWORD` - WebDAV password
   - `WEBDAV_FILE_PATH` - CSV file name/path on the WebDAV server

2. Ensure `config/events.txt` contains the list of selectable events and
   `config/names.txt` contains the list of names used for the placeholder value

## Translations

UI text lives in `translations/<language-code>.yaml` (currently `de` and
`en`). To add a language, copy an existing file, translate the values (keep
the keys and any `{placeholder}` tokens unchanged), and add its code to
`AVAILABLE_LANGUAGES` in `i18n.php`. The chosen language is stored in a
cookie so it carries over across the registration flow.

## Usage

1. Access `index.php` in a web browser
2. Fill out the registration form
3. Submit to store the registration in the WebDAV-hosted CSV file