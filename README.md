# PHP Event Registration Form

A simple web-based event registration form with WebDAV storage and email confirmation.

## Features
- User registration form with name, email, and event date
- WebDAV storage for registration data
- CSV file handling
- Email confirmation using SMTP
- Form validation and sanitization

## Installation

1. Clone or download the project files
2. Install PHPMailer dependency:
   ```bash
   composer require phpmailer/phpmailer
   ```

## Configuration

1. Update WebDAV settings in `process_form.php`:
   - `$webdav_url` - WebDAV server URL
   - `$webdav_username` - WebDAV username
   - `$webdav_password` - WebDAV password

2. Configure SMTP settings in `general_functions.php`:
   - Update SMTP server details in `sendConfirmationEmail()` function

3. Ensure the `names.txt` file contains the list of event names

## Usage

1. Access `event_form.php` in a web browser
2. Fill out the registration form
3. Submit to store data in WebDAV and send confirmation email