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
  --url 'http://localhost:8081/ocs/v2.php/search/providers/files/search?term=<term>' \
  -u admin:admin \
  --header 'Accept: application/json' \
  --header 'OCS-APIRequest: true'
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
    "data": {
      "name": "Fichiers",
      "isPaginated": true,
      "entries": [
        {
          "thumbnailUrl": "https:\/\/nc24.test.arawa.fr\/core\/preview?x=32&y=32&fileId=6481",
          "title": "02-battlestar-galactica-mobidic.jpeg",
          "subline": "",
          "resourceUrl": "https:\/\/nc24.test.arawa.fr\/f\/6481",
          "icon": "\/apps\/theming\/img\/core\/filetypes\/image.svg?v=1",
          "rounded": false,
          "attributes": {
            "fileId": "6481",
            "path": "\/02-battlestar-galactica-mobidic.jpeg"
          }
        },
        {
          "thumbnailUrl": "https:\/\/nc24.test.arawa.fr\/core\/preview?x=32&y=32&fileId=6471",
          "title": "2020-10-06-mobilizon-illustration-D_realisation.jpg",
          "subline": "",
          "resourceUrl": "https:\/\/nc24.test.arawa.fr\/f\/6471",
          "icon": "\/apps\/theming\/img\/core\/filetypes\/image.svg?v=1",
          "rounded": false,
          "attributes": {
            "fileId": "6471",
            "path": "\/2020-10-06-mobilizon-illustration-D_realisation.jpg"
          }
        }
      ],
      "cursor": 5
    }
  }
}
```
