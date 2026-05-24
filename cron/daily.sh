#!/bin/bash
# ============================================
# LebanonCinema.com Daily Cron Job
# Add to crontab: 0 6 * * * /path/to/cron/daily.sh
# ============================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
LOG_FILE="$SCRIPT_DIR/logs/cron_$(date +%Y%m%d).log"

mkdir -p "$SCRIPT_DIR/logs"

echo "==============================" >> "$LOG_FILE"
echo "Started: $(date)"               >> "$LOG_FILE"
echo "==============================" >> "$LOG_FILE"

# 0. Schema migration
echo "[Schema] Running migration..."  >> "$LOG_FILE"
php -r "
    require '$PROJECT_DIR/config.php';
    runSchemaMigrations(getDbConnection());
" >> "$LOG_FILE" 2>&1

# 1. Sync movies from TMDb
echo "[TMDb Sync] Starting..."        >> "$LOG_FILE"
php "$PROJECT_DIR/scraper/tmdb_sync.php" >> "$LOG_FILE" 2>&1

# 2. Run all cinema scrapers
echo "[Scrapers] Running all..."      >> "$LOG_FILE"
php "$PROJECT_DIR/scraper/run_all.php" >> "$LOG_FILE" 2>&1

# 3. Regenerate sitemap
echo "[Sitemap] Regenerating..."      >> "$LOG_FILE"
php -r "
    require '$PROJECT_DIR/config.php';
    \$_SERVER['REQUEST_URI'] = '/sitemap.xml';
    include '$PROJECT_DIR/public/sitemap.php';
" >> "$LOG_FILE" 2>&1

echo "Finished: $(date)"              >> "$LOG_FILE"

# Keep only last 30 days of logs
find "$SCRIPT_DIR/logs" -name "cron_*.log" -mtime +30 -delete
