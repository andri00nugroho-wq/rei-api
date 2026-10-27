<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config.php';

$locationsPath = __DIR__ . '/branch-locations.json';
$locationsJson = file_get_contents($locationsPath);

if ($locationsJson === false) {
    throw new RuntimeException('Could not read the published branch listing.');
}

$locations = json_decode($locationsJson, true, 512, JSON_THROW_ON_ERROR);

if (!is_array($locations)) {
    throw new RuntimeException('The published branch listing must be a JSON array.');
}

$findBranch = $pdo->prepare(
    'SELECT id FROM branches WHERE branch_number = :branch_number LIMIT 1'
);
$insertBranch = $pdo->prepare(
    'INSERT INTO branches (branch_number, title, address, phone, hours, notes, map_link, sort_order)
     VALUES (:branch_number, :title, :address, :phone, :hours, :notes, :map_link, :sort_order)'
);

$pdo->beginTransaction();

try {
    foreach ($locations as $location) {
        if (
            !is_array($location)
            || !isset($location['branch_number'], $location['title'])
            || !is_string($location['branch_number'])
            || !is_string($location['title'])
        ) {
            throw new UnexpectedValueException('A branch listing has invalid required fields.');
        }

        $findBranch->execute(['branch_number' => $location['branch_number']]);

        if ($findBranch->fetchColumn() !== false) {
            continue;
        }

        $address = trim((string) ($location['address'] ?? ''));
        $mapQuery = trim($location['title'] . ' ' . $address);
        $insertBranch->execute([
            'branch_number' => $location['branch_number'],
            'title' => $location['title'],
            'address' => $address,
            'phone' => (string) ($location['phone'] ?? ''),
            'hours' => (string) ($location['hours'] ?? ''),
            'notes' => (string) ($location['notes'] ?? ''),
            'map_link' => 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($mapQuery),
            'sort_order' => (int) $location['branch_number'],
        ]);
    }

    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $error;
}

echo count($locations) . " published branch listings processed.\n";
