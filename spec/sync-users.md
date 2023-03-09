## Sync users from Drupal to Nextcloud

Needs to be addressed:

> When a new website user account is activated, a corresponding Nextcloud user account must be automatically created with the default privileges (permissions) set for the Nextcloud instance related to the website.
>
> As result, site users can pass from a system to the other in a secure and transparent way and start working.
The mapping between the 2 accounts can be based on the user name used by the ""Single-Sign on"" service (EU Login).


Arawa commentary:

> You can use the Nextcloud's API/REST to create an user and add it in a group. By adding it to an existing group, the file and folder restrictions related to that group will be applied.
>
> For example, you create the "Philippe" user who is added in the "admin" and "Manager Team" groups.
>
> So, the "Philippe" user created and all restrictions applied on the files or folders with the "Manager team" group will also be restricted for the "Philippe" user.

### Create user

Request example:

```shell
curl -X POST http://localhost:8081/ocs/v1.php/cloud/users \
  -u "admin:admin" \
  -H "OCS-APIRequest:true" \
  -H "Accept:application/json" \
  -d userid="Philippe" \
  -d email="Philippe1@example.com" \
  -d password="Philippepassword123" \
  -d groups[]="admin" \
  -d groups[]="Manager Team"
```

Response example:

```json
{
  "ocs": {
    "meta": {
      "status": "ok",
      "statuscode": 100,
      "message": "OK",
      "totalitems": "",
      "itemsperpage": ""
    },
    "data": {
      "id": "Philippe"
    }
  }
}
```

### Get user info

Example request:

```shell
curl -X GET 'http://localhost:8081/ocs/v1.php/cloud/users/Philippe' \
  -u admin:admin \
  -H "Accept: application/json" \
  -H "OCS-APIRequest: true"
```

Example response:

```json
{
  "ocs": {
    "meta": {
      "status": "ok",
      "statuscode": 100,
      "message": "OK",
      "totalitems": "",
      "itemsperpage": ""
    },
    "data": {
      "enabled": true,
      "storageLocation": "\/var\/www\/html\/data\/Philippe",
      "id": "Philippe",
      "lastLogin": 0,
      "backend": "Database",
      "subadmin": [],
      "quota": {
        "quota": "none",
        "used": 0
      },
      "email": null,
      "additional_mail": [],
      "displayname": "Philippe",
      "phone": "",
      "address": "",
      "website": "",
      "twitter": "",
      "organisation": "",
      "role": "",
      "headline": "",
      "biography": "",
      "profile_enabled": "1",
      "groups": [
        "admin"
      ],
      "language": "en",
      "locale": "",
      "notify_email": null,
      "backendCapabilities": {
        "setDisplayName": true,
        "setPassword": true
      }
    }
  }
}
```
