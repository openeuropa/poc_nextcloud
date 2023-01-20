## Group folder

Code to execute:

```php
<?php
use Drupal\poc_nextcloud\Endpoint\NxGroupFolderEndpoint;
use Drupal\poc_nextcloud\NxEntity\NxGroupFolder;
use Drupal\Tests\poc_nextcloud\Tools\ValueExporter;
use PHPUnit\Framework\Assert;

$endpoint = NxGroupFolderEndpoint::fromConnection($connection);

$ret = [];
$ids_before = $endpoint->loadIds();
Assert::assertIsArray($ids_before);
array_map([Assert::class, 'assertIsInt'], $ids_before);

$id = NxGroupFolder::createStubWithMountpoint('example')
  ->save($endpoint);
Assert::assertIsInt($id);

$ret['loaded group folder'] = $folder = $endpoint->load($id);

$all_ids = $endpoint->loadIds();
Assert::assertSame([...$ids_before, $id], $all_ids);

$all_folders = $endpoint->loadAll();
Assert::assertSame($all_ids, array_keys($all_folders));
ValueExporter::assertSameExport($folder, $all_folders[$id]);

$endpoint->delete($id);
Assert::assertSame($ids_before, $endpoint->loadIds());

return $ret;
?>
```

Result:

```yml
'loaded group folder':
  class: Drupal\poc_nextcloud\NxEntity\NxGroupFolder
  getId(): 35
  getMountPoint(): example
  getGroups(): {  }
  getQuota(): -3
  getSize(): 0
  getAcl(): false
  getManage(): {  }

```

Recorded traffic:

```yml
-
  request:
    method: GET
    uri: 'http://nextcloud:80/apps/groupfolders/folders'
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
    method: POST
    uri: 'http://nextcloud:80/apps/groupfolders/folders'
    form_params:
      mountpoint: example
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
          id: 35
-
  request:
    method: GET
    uri: 'http://nextcloud:80/apps/groupfolders/folders/35'
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
          id: 35
          mount_point: example
          groups: {  }
          quota: '-3'
          size: 0
          acl: false
          manage: {  }
-
  request:
    method: GET
    uri: 'http://nextcloud:80/apps/groupfolders/folders'
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
          35:
            id: 35
            mount_point: example
            groups: {  }
            quota: '-3'
            size: 0
            acl: false
            manage: {  }
-
  request:
    method: GET
    uri: 'http://nextcloud:80/apps/groupfolders/folders'
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
          35:
            id: 35
            mount_point: example
            groups: {  }
            quota: '-3'
            size: 0
            acl: false
            manage: {  }
-
  request:
    method: GET
    uri: 'http://nextcloud:80/apps/groupfolders/folders/35'
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
          id: 35
          mount_point: example
          groups: {  }
          quota: '-3'
          size: 0
          acl: false
          manage: {  }
-
  request:
    method: DELETE
    uri: 'http://nextcloud:80/apps/groupfolders/folders/35'
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
          success: true
-
  request:
    method: GET
    uri: 'http://nextcloud:80/apps/groupfolders/folders'
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

```
