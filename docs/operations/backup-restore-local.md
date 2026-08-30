# Local PostgreSQL backup and restore verification

This procedure turns the production backup/restore policy into a safe local exercise against the Docker Compose PostgreSQL service.

## Create a backup

Start the local stack, then run:

```bash
scripts/postgres-backup.sh var/backups
```

The command writes a PostgreSQL custom-format dump plus a `.sha256` checksum. It does not delete or restore data.

Optional environment overrides:

```bash
POSTGRES_DB=jobpilot POSTGRES_USER=jobpilot scripts/postgres-backup.sh var/backups
```

Treat the generated backup as sensitive because it can contain candidate, recruiter and application data. Do not commit it to Git.

## Verify a restore safely

Restore only into a brand-new isolated database whose name ends with `_restore_test`:

```bash
scripts/postgres-restore-verify.sh \
  var/backups/jobpilot-YYYYMMDDTHHMMSSZ.dump \
  jobpilot_monthly_restore_test
```

The helper refuses an existing target database and refuses names that do not match the isolated restore-test suffix. If restore fails, it removes the newly created target database. On success, it keeps the isolated database for manual inspection.

The command performs basic PostgreSQL checks only. It does **not** prove application-level recovery by itself. A production restore exercise still requires the smoke checks described in `backup-restore.md` and must keep Gmail, external connectors and application submission disabled while validating restored data.

## Cleanup after inspection

After manual validation, remove only the explicitly isolated test database:

```bash
docker compose exec -T db dropdb --username=jobpilot jobpilot_monthly_restore_test
```

Never run restore verification against `jobpilot`, a real development database, or a production database.

## Evidence to record

For each exercise, record the backup timestamp, application commit, checksum verification result, restore duration, isolated target database name, integrity/smoke checks performed, observed failures and final decision. Do not record secrets, OAuth tokens, email bodies or CV contents.
