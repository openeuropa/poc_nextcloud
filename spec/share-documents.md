## Share documents

Needs to be addressed:

> Users can share document(s) stored in the Nextcloud directly in the website. These sharing are only visible by site users matching the access permissions set in the Nextcloud.
>
> As results, users can:
> - "Promote" through the communities site their work (documents) to the other members of their group or to other site users, if the document's access permissions allow it;
> - Create shortcuts in special locations of the communities site (dashboard or group landing page) to important documents and eventually downloading them.

Arawa commentary:

> It’s possible to create and get public links only. Please, look at the response example. From the URL in the response json you have a public link. For more information, here is the documentation : https://docs.nextcloud.com/server/latest/developer_manual/client_apis/OCS/ocs-share-api.html

### Fetch activities

Request example:

```shell
curl --request GET \
  --url http://localhost:8081/ocs/v2.php/apps/files_sharing/api/v1/shares \
  -u admin:admin \
  --header 'OCS-ApiRequest: true' \
  --header 'accept: application/json'
```

Response example:

```json
{
  "ocs": {
    "meta": {
      "status": "ok",
      "statuscode": 200,
      "message": "OK"
    },
    "data": [
      {
        "id": "1",
        "share_type": 0,
        "uid_owner": "bfotia",
        "displayname_owner": "Baptiste Fotia",
        "permissions": 31,
        "can_edit": true,
        "can_delete": true,
        "stime": 1655127667,
        "parent": null,
        "expiration": null,
        "token": null,
        "uid_file_owner": "bfotia",
        "note": "",
        "label": null,
        "displayname_file_owner": "Baptiste Fotia",
        "path": "/Mes documents",
        "item_type": "folder",
        "mimetype": "httpd/unix-directory",
        "has_preview": false,
        "storage_id": "home::bfotia",
        "storage": 3,
        "item_source": 1658,
        "file_source": 1658,
        "file_parent": 356,
        "file_target": "/Mes documents",
        "share_with": "fbar",
        "share_with_displayname": "Foo Bar",
        "share_with_displayname_unique": "foobar@company.com",
        "status": [],
        "mail_send": 0,
        "hide_download": 0
      },
      {
        "id": "2",
        "share_type": 3,
        "uid_owner": "bfotia",
        "displayname_owner": "Baptiste Fotia",
        "permissions": 17,
        "can_edit": true,
        "can_delete": true,
        "stime": 1666001777,
        "parent": null,
        "expiration": "2022-10-31 00:00:00",
        "token": "LJQXkd86FY8i3xg",
        "uid_file_owner": "bfotia",
        "note": "",
        "label": "",
        "displayname_file_owner": "Baptiste Fotia",
        "path": "/01-art-auteur.jpg",
        "item_type": "file",
        "mimetype": "image/jpeg",
        "has_preview": true,
        "storage_id": "home::bfotia",
        "storage": 3,
        "item_source": 413,
        "file_source": 413,
        "file_parent": 356,
        "file_target": "/01-art-auteur.jpg",
        "share_with": null,
        "share_with_displayname": "(Shared link)",
        "password": null,
        "send_password_by_talk": false,
        "url": "https://localhost/s/LJQXkd86FY8i3xg",
        "mail_send": 0,
        "hide_download": 0
      }
    ]
  }
}
```
