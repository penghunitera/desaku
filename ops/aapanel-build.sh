#!/bin/bash
set -euo pipefail
DESAKU_APP=/www/wwwroot/desaku.test
DESAKU_WORK=/tmp/desaku-deploy-work-20261013
DESAKU_PHP=/www/server/php/84/bin/php
mkdir -p "$DESAKU_WORK"
chmod 700 "$DESAKU_WORK"
trap 'DESAKU_STATUS=$?; printf "%s\n" "$DESAKU_STATUS" > "$DESAKU_WORK/build.exit"' EXIT
exec 9>"$DESAKU_WORK/build.lock"
flock -n 9
if [ ! -f "$DESAKU_APP/artisan" ]; then
  python3 - <<'PY'
import pathlib,shutil
app=pathlib.Path('/www/wwwroot/desaku.test')
allowed={'.user.ini','502.html','index.html','404.html','.htaccess'}
unexpected={p.name for p in app.iterdir()}-allowed
if unexpected: raise RuntimeError('Refusing to overwrite existing non-default website files.')
backup=pathlib.Path('/tmp/desaku-deploy-work-20261013/default-site')
backup.mkdir(mode=0o700,exist_ok=True)
for p in app.iterdir():
    if p.name!='.user.ini': shutil.move(str(p),str(backup/p.name))
PY
  if [ ! -d "$DESAKU_WORK/repository/.git" ]; then
    GIT_TERMINAL_PROMPT=0 git clone --branch main --single-branch https://github.com/penghunitera/desaku.git "$DESAKU_WORK/repository"
  fi
  cp -a "$DESAKU_WORK/repository/." "$DESAKU_APP/"
fi
chown www:www "$DESAKU_APP"
find "$DESAKU_APP" -mindepth 1 ! -name '.user.ini' -exec chown www:www {} +
mkdir -p "$DESAKU_WORK/composer-home" "$DESAKU_WORK/composer-cache" "$DESAKU_WORK/npm-cache"
chown www:www "$DESAKU_WORK/composer-home" "$DESAKU_WORK/composer-cache" "$DESAKU_WORK/npm-cache"
chmod 711 "$DESAKU_WORK"
if [ ! -f "$DESAKU_APP/.env" ]; then
  cp "$DESAKU_APP/.env.example" "$DESAKU_APP/.env"
  chown www:www "$DESAKU_APP/.env"
  chmod 600 "$DESAKU_APP/.env"
fi
cd "$DESAKU_APP"
runuser -u www -- env COMPOSER_HOME="$DESAKU_WORK/composer-home" COMPOSER_CACHE_DIR="$DESAKU_WORK/composer-cache" "$DESAKU_PHP" /usr/bin/composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
runuser -u www -- "$DESAKU_PHP" /usr/bin/composer check-platform-reqs --no-dev
DESAKU_NODE_VERSION=v22.23.3
case "$(uname -m)" in x86_64) DESAKU_NODE_ARCH=x64;; aarch64) DESAKU_NODE_ARCH=arm64;; *) exit 15;; esac
DESAKU_NODE_FILE="node-$DESAKU_NODE_VERSION-linux-$DESAKU_NODE_ARCH.tar.xz"
DESAKU_NODE_DIR="/www/server/desaku-node/$DESAKU_NODE_VERSION"
if [ ! -x "$DESAKU_NODE_DIR/bin/node" ]; then
  curl -fSL --connect-timeout 20 --max-time 300 --proto '=https' "https://nodejs.org/dist/$DESAKU_NODE_VERSION/SHASUMS256.txt" -o "$DESAKU_WORK/SHASUMS256.txt"
  curl -fSL --connect-timeout 20 --max-time 300 --proto '=https' "https://nodejs.org/dist/$DESAKU_NODE_VERSION/$DESAKU_NODE_FILE" -o "$DESAKU_WORK/$DESAKU_NODE_FILE"
  cd "$DESAKU_WORK"
  awk -v file="$DESAKU_NODE_FILE" '$2==file {print}' SHASUMS256.txt > node-sha256.txt
  test -s node-sha256.txt
  sha256sum -c node-sha256.txt
  mkdir -p "$DESAKU_NODE_DIR"
  tar -xJf "$DESAKU_WORK/$DESAKU_NODE_FILE" --strip-components=1 -C "$DESAKU_NODE_DIR"
  chmod -R a+rX "$DESAKU_NODE_DIR"
fi
cd "$DESAKU_APP"
runuser -u www -- env PATH="$DESAKU_NODE_DIR/bin:$PATH" npm_config_cache="$DESAKU_WORK/npm-cache" npm ci
runuser -u www -- env PATH="$DESAKU_NODE_DIR/bin:$PATH" npm_config_cache="$DESAKU_WORK/npm-cache" npm run build
runuser -u www -- git rev-parse HEAD
"$DESAKU_NODE_DIR/bin/node" --version
printf 'DESAKU_BUILD_COMPLETE\n'
