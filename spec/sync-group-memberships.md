## Sync groups/memberships

Needs to be addressed:

> "When a new website user account is activated, a corresponding Nextcloud user account must be automatically created with the default privileges (permissions) set for the Nextcloud instance related to the website.
>
> As result, site users can pass from a system to the other in a secure and transparent way and start working.
>
> The mapping between the 2 accounts can be based on the user name used by the "Single-Sign on" service (EU Login).


Arawa commentary:

> You can use the Nextcloud's API/REST to create an user and add it in a group. By adding it to an existing group, the file and folder restrictions related to that group will be applied.
>
> For example, you create the "Philippe" user who is added in the "admin" and "Manager Team" groups.
>
> So, the "Philippe" user created and all restrictions applied on the files or folders with the "Manager team" group will also be restricted for the "Philippe" user.

### Create group folder

API: [Groupfolders](https://github.com/arawa/groupfolders#api)

Request example:

```shell
curl -X POST http://nextcloud/apps/groupfolders/folders \
  -u "admin:admin" \
  -H "OCS-APIRequest:true" \
  -H "Content-Type:application/x-www-form-urlencoded" \
  -H "Accept:application/json" \
  -d "mountpoint=foobar"
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
      "id": 24
    }
  }
}
```

### Create workspace

API: Workspace

Request example:

```shell
curl -X POST http://nextcloud/apps/workspace/spaces \
  -u "admin:admin" \
  -H "Content-Type:application/x-www-form-urlencoded" \
  -d "spaceName=foobar&folderId=24"
```

Response example:

```json
{
  "space_name": "foobar",
  "id_space": 9,
  "folder_id": 24,
  "color": "#4da6cd",
  "groups": {
    "SPACE-GE-9": {
      "gid": "SPACE-GE-9",
      "displayName": "GE-9"
    },
    "SPACE-U-9": {
      "gid": "SPACE-U-9",
      "displayName": "U-9"
    }
  },
  "statuscode": 201
}
```
