-- Internal source-data views. The dpt table is separate from verified residents.
-- Ages reflect the source as supplied; these aggregates do not measure income,
-- employment, PBB potential, total population, or current eligibility to vote.
-- L/P are the source gender codes. Every other value is counted as unknown.
CREATE VIEW dpt_demografi AS
SELECT iddpt,
       CASE WHEN UPPER(TRIM(nama)) = 'NAMA'
                 AND UPPER(TRIM(jenis_kelamin)) = 'JENIS KELAMIN'
                 AND UPPER(TRIM(usia)) = 'USIA'
            THEN 1 ELSE 0 END AS baris_judul,
       UPPER(COALESCE(NULLIF(TRIM(kabupaten), ''), '(BELUM DIISI)')) AS kabupaten,
       UPPER(COALESCE(NULLIF(TRIM(kecamatan), ''), '(BELUM DIISI)')) AS kecamatan,
       UPPER(COALESCE(NULLIF(TRIM(desa_kelurahan), ''), '(BELUM DIISI)')) AS desa_kelurahan,
       NULLIF(TRIM(tps), '') AS tps,
       COALESCE(NULLIF(UPPER(TRIM(jenis_kelamin)), ''), '?') AS jenis_kelamin,
       CASE WHEN TRIM(COALESCE(usia, '')) <> ''
                 AND TRIM(usia) NOT GLOB '*[^0-9]*'
                 AND CAST(TRIM(usia) AS INTEGER) BETWEEN 0 AND 120
            THEN CAST(TRIM(usia) AS INTEGER) END AS usia_tercatat
FROM dpt;

CREATE VIEW dpt_potensi_desa AS
SELECT kabupaten, kecamatan, desa_kelurahan,
       COUNT(*) AS jumlah_dpt,
       SUM(CASE WHEN jenis_kelamin = 'L' THEN 1 ELSE 0 END) AS laki_laki,
       SUM(CASE WHEN jenis_kelamin = 'P' THEN 1 ELSE 0 END) AS perempuan,
       SUM(CASE WHEN jenis_kelamin NOT IN ('L', 'P') THEN 1 ELSE 0 END) AS gender_belum_valid,
       SUM(CASE WHEN usia_tercatat BETWEEN 0 AND 14 THEN 1 ELSE 0 END) AS usia_0_14,
       SUM(CASE WHEN usia_tercatat BETWEEN 15 AND 64 THEN 1 ELSE 0 END) AS usia_15_64,
       SUM(CASE WHEN usia_tercatat >= 65 THEN 1 ELSE 0 END) AS usia_65_ke_atas,
       SUM(CASE WHEN usia_tercatat IS NULL THEN 1 ELSE 0 END) AS usia_belum_valid,
       COUNT(DISTINCT tps) AS jumlah_tps
FROM dpt_demografi
WHERE baris_judul = 0
GROUP BY kabupaten, kecamatan, desa_kelurahan;
