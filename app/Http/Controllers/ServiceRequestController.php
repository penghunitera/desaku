<?php

namespace App\Http\Controllers;

use App\Models\Resident;
use App\Models\ServiceRequest;
use App\Models\Village;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServiceRequestController extends Controller
{
    public const SERVICES = [
        'ktp-el' => [
            'name' => 'Pembuatan KTP-el', 'short' => 'KTP', 'duration' => '3–7 hari kerja', 'category' => 'dukcapil', 'category_name' => 'Administrasi Kependudukan',
            'destination' => 'Dinas Kependudukan dan Pencatatan Sipil Kabupaten Toba',
            'description' => 'Pengantar perekaman baru, penggantian karena rusak/hilang, atau perubahan elemen data.',
            'requirements' => ['Kartu Keluarga', 'KTP lama atau surat kehilangan (jika ada)', 'Dokumen pendukung perubahan data'],
        ],
        'kk-terbaru' => [
            'name' => 'Kartu Keluarga terbaru', 'short' => 'KK', 'duration' => '3–7 hari kerja', 'category' => 'dukcapil', 'category_name' => 'Administrasi Kependudukan',
            'destination' => 'Dinas Kependudukan dan Pencatatan Sipil Kabupaten Toba',
            'description' => 'Penerbitan KK baru karena perubahan anggota keluarga, status, atau data kependudukan.',
            'requirements' => ['Kartu Keluarga lama', 'Bukti perubahan peristiwa penting', 'KTP anggota keluarga terkait'],
        ],
        'surat-pindah' => [
            'name' => 'Surat pindah penduduk', 'short' => 'SP', 'duration' => '5–10 hari kerja', 'category' => 'dukcapil', 'category_name' => 'Administrasi Kependudukan',
            'destination' => 'Dinas Kependudukan dan Pencatatan Sipil Kabupaten Toba',
            'description' => 'Pengantar perpindahan alamat antar desa, kecamatan, kabupaten, atau provinsi.',
            'requirements' => ['Kartu Keluarga', 'KTP pemohon', 'Alamat tujuan lengkap'],
        ],
        'skrining-kesehatan' => [
            'name' => 'Pendaftaran skrining kesehatan', 'short' => 'SE', 'duration' => '1–3 hari kerja', 'category' => 'health', 'category_name' => 'Kesehatan Masyarakat',
            'destination' => 'Puskesmas Borbor', 'description' => 'Pendaftaran skrining hipertensi, tuberkulosis, kesehatan ibu hamil, dan risiko stunting.',
            'requirements' => ['NIK warga', 'Nomor HP aktif', 'Informasi kebutuhan atau keluhan awal'],
        ],
        'bantuan-sosial' => [
            'name' => 'Pendaftaran dan pemutakhiran bansos', 'short' => 'BS', 'duration' => '7–14 hari kerja', 'category' => 'social', 'category_name' => 'Bantuan Sosial',
            'destination' => 'Dinas Sosial Kabupaten Toba', 'description' => 'Pengajuan pendataan, pemutakhiran kondisi keluarga, atau masa sanggah penerima bantuan sosial.',
            'requirements' => ['Kartu Keluarga', 'KTP', 'Dokumen pendukung kondisi sosial ekonomi'],
        ],
        'validasi-bphtb' => [
            'name' => 'Validasi dan konsultasi BPHTB', 'short' => 'BT', 'duration' => '5–10 hari kerja', 'category' => 'tax', 'category_name' => 'PBB & BPHTB',
            'destination' => 'Badan Pengelolaan Pendapatan Daerah Kabupaten Toba', 'description' => 'Permohonan awal validasi transaksi tanah/bangunan dan konsultasi kelengkapan BPHTB.',
            'requirements' => ['KTP dan KK', 'SPPT PBB/NOP', 'Dokumen transaksi atau alas hak'],
        ],
        'pesanan-bumdes' => [
            'name' => 'Pemesanan produk BUMDes', 'short' => 'BM', 'duration' => '1–2 hari kerja', 'category' => 'bumdes', 'category_name' => 'BUMDes Simare',
            'destination' => 'Pengelola BUMDes Simare', 'description' => 'Pesan ternak, pakan, atau produk desa dan tunggu konfirmasi stok serta jadwal serah terima.',
            'requirements' => ['Identitas pemesan', 'Nomor HP aktif', 'Nama produk dan jumlah pesanan'],
        ],
        'program-pemerintah' => [
            'name' => 'Pendaftaran program pemerintah', 'short' => 'PP', 'duration' => '5–14 hari kerja', 'category' => 'government', 'category_name' => 'Program Pemerintah',
            'destination' => 'Pemerintah Desa Simare', 'description' => 'Sampaikan minat pada program desa, kabupaten, provinsi, atau pusat untuk diverifikasi operator.',
            'requirements' => ['KTP dan KK', 'Nama program yang diminati', 'Dokumen persyaratan program bila tersedia'],
        ],
    ];

    public function index(): View
    {
        return view('services.index', ['services' => self::SERVICES]);
    }

    public function create(Request $request): View
    {
        $selected = $request->string('jenis')->toString();
        if (! array_key_exists($selected, self::SERVICES)) {
            $selected = 'ktp-el';
        }

        return view('services.create', ['services' => self::SERVICES, 'selected' => $selected]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'service_type' => ['required', Rule::in(array_keys(self::SERVICES))],
            'full_name' => ['required', 'string', 'max:120'],
            'nik' => ['required', 'digits:16'],
            'phone' => ['required', 'regex:/^(?:\+62|62|0)8[0-9]{8,12}$/'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'hamlet' => ['required', 'string', 'max:80'],
            'address' => ['required', 'string', 'max:500'],
            'purpose' => ['required', 'string', 'max:1000'],
            'destination_address' => ['nullable', 'required_if:service_type,surat-pindah', 'string', 'max:500'],
            'documents' => [Rule::requiredIf(fn () => in_array(self::SERVICES[$request->string('service_type')->toString()]['category'] ?? '', ['dukcapil', 'social', 'tax'], true)), 'array', 'max:5'],
            'documents.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'consent' => ['accepted'],
        ], [
            'nik.digits' => 'NIK harus terdiri dari 16 angka.',
            'phone.regex' => 'Masukkan nomor HP Indonesia yang valid.',
            'documents.required' => 'Unggah minimal satu dokumen persyaratan.',
            'documents.*.max' => 'Ukuran setiap dokumen maksimal 5 MB.',
            'consent.accepted' => 'Persetujuan pemrosesan data wajib diberikan.',
        ]);

        $serviceRequest = DB::transaction(function () use ($request, $validated) {
            $village = Village::firstOrCreate(['code' => 'SIMARE'], [
                'name' => 'Desa Simare', 'district' => 'Borbor', 'regency' => 'Toba', 'province' => 'Sumatera Utara',
            ]);
            $nikHash = hash_hmac('sha256', $validated['nik'], config('app.key'));
            $resident = Resident::withTrashed()->firstOrNew(['village_id' => $village->id, 'nik_hash' => $nikHash]);
            $resident->fill([
                'nik_encrypted' => Crypt::encryptString($validated['nik']),
                'full_name' => $validated['full_name'], 'phone' => $this->normalizePhone($validated['phone']),
                'birth_date' => $validated['birth_date'] ?? null, 'hamlet' => $validated['hamlet'],
            ]);
            $resident->deleted_at = null;
            $resident->save();

            $submission = ServiceRequest::create([
                'public_id' => (string) Str::uuid(), 'tracking_code' => $this->trackingCode(),
                'village_id' => $village->id, 'resident_id' => $resident->id,
                'category' => self::SERVICES[$validated['service_type']]['category'], 'service_type' => $validated['service_type'], 'status' => 'submitted',
                'destination_agency' => self::SERVICES[$validated['service_type']]['destination'],
                'form_data' => ['address' => $validated['address'], 'purpose' => $validated['purpose'], 'destination_address' => $validated['destination_address'] ?? null],
                'consented_at' => now(), 'submitted_at' => now(),
            ]);

            foreach ($request->file('documents', []) as $index => $file) {
                $path = $file->store("service-requests/{$submission->public_id}", 'local');
                $submission->documents()->create([
                    'document_type' => 'persyaratan-'.($index + 1), 'original_name' => $file->getClientOriginalName(),
                    'storage_path' => $path, 'mime_type' => $file->getMimeType() ?: 'application/octet-stream', 'size' => $file->getSize(),
                ]);
            }
            $submission->histories()->create([
                'status' => 'submitted', 'title' => 'Permohonan berhasil diajukan',
                'description' => 'Berkas menunggu pemeriksaan awal oleh operator Desa Simare.',
            ]);

            return $submission;
        });

        $request->session()->put("service_access.{$serviceRequest->public_id}", true);

        return redirect()->route('services.show', $serviceRequest)->with('submitted', true);
    }

    public function track(Request $request): RedirectResponse
    {
        $validated = $request->validate(['tracking' => ['required', 'string', 'max:30'], 'tracking_phone' => ['required', 'string', 'max:20']]);
        $submission = ServiceRequest::with('resident')->where('tracking_code', strtoupper(trim($validated['tracking'])))->first();
        if (! $submission || $submission->resident?->phone !== $this->normalizePhone($validated['tracking_phone'])) {
            return back()->withErrors(['tracking' => 'Nomor permohonan atau nomor HP tidak cocok.'])->withInput();
        }
        $request->session()->put("service_access.{$submission->public_id}", true);

        return redirect()->route('services.show', $submission);
    }

    public function show(Request $request, ServiceRequest $serviceRequest): View
    {
        abort_unless($request->session()->get("service_access.{$serviceRequest->public_id}"), 403);
        $serviceRequest->load(['resident', 'documents', 'histories']);
        $service = self::SERVICES[$serviceRequest->service_type] ?? abort(404);

        return view('services.show', ['submission' => $serviceRequest, 'service' => $service]);
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\D+/', '', $phone);

        return str_starts_with($phone, '0') ? '62'.substr($phone, 1) : (str_starts_with($phone, '62') ? $phone : '62'.$phone);
    }

    private function trackingCode(): string
    {
        do {
            $code = 'SMR-'.now()->format('ymd').'-'.strtoupper(Str::random(5));
        } while (ServiceRequest::where('tracking_code', $code)->exists());

        return $code;
    }
}
