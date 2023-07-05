<?php

/**
 * @file
 * Additional settings for Drupal.
 *
 * This file is included from settings.override.php.
 */

// Settings for the mock server, as in oe_authentication.
// SSL Configuration to not verify CAS server. DO NOT USE IN PRODUCTION!
$config['cas.settings']['server']['verify'] = '2';
$config['oe_authentication.settings']['protocol'] = 'eulogin';
// Use the original validation path, as needed for Nextcloud user_cas.
$config['oe_authentication.settings']['validation_path'] = 'p3/serviceValidate';
// Set a distinguishable title for the mock login form.
$config['cas_mock_server.settings']['login_form']['title'] = 'EU Login';
// Username does not work, it has to be the email address.
$config['cas_mock_server.settings']['login_form']['email'] = 'E-mail address';
// Don't let the mock accounts expire so soon.
$config['cas_mock_server.settings']['users']['expire'] = 60 * 60 * 24 * 360;

// Configuration overrides for 'poc_nextcloud' module.
// Credentials of the API user.
// This can be 'admin', but it is recommended to created a dedicated user.
$config['poc_nextcloud.settings']['nextcloud_user'] = getenv('NEXTCLOUD_API_USER');
$config['poc_nextcloud.settings']['nextcloud_pass'] = getenv('NEXTCLOUD_API_PASS');
// Nextcloud URL for server-side API requests.
$config['poc_nextcloud.settings']['nextcloud_url'] = getenv('NEXTCLOUD_API_URL');
// Nextcloud URL for client-side links and redirects.
// In some scenarios this could be different from the server-side url.
$config['poc_nextcloud.settings']['nextcloud_web_url'] = getenv('NEXTCLOUD_WEB_URL');
// Encryption key to encrypt authorization cookies or tokens.
// This can be an arbitrary-length random string.
$config['poc_nextcloud.settings']['storage_encryption_key'] = getenv('NEXTCLOUD_CRYPT_SECRET');

// Enable full error reporting, for easier development.
error_reporting(E_ALL);
ini_set('display_errors', TRUE);
ini_set('display_startup_errors', TRUE);
$config['system.logging']['error_level'] = 'verbose';
