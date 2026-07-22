<?php
/**
 * Seed at least 10 WPU positions with minimum requirements.
 * Run once: php seed_positions.php
 */

require_once __DIR__ . '/config/database.php';

$positions = [
    [
        'title' => 'University Extension Specialist IV SG 22',
        'description' => 'Responsible for planning and implementing extension programs in assigned communities.',
        'requirements' => "Bachelor's degree relevant to the position\nCareer Service eligibility\nWillingness to work in field assignments",
        'salary_grade' => 'SG 22',
        'salary_range' => 'PHP 71,511.00',
        'career_service_eligibilities' => 'Professional Eligibility',
    ],
    [
        'title' => 'University Extension Specialist III SG 20',
        'description' => 'Assists in the development and delivery of university extension services.',
        'requirements' => "Bachelor's degree\nNo prior experience required for entry level\nGood communication skills",
        'salary_grade' => 'SG 20',
        'salary_range' => 'PHP 57,806.00',
        'career_service_eligibilities' => 'Professional Eligibility',
    ],
    [
        'title' => 'University Extension Specialist II SG 18',
        'description' => 'Supports extension projects and community engagement activities.',
        'requirements' => "Bachelor's degree\nBasic computer literacy\nWilling to travel within Palawan",
        'salary_grade' => 'SG 18',
        'salary_range' => 'PHP 46,725.00',
        'career_service_eligibilities' => 'Professional Eligibility',
    ],
    [
        'title' => 'University Extension Specialist I SG 16',
        'description' => 'Entry-level extension specialist role supporting program implementation.',
        'requirements' => "Bachelor's degree\nFresh graduates may apply\nInterest in community development",
        'salary_grade' => 'SG 16',
        'salary_range' => 'PHP 38,150.00',
        'career_service_eligibilities' => 'Professional Eligibility',
    ],
    [
        'title' => 'University Extension Associate I SG 12',
        'description' => 'Provides clerical and technical support to extension programs.',
        'requirements' => "College level or equivalent\nBasic documentation skills\nTeam player",
        'salary_grade' => 'SG 12',
        'salary_range' => 'PHP 27,000.00',
        'career_service_eligibilities' => 'Sub-Professional Eligibility',
    ],
    [
        'title' => 'Administrative Assistant III SG 9',
        'description' => 'Handles administrative tasks, records management, and office coordination.',
        'requirements' => "High school graduate or college level\nBasic MS Office skills\nOrganized and detail-oriented",
        'salary_grade' => 'SG 9',
        'salary_range' => 'PHP 21,211.00',
        'career_service_eligibilities' => 'Sub-Professional Eligibility',
    ],
    [
        'title' => 'Administrative Aide VI (Clerk III) SG 6',
        'description' => 'Performs clerical duties including filing, encoding, and front-line assistance.',
        'requirements' => "High school graduate\nBasic typing and encoding skills\nNo experience required",
        'salary_grade' => 'SG 6',
        'salary_range' => 'PHP 17,553.00',
        'career_service_eligibilities' => 'Sub-Professional Eligibility',
    ],
    [
        'title' => 'Administrative Aide IV (Clerk II) SG 4',
        'description' => 'Entry-level clerical position for office support and document handling.',
        'requirements' => "High school graduate\nWilling to learn on the job\nGood interpersonal skills",
        'salary_grade' => 'SG 4',
        'salary_range' => 'PHP 15,524.00',
        'career_service_eligibilities' => 'Sub-Professional Eligibility',
    ],
    [
        'title' => 'IT Support Staff SG 9',
        'description' => 'Provides basic IT support, hardware troubleshooting, and user assistance.',
        'requirements' => "IT-related course or equivalent experience\nBasic networking knowledge\nProblem-solving skills",
        'salary_grade' => 'SG 9',
        'salary_range' => 'PHP 21,211.00',
        'career_service_eligibilities' => 'Professional Eligibility',
    ],
    [
        'title' => 'Library Aide SG 4',
        'description' => 'Assists in library operations including shelving, cataloging, and patron services.',
        'requirements' => "High school graduate\nInterest in library work\nNo prior library experience required",
        'salary_grade' => 'SG 4',
        'salary_range' => 'PHP 15,524.00',
        'career_service_eligibilities' => 'Sub-Professional Eligibility',
    ],
    [
        'title' => 'Research Assistant SG 12',
        'description' => 'Supports faculty and researchers in data collection, documentation, and report preparation.',
        'requirements' => "Bachelor's degree\nBasic research and writing skills\nFresh graduates welcome",
        'salary_grade' => 'SG 12',
        'salary_range' => 'PHP 27,000.00',
        'career_service_eligibilities' => 'Professional Eligibility',
    ],
    [
        'title' => 'Guidance Counselor SG 18',
        'description' => 'Provides student guidance, counseling services, and wellness program support.',
        'requirements' => "Bachelor's degree in Psychology, Guidance and Counseling, or related field\nLicensed preferred but not required for entry\nEmpathy and active listening skills",
        'salary_grade' => 'SG 18',
        'salary_range' => 'PHP 46,725.00',
        'career_service_eligibilities' => 'Bar/Board Eligibility (RA 1080)',
    ],
];

try {
    $db = new Database();
    $pdo = $db->getConnection();

    $adminStmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1");
    $admin = $adminStmt->fetch();
    $createdBy = $admin ? (int) $admin['id'] : 1;

    $checkStmt = $pdo->prepare('SELECT id FROM positions WHERE title = ? LIMIT 1');
    $insertStmt = $pdo->prepare(
        'INSERT INTO positions (
            title, description, requirements, min_experience_years, max_applications,
            salary_range, salary_grade, training_hours, career_service_eligibilities,
            location, employment_type, deadline, is_active, created_by
        ) VALUES (
            :title, :description, :requirements, 0, NULL,
            :salary_range, :salary_grade, NULL, :career_service_eligibilities,
            :location, :employment_type, :deadline, 1, :created_by
        )'
    );

    $inserted = 0;
    $skipped = 0;

    foreach ($positions as $position) {
        $checkStmt->execute([$position['title']]);
        if ($checkStmt->fetch()) {
            $skipped++;
            echo "Skipped (exists): {$position['title']}\n";
            continue;
        }

        $insertStmt->execute([
            ':title' => $position['title'],
            ':description' => $position['description'],
            ':requirements' => $position['requirements'],
            ':salary_range' => $position['salary_range'],
            ':salary_grade' => $position['salary_grade'],
            ':career_service_eligibilities' => $position['career_service_eligibilities'],
            ':location' => 'San Juan, Aborlan, Palawan',
            ':employment_type' => 'full-time',
            ':deadline' => '2026-12-31',
            ':created_by' => $createdBy,
        ]);

        $inserted++;
        echo "Created: {$position['title']}\n";
    }

    $total = $pdo->query('SELECT COUNT(*) FROM positions')->fetchColumn();
    echo "\nDone. Inserted: {$inserted}, Skipped: {$skipped}, Total positions in DB: {$total}\n";
} catch (Exception $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
