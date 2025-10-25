<?php
/**
 * ========================================
 * AUDIT TRAIL SYSTEM
 * File: /php/audit_trail.php
 * ========================================
 */

/**
 * Record an audit action
 * @param PDO $pdo
 * @param int|null $userId
 * @param string $action
 * @param string|null $details
 * @param string|null $affectedTable
 * @param int|null $affectedId
 * @return bool
 */
function recordAudit($pdo, $userId, $action, $details = null, $affectedTable = null, $affectedId = null)
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO audit_trail 
                (user_id, action, details, affected_table, affected_id, ip_address, user_agent, created_at)
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        return $stmt->execute([
            $userId,
            $action,
            $details,
            $affectedTable,
            $affectedId,
            $ipAddress,
            $userAgent
        ]);
    } catch (PDOException $e) {
        error_log("Audit log error: " . $e->getMessage());
        return false;
    }
}

/**
 * Fetch audit trail records (with optional filters)
 */
function fetchAuditTrail($pdo, $limit = 50, $offset = 0, $action = null, $userId = null)
{
    try {
        $query = "
            SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) AS full_name, u.email
            FROM audit_trail a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE 1=1
        ";

        $params = [];

        if ($action) {
            $query .= " AND a.action = ?";
            $params[] = $action;
        }

        if ($userId) {
            $query .= " AND a.user_id = ?";
            $params[] = $userId;
        }

        $query .= " ORDER BY a.created_at DESC LIMIT ? OFFSET ?";
        $params[] = (int)$limit;
        $params[] = (int)$offset;

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Fetch audit trail error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get summary statistics for audit trail
 */
function getAuditStats($pdo)
{
    try {
        $stats = [];

        $stats['total_logs'] = $pdo->query("SELECT COUNT(*) FROM audit_trail")->fetchColumn();

        $stats['logs_today'] = $pdo->query("
            SELECT COUNT(*) FROM audit_trail 
            WHERE DATE(created_at) = CURDATE()
        ")->fetchColumn();

        $stats['active_users_today'] = $pdo->query("
            SELECT COUNT(DISTINCT user_id) FROM audit_trail 
            WHERE DATE(created_at) = CURDATE()
        ")->fetchColumn();

        $stats['common_actions'] = $pdo->query("
            SELECT action, COUNT(*) AS count 
            FROM audit_trail 
            GROUP BY action 
            ORDER BY count DESC 
            LIMIT 5
        ")->fetchAll(PDO::FETCH_ASSOC);

        return $stats;
    } catch (PDOException $e) {
        error_log("Audit stats error: " . $e->getMessage());
        return [];
    }
}

/**
 * Delete logs older than X days (default: 90 days)
 */
function cleanOldAuditLogs($pdo, $days = 90)
{
    try {
        $stmt = $pdo->prepare("
            DELETE FROM audit_trail 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Clean audit logs error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Standardized audit action constants
 */
define('AUDIT_LOGIN', 'LOGIN');
define('AUDIT_LOGOUT', 'LOGOUT');
define('AUDIT_DASHBOARD_ACCESS', 'DASHBOARD_ACCESS');

define('AUDIT_PRODUCT_CREATE', 'PRODUCT_CREATE');
define('AUDIT_PRODUCT_UPDATE', 'PRODUCT_UPDATE');
define('AUDIT_PRODUCT_DELETE', 'PRODUCT_DELETE');

define('AUDIT_ORDER_CREATE', 'ORDER_CREATE');
define('AUDIT_ORDER_UPDATE', 'ORDER_UPDATE');
define('AUDIT_ORDER_DELETE', 'ORDER_DELETE');

define('AUDIT_USER_CREATE', 'USER_CREATE');
define('AUDIT_USER_UPDATE', 'USER_UPDATE');
define('AUDIT_USER_DELETE', 'USER_DELETE');

define('AUDIT_FAILED_LOGIN', 'FAILED_LOGIN');
define('AUDIT_PASSWORD_CHANGE', 'PASSWORD_CHANGE');
define('AUDIT_SETTINGS_UPDATE', 'SETTINGS_UPDATE');
?>
