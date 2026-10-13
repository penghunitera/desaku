<?php

namespace App\Services;

use App\Models\PropertyTaxSnapshot;
use App\Models\Resident;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;
use UnexpectedValueException;

class PropertyTaxSyncService
{
    public function __construct(private PropertyTaxClient $client) {}

    public function sync(Resident $resident): PropertyTaxSnapshot
    {
        $snapshot = PropertyTaxSnapshot::firstOrCreate(['resident_id' => $resident->id], [
            'village_id' => $resident->village_id, 'nik_hash' => $resident->nik_hash ?? '',
        ]);
        $snapshot->update(['attempted_at' => now()]);

        try {
            $nik = Crypt::decryptString($resident->nik_encrypted ?? '');
            if (! preg_match('/^\d{16}$/D', $nik) || ! hash_equals($resident->nik_hash ?? '', hash_hmac('sha256', $nik, config('app.key')))) {
                throw new UnexpectedValueException('invalid_identity');
            }
            // The NIK endpoint provides a cross-year total, not an annual assessment.
            $total = $this->total($this->client->check($nik));
        } catch (Throwable $exception) {
            $code = match (true) {
                $exception instanceof ConnectionException, $exception instanceof RequestException => 'api_unavailable',
                $exception instanceof DecryptException => 'invalid_identity',
                $exception->getMessage() === 'invalid_identity' => 'invalid_identity',
                default => 'unsupported_response',
            };
            // Keep the last successful amount and date. Never log NIK or raw responses.
            $snapshot->update(['sync_status' => 'failed', 'error_code' => $code]);
            $this->audit($snapshot, 'property_tax.sync_failed');

            return $snapshot->refresh();
        }

        $snapshot->update([
            'village_id' => $resident->village_id, 'nik_hash' => $resident->nik_hash,
            'total_outstanding' => $total, 'sync_status' => 'success', 'checked_at' => now(), 'error_code' => null,
        ]);
        $this->audit($snapshot, 'property_tax.synced');

        return $snapshot->refresh();
    }

    public function total(array $response): string
    {
        if (isset($response['responseText'])) {
            if (! preg_match('/(?:total\s+tunggakan)\s*[:=]\s*((?:Rp\.?\s*)?[0-9][0-9., ]*)(?:\r?\n|$)/iu', $response['responseText'], $match)) {
                throw new UnexpectedValueException('Missing explicit total outstanding.');
            }

            return $this->amount(trim($match[1]));
        }

        $result = $response['result'] ?? [];
        if (($result['success'] ?? true) === false || ! empty($result['error'])) {
            throw new UnexpectedValueException('Upstream returned an error, not a tax total.');
        }
        foreach ([$result, $result['data'] ?? []] as $node) {
            if (! is_array($node) || array_is_list($node)) {
                continue;
            }
            foreach ($node as $key => $value) {
                if (in_array(strtolower(str_replace(' ', '_', $key)), ['total_tunggakan', 'total_outstanding'], true)) {
                    return $this->amount($value);
                }
            }
        }

        throw new UnexpectedValueException('Missing explicit total outstanding.');
    }

    private function amount(mixed $value): string
    {
        if (is_int($value) || is_float($value)) {
            $amount = (float) $value;
        } elseif (is_string($value)) {
            $value = preg_replace('/^Rp\.?\s*/iu', '', trim($value));
            $value = str_replace(' ', '', $value);
            if (preg_match('/^\d{1,3}(?:\.\d{3})+(?:,\d{1,2})?$/D', $value)) {
                $value = str_replace(['.', ','], ['', '.'], $value);
            } elseif (preg_match('/^\d+(?:,\d{1,2})?$/D', $value)) {
                $value = str_replace(',', '.', $value);
            } elseif (! preg_match('/^\d+\.\d{1,2}$/D', $value)) {
                throw new UnexpectedValueException('Invalid outstanding amount.');
            }
            $amount = (float) $value;
        } else {
            throw new UnexpectedValueException('Invalid outstanding amount.');
        }
        if (! is_finite($amount) || $amount < 0 || $amount > 9999999999999.99) {
            throw new UnexpectedValueException('Outstanding amount outside supported range.');
        }

        return number_format($amount, 2, '.', '');
    }

    private function audit(PropertyTaxSnapshot $snapshot, string $action): void
    {
        DB::table('audit_logs')->insert([
            'village_id' => $snapshot->village_id, 'user_id' => $snapshot->requested_by,
            'action' => $action, 'subject_type' => Resident::class, 'subject_id' => $snapshot->resident_id,
            'metadata' => json_encode(['error_code' => $snapshot->error_code]), 'created_at' => now(),
        ]);
    }
}
