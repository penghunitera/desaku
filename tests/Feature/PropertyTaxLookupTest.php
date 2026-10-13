<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PropertyTaxLookupTest extends TestCase
{
    public function test_lookup_page_is_publicly_available(): void
    {
        $this->get('/pbb/cek-tunggakan')
            ->assertOk()
            ->assertSee('Cek tunggakan PBB berdasarkan NIK');
    }

    public function test_nik_must_contain_exactly_sixteen_digits(): void
    {
        $this->from('/pbb/cek-tunggakan')
            ->post('/pbb/cek-tunggakan', ['nik' => '123'])
            ->assertRedirect('/pbb/cek-tunggakan')
            ->assertSessionHasErrors('nik');
    }

    public function test_it_sends_nik_to_property_tax_service_and_displays_result(): void
    {
        Http::fake([
            '*' => Http::response([
                'NAMA_WP' => 'Warga Simare',
                'Total Tunggakan' => 'Rp150.000',
            ]),
        ]);

        $this->post('/pbb/cek-tunggakan', ['nik' => '1201010101010001'])
            ->assertOk()
            ->assertSee('Warga Simare')
            ->assertSee('Rp150.000');

        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'GET'
                && $request->url() === config('services.property_tax.endpoint')
                && $request->hasHeader('Content-Type', 'application/json')
                && $request['NIK'] === '1201010101010001';
        });
    }

    public function test_it_displays_the_actual_legacy_text_response(): void
    {
        Http::fake(['*' => Http::response('Total Tunggakan: 0', 200, ['Content-Type' => 'text/html; charset=UTF-8'])]);

        $this->post('/pbb/cek-tunggakan', ['nik' => '0000000000000000'])
            ->assertOk()
            ->assertSee('Total Tunggakan: 0')
            ->assertSee('Terhubung ke layanan PBB')
            ->assertSee('value="0000000000000000"', false)
            ->assertDontSee('Respons layanan PBB tidak dapat diproses');
    }

    public function test_it_converts_legacy_html_to_text_without_rendering_scripts(): void
    {
        Http::fake(['*' => Http::response(
            '<table><tr><th>NAMA_WP</th><td>Warga Simare</td></tr><tr><th>Total Tunggakan</th><td>Rp150.000</td></tr></table><script>alert("upstream")</script><img src=x onerror="alert(1)">',
            200,
            ['Content-Type' => 'text/html'],
        )]);

        $this->post('/pbb/cek-tunggakan', ['nik' => '1201010101010001'])
            ->assertOk()
            ->assertSee('Warga Simare')
            ->assertSee('Rp150.000')
            ->assertDontSee('alert("upstream")', false)
            ->assertDontSee('onerror=', false);
    }

    public function test_it_accepts_wrapped_json_and_shows_an_empty_result(): void
    {
        Http::fake(['*' => Http::response(['data' => []])]);

        $this->post('/pbb/cek-tunggakan', ['nik' => '1201010101010001'])
            ->assertOk()
            ->assertSee('Data tidak ditemukan');
    }

    public function test_it_does_not_treat_an_html_error_page_as_tax_data(): void
    {
        Http::fake(['*' => Http::response('<h1>Database Error</h1><p>Unable to connect to database.</p>', 200)]);

        $this->post('/pbb/cek-tunggakan', ['nik' => '1201010101010001'])
            ->assertOk()
            ->assertSee('Respons layanan PBB tidak dapat diproses')
            ->assertDontSee('Database Error');
    }

    public function test_it_shows_safe_message_when_service_is_unavailable(): void
    {
        Http::fake(['*' => Http::response([], 503)]);

        $this->post('/pbb/cek-tunggakan', ['nik' => '1201010101010001'])
            ->assertOk()
            ->assertSee('Layanan data PBB sedang tidak dapat dihubungi');
    }
}
