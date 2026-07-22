<?php
/**
 * API Documentation
 * 
 * This file provides an overview of available API endpoints
 */

require_once __DIR__ . '/../config/config.php';

$apiDocs = [
    'version' => '1.0.0',
    'base_url' => BASE_URL . 'api/',
    
    'authentication' => [
        'type' => 'Session-based',
        'description' => 'User must be logged in. Session cookie is used for authentication.',
        'csrf_protection' => 'CSRF token required for POST, PUT, PATCH, DELETE requests'
    ],
    
    'endpoints' => [
        'users' => [
            'base' => 'users.php',
            'methods' => [
                [
                    'method' => 'GET',
                    'action' => 'index',
                    'auth' => 'admin',
                    'description' => 'Get all users',
                    'params' => ['page', 'per_page', 'role', 'is_active', 'search']
                ],
                [
                    'method' => 'GET',
                    'action' => 'profile',
                    'auth' => 'user',
                    'description' => 'Get user profile',
                    'params' => ['user_id (optional)']
                ],
                [
                    'method' => 'GET',
                    'action' => 'activity',
                    'auth' => 'user',
                    'description' => 'Get user activity logs',
                    'params' => ['user_id (optional)', 'limit']
                ],
                [
                    'method' => 'POST',
                    'action' => 'register',
                    'auth' => 'none',
                    'description' => 'Register new user',
                    'params' => ['username', 'email', 'password', 'full_name', 'phone', 'address']
                ],
                [
                    'method' => 'PUT',
                    'action' => 'update-profile',
                    'auth' => 'user',
                    'description' => 'Update user profile',
                    'params' => ['user_id (optional)', 'full_name', 'phone', 'address', 'etc.']
                ],
                [
                    'method' => 'PUT',
                    'action' => 'change-password',
                    'auth' => 'user',
                    'description' => 'Change user password',
                    'params' => ['current_password', 'new_password']
                ],
                [
                    'method' => 'PUT',
                    'action' => 'toggle-status',
                    'auth' => 'admin',
                    'description' => 'Toggle user active status',
                    'params' => ['user_id']
                ]
            ]
        ],
        
        'positions' => [
            'base' => 'positions.php',
            'methods' => [
                [
                    'method' => 'GET',
                    'action' => 'index',
                    'auth' => 'user',
                    'description' => 'Get all positions',
                    'params' => ['page', 'per_page', 'is_active', 'employment_type', 'search']
                ],
                [
                    'method' => 'GET',
                    'action' => 'show',
                    'auth' => 'none',
                    'description' => 'Get single position',
                    'params' => ['id']
                ],
                [
                    'method' => 'GET',
                    'action' => 'active',
                    'auth' => 'none',
                    'description' => 'Get active positions',
                    'params' => []
                ],
                [
                    'method' => 'GET',
                    'action' => 'stats',
                    'auth' => 'admin',
                    'description' => 'Get position statistics',
                    'params' => ['position_id (optional)']
                ],
                [
                    'method' => 'POST',
                    'action' => 'create',
                    'auth' => 'admin',
                    'description' => 'Create new position',
                    'params' => ['title', 'description', 'requirements', 'min_experience_years', 'max_applications', 'salary_range', 'location', 'employment_type', 'deadline']
                ],
                [
                    'method' => 'PUT',
                    'action' => 'update',
                    'auth' => 'admin',
                    'description' => 'Update position',
                    'params' => ['id', '...fields to update']
                ],
                [
                    'method' => 'PUT',
                    'action' => 'toggle-status',
                    'auth' => 'admin',
                    'description' => 'Toggle position active status',
                    'params' => ['id']
                ],
                [
                    'method' => 'DELETE',
                    'action' => 'delete',
                    'auth' => 'admin',
                    'description' => 'Delete position',
                    'params' => ['id']
                ]
            ]
        ],
        
        'applications' => [
            'base' => 'applications.php',
            'methods' => [
                [
                    'method' => 'GET',
                    'action' => 'index',
                    'auth' => 'user',
                    'description' => 'Get applications (all for admin, own for users)',
                    'params' => ['page', 'per_page', 'status', 'position_id', 'search']
                ],
                [
                    'method' => 'GET',
                    'action' => 'show',
                    'auth' => 'user',
                    'description' => 'Get single application',
                    'params' => ['id']
                ],
                [
                    'method' => 'GET',
                    'action' => 'stats',
                    'auth' => 'user',
                    'description' => 'Get application statistics',
                    'params' => ['position_id (optional)']
                ],
                [
                    'method' => 'POST',
                    'action' => 'submit',
                    'auth' => 'user',
                    'description' => 'Submit new application',
                    'params' => ['position_id', 'cover_letter', 'resume (file)']
                ],
                [
                    'method' => 'PUT',
                    'action' => 'update-status',
                    'auth' => 'admin',
                    'description' => 'Update application status',
                    'params' => ['id', 'status']
                ],
                [
                    'method' => 'DELETE',
                    'action' => 'delete',
                    'auth' => 'user/admin',
                    'description' => 'Delete application',
                    'params' => ['id']
                ]
            ]
        ]
    ],
    
    'response_format' => [
        'success' => [
            'success' => true,
            'message' => 'Success message',
            'data' => '... response data ...',
            'timestamp' => '2025-11-09 12:00:00'
        ],
        'error' => [
            'success' => false,
            'message' => 'Error message',
            'errors' => '... validation errors (optional) ...',
            'timestamp' => '2025-11-09 12:00:00'
        ],
        'paginated' => [
            'success' => true,
            'message' => 'Success message',
            'data' => [
                'items' => '... array of items ...',
                'pagination' => [
                    'total' => 100,
                    'per_page' => 10,
                    'current_page' => 1,
                    'total_pages' => 10,
                    'has_more' => true
                ]
            ],
            'timestamp' => '2025-11-09 12:00:00'
        ]
    ],
    
    'status_codes' => [
        200 => 'Success',
        201 => 'Created',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        422 => 'Validation Error',
        500 => 'Internal Server Error'
    ]
];

