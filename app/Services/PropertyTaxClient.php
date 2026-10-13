<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

class PropertyTaxClient
{
    public function check(string $nik): array
    {
        // The legacy PBB API expects a GET request with a JSON body.
        $response = Http::acceptJson()
            ->connectTimeout(5)
            ->timeout(15)
            ->send('GET', config('services.property_tax.endpoint'), [
                'json' => ['NIK' => $nik],
            ])
            ->throw();

        $json = $response->json();

        if (is_array($json)) {
            return ['result' => $json];
        }

        $body = $response->body();
        // Convert legacy HTML output to readable text; never render upstream HTML.
        $body = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1\s*>/is', '', $body);
        $body = preg_replace('/<br\s*\/?\s*>|<\/(?:p|div|tr|li|h[1-6]|table)\s*>/i', "\n", $body);
        $body = preg_replace('/<\/(?:td|th)\s*>/i', '  ', $body);
        $body = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $body = str_replace("\u{00A0}", ' ', $body);
        $lines = array_filter(array_map(
            fn (string $line) => trim(preg_replace('/[\t ]+/', ' ', $line)),
            preg_split('/\R/', $body),
        ), fn (string $line) => $line !== '');
        $text = implode("\n", $lines);

        if ($text === '' || ! preg_match('/tunggakan|pbb|wajib\s*pajak|nama[_\s]*wp|data\s+(?:tidak|belum)\s+(?:ada|ditemukan)/i', $text)) {
            throw new UnexpectedValueException('Respons PBB tidak sesuai format yang didukung.');
        }

        return ['responseText' => $text];
    }
}
