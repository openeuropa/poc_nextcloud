## Get activity logs

Needs to be addressed:

> When users access the website, they can consult activity logs related to documents managed in the Nextcloud with which they are concerned.
>
> As results, they are kept informed about the activities without being obliged to access the Nextcloud.

Arawa commentary:

> "There is an API/REST to list all activities.
>
> You can add parameters to filter the results : https://github.com/nextcloud/activity/blob/master/docs/endpoint-v2.md#parameters

### Fetch activities

Request example:

```shell
curl -X GET https://localhost:8081/ocs/v2.php/apps/activity/api/v2/activity\
  -u "admin:admin"\
  -H "OCS-APIRequest:true"\
  -H "accept:application/json"
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
        "activity_id": 415,
        "app": "files",
        "type": "file_created",
        "user": "admin",
        "subject": "You created Readme.md",
        "subject_rich": [
          "You created {file}",
          {
            "file": {
              "type": "file",
              "id": "1162",
              "name": "Readme.md",
              "path": "Readme.md",
              "link": "http:\/\/localhost:8024\/f\/1162"
            }
          }
        ],
        "message": "",
        "message_rich": [
          "",
          []
        ],
        "object_type": "files",
        "object_id": 1162,
        "object_name": "\/Readme.md",
        "objects": {
          "1162": "\/Readme.md"
        },
        "link": "http:\/\/localhost:8024\/apps\/files\/?dir=\/",
        "icon": "http:\/\/localhost:8024\/apps\/files\/img\/add-color.svg",
        "datetime": "2022-10-18T13:20:19+00:00"
      }
    ]
  }
}
```
