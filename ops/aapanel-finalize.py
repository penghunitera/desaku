#!/usr/bin/env python3
"""Enable the dedicated DESAKU worker after a verified snapshot import."""
import hashlib
import json
import os
import pathlib
import re
import subprocess
import urllib.error
import urllib.request

APP = pathlib.Path('/www/wwwroot/desaku.test')
WORK = pathlib.Path('/tmp/desaku-deploy-work-20261013')
SNAPSHOT = pathlib.Path('/tmp/desaku-staging-20261013/snapshot')
PHP = '/www/server/php/84/bin/php'
BASE = 'http://103.170.89.151:8088'


def run(args):
    result = subprocess.run(args, cwd=APP, capture_output=True, text=True)
    if result.returncode:
        raise RuntimeError('Scoped command failed: ' + args[0])
    return result.stdout.strip()


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None


def main():
    if os.geteuid() != 0 or not (WORK / 'data-import.done').exists() or (WORK / 'import.exit').read_text().strip() != '0':
        raise RuntimeError('Verified successful DESAKU import is required')
    # Keep retry_after longer than the worker timeout, preventing concurrent retries.
    env = APP / '.env'
    contents = env.read_text()
    if re.search(r'^DB_QUEUE_RETRY_AFTER=', contents, re.M):
        contents = re.sub(r'^DB_QUEUE_RETRY_AFTER=.*$', 'DB_QUEUE_RETRY_AFTER=180', contents, flags=re.M)
    else:
        contents += '\nDB_QUEUE_RETRY_AFTER=180\n'
    env.write_text(contents)
    env.chmod(0o600)
    artisan = ['runuser', '-u', 'www', '--', PHP, 'artisan']
    run(artisan + ['optimize'])
    public_link = APP / 'public/storage'
    if not public_link.exists() and not public_link.is_symlink():
        run(artisan + ['storage:link'])
    if public_link.resolve() != APP / 'storage/app/public':
        raise RuntimeError('Unexpected public storage target')
    unit = pathlib.Path('/etc/systemd/system/desaku-queue.service')
    source = (APP / 'ops/desaku-queue.service').read_text()
    if unit.exists() and unit.read_text() != source:
        raise RuntimeError('Refusing to replace a different existing queue service')
    unit.write_text(source)
    unit.chmod(0o644)
    run(['systemctl', 'daemon-reload'])
    run(['systemctl', 'enable', '--now', 'desaku-queue.service'])
    run(artisan + ['queue:restart'])
    print('QUEUE_STATE ' + run(['systemctl', 'is-active', 'desaku-queue.service']))
    print('QUEUE_BOOT ' + run(['systemctl', 'is-enabled', 'desaku-queue.service']))
    print(run(['systemctl', 'show', 'desaku-queue.service', '--property=User,MainPID,SubState']))
    manifest = json.loads((SNAPSHOT / 'manifest.json').read_text())
    for relative, expected in manifest['files'].items():
        with (APP / relative).open('rb') as handle:
            if hashlib.file_digest(handle, 'sha256').hexdigest() != expected:
                raise RuntimeError('Private file verification failed')
    opener = urllib.request.build_opener(NoRedirect())
    paths = {'/up': 200, '/': 200, '/pengelola/masuk': 200,
             '/data/statistik': 200, '/transparansi/apbdes': 200,
             '/ppid': 200, '/jdih': 200, '/lapak': 200, '/pbb/potensi': 200,
             '/pengelola': 302, '/.env': 404, '/source.sqlite': 404,
             '/storage/app/private': 404}
    assets = json.loads((APP / 'public/build/manifest.json').read_text())
    for asset in assets.values():
        paths['/build/' + asset['file']] = 200
    for relative in manifest['files']:
        if relative.startswith('storage/app/private/'):
            paths['/storage/' + relative.removeprefix('storage/app/private/')] = 404
    for path, expected in paths.items():
        try:
            response = opener.open(BASE + path, timeout=20)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            status = response.code
            # Only status counts are printed; never resident data or private document names.
            if status != expected:
                raise RuntimeError('HTTP verification failed for public route or protected path; status=' + str(status))
    print('HTTP_CHECKS_OK count=' + str(len(paths)))
    print('PRIVATE_FILES_OK count=' + str(len(manifest['files'])))
    print('DESAKU_DEPLOY_VERIFIED')


if __name__ == '__main__':
    main()
