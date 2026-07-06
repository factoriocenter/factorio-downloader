#!/bin/bash
#
# scripts/cpanel-pull.sh
#
# Safe auto-pull for cPanel: fast-forwards the deployed clone to origin/main so
# the live site receives whatever the daily GitHub Action committed (new
# versions, etc.) without any manual "Update from Remote" click.
#
# SAFETY:
#   - Uses `git merge --ff-only`, which ONLY advances when it can fast-forward.
#   - It NEVER deletes untracked files (.env, vendor/, certs/, versions.cache.json).
#   - It NEVER overwrites local uncommitted changes; if the tree can't fast-forward
#     it just skips and logs, leaving everything as-is. No `reset --hard` here.
#
# SETUP (once): in cPanel > Cron Jobs, add an hourly job:
#   /bin/bash /home/ggames/public_html/facdl/scripts/cpanel-pull.sh >> "$HOME/facdl-pull.log" 2>&1
# Schedule: "0 * * * *" (every hour) is plenty, since the Action runs daily.

# Resolve the repository directory (parent of this script) regardless of CWD.
REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$REPO_DIR" || { echo "$(date '+%F %T') ERROR: cannot cd to $REPO_DIR"; exit 1; }

# Locate git (cron often has a minimal PATH).
GIT="$(command -v git || echo /usr/bin/git)"

"$GIT" fetch --quiet origin main || { echo "$(date '+%F %T') fetch failed (network?); skipping."; exit 0; }

BEFORE="$("$GIT" rev-parse HEAD 2>/dev/null)"
if "$GIT" merge --ff-only --quiet origin/main; then
    AFTER="$("$GIT" rev-parse HEAD 2>/dev/null)"
    if [ "$BEFORE" != "$AFTER" ]; then
        echo "$(date '+%F %T') updated: ${BEFORE:0:7} -> ${AFTER:0:7}"
    fi
    # No change: stay quiet to keep the log clean.
else
    echo "$(date '+%F %T') not fast-forwardable (local changes on the server?); left untouched."
fi
