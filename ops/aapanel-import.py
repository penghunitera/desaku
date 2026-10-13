#!/usr/bin/env python3
"""One-time, scoped deployment of the approved DESAKU snapshot through aaPanel."""
import base64
import fcntl
import hashlib
import json
import os
import pathlib
import pwd
import re
import shutil
import stat
import subprocess
import sys
import zipfile

APP = pathlib.Path('/www/wwwroot/desaku.test')
WORK = pathlib.Path('/tmp/desaku-deploy-work-20261013')
STAGING = pathlib.Path('/tmp/desaku-staging-20261013')
ARCHIVE = STAGING / 'desaku-data-20261013.zip'
SNAPSHOT = STAGING / 'snapshot'
EXPECTED_SHA256 = '3b35273dbb9532e88e675ae2945926dedbdcac30b1527716fb2918d12d40ec58'
PHP = '/www/server/php/84/bin/php'


def digest(path):
    with path.open('rb') as handle:
        return hashlib.file_digest(handle, 'sha256').hexdigest()


def run(command, **kwargs):
    result = subprocess.run(command, capture_output=True, text=True, **kwargs)
    if result.stdout:
        print(result.stdout, end='', flush=True)
    if result.returncode:
        # These commands do not emit personal records or credentials.
        if result.stderr:
            print(result.stderr, end='', flush=True)
        raise RuntimeError('Command failed: ' + command[0] + ' exit=' + str(result.returncode))
    return result.stdout


def main():
    if os.geteuid() != 0 or not (APP / 'artisan').exists():
        raise RuntimeError('Requires the scoped aaPanel administrator deployment context')
    WORK.mkdir(mode=0o700, exist_ok=True)
    lock = open(WORK / 'import.lock', 'a')
    fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
    if (WORK / 'data-import.done').exists():
        raise RuntimeError('Data already imported; use verification rather than repeating import')
    if ARCHIVE.is_symlink() or digest(ARCHIVE) != EXPECTED_SHA256:
        raise RuntimeError('Approved upload missing or archive checksum mismatch')
    STAGING.chmod(0o700)
    ARCHIVE.chmod(0o600)
    SNAPSHOT.mkdir(mode=0o700, exist_ok=True)
    with zipfile.ZipFile(ARCHIVE) as archive:
        for item in archive.infolist():
            relative = pathlib.PurePosixPath(item.filename)
            mode = (item.external_attr >> 16) & 0o170000
            if relative.is_absolute() or '..' in relative.parts or mode == stat.S_IFLNK:
                raise RuntimeError('Unsafe snapshot archive path')
        archive.extractall(SNAPSHOT)
    for path in [SNAPSHOT, *SNAPSHOT.rglob('*')]:
        if path.is_symlink():
            raise RuntimeError('Snapshot symlink is not allowed')
        path.chmod(0o700 if path.is_dir() else 0o600)
    manifest = json.loads((SNAPSHOT / 'manifest.json').read_text())
    if manifest['format'] != 2 or digest(SNAPSHOT / 'source.sqlite') != manifest['database_sha256']:
        raise RuntimeError('Snapshot database checksum mismatch')
    key = (SNAPSHOT / 'app-key.txt').read_text().strip()
    if not key.startswith('base64:') or len(base64.b64decode(key[7:], validate=True)) != 32:
        raise RuntimeError('Invalid preserved encryption key')
    www = pwd.getpwnam('www')
    artisan = ['runuser', '-u', 'www', '--', PHP, 'artisan']
    run(artisan + ['down', '--retry=60'], cwd=APP)
    envfile = APP / '.env'
    contents = envfile.read_text()
    previous = re.search(r'^APP_KEY=(.*)$', contents, re.M)
    if previous and previous.group(1).strip('"\'') not in ('', key):
        raise RuntimeError('Refusing to replace a different configured application key')
    backup = WORK / 'env-before-import.private'
    if not backup.exists():
        backup.write_text(contents)
        backup.chmod(0o600)
    contents, replaced = re.subn(r'^APP_KEY=.*$', lambda _: 'APP_KEY=' + key, contents, flags=re.M)
    if replaced != 1:
        raise RuntimeError('Exactly one APP_KEY setting is required')
    envfile.write_text(contents)
    os.chown(envfile, www.pw_uid, www.pw_gid)
    envfile.chmod(0o600)
    run(artisan + ['config:clear'], cwd=APP)
    run(artisan + ['migrate', '--force'], cwd=APP)
    for relative, checksum in manifest['files'].items():
        if not re.match(r'^storage/app/(private|public)/', relative) or '..' in pathlib.PurePosixPath(relative).parts:
            raise RuntimeError('Unexpected private file path')
        source, destination = SNAPSHOT / relative, APP / relative
        if digest(source) != checksum or destination.is_symlink():
            raise RuntimeError('Private source file checksum mismatch')
        if destination.exists() and digest(destination) != checksum:
            raise RuntimeError('Refusing to replace a different application document')
        destination.parent.mkdir(parents=True, mode=0o750, exist_ok=True)
        for parent in [destination.parent, *destination.parent.parents]:
            if parent == APP / 'storage/app':
                break
            os.chown(parent, www.pw_uid, www.pw_gid)
            parent.chmod(0o750)
        shutil.copyfile(source, destination)
        os.chown(destination, www.pw_uid, www.pw_gid)
        destination.chmod(0o600)
        if digest(destination) != checksum:
            raise RuntimeError('Copied private document checksum mismatch')
    run([PHP, 'ops/import-sqlite-snapshot.php', str(SNAPSHOT)], cwd=APP)
    marker = WORK / 'data-import.done'
    marker.write_text(EXPECTED_SHA256 + '\n')
    marker.chmod(0o600)
    print('PRIVATE_FILES_OK count=' + str(len(manifest['files'])), flush=True)
    run(artisan + ['optimize'], cwd=APP)
    run(artisan + ['queue:restart'], cwd=APP)
    run(artisan + ['up'], cwd=APP)
    print('DESAKU_IMPORT_COMPLETE', flush=True)


if __name__ == '__main__':
    status = 1
    try:
        main()
        status = 0
    except Exception as error:
        print('DESAKU_DEPLOY_FAILED type=' + type(error).__name__ + '; ' + str(error), flush=True)
    finally:
        WORK.mkdir(mode=0o700, exist_ok=True)
        (WORK / 'import.exit').write_text(str(status) + '\n')
    sys.exit(status)
