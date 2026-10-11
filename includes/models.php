<?php
/**
 * Database Models for Dumaran Hiring System
 */

require_once __DIR__ . '/../config/database.php';
if (!function_exists('normalizeUserNameFields')) {
    require_once __DIR__ . '/../config/config.php';
}

class BaseModel {
    protected $db;
    protected $table;

    public function __construct() {
        try {
            $database = new Database();
            $this->db = $database->getConnection();
            if ($this->db === null) {
                throw new Exception("Database connection is null. Please check your database configuration.");
            }
        } catch (\Exception $e) {
            error_log("BaseModel constructor error: " . $e->getMessage());
            throw $e;
        }
    }

    public function getConnection() {
        return $this->db;
    }

    public function findById($id) {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function findAll($where = '', $params = []) {
        $sql = "SELECT * FROM {$this->table}";
        if ($where) {
            $sql .= " WHERE {$where}";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Count rows matching an optional WHERE clause (avoids loading full result sets).
     */
    public function count($where = '', $params = []) {
        $sql = "SELECT COUNT(*) AS cnt FROM {$this->table}";
        if ($where !== '') {
            $sql .= " WHERE {$where}";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Fetch multiple rows keyed by primary key id.
     *
     * @return array<int, array>
     */
    public function findByIds(array $ids) {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id IN ({$placeholders})");
        $stmt->execute($ids);
        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(int) $row['id']] = $row;
        }
        return $map;
    }

    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }
}

class User extends BaseModel {
    protected $table = 'users';

    public function findByEmail($email) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    public function findByGoogleId($googleId) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE google_id = ? AND oauth_provider = 'google'");
        $stmt->execute([$googleId]);
        return $stmt->fetch();
    }

    public function findByUsername($username) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch();
    }

    public function create($data) {
        normalizeUserNameFields($data);

        $sql = "INSERT INTO users (username, email, password, role, full_name, last_name, first_name, middle_name, phone, address, age, sex, google_id, oauth_provider) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        
        $params = [
            $data['username'],
            $data['email'],
            isset($data['password']) ? password_hash($data['password'], PASSWORD_DEFAULT) : null,
            $data['role'] ?? 'applicant',
            $data['full_name'],
            $data['last_name'] ?? null,
            $data['first_name'] ?? null,
            $data['middle_name'] ?? null,
            $data['phone'] ?? null,
            $data['address'] ?? null,
            $data['age'] ?? null,
            $data['sex'] ?? null,
            $data['google_id'] ?? null,
            $data['oauth_provider'] ?? null
        ];
        
        $result = $stmt->execute($params);
        
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            error_log("User::create() Failed: " . print_r($errorInfo, true));
            error_log("User::create() SQL: " . $sql);
            error_log("User::create() Params: " . print_r($params, true));
        } else {
            error_log("User::create() Success - New user created with email: " . $data['email']);
        }
        
