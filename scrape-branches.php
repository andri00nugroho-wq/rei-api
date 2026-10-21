    }

    echo "\n";
    echo str_pad('NO', 6)
        . str_pad('NAMA CABANG', 42)
        . str_pad('ALAMAT', 12)
        . str_pad('MAP', 8)
        . "MENUJU LOKASI\n";

    echo str_repeat('-', 100) . "\n";

    $inserted = 0;
    $updated = 0;
    $failed = 0;
    $failures = [];

    $pdo->beginTransaction();

    try {
        foreach ($branches as $branch) {
            try {
                $status = syncBranch($pdo, $branch);

                if ($status === 'inserted') {
                    $inserted++;
                } else {
                    $updated++;
                }

                echo str_pad($branch['branch_number'], 6)
                    . str_pad(
                        mb_substr($branch['title'], 0, 39, 'UTF-8'),
                        42
                    )
                    . str_pad($branch['address'] !== '' ? 'YA' : 'KOSONG', 12)
                    . str_pad($branch['map_embed_url'] !== '' ? 'YA' : 'TIDAK', 8)
                    . ($branch['map_link'] !== '' ? 'YA' : 'TIDAK')
                    . "\n";

                if ($branch['address'] === '') {
                    echo "  PERINGATAN: alamat cabang {$branch['branch_number']} kosong pada hasil scraping.\n";
                }
            } catch (Throwable $e) {
                $failed++;
                $failures[] = [
                    'number' => $branch['branch_number'],
                    'message' => $e->getMessage(),
                ];

                echo "GAGAL cabang {$branch['branch_number']}: "
                    . $e->getMessage() . "\n";
            }
        }

        // Jika ada kegagalan, data yang sukses tetap disimpan.
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
    }

    $totalDb = (int) $pdo->query(
        'SELECT COUNT(*) FROM `' . DB_TABLE . '`'
    )->fetchColumn();

    echo "\n========================================\n";
    echo " HASIL SINKRONISASI\n";
    echo "========================================\n";
    echo "Cabang diparsing : " . count($branches) . "\n";
    echo "Cabang ditambah  : {$inserted}\n";
    echo "Cabang diperbarui: {$updated}\n";
    echo "Cabang gagal     : {$failed}\n";
    echo "Total di database: {$totalDb}\n";
    echo "HTML debug       : " . DEBUG_FILE . "\n";

    if ($failures !== []) {
        echo "\nRincian kegagalan:\n";

        foreach ($failures as $failure) {
            echo '- Cabang ' . $failure['number']
                . ': ' . $failure['message'] . "\n";
        }
    }

    echo "\nSelesai.\n";
}

// Jalankan otomatis hanya ketika file ini dipanggil langsung.
// Jika di-include oleh halaman admin, main() dipanggil setelah login dan CSRF diperiksa.
$isDirectExecution = isset($_SERVER['SCRIPT_FILENAME'])
    && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__);

if ($isDirectExecution) {
    try {
        main();
    } catch (Throwable $e) {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "\nERROR: " . $e->getMessage() . "\n");
            exit(1);
        }

        http_response_code(500);
        echo 'ERROR: ' . htmlspecialchars(
            $e->getMessage(),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}
