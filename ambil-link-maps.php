<?php
declare(strict_types=1);

set_time_limit(120);

$url = 'https://rajaemasindonesia.co.id/';

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_CONNECTTIMEOUT => 20,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_ENCODING => '',
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130.0 Safari/537.36',
]);

$html = curl_exec($ch);
$error = curl_error($ch);
$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($html === false || $status >= 400) {
    exit('Gagal mengambil halaman. HTTP: ' . $status . ' - ' .
        htmlspecialchars($error, ENT_QUOTES, 'UTF-8'));
}

file_put_contents(__DIR__ . '/debug-branches.html', $html);

libxml_use_internal_errors(true);

$dom = new DOMDocument();
$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
$xpath = new DOMXPath($dom);
$anchors = $xpath->query('//a[@href]');

$hasil = [];
$seen = [];

if ($anchors !== false) {
    foreach ($anchors as $anchor) {
        $label = trim(
            preg_replace('/\s+/u', ' ', $anchor->textContent)
        );

        $labelLower = mb_strtolower($label, 'UTF-8');

        if (!in_array($labelLower, ['map', 'menuju lokasi'], true)) {
            continue;
        }

        $href = html_entity_decode(
            trim($anchor->getAttribute('href')),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        if ($href === '') {
            continue;
        }

        $key = $labelLower . '|' . $href;
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $hasil[] = [
            'jenis' => $label,
            'url' => $href,
        ];
    }
}

libxml_clear_errors();

// Simpan CSV
$csvPath = __DIR__ . '/link-maps-raja-emas.csv';
$file = fopen($csvPath, 'wb');

if ($file !== false) {
    fwrite($file, "\xEF\xBB\xBF");
    fputcsv($file, ['No', 'Jenis Link', 'URL Asli']);

    foreach ($hasil as $i => $item) {
        fputcsv($file, [
            $i + 1,
            $item['jenis'],
            $item['url'],
        ]);
    }

    fclose($file);
}

// Tampilkan hasil
header('Content-Type: text/html; charset=UTF-8');

echo '<!DOCTYPE html>';
echo '<html lang="id"><head>';
echo '<meta charset="UTF-8">';
echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
echo '<title>Link Maps Raja Emas</title>';
echo '</head><body>';

echo '<h2>Link Maps Raja Emas Indonesia</h2>';
echo '<p>Sumber: <a href="https://rajaemasindonesia.co.id/#lokasi" target="_blank">Lokasi Kami</a></p>';
echo '<p>Total link ditemukan: ' . count($hasil) . '</p>';

if (is_file($csvPath)) {
    echo '<p><a href="link-maps-raja-emas.csv" download>Unduh CSV</a></p>';
}

echo '<table border="1" cellpadding="8" cellspacing="0">';
echo '<tr><th>No.</th><th>Jenis Link</th><th>URL Asli</th></tr>';

foreach ($hasil as $i => $item) {
    $safeUrl = htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8');

    echo '<tr>';
    echo '<td>' . ($i + 1) . '</td>';
    echo '<td>' . htmlspecialchars($item['jenis'], ENT_QUOTES, 'UTF-8') . '</td>';
    echo '<td><a href="' . $safeUrl . '" target="_blank" rel="noopener noreferrer">' .
        $safeUrl . '</a></td>';
    echo '</tr>';
}

if (!$hasil) {
    echo '<tr><td colspan="3">Link tidak ditemukan. Periksa debug-branches.html.</td></tr>';
}

echo '</table>';
echo '</body></html>';
