<?php
/**
 * Database query benchmark for admin dashboard data patterns
 * Usage: php scripts/perf_bench_db.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/models.php';

function bench(string $label, callable $fn): array {
    $start = microtime(true);
    $result = $fn();
    $ms = (microtime(true) - $start) * 1000;
    $count = is_array($result) ? count($result) : (is_int($result) ? $result : 0);
    return ['label' => $label, 'ms' => round($ms, 2), 'rows_or_count' => $count];
}

$userModel = new User();
$positionModel = new Position();
$applicationModel = new Application();

$results = [];
$results[] = bench('count applicants (optimized count())', fn () => $userModel->count('role = ?', [ROLE_APPLICANT]));
$results[] = bench('dashboard summary counts (single query)', fn () => $applicationModel->getAdminDashboardSummaryCounts());
$results[] = bench('getApplicationsWithDetails limit 50', fn () => $applicationModel->getApplicationsWithDetails('', [], false, 50));
$results[] = bench('admin metric rows (analytics)', fn () => $applicationModel->getAdminApplicationMetricRows(true));

$positions = $positionModel->getActivePositions();
$results[] = bench('getActivePositions', fn () => $positions);

$start = microtime(true);
foreach ($positions as $p) {
    $positionModel->getApplicationCount($p['id']);
}
$results[] = [
    'label' => 'N+1 getApplicationCount x' . count($positions),
    'ms' => round((microtime(true) - $start) * 1000, 2),
    'rows_or_count' => count($positions),
];

$start = microtime(true);
$positionModel->getApplicationCountsByPositionIds(array_column($positions, 'id'));
$results[] = [
    'label' => 'batch getApplicationCountsByPositionIds x' . count($positions),
    'ms' => round((microtime(true) - $start) * 1000, 2),
    'rows_or_count' => count($positions),
];

echo json_encode($results, JSON_PRETTY_PRINT) . PHP_EOL;