        return $result;
    }

    public function update($id, $data) {
        if (isset($data['last_name']) || isset($data['first_name']) || isset($data['middle_name']) || isset($data['full_name'])) {
            normalizeUserNameFields($data);
        }

        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            if ($key !== 'id' && $key !== 'password') {
                $fields[] = "{$key} = ?";
                // PDO handles NULL values correctly when passed directly
                $values[] = $value === '' ? null : $value; // Convert empty string to null for consistency
            }
        }
        
        if (isset($data['password']) && !empty($data['password'])) {
            $fields[] = "password = ?";
            $values[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        if (empty($fields)) {
            error_log("User::update() - No fields to update for user ID: $id");
            return false;
        }
        
        $values[] = $id;
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        
        // Debug logging
        error_log("User::update() SQL: " . $sql);
        error_log("User::update() Fields: " . implode(', ', array_keys(array_diff_key($data, ['id' => '', 'password' => '']))));
        if (isset($data['ipcr_file_path'])) {
            error_log("User::update() ipcr_file_path value: " . var_export($data['ipcr_file_path'], true) . " (is_null: " . (is_null($data['ipcr_file_path']) ? 'YES' : 'NO') . ")");
        }
        if (isset($data['pe_file_path'])) {
            error_log("User::update() pe_file_path value: " . var_export($data['pe_file_path'], true) . " (is_null: " . (is_null($data['pe_file_path']) ? 'YES' : 'NO') . ")");
        }
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($values);
        
        if (!$result) {
            $errorInfo = $stmt->errorInfo();
            error_log("User::update() Failed: " . print_r($errorInfo, true));
        } else {
            error_log("User::update() Success for user ID: $id");
            // Log affected rows
            $affectedRows = $stmt->rowCount();
            error_log("User::update() Affected rows: " . $affectedRows);
        }
        
        return $result;
    }

    public function verifyPassword($email, $password) {
        $user = $this->findByEmail($email);
        if ($user && $user['password'] && password_verify($password, $user['password'])) {
            return $user;
        }
        return false;
    }

    public function createGoogleUser($googleUserData) {
        // Generate a unique username from email
        $emailParts = explode('@', $googleUserData['email']);
        $baseUsername = $emailParts[0];
        $username = $baseUsername;
        $counter = 1;
        
        // Ensure username is unique
        while ($this->findByUsername($username)) {
            $username = $baseUsername . $counter;
            $counter++;
        }

        $userData = [
            'username' => $username,
            'email' => $googleUserData['email'],
            'password' => null, // No password for OAuth users
            'role' => ROLE_APPLICANT,
            'full_name' => $googleUserData['name'],
            'phone' => null,
            'address' => null,
            'google_id' => $googleUserData['id'],
            'oauth_provider' => 'google'
        ];

        if ($this->create($userData)) {
            return $this->findByGoogleId($googleUserData['id']);
        }
        
        return false;
    }

    public function getTotalExperience($userId) {
        $stmt = $this->db->prepare("
            SELECT SUM(years_duration) as total_years 
            FROM user_experience 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return $result['total_years'] ?? 0;
    }

    public function logActivity($userId, $action, $description, $ipAddress = null, $userAgent = null) {
        $stmt = $this->db->prepare("
            INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $userId,
            $action,
            $description,
            $ipAddress ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $userAgent ?? $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    }

    /**
     * Get all system activity logs with user information
     */
    public function getAllActivityLogs($limit = 100, $offset = 0, $filters = []) {
        $where = [];
        $params = [];
        
        // Filter by role if provided
        if (!empty($filters['role'])) {
            $where[] = "u.role = ?";
            $params[] = $filters['role'];
        }
        
        // Filter by action if provided
        if (!empty($filters['action'])) {
            $where[] = "al.action LIKE ?";
            $params[] = '%' . $filters['action'] . '%';
        }
        
        // Filter by user ID if provided
        if (!empty($filters['user_id'])) {
            $where[] = "al.user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        // Filter by date range if provided
        if (!empty($filters['date_from'])) {
            $where[] = "DATE(al.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "DATE(al.created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        // Cast limit and offset to integers for the query
        $limit = (int)$limit;
        $offset = (int)$offset;
        
        $sql = "
            SELECT 
                al.*,
                u.full_name,
                u.email,
                u.username,
                u.role
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            {$whereClause}
            ORDER BY al.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Get total count of activity logs
     */
    public function getActivityLogsCount($filters = []) {
        $where = [];
        $params = [];
        
        // Apply same filters as getAllActivityLogs
        if (!empty($filters['role'])) {
            $where[] = "u.role = ?";
            $params[] = $filters['role'];
        }
        
        if (!empty($filters['action'])) {
            $where[] = "al.action LIKE ?";
            $params[] = '%' . $filters['action'] . '%';
        }
        
        if (!empty($filters['user_id'])) {
            $where[] = "al.user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = "DATE(al.created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "DATE(al.created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        $sql = "
            SELECT COUNT(*) as total
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            {$whereClause}
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result['total'] ?? 0;
    }

    public function setEmailVerificationCode($userId, $code, $expiresAt) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET email_verification_code = ?, 
                email_verification_expires_at = ?, 
                email_verification_last_sent_at = NOW(),
                email_verification_attempts = 0
            WHERE id = ?
        ");
        return $stmt->execute([$code, $expiresAt, $userId]);
    }

    public function markEmailVerified($userId) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET email_verified_at = NOW(),
                email_verification_code = NULL,
                email_verification_expires_at = NULL,
                email_verification_attempts = 0
            WHERE id = ?
        ");
        return $stmt->execute([$userId]);
    }

    public function clearEmailVerificationCode($userId) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET email_verification_code = NULL,
                email_verification_expires_at = NULL,
                email_verification_attempts = 0
            WHERE id = ?
        ");
        return $stmt->execute([$userId]);
    }

    public function incrementEmailVerificationAttempts($userId) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET email_verification_attempts = email_verification_attempts + 1
            WHERE id = ?
        ");
        return $stmt->execute([$userId]);
    }
}

class Position extends BaseModel {
    protected $table = 'positions';

    public function create($data) {
        $sql = "INSERT INTO positions (title, description, requirements, min_experience_years, 
                max_applications, salary_range, salary_grade, training_hours, career_service_eligibilities, 
                location, employment_type, deadline, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['title'],
            $data['description'],
            $data['requirements'],
            $data['min_experience_years'] ?? 0,
            $data['max_applications'] ?? null,
            $data['salary_range'] ?? null,
            $data['salary_grade'] ?? null,
            $data['training_hours'] ?? null,
            $data['career_service_eligibilities'] ?? null,
            $data['location'] ?? null,
            $data['employment_type'] ?? 'full-time',
            $data['deadline'] ?? null,
            $data['created_by']
        ]);
    }

    public function update($id, $data) {
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            if ($key !== 'id') {
                $fields[] = "{$key} = ?";
                $values[] = $value;
            }
        }
        
        $values[] = $id;
        $sql = "UPDATE positions SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    public function getActivePositions() {
        return $this->findAll('is_active = 1 AND (deadline IS NULL OR deadline >= CURDATE())');
    }

    public function getApplicationCount($positionId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM applications WHERE position_id = ?");
        $stmt->execute([$positionId]);
        $result = $stmt->fetch();
        return (int) $result['count'];
    }

    /**
     * Application counts for many positions in one query.
     *
     * @param int[] $positionIds
     * @return array<int, int> position_id => count
     */
    public function getApplicationCountsByPositionIds(array $positionIds) {
        $positionIds = array_values(array_unique(array_filter(array_map('intval', $positionIds))));
        if ($positionIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($positionIds), '?'));
        $sql = "SELECT position_id, COUNT(*) AS cnt FROM applications
                WHERE position_id IN ({$placeholders})
                GROUP BY position_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($positionIds);
        $counts = array_fill_keys($positionIds, 0);
        foreach ($stmt->fetchAll() as $row) {
            $counts[(int) $row['position_id']] = (int) $row['cnt'];
        }
        return $counts;
    }
}

class EmploymentType extends BaseModel {
    protected $table = 'employment_types';

