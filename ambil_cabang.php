<?php
declare(strict_types=1);

$url = 'https://rajaemasindonesia.co.id/';

$context = stream_context_create([
    'http' => [
        'timeout' => 40,
        'follow_location' => 1,
        'header' => "User-Agent: Mozilla/5.0\r\n"
    ],
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true
    ]
]);

$html = @file_get_contents($url, false, $context);

if ($html === false) {
    exit("GAGAL: Tidak bisa mengambil halaman website.\n");
}

/*
 * Hilangkan tag HTML dan ubah menjadi teks.
 * Data cabang di halaman utama memiliki pola # 01, # 02, dst.
 */
$html = str_replace(
    ['</h1>', '</h2>', '</h3>', '</p>', '</div>', '</article>', '<br>', '<br/>', '<br />'],
    "\n",
    $html
);

$teks = html_entity_decode(
    strip_tags($html),
    ENT_QUOTES | ENT_HTML5,
    'UTF-8'
);

$teks = preg_replace('/\r\n?/', "\n", $teks);
$teks = preg_replace('/[ \t]+/u', ' ', $teks);

/*
 * Ambil bagian setelah judul Lokasi Kami.
 */
$posisi = mb_stripos($teks, 'Lokasi Kami');

if ($posisi === false) {
    exit("GAGAL: Bagian Lokasi Kami tidak ditemukan.\n");
}

$teks = mb_substr($teks, $posisi);

/*
 * Pisahkan data berdasarkan nomor cabang.
 */
$hasil = preg_split(
    '/(?=^\s*#\s*\d{1,3}\s*$)/m',
    $teks,
    -1,
    PREG_SPLIT_NO_EMPTY
);

$jumlah = 0;

foreach ($hasil as $blok) {
    if (!preg_match('/^\s*#\s*(\d{1,3})\s*$/m', $blok, $nomorMatch)) {
        continue;
    }

    $nomor = (int) $nomorMatch[1];

    if ($nomor < 1) {
        continue;
    }

    $baris = array_values(array_filter(
        array_map('trim', explode("\n", $blok)),
        fn($v) => $v !== ''
    ));

    $nama = '';
    $alamat = '';
    $whatsapp = '';
    $jam = '';
    $catatan = 'BUKA SETIAP HARI';

    $mulai = false;
    $alamatBaris = [];

    foreach ($baris as $i => $v) {
        if (preg_match('/^#\s*\d{1,3}$/', $v)) {
            continue;
        }

        if (
            stripos($v, 'Hp & Whatsapp') !== false ||
            stripos($v, 'Hp&Whatsapp') !== false ||
            stripos($v, 'Hp & Whtasapp') !== false
        ) {
            if (preg_match('/(?:Whatsapp|Whtasapp)\s*:?\s*(.*)$/i', $v, $m)) {
                $whatsapp = trim($m[1]);
            }

            if ($whatsapp === '' && isset($baris[$i + 1])) {
                $whatsapp = trim($baris[$i + 1]);
            }

            continue;
        }

        if (preg_match('/^(Buka|Jam Operasional)\s*:\s*(.*)$/i', $v, $m)) {
            $jam = trim($m[2]);

            if (stripos($jam, 'Pukul ') === 0) {
                $jam = trim(substr($jam, 6));
            }

            if ($jam === '' && isset($baris[$i + 1])) {
                $jam = trim($baris[$i + 1]);
            }

            continue;
        }

        if (stripos($v, 'BUKA SETIAP HARI') !== false) {
            $catatan = 'BUKA SETIAP HARI';
            continue;
        }

        if (
            stripos($v, 'Telephone:') === 0 ||
            stripos($v, 'Map') === 0 ||
            stripos($v, 'Menuju Lokasi') === 0
        ) {
            continue;
        }

        if (!$mulai) {
            if (
                stripos($v, 'Jakarta') !== false ||
                stripos($v, 'Depok') !== false ||
                stripos($v, 'Bekasi') !== false ||
                stripos($v, 'Tangerang') !== false ||
                stripos($v, 'Bogor') !== false ||
                stripos($v, 'Bandung') !== false ||
                stripos($v, 'Surabaya') !== false ||
                stripos($v, 'Semarang') !== false ||
                stripos($v, 'Serang') !== false ||
                stripos($v, 'Outlet ') === 0
            ) {
                $nama = $v;
                $mulai = true;
                continue;
            }
        }

        if ($mulai) {
            $alamatBaris[] = $v;
        }
    }

    $alamat = trim(implode(' ', $alamatBaris));

    if ($nama === '') {
        continue;
    }

    // Hilangkan nomor urut dari nama bila sumber menggunakan "01 Nama".
    $nama = preg_replace('/^#?\s*\d+\s+/', '', $nama);

    // Rapikan nomor WhatsApp.
    $whatsapp = trim(preg_replace('/\s+/', ' ', $whatsapp));

    $angkaWA = preg_replace('/\D+/', '', $whatsapp);

    if ($angkaWA !== '' && str_starts_with($angkaWA, '0')) {
        $angkaWA = '62' . substr($angkaWA, 1);
    }

    $jumlah++;

    echo str_pad((string) $nomor, 2, '0', STR_PAD_LEFT) . PHP_EOL;
    echo $nama . PHP_EOL;
    echo str_repeat('=', mb_strlen($nama)) . PHP_EOL . PHP_EOL;
    echo $alamat . PHP_EOL . PHP_EOL;
    echo 'Hp & Whatsapp: ' . $whatsapp . PHP_EOL . PHP_EOL;
    echo 'Buka : Pukul ' . $jam . PHP_EOL;
    echo 'Catatan: ' . $catatan . PHP_EOL . PHP_EOL;

    echo 'Map: https://maps.google.com/maps?q='
        . rawurlencode('Raja Emas ' . $nama)
        . '&t=m&z=14&output=embed&iwloc=near'
        . PHP_EOL . PHP_EOL;

    echo str_repeat('-', 60) . PHP_EOL . PHP_EOL;
}

echo "Total data yang terbaca: {$jumlah}\n";

if ($jumlah === 0) {
    echo "Debug: HTML berhasil diunduh, tetapi pola nomor cabang tidak cocok.\n";
    echo "Coba periksa apakah halaman yang diunduh memuat data lokasi.\n";
    file_put_contents(__DIR__ . '/debug_cabang.html', $html);
    echo "HTML tersimpan di debug_cabang.html\n";
}
