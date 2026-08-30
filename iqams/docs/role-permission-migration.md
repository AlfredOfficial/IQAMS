# Role and permission migration runbook

IQAMS is currently in the compatibility phase: `users.role_id` and Spatie's
`model_has_roles` are both maintained. Do not remove the legacy column until the
reconciliation command reports a clean result in production.

## Deployment

1. Back up the database.
2. Deploy the application and run `php artisan migrate --force`.
3. Run `php artisan db:seed --class=RolePermissionSeeder --force`.
4. Run `php artisan roles:reconcile`. This command is read-only and returns a
   failing exit code for missing, multiple, or mismatched assignments.
5. Resolve every reported user before enabling the new role-assignment screen.
6. Run the application test suite and clear the permission cache with
   `php artisan permission:cache-reset`.

The migration intentionally fails if duplicate legacy role names exist. Users
whose legacy role is null or invalid receive no Spatie assignment and therefore
no portal access.

## Rollback during compatibility

Before the legacy columns are removed, roll back the application release and
authorization reads to `users.role_id`. The compatibility service writes both
stores, so valid assignments remain available. If the schema migration itself
must be rolled back, first verify that `users.role_id` is populated for every
assigned user, then run `php artisan migrate:rollback --step=1`.

## Legacy retirement (separate release)

Only after repeated clean reconciliation results should a later release convert
the remaining attendance/reporting joins from `users.role_id` and
`roles.role_name` to Spatie relations. That release may then remove `role_id` and
`role_name`; Spatie's `roles.name` remains the source of truth.