    public function create($data) {
        $sql = "INSERT INTO employment_types (name, slug, is_active) VALUES (?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['name'],
            $data['slug'] ?? $this->generateSlug($data['name']),
            $data['is_active'] ?? 1
        ]);
    }

    public function update($id, $data) {
        $fields = [];
        $values = [];
        
        foreach ($data as $key => $value) {
            if ($key !== 'id') {
                if ($key === 'name' && !isset($data['slug'])) {
                    // Auto-generate slug if name is changed
                    $fields[] = "slug = ?";
                    $values[] = $this->generateSlug($value);
                }
                $fields[] = "{$key} = ?";
                $values[] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $values[] = $id;
        $sql = "UPDATE employment_types SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    public function getActiveTypes() {
        return $this->findAll('is_active = 1 ORDER BY name ASC');
    }

    public function findBySlug($slug) {
        $stmt = $this->db->prepare("SELECT * FROM employment_types WHERE slug = ?");
        $stmt->execute([$slug]);
        return $stmt->fetch();
    }

    public function isUsedInPositions($employmentTypeId) {
        // Get the employment type to find its slug
        $type = $this->findById($employmentTypeId);
        if (!$type) {
            return false;
        }
        
        // Check if any positions are using this employment type
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM positions WHERE employment_type = ?");
        $stmt->execute([$type['slug']]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    }

    private function generateSlug($name) {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        
        // Ensure uniqueness
        $originalSlug = $slug;
        $counter = 1;
        while ($this->findBySlug($slug)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
}

class Application extends BaseModel {
    protected $table = 'applications';

    /** @var bool|null Cached schema check for reviewed_at column */
    private static $hasReviewedAtColumn = null;

    private function invalidateAdminDashboardCache() {
        try {
            require_once __DIR__ . '/services/Cache.php';
            (new Cache())->delete('admin_dashboard_summary_counts');
        } catch (Throwable $e) {
            error_log('Dashboard cache invalidation skipped: ' . $e->getMessage());
        }
    }

    private function applicationsHasReviewedAtColumn() {
        if (self::$hasReviewedAtColumn !== null) {
            return self::$hasReviewedAtColumn;
        }
        try {
            $checkStmt = $this->db->prepare("SHOW COLUMNS FROM applications LIKE 'reviewed_at'");
            $checkStmt->execute();
            self::$hasReviewedAtColumn = $checkStmt->rowCount() > 0;
        } catch (PDOException $e) {
            self::$hasReviewedAtColumn = false;
        }
        return self::$hasReviewedAtColumn;
    }

    public function create($data) {
        $sql = "INSERT INTO applications (user_id, position_id, cover_letter, resume_file, 
                total_experience_years, meets_requirements) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $ok = $stmt->execute([
            $data['user_id'],
            $data['position_id'],
            $data['cover_letter'] ?? null,
            $data['resume_file'] ?? null,
            $data['total_experience_years'] ?? 0,
            $data['meets_requirements'] ?? false
        ]);
        if ($ok) {
            $this->invalidateAdminDashboardCache();
        }
        return $ok;
    }

    public function update($id, $data) {
        $fields = [];
        $values = [];
        
        // Check if status is being updated to approved or rejected
        if (isset($data['status']) && in_array($data['status'], [STATUS_APPROVED, STATUS_REJECTED])) {
            if ($this->applicationsHasReviewedAtColumn()) {
                $data['reviewed_at'] = date('Y-m-d H:i:s');
            }
        }
        
        foreach ($data as $key => $value) {
            if ($key !== 'id') {
                $fields[] = "{$key} = ?";
                $values[] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $values[] = $id;
        $sql = "UPDATE applications SET " . implode(', ', $fields) . " WHERE id = ?";
        
        try {
            $stmt = $this->db->prepare($sql);
            $ok = $stmt->execute($values);
            if ($ok) {
                $this->invalidateAdminDashboardCache();
            }
            return $ok;
        } catch (PDOException $e) {
            error_log("Application update failed: " . $e->getMessage());
            error_log("SQL: " . $sql);
            error_log("Values: " . print_r($values, true));
            return false;
        }
    }

    public function findByUserAndPosition($userId, $positionId) {
        $stmt = $this->db->prepare("SELECT * FROM applications WHERE user_id = ? AND position_id = ?");
        $stmt->execute([$userId, $positionId]);
        return $stmt->fetch();
    }

    public function getApplicationsWithDetails($where = '', $params = [], $includeArchived = false, $limit = null, $offset = null) {
        $sql = "SELECT a.*, u.full_name as applicant_name, u.email as applicant_email, 
                       p.title as position_title, p.min_experience_years
                FROM applications a
                JOIN users u ON a.user_id = u.id
                JOIN positions p ON a.position_id = p.id";
        
        $whereClause = [];
        $whereParams = [];
        
        // Exclude archived applications by default
        if (!$includeArchived) {
            $whereClause[] = "(a.is_archived = 0 OR a.is_archived IS NULL)";
        }
        
        // Add custom where clause if provided
        if (!empty($where)) {
            $whereClause[] = $where;
            $whereParams = array_merge($whereParams, $params);
        }
        
        if (!empty($whereClause)) {
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }
        
        $sql .= " ORDER BY a.applied_at DESC";

        if ($limit !== null) {
            $sql .= " LIMIT " . (int) $limit;
            if ($offset !== null) {
                $sql .= " OFFSET " . (int) $offset;
            }
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($whereParams);
        return $stmt->fetchAll();
    }

    /**
     * Admin list filters with server-side pagination (same joins as getApplicationsWithDetails).
     */
    public function getAdminApplicationsList(array $filters, $limit, $offset) {
        [$whereSql, $params] = $this->buildAdminApplicationsFilterSql($filters);
        return $this->getApplicationsWithDetails($whereSql, $params, !empty($filters['include_archived']), (int) $limit, (int) $offset);
    }

    public function countAdminApplicationsList(array $filters) {
        [$whereSql, $params] = $this->buildAdminApplicationsFilterSql($filters);
        $sql = "SELECT COUNT(*) AS cnt
                FROM applications a
                JOIN users u ON a.user_id = u.id
                JOIN positions p ON a.position_id = p.id";
        $whereParts = [];
        if (empty($filters['include_archived'])) {
            $whereParts[] = "(a.is_archived = 0 OR a.is_archived IS NULL)";
        }
        if ($whereSql !== '') {
            $whereParts[] = $whereSql;
        }
        if ($whereParts !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $whereParts);
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Lightweight rows for ATS dashboard metrics (single query, no per-row validation queries).
     */
    public function getAdminApplicationMetricRows($includeArchived = true) {
        $sql = "SELECT a.id, a.user_id, a.position_id, a.status, a.is_archived, a.applied_at,
                       a.reviewed_at, a.updated_at, a.interview_date, a.meets_requirements,
                       a.total_experience_years,
                       u.full_name AS applicant_name, u.career_service_eligibilities, u.training_programs,
                       p.title AS position_title, p.min_experience_years,
                       p.career_service_eligibilities AS position_cse,
                       p.training_hours, p.location AS position_location
                FROM applications a
                JOIN users u ON a.user_id = u.id
                JOIN positions p ON a.position_id = p.id";
        if (!$includeArchived) {
            $sql .= " WHERE (a.is_archived = 0 OR a.is_archived IS NULL)";
        }
        $sql .= " ORDER BY a.applied_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Summary counts for admin dashboard cards.
     *
     * @return array{applicants:int,active_positions:int,total_applications:int,pending_applications:int}
     */
    public function getAdminDashboardSummaryCounts() {
        $stmt = $this->db->query("
            SELECT
                (SELECT COUNT(*) FROM users WHERE role = 'applicant') AS applicants,
                (SELECT COUNT(*) FROM positions WHERE is_active = 1 AND (deadline IS NULL OR deadline >= CURDATE())) AS active_positions,
                (SELECT COUNT(*) FROM applications WHERE (is_archived = 0 OR is_archived IS NULL)) AS total_applications,
                (SELECT COUNT(*) FROM applications WHERE status = 'pending' AND (is_archived = 0 OR is_archived IS NULL)) AS pending_applications
        ");
        $row = $stmt->fetch();
        return [
            'applicants' => (int) ($row['applicants'] ?? 0),
            'active_positions' => (int) ($row['active_positions'] ?? 0),
            'total_applications' => (int) ($row['total_applications'] ?? 0),
            'pending_applications' => (int) ($row['pending_applications'] ?? 0),
        ];
    }

    /**
     * @return array{0: string, 1: array}
     */
    private function buildAdminApplicationsFilterSql(array $filters) {
        $parts = [];
        $params = [];

        if (!empty($filters['include_archived'])) {
            // no default archive exclusion in WHERE fragment (handled by caller on count/list)
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $parts[] = 'a.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['position_id']) && $filters['position_id'] !== 'all') {
            $parts[] = 'a.position_id = ?';
            $params[] = (int) $filters['position_id'];
        }

        if (!empty($filters['search'])) {
            $parts[] = '(u.full_name LIKE ? OR u.email LIKE ? OR p.title LIKE ? OR a.cover_letter LIKE ?)';
            $term = '%' . $filters['search'] . '%';
            $params = array_merge($params, [$term, $term, $term, $term]);
        }

        return [implode(' AND ', $parts), $params];
    }

    public function getUserApplications($userId) {
        return $this->getApplicationsWithDetails('a.user_id = ?', [$userId]);
    }

    /**
     * Archive an application (soft delete)
     */
    public function archive($id) {
        $stmt = $this->db->prepare("UPDATE applications SET is_archived = 1, archived_at = NOW() WHERE id = ?");
        $ok = $stmt->execute([$id]);
        if ($ok) {
            $this->invalidateAdminDashboardCache();
        }
        return $ok;
    }

    /**
     * Unarchive an application
     */
    public function unarchive($id) {
        $stmt = $this->db->prepare("UPDATE applications SET is_archived = 0, archived_at = NULL WHERE id = ?");
        $ok = $stmt->execute([$id]);
        if ($ok) {
            $this->invalidateAdminDashboardCache();
        }
        return $ok;
    }

    /**
     * Get archived applications with details
     */
    public function getArchivedApplicationsWithDetails($where = '', $params = []) {
        $sql = "SELECT a.*, u.full_name as applicant_name, u.email as applicant_email, 
                       p.title as position_title, p.min_experience_years
                FROM applications a
                JOIN users u ON a.user_id = u.id
                JOIN positions p ON a.position_id = p.id
                WHERE (a.is_archived = 1)";
        
        if (!empty($where)) {
            $sql .= " AND " . $where;
        }
        
        $sql .= " ORDER BY a.archived_at DESC, a.applied_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}

class Experience extends BaseModel {
    protected $table = 'user_experience';

    /**
     * Calculate years of experience from start and end dates
     */
    private function calculateYearsDuration($startDate, $endDate = null, $isCurrent = false) {
        if (empty($startDate)) {
            return 0.0;
        }
        
        // If is_current is true or end_date is null/empty, use current date
        if ($isCurrent || empty($endDate)) {
            $end = new DateTime();
        } else {
            $end = new DateTime($endDate);
        }
        
        $start = new DateTime($startDate);
        
        if ($end < $start) {
            return 0.0;
        }
        
        $diff = $start->diff($end);
        // Calculate years with decimal precision (months converted to decimal)
        $years = $diff->y + ($diff->m / 12) + ($diff->d / 365);
        
        return round($years, 1);
    }

    public function create($data) {
        // Calculate years_duration
        $yearsDuration = $this->calculateYearsDuration(
            $data['start_date'],
            $data['end_date'] ?? null,
            $data['is_current'] ?? false
        );
        
        $sql = "INSERT INTO user_experience (user_id, company_name, position_title, 
                start_date, end_date, is_current, description, years_duration) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['user_id'],
            $data['company_name'],
            $data['position_title'],
            $data['start_date'],
            $data['end_date'] ?? null,
            $data['is_current'] ?? false,
            $data['description'] ?? null,
            $yearsDuration
        ]);
    }

    public function update($id, $data) {
        $fields = [];
        $values = [];
        
        // Check if we need to recalculate years_duration
        if (isset($data['start_date']) || isset($data['end_date']) || isset($data['is_current'])) {
            // Get the full record to calculate properly
            $existing = $this->findById($id);
            if ($existing) {
                $startDate = $data['start_date'] ?? $existing['start_date'];
                // Handle end_date: if is_current is true, end_date should be null
                $isCurrent = isset($data['is_current']) ? $data['is_current'] : $existing['is_current'];
                if ($isCurrent) {
                    $endDate = null;
                } else {
                    // If is_current is false, use provided end_date or existing one
                    $endDate = isset($data['end_date']) && !empty($data['end_date']) ? $data['end_date'] : $existing['end_date'];
                }
                
                // Recalculate years_duration
                $data['years_duration'] = $this->calculateYearsDuration($startDate, $endDate, $isCurrent);
            }
        }
        
        // Normalize end_date for database: if is_current is true, set end_date to null
        if (isset($data['is_current']) && $data['is_current']) {
            $data['end_date'] = null;
        } elseif (isset($data['end_date']) && empty($data['end_date'])) {
            // If end_date is empty string and not current, keep existing end_date
            unset($data['end_date']);
        }
        
        foreach ($data as $key => $value) {
            if ($key !== 'id') {
                $fields[] = "{$key} = ?";
                $values[] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $values[] = $id;
        $sql = "UPDATE user_experience SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    public function getUserExperience($userId) {
        $stmt = $this->db->prepare("SELECT * FROM user_experience WHERE user_id = ? ORDER BY start_date DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}

class ApplicationDocument extends BaseModel {
    protected $table = 'application_documents';

    public function create($data) {
        $sql = "INSERT INTO application_documents (application_id, document_type, file_name, file_path, file_size) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['application_id'],
            $data['document_type'],
            $data['file_name'],
            $data['file_path'],
            $data['file_size'] ?? null
        ]);
    }

    public function getApplicationDocuments($applicationId) {
        $stmt = $this->db->prepare("SELECT * FROM application_documents WHERE application_id = ? ORDER BY uploaded_at ASC");
        $stmt->execute([$applicationId]);
        return $stmt->fetchAll();
    }

    public function deleteByApplicationId($applicationId) {
        $stmt = $this->db->prepare("DELETE FROM application_documents WHERE application_id = ?");
        return $stmt->execute([$applicationId]);
    }
}

class TrainingCertificate extends BaseModel {
    protected $table = 'user_training_certificates';

    public function create($data) {
        $sql = "INSERT INTO user_training_certificates (user_id, file_name, file_path, file_size) 
                VALUES (?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['user_id'],
            $data['file_name'],
            $data['file_path'],
            $data['file_size'] ?? null
        ]);
    }

    public function getUserCertificates($userId) {
        $stmt = $this->db->prepare("SELECT * FROM user_training_certificates WHERE user_id = ? ORDER BY uploaded_at ASC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function deleteById($id, $userId) {
        // Ensure the certificate belongs to the user before deleting
        $stmt = $this->db->prepare("DELETE FROM user_training_certificates WHERE id = ? AND user_id = ?");
        return $stmt->execute([$id, $userId]);
    }

    public function getNextSequenceNumber($userId) {
        // Get the highest sequence number for this user to determine the next TS number
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM user_training_certificates WHERE user_id = ?");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return ($result['count'] ?? 0) + 1;
    }
}

class UserApplicationDocument extends BaseModel {
    protected $table = 'user_application_documents';

    public function create($data) {
        $sql = "INSERT INTO user_application_documents (user_id, document_type, file_name, file_path, file_size) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['user_id'],
            $data['document_type'],
            $data['file_name'],
            $data['file_path'],
            $data['file_size'] ?? null
        ]);
    }

    public function getUserDocuments($userId, $documentType = null) {
        if ($documentType) {
            $stmt = $this->db->prepare("SELECT * FROM user_application_documents WHERE user_id = ? AND document_type = ? ORDER BY uploaded_at ASC");
            $stmt->execute([$userId, $documentType]);
        } else {
            $stmt = $this->db->prepare("SELECT * FROM user_application_documents WHERE user_id = ? ORDER BY document_type, uploaded_at ASC");
            $stmt->execute([$userId]);
        }
        return $stmt->fetchAll();
    }

    public function getUserDocumentByType($userId, $documentType) {
        // Get single document for types that only allow one file (APLET, PDS, WES, TOR, DIP)
        $stmt = $this->db->prepare("SELECT * FROM user_application_documents WHERE user_id = ? AND document_type = ? ORDER BY uploaded_at DESC LIMIT 1");
        $stmt->execute([$userId, $documentType]);
        return $stmt->fetch();
    }

    public function deleteById($id, $userId) {
        // Ensure the document belongs to the user before deleting
        $stmt = $this->db->prepare("DELETE FROM user_application_documents WHERE id = ? AND user_id = ?");
        return $stmt->execute([$id, $userId]);
    }

    public function getNextSequenceNumber($userId, $documentType) {
        // Get the count for this document type to determine the next sequence number
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM user_application_documents WHERE user_id = ? AND document_type = ?");
        $stmt->execute([$userId, $documentType]);
        $result = $stmt->fetch();
        return ($result['count'] ?? 0) + 1;
    }
}

class Message extends BaseModel {
    protected $table = 'messages';

    public function create($data) {
        try {
            // Check if recipient_id column exists (for backward compatibility)
            $hasRecipientColumn = $this->columnExists('recipient_id');
            
            if ($hasRecipientColumn) {
                // Support both application-based and user-to-user messaging
                $sql = "INSERT INTO messages (application_id, sender_id, recipient_id, sender_role, message) 
                        VALUES (?, ?, ?, ?, ?)";
                $stmt = $this->db->prepare($sql);
                $result = $stmt->execute([
                    $data['application_id'] ?? null,
                    $data['sender_id'],
                    $data['recipient_id'] ?? null,
                    $data['sender_role'],
                    $data['message']
                ]);
                
                if (!$result) {
                    $errorInfo = $stmt->errorInfo();
                    throw new Exception('Failed to insert message: ' . ($errorInfo[2] ?? 'Unknown error'));
                }
                
                return $result;
            } else {
                // Fallback for old schema without recipient_id
                // Only support application-based messaging
                if (empty($data['application_id'])) {
                    throw new Exception('recipient_id column does not exist. Please run the database migration to enable user-to-user messaging.');
                }
                $sql = "INSERT INTO messages (application_id, sender_id, sender_role, message) 
                        VALUES (?, ?, ?, ?)";
                $stmt = $this->db->prepare($sql);
                $result = $stmt->execute([
                    $data['application_id'],
                    $data['sender_id'],
                    $data['sender_role'],
                    $data['message']
                ]);
                
                if (!$result) {
                    $errorInfo = $stmt->errorInfo();
                    throw new Exception('Failed to insert message: ' . ($errorInfo[2] ?? 'Unknown error'));
                }
                
                return $result;
            }
        } catch (\PDOException $e) {
            error_log("Message create PDO error: " . $e->getMessage());
            throw $e;
        } catch (\Exception $e) {
            error_log("Message create error: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Check if a column exists in the messages table
     */
    private function columnExists($columnName) {
        try {
            $stmt = $this->db->prepare("SHOW COLUMNS FROM messages LIKE ?");
            $stmt->execute([$columnName]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Error checking column existence: " . $e->getMessage());
            return false;
        }
    }

    public function getMessagesByApplication($applicationId, $limit = 50) {
        // Get all messages for an application
        // This includes messages with the application_id
        $stmt = $this->db->prepare("
            SELECT m.*, u.full_name as sender_name 
            FROM messages m 
            LEFT JOIN users u ON m.sender_id = u.id 
            WHERE m.application_id = ? 
            ORDER BY m.created_at ASC 
            LIMIT ?
        ");
        $stmt->execute([$applicationId, $limit]);
        return $stmt->fetchAll();
    }

    public function getMessagesByUsers($senderId, $recipientId, $limit = 50) {
        // Get messages between two users (bidirectional)
        // Include messages with recipient_id (direct user-to-user) AND application-based messages
        // Works for both admin-applicant and applicant-admin conversations
        // 1. Direct messages via recipient_id (bidirectional)
        // 2. Application-based messages where applicant is involved
        $stmt = $this->db->prepare("
            SELECT DISTINCT m.*, u.full_name as sender_name
            FROM messages m 
            LEFT JOIN users u ON m.sender_id = u.id 
            LEFT JOIN applications app ON m.application_id = app.id
            WHERE (
                -- Direct user-to-user messages (bidirectional) via recipient_id
                (m.sender_id = ? AND m.recipient_id = ?) 
                OR (m.sender_id = ? AND m.recipient_id = ?)
                OR (
                    -- Application-based messages where the applicant is involved
                    -- Check if recipientId is the applicant for this application
                    m.application_id IS NOT NULL 
                    AND app.user_id = ?
                )
            )
            ORDER BY m.created_at ASC 
            LIMIT ?
        ");
        $stmt->execute([$senderId, $recipientId, $recipientId, $senderId, $recipientId, $limit]);
        return $stmt->fetchAll();
    }

    public function getMessagesForRecipient($recipientId, $limit = 50) {
        // Get all messages for an applicant recipient:
        // 1. Direct messages sent to them (via recipient_id) from admins
        // 2. Direct messages sent by them (via recipient_id) to admins
        // 3. Application-based messages from their applications (always admin-applicant)
        // IMPORTANT: Filter to ensure applicants only see messages from/to admins (not from other applicants)
        $stmt = $this->db->prepare("
            SELECT DISTINCT m.*, u.full_name as sender_name, 
                   u.role as sender_user_role
            FROM messages m 
            LEFT JOIN users u ON m.sender_id = u.id 
            LEFT JOIN users recipient_user ON m.recipient_id = recipient_user.id
            LEFT JOIN applications app ON m.application_id = app.id
            WHERE (
                -- Direct user-to-user messages where recipient is involved (bidirectional)
                -- Messages sent TO the applicant (recipient_id = applicant)
                (
                    m.recipient_id = ?
                    AND m.sender_id != ?
                    AND u.role = 'admin'
                )
                -- OR messages sent BY the applicant (sender_id = applicant, recipient_id = admin)
                OR (
                    m.sender_id = ?
                    AND m.recipient_id IS NOT NULL
                    AND m.recipient_id != ?
                    AND recipient_user.role = 'admin'
                )
                -- OR application-based messages where this recipient is the applicant
                OR (
                    m.application_id IS NOT NULL
                    AND app.user_id = ?
                )
            )
            ORDER BY m.created_at ASC 
            LIMIT ?
        ");
        $stmt->execute([$recipientId, $recipientId, $recipientId, $recipientId, $recipientId, $limit]);
        return $stmt->fetchAll();
    }

    public function markAsRead($messageIds, $userId) {
        if (empty($messageIds)) {
            return true;
        }
        $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
        $stmt = $this->db->prepare("
            UPDATE messages 
            SET is_read = 1 
            WHERE id IN ($placeholders) 
            AND sender_id != ?
        ");
        $params = array_merge($messageIds, [$userId]);
        return $stmt->execute($params);
    }

    public function getUnreadCount($applicationId, $userId) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM messages 
            WHERE application_id = ? 
            AND sender_id != ? 
            AND is_read = 0
        ");
        $stmt->execute([$applicationId, $userId]);
        $result = $stmt->fetch();
        return (int)($result['count'] ?? 0);
    }

    /**
     * Get conversations for admin (all users they've chatted with)
     */
    public function getAdminConversations($adminId, $limit = 50, $offset = 0) {
        $hasRecipientColumn = $this->columnExists('recipient_id');
        
        if ($hasRecipientColumn) {
            // Optimized single query with subqueries (but limited to reduce load)
            $stmt = $this->db->prepare("
                SELECT 
                    u.id as user_id,
                    u.full_name,
                    u.email,
                    u.role,
                    MAX(m.created_at) as last_message_at,
                    (SELECT COUNT(*) 
                     FROM messages m2
                     LEFT JOIN applications app2 ON m2.application_id = app2.id
                     WHERE (
                         (m2.sender_id = ? AND m2.recipient_id = u.id)
                         OR (m2.sender_id = u.id AND m2.recipient_id = ?)
                         OR (m2.application_id IS NOT NULL AND app2.user_id = u.id)
                     )
                     AND m2.sender_id != ?
                     AND m2.is_read = 0
                     LIMIT 1000
                    ) as unread_count,
                    (SELECT m3.message 
                     FROM messages m3
                     LEFT JOIN applications app3 ON m3.application_id = app3.id
                     WHERE (
                         (m3.sender_id = ? AND m3.recipient_id = u.id)
                         OR (m3.sender_id = u.id AND m3.recipient_id = ?)
                         OR (m3.application_id IS NOT NULL AND app3.user_id = u.id)
                     )
                     ORDER BY m3.created_at DESC
                     LIMIT 1
                    ) as last_message
                FROM messages m
                LEFT JOIN applications app ON m.application_id = app.id
                LEFT JOIN users u ON (u.id = COALESCE(m.recipient_id, app.user_id))
                WHERE (
                    (m.sender_id = ? AND m.recipient_id IS NOT NULL)
                    OR (m.recipient_id = ? AND m.sender_id != ?)
                    OR (m.application_id IS NOT NULL AND app.user_id IS NOT NULL)
                )
                AND u.id IS NOT NULL
                AND u.role = 'applicant'
                GROUP BY u.id, u.full_name, u.email, u.role
                ORDER BY last_message_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([
                $adminId, $adminId, $adminId,
                $adminId, $adminId,
                $adminId, $adminId, $adminId,
                $limit, $offset
            ]);
        } else {
            // Fallback for old schema
            $stmt = $this->db->prepare("
                SELECT 
                    u.id as user_id,
                    u.full_name,
                    u.email,
                    u.role,
                    MAX(m.created_at) as last_message_at,
                    (SELECT COUNT(*) 
                     FROM messages m2
                     WHERE m2.application_id = app.id 
                     AND m2.sender_id != ? 
                     AND m2.is_read = 0
                    ) as unread_count,
                    (SELECT m3.message 
                     FROM messages m3
                     WHERE m3.application_id = app.id 
                     ORDER BY m3.created_at DESC 
                     LIMIT 1
                    ) as last_message
                FROM messages m
                INNER JOIN applications app ON m.application_id = app.id
                INNER JOIN users u ON app.user_id = u.id
                WHERE (m.sender_id = ? OR EXISTS (
                    SELECT 1 FROM messages m4 
                    WHERE m4.application_id = app.id AND m4.sender_id = ?
                ))
                AND u.role = 'applicant'
                GROUP BY u.id, u.full_name, u.email, u.role, app.id
                ORDER BY last_message_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$adminId, $adminId, $adminId, $limit, $offset]);
        }
        
        return $stmt->fetchAll();
    }

    /**
     * Get conversations for applicant (only admins they've chatted with)
     */
    public function getApplicantConversations($applicantId) {
        $hasRecipientColumn = $this->columnExists('recipient_id');
        
        if ($hasRecipientColumn) {
            // Get conversations where:
            // 1. Admin sent message to applicant (recipient_id = applicant)
            // 2. Applicant sent message to admin (sender_id = applicant, recipient_id = admin)
            // 3. Application-based messages where admin sent to applicant's application
            $stmt = $this->db->prepare("
                SELECT DISTINCT
                    u.id as user_id,
                    u.full_name,
                    u.email,
                    u.role,
                    MAX(m.created_at) as last_message_at,
                    (SELECT COUNT(*) FROM messages m2 
                     WHERE (
                        -- Unread messages from admin to applicant
                        (m2.sender_id = u.id AND m2.recipient_id = ?)
                        OR (m2.sender_id = ? AND m2.recipient_id = u.id)
                        OR (m2.application_id IS NOT NULL AND EXISTS (
                            SELECT 1 FROM applications app2 
                            WHERE app2.id = m2.application_id AND app2.user_id = ?
                        ))
                     )
                     AND m2.sender_id != ? AND m2.is_read = 0) as unread_count,
                    (SELECT m3.message FROM messages m3 
                     WHERE (
                        (m3.sender_id = u.id AND m3.recipient_id = ?)
                        OR (m3.sender_id = ? AND m3.recipient_id = u.id)
                        OR (m3.application_id IS NOT NULL AND EXISTS (
                            SELECT 1 FROM applications app3 
                            WHERE app3.id = m3.application_id AND app3.user_id = ?
                        ))
                     )
                     ORDER BY m3.created_at DESC LIMIT 1) as last_message
                FROM messages m
                LEFT JOIN applications app ON m.application_id = app.id
                LEFT JOIN users u ON (
                    -- Admin who sent message to applicant
                    (m.sender_id = u.id AND m.recipient_id = ? AND u.role = 'admin')
                    -- OR applicant sent to admin
                    OR (m.sender_id = ? AND m.recipient_id = u.id AND u.role = 'admin')
                    -- OR application-based where admin sent message
                    OR (m.application_id IS NOT NULL AND app.user_id = ? AND m.sender_id = u.id AND u.role = 'admin')
                )
                WHERE (
                    -- Messages sent TO applicant from admin
                    (m.recipient_id = ? AND m.sender_id != ? AND u.role = 'admin')
                    -- OR messages sent BY applicant to admin
                    OR (m.sender_id = ? AND m.recipient_id IS NOT NULL AND m.recipient_id != ? AND u.role = 'admin')
                    -- OR application-based messages
                    OR (m.application_id IS NOT NULL AND app.user_id = ? AND u.role = 'admin')
                )
                AND u.id IS NOT NULL
                GROUP BY u.id, u.full_name, u.email, u.role
                ORDER BY last_message_at DESC
            ");
            $stmt->execute([
                $applicantId, $applicantId, $applicantId, $applicantId,
                $applicantId, $applicantId, $applicantId,
                $applicantId, $applicantId, $applicantId,
                $applicantId, $applicantId, $applicantId, $applicantId, $applicantId
            ]);
        } else {
            // Fallback for old schema
            $stmt = $this->db->prepare("
                SELECT DISTINCT
                    u.id as user_id,
                    u.full_name,
                    u.email,
                    u.role,
                    MAX(m.created_at) as last_message_at,
                    (SELECT COUNT(*) FROM messages m2 
                     WHERE m2.application_id = app.id 
                     AND m2.sender_id != ? AND m2.is_read = 0) as unread_count,
                    (SELECT m3.message FROM messages m3 
                     WHERE m3.application_id = app.id 
                     ORDER BY m3.created_at DESC LIMIT 1) as last_message
                FROM messages m
                INNER JOIN applications app ON m.application_id = app.id
                INNER JOIN users u ON m.sender_id = u.id
                WHERE app.user_id = ?
                AND u.role = 'admin'
                GROUP BY u.id, u.full_name, u.email, u.role
                ORDER BY last_message_at DESC
            ");
            $stmt->execute([$applicantId, $applicantId]);
        }
        
        return $stmt->fetchAll();
    }

    /**
     * Get unread count for a conversation
     */
    public function getConversationUnreadCount($userId, $otherUserId, $isAdmin = false) {
        $hasRecipientColumn = $this->columnExists('recipient_id');
        
        if ($hasRecipientColumn) {
            if ($isAdmin) {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as count 
                    FROM messages m
                    LEFT JOIN applications app ON m.application_id = app.id
                    WHERE (
                        (m.sender_id = ? AND m.recipient_id = ?)
                        OR (m.sender_id = ? AND m.recipient_id = ?)
                        OR (m.application_id IS NOT NULL AND app.user_id = ?)
                    )
                    AND m.sender_id != ?
                    AND m.is_read = 0
                ");
                $stmt->execute([$userId, $otherUserId, $otherUserId, $userId, $otherUserId, $userId]);
            } else {
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as count 
                    FROM messages m
                    LEFT JOIN applications app ON m.application_id = app.id
                    WHERE (
                        (m.sender_id = ? AND m.recipient_id = ?)
                        OR (m.application_id IS NOT NULL AND app.user_id = ?)
                    )
                    AND m.sender_id != ?
                    AND m.is_read = 0
                ");
                $stmt->execute([$otherUserId, $userId, $userId, $userId]);
            }
        } else {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM messages m
                INNER JOIN applications app ON m.application_id = app.id
                WHERE app.user_id = ?
                AND m.sender_id != ?
                AND m.is_read = 0
            ");
            $stmt->execute([$userId, $userId]);
        }
        
        $result = $stmt->fetch();
        return (int)($result['count'] ?? 0);
    }
}

class Notification extends BaseModel {
    protected $table = 'notifications';

    /**
     * Create a new notification
     */
    public function create($data) {
        $sql = "INSERT INTO notifications (user_id, type, title, message, related_id, related_type) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['user_id'],
            $data['type'] ?? 'application_submitted',
            $data['title'],
            $data['message'],
            $data['related_id'] ?? null,
            $data['related_type'] ?? null
        ]);
    }

    /**
     * Get notifications for a user
     */
    public function getUserNotifications($userId, $limit = null, $unreadOnly = false) {
        $sql = "SELECT * FROM notifications WHERE user_id = ?";
        
        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        if ($limit) {
            $sql .= " LIMIT " . (int)$limit;
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Get unread count for a user
     */
    public function getUnreadCount($userId) {
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        return (int)($result['count'] ?? 0);
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId, $userId) {
        $stmt = $this->db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?");
        return $stmt->execute([$notificationId, $userId]);
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead($userId) {
        $stmt = $this->db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
        return $stmt->execute([$userId]);
    }

    /**
     * Create notification for all admins
     */
    public function notifyAllAdmins($type, $title, $message, $relatedId = null, $relatedType = null) {
        $userModel = new User();
        $admins = $userModel->findAll('role = ?', [ROLE_ADMIN]);
        
        foreach ($admins as $admin) {
            $this->create([
                'user_id' => $admin['id'],
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'related_id' => $relatedId,
                'related_type' => $relatedType
            ]);
        }
        
        return true;
    }

    /**
     * Delete notification
     */
    public function delete($id) {
        $stmt = $this->db->prepare("DELETE FROM notifications WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
?>