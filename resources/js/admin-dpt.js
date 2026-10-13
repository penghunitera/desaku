const form = document.querySelector('.dpt-filter');

if (form) {
    const district = form.querySelector('[name="dpt_kecamatan"]');
    const village = form.querySelector('[name="dpt_desa"]');
    const villages = JSON.parse(form.dataset.dptVillages);
    const title = (value) => value.toLocaleLowerCase('id-ID').replace(/(^|\s)\S/g, (letter) => letter.toLocaleUpperCase('id-ID'));
    const updateVillages = () => {
        const selected = village.value;
        const options = [new Option('Semua desa / kelurahan', '')];
        for (const row of villages) {
            if (!district.value || row.district === district.value) {
                options.push(new Option(`${title(row.name)} · ${title(row.district)}`, row.key, false, row.key === selected));
            }
        }
        village.replaceChildren(...options);
        if (!options.some((option) => option.value === selected)) village.value = '';
    };

    district.addEventListener('change', updateVillages);
    updateVillages();
}

const recordForm = document.querySelector('.dpt-record-filter');
if (recordForm) {
    const minimumAge = recordForm.querySelector('[name="dpt_usia_min"]');
    const maximumAge = recordForm.querySelector('[name="dpt_usia_max"]');
    const updateAgeRange = () => { maximumAge.min = minimumAge.value || '0'; };
    minimumAge.addEventListener('input', updateAgeRange);
    updateAgeRange();
}
