<?php

namespace App\Http\Controllers;

use App\Services\PropertyTaxClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class PropertyTaxController extends Controller
{
    public function index(): View
    {
        return view('property-tax.index');
    }

    public function check(Request $request, PropertyTaxClient $client): View
    {
        $validated = $request->validate([
            'nik' => ['required', 'digits:16'],
        ], [
            'nik.required' => 'NIK wajib diisi.',
            'nik.digits' => 'NIK harus terdiri dari 16 angka.',
        ]);

        try {
            return view('property-tax.index', [
                ...$client->check($validated['nik']),
                'searched' => true,
                'searchedNik' => $validated['nik'],
            ]);
        } catch (ConnectionException|RequestException $exception) {
            report($exception);

            return view('property-tax.index', [
                'searched' => true,
                'searchedNik' => $validated['nik'],
                'serviceError' => 'Layanan data PBB sedang tidak dapat dihubungi. Silakan coba kembali beberapa saat lagi.',
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return view('property-tax.index', [
                'searched' => true,
                'searchedNik' => $validated['nik'],
                'serviceError' => 'Respons layanan PBB tidak dapat diproses. Silakan hubungi operator desa.',
            ]);
        }
    }
}
