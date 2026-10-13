<?php

// CLI-only, one-time import into an empty DESAKU MySQL schema. No credentials or data are embedded.
if (PHP_SAPI !== 'cli' || $argc !== 2) {
    exit("Usage: php ops/import-sqlite-snapshot.php /private/snapshot-directory\n");
}

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$pdo = null;
$stage = 'preflight';
try {
    $directory = realpath($argv[1]);
    if (! $directory || str_starts_with($directory.'/', realpath(dirname(__DIR__).'/public').'/')) {
        throw new RuntimeException('Snapshot must be outside public directory');
    }
    $manifest = json_decode(file_get_contents($directory.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    if (($manifest['format'] ?? null) !== 2 || ! hash_equals($manifest['database_sha256'], hash_file('sha256', $directory.'/source.sqlite'))) {
        throw new RuntimeException('Snapshot checksum mismatch');
    }
    if (! hash_equals(trim(file_get_contents($directory.'/app-key.txt')), (string) config('app.key'))) {
        throw new RuntimeException('Preserved encryption key must be configured first');
    }
    if (DB::getDriverName() !== 'mysql' || DB::getDatabaseName() !== 'sql_desaku_test') {
        throw new RuntimeException('Only the scoped DESAKU MySQL database is allowed');
    }

    $allowed = [
        'dpt', 'dpt_imports', 'villages', 'bumdes_products', 'family_cards', 'health_programs',
        'public_documents', 'users', 'village_budgets', 'village_potentials', 'audit_logs',
        'residents', 'village_articles', 'health_observations', 'orders', 'property_tax_snapshots',
        'service_requests', 'tax_objects', 'tb_cases', 'property_tax_assessments',
        'service_request_documents', 'service_request_histories', 'tb_contacts', 'tb_treatment_logs',
    ];
    $actual = array_column($manifest['tables'], 'name');
    $expected = $allowed;
    sort($actual);
    sort($expected);
    if ($actual !== $expected) {
        throw new RuntimeException('Unexpected manifest tables');
    }
    $quote = static function (string $identifier): string {
        if (! preg_match('/^[a-z_][a-z0-9_]*$/D', $identifier)) {
            throw new RuntimeException('Invalid SQL identifier');
        }
        return '`'.$identifier.'`';
    };
    foreach ($manifest['files'] as $path => $hash) {
        if (! preg_match('#^storage/app/(private|public)/#', $path) || str_contains($path, '..') || ! hash_equals($hash, hash_file('sha256', $directory.'/'.$path))) {
            throw new RuntimeException('Private file checksum mismatch');
        }
    }

    $pdo = DB::connection()->getPdo();
    $source = new PDO('sqlite:'.$directory.'/source.sqlite', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $source->exec('PRAGMA query_only=ON');
    foreach ($manifest['tables'] as $table) {
        $name = $quote($table['name']);
        if ((int) $pdo->query('SELECT COUNT(*) FROM '.$name)->fetchColumn() !== 0) {
            throw new RuntimeException('Target business tables must be empty');
        }
        $columns = $pdo->query('SHOW COLUMNS FROM '.$name)->fetchAll(PDO::FETCH_COLUMN);
        $provided = $table['columns'];
        foreach ($provided as $column) {
            $quote($column);
        }
        sort($columns);
        sort($provided);
        if ($columns !== $provided || (int) $source->query('SELECT COUNT(*) FROM '.$name)->fetchColumn() !== (int) $table['count']) {
            throw new RuntimeException('Source and destination schema/count mismatch');
        }
    }

    $pdo->beginTransaction();
    $total = 0;
    foreach ($manifest['tables'] as $table) {
        $stage = $table['name'];
        $name = $quote($table['name']);
        $columns = implode(',', array_map($quote, $table['columns']));
        $statement = $source->query('SELECT '.$columns.' FROM '.$name);
        $batch = [];
        $write = static function (array $rows) use ($pdo, $name, $columns, $table): void {
            $group = '('.implode(',', array_fill(0, count($table['columns']), '?')).')';
            $sql = 'INSERT INTO '.$name.' ('.$columns.') VALUES '.implode(',', array_fill(0, count($rows), $group));
            $parameters = [];
            foreach ($rows as $row) {
                array_push($parameters, ...$row);
            }
            $pdo->prepare($sql)->execute($parameters);
        };
        while ($row = $statement->fetch(PDO::FETCH_NUM)) {
            $batch[] = $row;
            if (count($batch) === 150) {
                $write($batch);
                $batch = [];
            }
        }
        if ($batch) {
            $write($batch);
        }
        $count = (int) $pdo->query('SELECT COUNT(*) FROM '.$name)->fetchColumn();
        if ($count !== (int) $table['count']) {
            throw new RuntimeException('Imported count mismatch');
        }
        $total += $count;
    }

    $stage = 'DPT verification';
    $dpt = collect($manifest['tables'])->firstWhere('name', 'dpt');
    $columns = implode(',', array_map($quote, $dpt['columns']));
    $hash = hash_init('sha256');
    $statement = $pdo->query('SELECT '.$columns.' FROM dpt ORDER BY iddpt');
    while ($row = $statement->fetch(PDO::FETCH_NUM)) {
        $row[0] = (int) $row[0];
        foreach (array_slice($row, 1, null, true) as $key => $value) {
            $row[$key] = $value === null ? null : (string) $value;
        }
        hash_update($hash, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR)."\n");
    }
    if (! hash_equals($manifest['dpt_rows_sha256'], hash_final($hash))) {
        throw new RuntimeException('DPT content checksum mismatch');
    }
    $aggregates = $pdo->query('SELECT * FROM dpt_potensi_desa ORDER BY kabupaten,kecamatan,desa_kelurahan')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($aggregates as &$row) {
        foreach ($row as $column => $value) {
            if (! in_array($column, ['kabupaten', 'kecamatan', 'desa_kelurahan'], true)) {
                $row[$column] = (int) $value;
            }
        }
    }
    unset($row);
    if ($aggregates !== $manifest['dpt_village_aggregates'] || (int) $pdo->query('SELECT COUNT(*) FROM dpt_demografi WHERE baris_judul=1')->fetchColumn() !== (int) $manifest['dpt_excluded_headers']) {
        throw new RuntimeException('DPT aggregate parity mismatch');
    }

    $stage = 'identity verification';
    foreach ($pdo->query('SELECT nik_encrypted,nik_hash FROM residents') as $resident) {
        $nik = Crypt::decryptString($resident['nik_encrypted']);
        if (! preg_match('/^\d{16}$/D', $nik) || ! hash_equals($resident['nik_hash'], hash_hmac('sha256', $nik, config('app.key')))) {
            throw new RuntimeException('Resident encryption/hash mismatch');
        }
    }
    foreach ($pdo->query('SELECT number_encrypted,number_hash FROM family_cards') as $card) {
        $number = Crypt::decryptString($card['number_encrypted']);
        if (! hash_equals($card['number_hash'], hash_hmac('sha256', $number, config('app.key')))) {
            throw new RuntimeException('Family card encryption/hash mismatch');
        }
    }
    $sourceUsers = $source->query('SELECT id,password FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    $targetUsers = $pdo->query('SELECT id,password FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($sourceUsers as $index => $user) {
        if ((int) $user['id'] !== (int) $targetUsers[$index]['id'] || ! hash_equals($user['password'], $targetUsers[$index]['password'])) {
            throw new RuntimeException('Account password hash changed');
        }
    }

    $pdo->commit();
    echo 'DESAKU_DATA_IMPORTED tables='.count($manifest['tables']).' rows='.$total.' dpt='.$dpt['count'].' village_groups='.count($aggregates)."\n";
    echo "DPT_CHECKSUM_OK; AGGREGATES_OK; ENCRYPTION_OK; ACCOUNT_HASHES_OK\n";
} catch (Throwable $exception) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Do not emit exception SQL/bindings or personal values into browser-visible logs.
    fwrite(STDERR, 'DESAKU_IMPORT_FAILED stage='.$stage.' type='.get_class($exception).' code='.$exception->getCode()."\n");
    exit(1);
}
