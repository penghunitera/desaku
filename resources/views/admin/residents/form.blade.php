@extends('admin.layout')
@section('title',$resident->exists ? 'Ubah Warga' : 'Tambah Warga')
@section('heading',$resident->exists ? 'Ubah data warga' : 'Tambah warga')
@section('subheading','Isi data pokok terlebih dahulu. Bagian lain dapat dilengkapi kapan saja.')
@section('content')
<form class="resident-wizard" method="post" action="{{ $resident->exists ? route('admin.residents.update',$resident) : route('admin.residents.store') }}">
    @csrf @if($resident->exists) @method('put') @endif
    <aside class="wizard-steps">
        <small>FORMULIR PENDUDUK</small>
        <a href="#identity" class="active"><i>1</i><span><b>Identitas</b><small>NIK dan biodata</small></span></a>
        <a href="#family"><i>2</i><span><b>Keluarga</b><small>KK dan hubungan</small></span></a>
        <a href="#address"><i>3</i><span><b>Alamat</b><small>Wilayah dan kontak</small></span></a>
        <a href="#social"><i>4</i><span><b>Sosial</b><small>Pendidikan dan pekerjaan</small></span></a>
        <a href="#other"><i>5</i><span><b>Data lainnya</b><small>Status dan catatan</small></span></a>
    </aside>
    <div class="wizard-content">
        <div class="form-notice"><span>i</span><p><b>Data pribadi dilindungi.</b> NIK disimpan terenkripsi dan setiap perubahan dicatat dalam riwayat aktivitas.</p></div>
        <section id="identity" class="form-section"><div class="section-title"><i>01</i><div><h2>Identitas warga</h2><p>Data utama sesuai dokumen kependudukan.</p></div></div><div class="field-grid">
            <label>NIK @unless($resident->exists)<sup>*</sup>@endunless<input name="nik" inputmode="numeric" maxlength="16" value="{{ old('nik') }}" placeholder="16 digit NIK" @required(!$resident->exists)><small>{{ $resident->exists ? 'Kosongkan jika NIK tidak berubah' : 'Wajib 16 angka' }}</small></label>
            <label>Nama lengkap <sup>*</sup><input name="full_name" value="{{ old('full_name',$resident->full_name) }}" required></label>
            <label>Jenis kelamin<select name="gender"><option value="">Pilih</option><option value="laki-laki" @selected(old('gender',$resident->gender)==='laki-laki')>Laki-laki</option><option value="perempuan" @selected(old('gender',$resident->gender)==='perempuan')>Perempuan</option></select></label>
            <label>Golongan darah<select name="blood_type"><option value="">Belum diketahui</option>@foreach(['A','B','AB','O'] as $blood)<option @selected(old('blood_type',$resident->blood_type)===$blood)>{{ $blood }}</option>@endforeach</select></label>
            <label>Tempat lahir<input name="birth_place" value="{{ old('birth_place',$resident->birth_place) }}"></label>
            <label>Tanggal lahir<input type="date" name="birth_date" value="{{ old('birth_date',$resident->birth_date?->format('Y-m-d')) }}"></label>
            <label>Agama<select name="religion"><option value="">Pilih</option>@foreach(['Islam','Kristen','Katolik','Hindu','Buddha','Konghucu','Kepercayaan'] as $religion)<option @selected(old('religion',$resident->religion)===$religion)>{{ $religion }}</option>@endforeach</select></label>
            <label>Status perkawinan<select name="marital_status"><option value="">Pilih</option>@foreach(['Belum Kawin','Kawin','Cerai Hidup','Cerai Mati'] as $status)<option @selected(old('marital_status',$resident->marital_status)===$status)>{{ $status }}</option>@endforeach</select></label>
        </div></section>
        <section id="family" class="form-section"><div class="section-title"><i>02</i><div><h2>Keluarga</h2><p>Hubungkan warga dengan Kartu Keluarga yang sudah terdaftar.</p></div></div><div class="field-grid">
            <label class="wide">Kartu Keluarga<select name="family_card_id"><option value="">Belum dihubungkan</option>@foreach($familyCards as $card)<option value="{{ $card->id }}" @selected((string)old('family_card_id',$resident->family_card_id)===(string)$card->id)>KK terdaftar #{{ $card->id }} · {{ $card->hamlet ?: 'Wilayah belum diisi' }} · {{ $card->residents_count }} anggota</option>@endforeach</select><small>Nomor KK disamarkan untuk menjaga privasi.</small></label>
            <label>Hubungan dalam keluarga<select name="family_relationship"><option value="">Pilih</option>@foreach(['Kepala Keluarga','Suami','Istri','Anak','Menantu','Cucu','Orang Tua','Famili Lain','Lainnya'] as $relation)<option @selected(old('family_relationship',$resident->family_relationship)===$relation)>{{ $relation }}</option>@endforeach</select></label>
        </div></section>
        <section id="address" class="form-section"><div class="section-title"><i>03</i><div><h2>Alamat dan kontak</h2><p>Digunakan untuk pelayanan dan pemetaan wilayah.</p></div></div><div class="field-grid">
            <label class="wide">Alamat lengkap<input name="address" value="{{ old('address',$resident->address) }}" placeholder="Nama jalan, nomor rumah, atau patokan"></label>
            <label>Dusun<input name="hamlet" value="{{ old('hamlet',$resident->hamlet) }}" placeholder="Contoh: Dusun I"></label><label>RT<input name="rt" maxlength="5" value="{{ old('rt',$resident->rt) }}"></label><label>RW<input name="rw" maxlength="5" value="{{ old('rw',$resident->rw) }}"></label>
            <label>Nomor HP<input name="phone" value="{{ old('phone',$resident->phone) }}"></label><label>Email<input type="email" name="email" value="{{ old('email',$resident->email) }}"></label>
        </div></section>
        <section id="social" class="form-section"><div class="section-title"><i>04</i><div><h2>Pendidikan dan pekerjaan</h2><p>Dasar statistik dan penentuan program desa.</p></div></div><div class="field-grid"><label>Pendidikan<input name="education" value="{{ old('education',$resident->education) }}" placeholder="Contoh: Tamat SMA"></label><label>Pekerjaan<input name="occupation" value="{{ old('occupation',$resident->occupation) }}" placeholder="Contoh: Petani"></label></div></section>
        <section id="other" class="form-section"><div class="section-title"><i>05</i><div><h2>Status dan Layanan Mandiri</h2><p>Status administrasi serta akses portal pribadi warga.</p></div></div><div class="field-grid"><label>Status penduduk <sup>*</sup><select name="resident_status" required>@foreach(['tetap'=>'Penduduk tetap','sementara'=>'Penduduk sementara','pindah'=>'Pindah','meninggal'=>'Meninggal'] as $value=>$label)<option value="{{ $value }}" @selected(old('resident_status',$resident->resident_status ?: 'tetap')===$value)>{{ $label }}</option>@endforeach</select></label><label>PIN Layanan Mandiri<input type="password" name="citizen_pin" inputmode="numeric" maxlength="6" placeholder="6 angka"><small>{{ $resident->citizen_pin_hash ? 'Kosongkan jika PIN tidak diubah' : 'Berikan PIN langsung kepada warga' }}</small></label><label class="wide">Catatan internal<textarea name="notes" rows="4">{{ old('notes',$resident->notes) }}</textarea></label></div></section>
        <div class="form-actions"><a class="button secondary" href="{{ $resident->exists ? route('admin.residents.show',$resident) : route('admin.residents.index') }}">Batal</a><button class="button primary">{{ $resident->exists ? 'Simpan perubahan' : 'Simpan data warga' }}</button></div>
    </div>
</form>
@endsection
