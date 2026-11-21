#!/bin/bash
# 開発環境（xs227431.xsrv.jp/melikret）だけに上げる。本番（melikret.com）には触らない
set -e
cd "$(dirname "$0")"
K="-i C:/Users/msdtk/.ssh/xserver_hoiku -P 10022"
D="xs227431@xs227431.xsrv.jp:/home/xs227431/xs227431.xsrv.jp/public_html/melikret"
for f in index.html css/style.css js/main.js js/water.js; do
  scp -q $K "$f" "$D/$f" 2>/dev/null
done
echo "deployed to dev: $(date +%H:%M:%S)"
