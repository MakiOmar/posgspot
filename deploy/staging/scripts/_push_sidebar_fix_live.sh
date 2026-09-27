#!/bin/bash
set -euo pipefail
export SSHPASS=$(cat /root/.hostinger_pass)
SCP='sshpass -e scp -P 65002 -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null'
REMOTE='u605441708@93.127.205.20'
ACC='/home/u605441708/domains/gamesspoteg.com/public_html/accounts.gamesspoteg.com'
LOCAL='/opt/spot-staging/accounts'

echo '== connectivity =='
sshpass -e ssh -p 65002 -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null \
  "$REMOTE" "test -f $ACC/config/adminlte.php && echo OK_ACC && ls $ACC/resources/views/vendor/adminlte/master.blade.php"

echo '== push config + master + css =='
$SCP "$LOCAL/config/adminlte.php" "${REMOTE}:${ACC}/config/adminlte.php"
$SCP "$LOCAL/resources/views/vendor/adminlte/master.blade.php" \
  "${REMOTE}:${ACC}/resources/views/vendor/adminlte/master.blade.php"
sshpass -e ssh -p 65002 -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null \
  "$REMOTE" "mkdir -p ${ACC}/public/css"
$SCP "$LOCAL/public/css/sidebar-scroll-fix.css" \
  "${REMOTE}:${ACC}/public/css/sidebar-scroll-fix.css"

echo '== clear caches on live =='
sshpass -e ssh -p 65002 -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null "$REMOTE" \
  "cd $ACC && PHP=php; [ -x /opt/alt/php82/usr/bin/php ] && PHP=/opt/alt/php82/usr/bin/php; [ -x /opt/alt/php83/usr/bin/php ] && PHP=/opt/alt/php83/usr/bin/php; echo PHP=\$PHP; \$PHP artisan view:clear; \$PHP artisan config:clear; \$PHP artisan cache:clear || true; grep -n layout_fixed_sidebar config/adminlte.php | head -2; grep -n 'Ensure long sidebar' resources/views/vendor/adminlte/master.blade.php | head -2; ls -la public/css/sidebar-scroll-fix.css"

echo '== verify HTTP =='
curl -sI https://accounts.gamesspoteg.com/css/sidebar-scroll-fix.css | head -10
echo DONE
