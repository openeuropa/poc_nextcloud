## User

Code to execute:

```php
<?php
/**
 * Variables passed in from outside.
 *
 * @var \Drupal\poc_nextcloud\Connection\ApiConnectionInterface $connection
 */

use Drupal\poc_nextcloud\Endpoint\NxUserEndpoint;
use Drupal\poc_nextcloud\Exception\FailureResponseException;
use Drupal\poc_nextcloud\NxEntity\NxUser;
use PHPUnit\Framework\Assert;

$endpoint = NxUserEndpoint::fromConnection($connection);

$ret = [];

$ids_before = $endpoint->loadIds();
Assert::assertIsArray($ids_before);
Assert::assertTrue(array_is_list($ids_before));
array_map([Assert::class, 'assertIsString'], $ids_before);

Assert::assertSame('Felipe', NxUser::createStubWithEmail(
  'Felipe',
  'Felipe@example.com',
)->save($endpoint));

try {
  NxUser::createStubWithEmail(
    'Felipe',
    'Felipe_1@example.com',
  )->save($endpoint);
  Assert::fail('Creating an already existing user should result in exception.');
}
catch (FailureResponseException) {
  // Pass.
  Assert::assertTrue(TRUE);
}

Assert::assertSame('Felipe_1', NxUser::createStubWithEmail(
  'Felipe_1',
  'Philippe@example.com',
)->save($endpoint));

$ids = $endpoint->loadIds();
Assert::assertEmpty(array_diff($ids_before, $ids));
Assert::assertSame(['Felipe', 'Felipe_1'], array_values(array_diff($ids, $ids_before)));

$endpoint->delete('Felipe_1');

// Delete non-existing account.
try {
  $endpoint->delete('Felipe_1');
  Assert::fail('Expected an exception for deleting a non-existing account.');
}
catch (FailureResponseException) {
  // Pass.
  Assert::assertTrue(TRUE);
}

$ret['load Felipe'] = $endpoint->load('Felipe');
Assert::assertNull($endpoint->load('Felipe_1'));

$endpoint->delete('Felipe');

Assert::assertSame($ids_before, $endpoint->loadIds());

return $ret;
?>
```

Result:

```yml
'load Felipe':
  class: Drupal\poc_nextcloud\NxEntity\NxUser
  getId(): Felipe
  isEnabled(): true
  getDisplayName(): Felipe
  getEmail(): felipe@example.com

```

Recorded traffic:

```yml
-
  request:
    method: GET
    uri: 'http://nextcloud:80/ocs/v1.php/cloud/users'
  response:
    status: 200
    data:
      ocs:
        meta:
          status: ok
          statuscode: 100
          message: OK
          totalitems: ''
          itemsperpage: ''
        data:
          users:
            - admin
-
  request:
    method: POST
    uri: 'http://nextcloud:80/ocs/v1.php/cloud/users'
    form_params:
      email: Felipe@example.com
      userid: Felipe
  response:
    status: 200
    data:
      ocs:
        meta:
          status: ok
          statuscode: 100
          message: OK
          totalitems: ''
          itemsperpage: ''
        data:
          id: Felipe
-
  request:
    method: POST
    uri: 'http://nextcloud:80/ocs/v1.php/cloud/users'
    form_params:
      email: Felipe_1@example.com
      userid: Felipe
  response:
    status: 200
    data:
      ocs:
        meta:
          status: failure
          statuscode: 102
          message: 'User already exists'
          totalitems: ''
          itemsperpage: ''
        data: {  }
-
  request:
    method: POST
    uri: 'http://nextcloud:80/ocs/v1.php/cloud/users'
    form_params:
      email: Philippe@example.com
      userid: Felipe_1
  response:
    status: 200
    data:
      ocs:
        meta:
          status: ok
          statuscode: 100
          message: OK
          totalitems: ''
          itemsperpage: ''
        data:
          id: Felipe_1
-
  request:
    method: GET
    uri: 'http://nextcloud:80/ocs/v1.php/cloud/users'
  response:
    status: 200
    data:
      ocs:
        meta:
          status: ok
          statuscode: 100
          message: OK
          totalitems: ''
          itemsperpage: ''
        data:
          users:
            - admin
            - Felipe
            - Felipe_1
-
  request:
    method: DELETE
    uri: 'http://nextcloud:80/ocs/v1.php/cloud/users/Felipe_1'
  response:
    status: 200
    data:
      ocs:
        meta:
          status: ok
          statuscode: 100
          message: OK
          totalitems: ''
          itemsperpage: ''
        data: {  }
-
  request:
    method: DELETE
    uri: 'http://nextcloud:80/ocs/v1.php/cloud/users/Felipe_1'
  response:
    status: 200
    data:
      ocs:
        meta:
          status: failure
          statuscode: 998
          message: ''
          totalitems: ''
          itemsperpage: ''
        data: {  }
-
  request:
    method: GET
    uri: 'http://nextcloud:80/ocs/v1.php/cloud/users/Felipe'
  response:
    status: 200
    data:
      ocs:
        meta:
          status: ok
          statuscode: 100
          message: OK
          totalitems: ''
          itemsperpage: ''
        data:
          enabled: true
          storageLocation: /var/www/html/data/Felipe
          id: Felipe
          lastLogin: 0
          backend: Database
          subadmin: {  }
          quota:
            quota: none
            used: 0
          email: felipe@example.com
          additional_mail: {  }
          displayname: Felipe
          phone: ''
          address: ''
          website: ''
          twitter: ''
          organisation: ''
          role: ''
          headline: ''
          biography: ''
          profile_enabled: '1'
          groups: {  }
          language: en
          locale: ''
          notify_email: null
          backendCapabilities:
            setDisplayName: true
            setPassword: true
-
  request:
    method: GET
    uri: 'http://nextcloud:80/ocs/v1.php/cloud/users/Felipe_1'
  response:
    status: 200
    data:
      ocs:
        meta:
          status: failure
          statuscode: 404
          message: 'User does not exist'
          totalitems: ''
          itemsperpage: ''
        data: {  }
-
  request:
    method: DELETE
    uri: 'http://nextcloud:80/ocs/v1.php/cloud/users/Felipe'
  response:
    status: 200
    data:
      ocs:
        meta:
          status: ok
          statuscode: 100
          message: OK
          totalitems: ''
          itemsperpage: ''
        data: {  }
-
  request:
    method: GET
    uri: 'http://nextcloud:80/ocs/v1.php/cloud/users'
  response:
    status: 200
    data:
      ocs:
        meta:
          status: ok
          statuscode: 100
          message: OK
          totalitems: ''
          itemsperpage: ''
        data:
          users:
            - admin

```
