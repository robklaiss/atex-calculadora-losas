#!/usr/bin/env bash
set -euo pipefail

# Backup script for SQLite DB (data/app.db)
# - Creates compressed, timestamped backups in backups/
# - Uses sqlite3 .backup for a safe online backup
# - Prunes backups older than 30 days

# Resolve repo root from this script location
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
DB_PATH="${ROOT_DIR}/data/app.db"
BACKUP_DIR="${ROOT_DIR}/backups"
DATESTAMP="$(date -u +"%Y%m%dT%H%M%SZ")"
BASENAME="app-${DATESTAMP}.db"
OUT_DB="${BACKUP_DIR}/${BASENAME}"
OUT_GZ="${OUT_DB}.gz"

mkdir -p "${BACKUP_DIR}"

if ! command -v sqlite3 >/dev/null 2>&1; then
  echo "[backup] ERROR: sqlite3 is not installed or not in PATH" >&2
  exit 1
fi

if [ ! -f "${DB_PATH}" ]; then
  echo "[backup] WARNING: DB file not found at ${DB_PATH}. Nothing to back up." >&2
  exit 0
fi

# Use a temp file then compress
TMP_DB="${OUT_DB}.tmp"

# Perform safe online backup
sqlite3 "${DB_PATH}" ".backup '${TMP_DB}'"

# Compress and remove temp
gzip -9c "${TMP_DB}" > "${OUT_GZ}"
rm -f "${TMP_DB}"

# Optional: create/refresh a symlink to the latest backup
ln -snf "${OUT_GZ}" "${BACKUP_DIR}/latest.db.gz"

# Prune backups older than 30 days
find "${BACKUP_DIR}" -type f -name 'app-*.db.gz' -mtime +30 -print -delete || true

# Done
SIZE=$(du -h "${OUT_GZ}" | awk '{print $1}')
echo "[backup] Created ${OUT_GZ} (${SIZE})"