// Output as JSON if requested via API
if (isset($_GET['format']) && $_GET['format'] === 'json') {
    header('Content-Type: application/json');
    echo json_encode($apiDocs, JSON_PRETTY_PRINT);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Documentation - WPU Applicant System</title>
    <link href="<?= BASE_URL ?>assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f3f4f6;
            padding: 40px 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 40px;
        }
        h1 {
            color: #1f2937;
            margin-bottom: 12px;
            font-size: 2rem;
        }
        .version {
            color: #6b7280;
            font-size: 0.875rem;
            margin-bottom: 32px;
        }
        h2 {
            color: #1f2937;
            margin: 32px 0 16px 0;
            font-size: 1.5rem;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 8px;
        }
        h3 {
            color: #374151;
            margin: 24px 0 12px 0;
            font-size: 1.25rem;
        }
        .endpoint {
            background: #f9fafb;
            border-left: 4px solid #2563eb;
            padding: 16px;
            margin: 16px 0;
            border-radius: 6px;
        }
        .method {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.875rem;
            margin-right: 8px;
        }
        .method.get { background: #10b981; color: white; }
        .method.post { background: #f59e0b; color: white; }
        .method.put { background: #2563eb; color: white; }
        .method.delete { background: #ef4444; color: white; }
        .auth {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            background: #e5e7eb;
            color: #374151;
            font-size: 0.875rem;
            margin-left: 8px;
        }
        .params {
            margin-top: 8px;
            padding-left: 20px;
        }
        code {
            background: #1f2937;
            color: #10b981;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.875rem;
        }
        pre {
            background: #1f2937;
            color: #e5e7eb;
            padding: 16px;
            border-radius: 6px;
            overflow-x: auto;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-code"></i> API Documentation</h1>
        <div class="version">Version <?= $apiDocs['version'] ?></div>
        
        <p style="color: #6b7280; line-height: 1.6; margin-bottom: 24px;">
            This documentation provides information about the RESTful API endpoints available in the WPU Applicant System.
            All endpoints return JSON responses.
        </p>

        <h2>Base URL</h2>
        <code><?= $apiDocs['base_url'] ?></code>

        <h2>Authentication</h2>
        <div style="background: #fef3c7; border-left: 4px solid #f59e0b; padding: 16px; margin: 16px 0; border-radius: 6px;">
            <strong>Type:</strong> <?= $apiDocs['authentication']['type'] ?><br>
            <strong>CSRF Protection:</strong> <?= $apiDocs['authentication']['csrf_protection'] ?>
        </div>

        <?php foreach ($apiDocs['endpoints'] as $resource => $info): ?>
            <h2><?= ucfirst($resource) ?></h2>
            <p style="color: #6b7280; margin-bottom: 16px;">
                Base endpoint: <code><?= $info['base'] ?></code>
            </p>

            <?php foreach ($info['methods'] as $endpoint): ?>
                <div class="endpoint">
                    <div>
                        <span class="method <?= strtolower($endpoint['method']) ?>">
                            <?= $endpoint['method'] ?>
                        </span>
                        <code><?= $info['base'] ?>?action=<?= $endpoint['action'] ?></code>
                        <span class="auth"><i class="fas fa-lock"></i> <?= ucfirst($endpoint['auth']) ?></span>
                    </div>
                    <p style="margin: 12px 0 8px 0; color: #374151;">
                        <?= $endpoint['description'] ?>
                    </p>
                    <?php if (!empty($endpoint['params'])): ?>
                        <div class="params">
                            <strong style="font-size: 0.875rem; color: #6b7280;">Parameters:</strong>
                            <ul style="margin-top: 4px; color: #6b7280; font-size: 0.875rem;">
                                <?php foreach ($endpoint['params'] as $param): ?>
                                    <li><?= $param ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>

        <h2>Response Format</h2>
        
        <h3>Success Response</h3>
        <pre><?= json_encode($apiDocs['response_format']['success'], JSON_PRETTY_PRINT) ?></pre>

        <h3>Error Response</h3>
        <pre><?= json_encode($apiDocs['response_format']['error'], JSON_PRETTY_PRINT) ?></pre>

        <h3>Paginated Response</h3>
        <pre><?= json_encode($apiDocs['response_format']['paginated'], JSON_PRETTY_PRINT) ?></pre>

        <h2>HTTP Status Codes</h2>
        <?php foreach ($apiDocs['status_codes'] as $code => $description): ?>
            <div style="padding: 8px 0; border-bottom: 1px solid #e5e7eb;">
                <code><?= $code ?></code> - <?= $description ?>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>
