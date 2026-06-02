# RBAC

## Roles

| Role | Rights |
| --- | --- |
| Owner | Organization, billing, users, sites, keys, channels |
| Admin | Sites, checks, notifications, incidents, users except owner transfer |
| Integrator | Assigned clients/projects/sites |
| Operator | View statuses, acknowledge/comment incidents |
| Viewer | Read-only dashboards, reports and history |

## Scope Levels

RBAC applies at:

- organization;
- project;
- site.

## Sensitive Actions

Require Owner or Admin:

- create/rotate/revoke site key;
- change notification channel secrets;
- invite/remove users;
- change plan limits;
- delete or disable site.

Sensitive actions must be written to audit log.
