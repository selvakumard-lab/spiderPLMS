# Project tracking

All figures come from `ProjectTrackerService`. They are recalculated whenever a project, activity, change request or client dependency is saved, and stored on the project.

## Working days

Everything uses **Monday to Friday** working days. Public holidays are not modelled.

## Progress

* The progress of a project is the `progress_percent` of its **latest activity** (by date, then id).
* A project marked **completed** always shows 100%.

## Deadline

```
effective deadline = revised deadline, if any, otherwise the original due date
revised deadline   = original due date + working days of APPROVED change requests
```

## Status

The tracker compares where the project **should** be with where it **is**.

```
planned days = working days from start to the effective deadline
elapsed days = working days from start to today (capped at planned)
gap          = elapsed / planned * 100 - progress
overdue days = working days past the effective deadline (0 if completed)
```

| Status | Rule (first match wins) |
|---|---|
| Completed | Project status is completed |
| Significantly Delayed | overdue more than 10 working days, or gap over 30 points |
| Delayed | overdue at all, or gap over 15 points |
| Slightly Delayed | gap over 5 points |
| On Time | otherwise |

## Delay attribution

```
behind days        = max(overdue days, gap as a share of planned days)
client delay days  = sum of days delayed across client dependencies
internal delay     = max(0, behind days - client delay days)
```

A client dependency is **pending** until a received date is set. It starts counting as delayed after its expected date.

## Change requests

| Status | Effect |
|---|---|
| Pending | Counted as a pending requirement. No effect on dates. |
| Approved | Its additional days extend the revised deadline. Its additional hours add to "additional work hours". |
| Rejected | No effect. |

## AI estimate

`POST /tracker/projects/{id}/analyse` and activity logging call DeepSeek with the activity log and receive `next_step_hours` and a short summary, stored on the activity and project. If no key is configured, there are no activities, or the call fails, a rule-based fallback is used and the response reports `ai_source: fallback`. The failure is logged.

## Reports

`GET /tracker/projects/{id}/reports/{type}?format=pdf|xlsx` where `type` is:

| Type | Contents |
|---|---|
| `progress` | Project facts, activity log with hours and estimates, meetings, status summary. |
| `performance` | Delay figures, internal versus client-side cause, change requests, client dependencies. |
