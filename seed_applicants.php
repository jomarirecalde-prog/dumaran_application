<?php
/**
 * Seed 10 sample applicant accounts with applications.
 * Run once: php seed_applicants.php
 *
 * Default password for all sample accounts: password
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/models.php';

const SAMPLE_APPLICANT_PASSWORD = 'password';

$applicants = [
    [
        'username' => 'maria.santos',
        'email' => 'maria.santos.sample@dumaran.test',
        'full_name' => 'Maria Elena Santos',
        'phone' => '09171234501',
        'address' => 'Poblacion, Dumaran, Palawan',
        'age' => 28,
        'sex' => 'Female',
        'civil_status' => 'Single',
        'highest_educational_attainment' => 'College Graduate',
        'application_type' => 'ENTRY LEVEL',
        'career_service_eligibilities' => 'Professional Eligibility',
        'position_title' => 'LGU Extension Specialist I SG 16',
        'status' => 'pending',
        'total_experience_years' => 2.0,
    ],
    [
        'username' => 'juan.delacruz',
        'email' => 'juan.delacruz.sample@dumaran.test',
        'full_name' => 'Juan Miguel Dela Cruz',
        'phone' => '09181234502',
        'address' => 'Bgy. San Jose, Dumaran, Palawan',
        'age' => 32,
        'sex' => 'Male',
        'civil_status' => 'Married',
        'highest_educational_attainment' => 'College Graduate',
        'application_type' => 'PROMOTION',
        'career_service_eligibilities' => 'Professional Eligibility',
        'position_title' => 'LGU Extension Specialist III SG 20',
        'status' => 'approved',
        'total_experience_years' => 6.5,
    ],
    [
        'username' => 'ana.reyes',
        'email' => 'ana.reyes.sample@dumaran.test',
        'full_name' => 'Ana Patricia Reyes',
        'phone' => '09201234503',
        'address' => 'Bgy. Santa Maria, Dumaran, Palawan',
        'age' => 24,
        'sex' => 'Female',
        'civil_status' => 'Single',
        'highest_educational_attainment' => 'College Graduate',
        'application_type' => 'ENTRY LEVEL',
        'career_service_eligibilities' => 'Sub-Professional Eligibility',
        'position_title' => 'Administrative Assistant III SG 9',
        'status' => 'pending',
        'total_experience_years' => 1.0,
    ],
    [
        'username' => 'carlo.garcia',
        'email' => 'carlo.garcia.sample@dumaran.test',
        'full_name' => 'Carlo Antonio Garcia',
        'phone' => '09351234504',
        'address' => 'Bgy. Ilian, Dumaran, Palawan',
        'age' => 35,
        'sex' => 'Male',
        'civil_status' => 'Married',
        'highest_educational_attainment' => 'Master\'s Degree Holder',
        'application_type' => 'PROMOTION',
        'career_service_eligibilities' => 'Professional Eligibility',
        'position_title' => 'LGU Extension Specialist IV SG 22',
        'status' => 'rejected',
        'total_experience_years' => 9.0,
    ],
    [
        'username' => 'liza.mendoza',
        'email' => 'liza.mendoza.sample@dumaran.test',
        'full_name' => 'Liza Mae Mendoza',
        'phone' => '09461234505',
        'address' => 'Poblacion, Dumaran, Palawan',
        'age' => 26,
        'sex' => 'Female',
        'civil_status' => 'Single',
        'highest_educational_attainment' => 'College Graduate',
        'application_type' => 'ENTRY LEVEL',
        'career_service_eligibilities' => 'Professional Eligibility',
        'position_title' => 'Research Assistant SG 12',
        'status' => 'pending',
        'total_experience_years' => 3.0,
    ],
    [
        'username' => 'roberto.villanueva',
        'email' => 'roberto.villanueva.sample@dumaran.test',
        'full_name' => 'Roberto Villanueva Jr.',
        'phone' => '09551234506',
        'address' => 'Bgy. Cabayugan, Dumaran, Palawan',
        'age' => 41,
        'sex' => 'Male',
        'civil_status' => 'Married',
        'highest_educational_attainment' => 'College Graduate',
        'application_type' => 'PROMOTION',
        'career_service_eligibilities' => 'Professional Eligibility',
        'position_title' => 'LGU Extension Specialist II SG 18',
        'status' => 'approved',
        'total_experience_years' => 12.0,
    ],
    [
        'username' => 'grace.torres',
        'email' => 'grace.torres.sample@dumaran.test',
        'full_name' => 'Grace Ann Torres',
        'phone' => '09671234507',
        'address' => 'Bgy. Danleg, Dumaran, Palawan',
        'age' => 22,
        'sex' => 'Female',
        'civil_status' => 'Single',
        'highest_educational_attainment' => 'Secondary/ High School Graduate',
        'application_type' => 'ENTRY LEVEL',
        'career_service_eligibilities' => 'Sub-Professional Eligibility',
        'position_title' => 'Administrative Aide VI (Clerk III) SG 6',
        'status' => 'pending',
        'total_experience_years' => 0.5,
    ],
    [
        'username' => 'mark.fernandez',
        'email' => 'mark.fernandez.sample@dumaran.test',
        'full_name' => 'Mark Lawrence Fernandez',
        'phone' => '09781234508',
        'address' => 'Poblacion, Dumaran, Palawan',
        'age' => 29,
        'sex' => 'Male',
        'civil_status' => 'Single',
        'highest_educational_attainment' => 'College Graduate',
        'application_type' => 'ENTRY LEVEL',
        'career_service_eligibilities' => 'Professional Eligibility',
        'position_title' => 'IT Support Staff SG 9',
        'status' => 'pending',
        'total_experience_years' => 4.0,
    ],
    [
        'username' => 'sophia.ramos',
        'email' => 'sophia.ramos.sample@dumaran.test',
        'full_name' => 'Sophia Isabel Ramos',
        'phone' => '09991234509',
        'address' => 'Bgy. Bacao, Dumaran, Palawan',
        'age' => 30,
        'sex' => 'Female',
        'civil_status' => 'Married',
        'highest_educational_attainment' => 'College Graduate',
        'application_type' => 'ENTRY LEVEL',
        'career_service_eligibilities' => 'Bar/Board Eligibility (RA 1080)',
        'position_title' => 'Guidance Counselor SG 18',
        'status' => 'rejected',
        'total_experience_years' => 5.0,
    ],
    [
        'username' => 'diego.navarro',
        'email' => 'diego.navarro.sample@dumaran.test',
        'full_name' => 'Diego Navarro',
        'phone' => '09191234510',
        'address' => 'Bgy. Santa Teresita, Dumaran, Palawan',
        'age' => 27,
        'sex' => 'Male',
        'civil_status' => 'Single',
        'highest_educational_attainment' => 'College Level',
        'application_type' => 'ENTRY LEVEL',
        'career_service_eligibilities' => 'Sub-Professional Eligibility',
        'position_title' => 'Library Aide SG 4',
        'status' => 'pending',
        'total_experience_years' => 1.5,
    ],
];

try {
    $userModel = new User();
    $applicationModel = new Application();
    $pdo = $userModel->getConnection();

    $positionStmt = $pdo->prepare('SELECT id FROM positions WHERE title = ? LIMIT 1');
    $fallbackPositionId = (int) $pdo->query('SELECT id FROM positions WHERE is_active = 1 ORDER BY id LIMIT 1')->fetchColumn();

    if (!$fallbackPositionId) {
        fwrite(STDERR, "No positions found. Run php seed_positions.php first.\n");
        exit(1);
    }

    $createdUsers = 0;
    $skippedUsers = 0;
    $createdApps = 0;
    $skippedApps = 0;

    foreach ($applicants as $row) {
        $existing = $userModel->findByEmail($row['email']);
        if ($existing) {
            $skippedUsers++;
            $userId = (int) $existing['id'];
            echo "Skipped user (exists): {$row['full_name']} ({$row['email']})\n";
        } else {
            $ok = $userModel->create([
                'username' => $row['username'],
                'email' => $row['email'],
                'password' => SAMPLE_APPLICANT_PASSWORD,
                'role' => 'applicant',
                'full_name' => $row['full_name'],
                'phone' => $row['phone'],
                'address' => $row['address'],
                'age' => $row['age'],
                'sex' => $row['sex'],
            ]);
            if (!$ok) {
                echo "Failed to create user: {$row['email']}\n";
                continue;
            }
            $userId = (int) $pdo->lastInsertId();
            $createdUsers++;

            $userModel->update($userId, [
                'civil_status' => $row['civil_status'],
                'highest_educational_attainment' => $row['highest_educational_attainment'],
                'application_type' => $row['application_type'],
                'career_service_eligibilities' => $row['career_service_eligibilities'],
                'email_verified_at' => date('Y-m-d H:i:s'),
                'data_privacy_accepted_at' => date('Y-m-d H:i:s'),
            ]);

            echo "Created user: {$row['full_name']} ({$row['email']})\n";
        }

        $positionStmt->execute([$row['position_title']]);
        $position = $positionStmt->fetch();
        $positionId = $position ? (int) $position['id'] : $fallbackPositionId;

        $appCheck = $pdo->prepare('SELECT id, status FROM applications WHERE user_id = ? AND position_id = ? LIMIT 1');
        $appCheck->execute([$userId, $positionId]);
        $existingApp = $appCheck->fetch();
        if ($existingApp) {
            $appId = (int) $existingApp['id'];
            if ($existingApp['status'] !== $row['status']) {
                $applicationModel->update($appId, ['status' => $row['status']]);
                echo "  → Updated application status: {$row['position_title']} ({$row['status']})\n";
            } else {
                $skippedApps++;
            }
            continue;
        }

        $applicationModel->create([
            'user_id' => $userId,
            'position_id' => $positionId,
            'cover_letter' => 'Sample application for testing the Dumaran Hiring System.',
            'total_experience_years' => $row['total_experience_years'],
            'meets_requirements' => 1,
        ]);

        $appId = (int) $pdo->lastInsertId();
        if ($row['status'] !== 'pending') {
            $applicationModel->update($appId, ['status' => $row['status']]);
        }

        $createdApps++;
        echo "  → Application: {$row['position_title']} ({$row['status']})\n";
    }

    $totalApplicants = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'applicant'")->fetchColumn();
    echo "\nDone. Users created: {$createdUsers}, skipped: {$skippedUsers}.\n";
    echo "Applications created: {$createdApps}, skipped: {$skippedApps}.\n";
    echo "Total applicants in DB: {$totalApplicants}\n";
    echo "Login password for all sample accounts: " . SAMPLE_APPLICANT_PASSWORD . "\n";
} catch (Exception $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
