#!/usr/bin/env python3
"""Mount DESAKU on existing HTTP port 80, keeping its upstream on loopback."""
import os
import pathlib
import re
import subprocess
import urllib.error
import urllib.request

APP = pathlib.Path('/www/wwwroot/desaku.test')
WORK = pathlib.Path('/tmp/desaku-deploy-work-20261013')
IP_CONF = pathlib.Path('/www/server/panel/vhost/nginx/103.170.89.151.conf')
APP_CONF = pathlib.Path('/www/server/panel/vhost/nginx/desaku.test.conf')
INCLUDE = pathlib.Path('/www/server/panel/vhost/nginx/extension/103.170.89.151/desaku-path.conf')
NGINX = '/www/server/nginx/sbin/nginx'
PHP = '/www/server/php/84/bin/php'


def run(args):
    result = subprocess.run(args, cwd=APP, capture_output=True, text=True)
    if result.returncode:
        raise RuntimeError('Scoped command failed: ' + args[0])
    return result.stdout.strip()


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def response(url):
    try:
        return urllib.request.build_opener(NoRedirect()).open(url, timeout=20)
    except urllib.error.HTTPError as error:
        return error


def main():
    if os.geteuid() != 0 or not (WORK / 'data-import.done').exists():
        raise RuntimeError('Requires the scoped administrator and verified DESAKU data import')
    ip_original, app_original = IP_CONF.read_text(), APP_CONF.read_text()
    if not re.search(r'server_name[^;]*\b103\.170\.89\.151\b', ip_original) or not re.search(r'^\s*listen\s+80\s*;', ip_original, re.M):
        raise RuntimeError('Unexpected IP virtual host')
    baseline = {}
    for path in ['/', '/simrs/public/']:
        with response('http://103.170.89.151' + path) as res:
            baseline[path] = (res.code, res.headers.get('Location'))
    directive = '    include ' + str(INCLUDE) + ';\n'
    if directive.strip() not in ip_original:
        if '/desaku' in ip_original:
            raise RuntimeError('Existing DESAKU location requires review')
        ip_updated, count = re.subn(r'(^\s*location\s+/\s*\{)', lambda m: directive + '\n' + m.group(1), ip_original, count=1, flags=re.M)
        if count != 1:
            raise RuntimeError('Expected a single existing root location')
    else:
        ip_updated = ip_original
    app_updated, count = re.subn(r'^\s*listen\s+(?:127\.0\.0\.1:)?8088\s*;', '    listen 127.0.0.1:8088;', app_original, flags=re.M)
    if count != 1:
        raise RuntimeError('Unexpected DESAKU listener')
    include_contents = (APP / 'ops/nginx-desaku-path.conf').read_text()
    if INCLUDE.exists() and INCLUDE.read_text() != include_contents:
        raise RuntimeError('Refusing to overwrite a different path proxy')
    for source, contents in [(IP_CONF, ip_original), (APP_CONF, app_original), (APP / '.env', (APP / '.env').read_text())]:
        backup = WORK / (source.name + '.before-path.private')
        if not backup.exists():
            backup.write_text(contents)
            backup.chmod(0o600)
    env = APP / '.env'
    contents = env.read_text()
    for key, value in {'APP_URL': 'http://103.170.89.151/desaku', 'ASSET_URL': 'http://103.170.89.151/desaku', 'SESSION_PATH': '/desaku', 'SESSION_COOKIE': 'desaku_session'}.items():
        if re.search(r'^' + key + '=', contents, re.M):
            contents = re.sub(r'^' + key + '=.*$', key + '=' + value, contents, flags=re.M)
        else:
            contents += '\n' + key + '=' + value + '\n'
    env.write_text(contents)
    env.chmod(0o600)
    artisan = ['runuser', '-u', 'www', '--', PHP, 'artisan']
    run(artisan + ['optimize'])
    INCLUDE.parent.mkdir(parents=True, exist_ok=True)
    INCLUDE.write_text(include_contents)
    INCLUDE.chmod(0o644)
    IP_CONF.write_text(ip_updated)
    APP_CONF.write_text(app_updated)
    try:
        run([NGINX, '-t'])
    except Exception:
        IP_CONF.write_text(ip_original)
        APP_CONF.write_text(app_original)
        raise
    run([NGINX, '-s', 'reload'])
    run(artisan + ['queue:restart'])
    for path, expected in baseline.items():
        with response('http://103.170.89.151' + path) as res:
            if (res.code, res.headers.get('Location')) != expected:
                raise RuntimeError('Existing application response changed')
    with response('http://103.170.89.151/desaku/up') as res:
        if res.code != 200:
            raise RuntimeError('DESAKU health failed through path proxy')
    with response('http://103.170.89.151/desaku/pengelola') as res:
        if res.code != 302 or res.headers.get('Location') != 'http://103.170.89.151/desaku/pengelola/masuk':
            raise RuntimeError('Login redirect escaped deployment prefix')
    with response('http://103.170.89.151/desaku/pengelola/masuk') as res:
        cookies = res.headers.get_all('Set-Cookie') or []
        if res.code != 200 or not any(cookie.startswith('desaku_session=') and 'path=/desaku' in cookie.lower() for cookie in cookies):
            raise RuntimeError('Session cookie path verification failed')
    print('NGINX_CONFIG_OK; LOOPBACK_UPSTREAM_8088; EXISTING_HTTP_RESPONSES_OK')
    print('PATH_HEALTH_OK; LOGIN_REDIRECT_OK; SESSION_COOKIE_PATH_OK')
    print('DESAKU_PUBLIC_URL=http://103.170.89.151/desaku/')


if __name__ == '__main__':
    main()
