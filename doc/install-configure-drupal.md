# Installation: Setup in Drupal

#### Install the module(s)

Add the github repository as a repository in `composer.json`, and require the module package `openeuropa/poc_nextcloud`.

Enable the modules you need:
- The base module `poc_nextcloud` is required to connect to Nextcloud and synchronize users.
- The submodule `poc_nextcloud_group_folder` is optional. It allows to link Drupal groups to Nextcloud group folders.
- The submodule `poc_nextcloud_demo` is only meant for demo and development purposes. It introduces configuration for group types, user roles and blocks. Typically an existing site will already have these, so it would be counter-productive to install this submodule. However, you can install it on a local test site as inspiration.


#### Configure the Nextcloud connection

The Nextcloud credentials are sensitive information.

##### Using the configuration system (not recommended)

You _can_ store these values as configuration in the database.
There is no UI for this, instead you need to run `drush config-set`.

However, this method is not recommended, because the credentials will be copied to other instances of the website (acceptance, local etc), either through (sanitized or not) database dumps, or through config-sync.

This causes two problems:
- The credentials may leak to unauthorized parties.
- It becomes harder to have different values for different instances of the Drupal website, e.g. production vs acceptance vs local.

##### Using config overrides (semi recommended)

In your `sites/default/settings.php`, add these lines:
```php
// Configuration override for
// Credentials of the API user.
$config['poc_nextcloud.settings']['nextcloud_user'] = 'api_user';
$config['poc_nextcloud.settings']['nextcloud_pass'] = 'api_user_pass';
// Nextcloud URL for server-side API requests.
$config['poc_nextcloud.settings']['nextcloud_url'] = 'https://my.nextcloud.url/';
// Nextcloud URL for client-side links and redirects.
// In some scenarios this could be different from the server-side url.
$config['poc_nextcloud.settings']['nextcloud_web_url'] = 'https://my.nextcloud.url/';
// Encryption key to encrypt authorization cookies or tokens.
// This can be an arbitrary-length random string.
$config['poc_nextcloud.settings']['storage_encryption_key'] = 'W76apvVvJ8yVxt2oA3FLPgZK65rRLLZ5QA6MgsxZqLmeSXVQ';
```

This should be safe enough, if you exclude the settings.php from backups, and overall prevent unauthorized access.

##### Using config overrides + getenv() (recommended)

If you don't like to maintain settings.php directly, you can use environment variables to control these settings.

In your `sites/default/settings.php`, replace the literal values with `getenv()` calls.
You are free to choose different names for the env vars.

```php
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
```

### Configure group types

Install `poc_nextcloud`
