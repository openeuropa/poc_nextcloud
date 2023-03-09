## Sync user roles within a group

Needs to be addressed:

> For the group-related sub-spaces (or folders) in the Nextcloud, some appointed group members must be granted with further management/control privileges than for other group members.
> As result, they can intervene on misconfigured files or folders that the group members that "own" them, cannot correct themselves, or that are in contradiction with the group or site code of conducts.

Arawa commentary:

> In the context of the use of using Workspace app and ensure its proper functioning. You have to add users in the "GE-<id-workspace>" where the "<id-workspace>' corresponds to the identifier of your workspace.
>
> But, be careful, the real identifier of the group is "SPACE-GE-<id-workspace>".

### Create user

Request example:

```shell
curl -X PATCH http://localhost:8081/apps/workspace/api/group/addUser/<id-workspace>\
  -u "admin:admin"\
  -H "Accept:application/json"\
  -H "Content-Type:application/x-www-form-urlencoded"\
  -d "gid=SPACE-GE-24&user=busclat"
```

Response example:

```json

```
