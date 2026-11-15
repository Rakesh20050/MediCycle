<?php
/**
 * MediCycle — PDO database connection and JSON error helpers.
 *
 * Usage in an API endpoint:
 *   require_once __DIR__ . '/../config/db_connect.php';   // gives you $pdo
 *   $stmt = execute_query('SELECT * FROM MEDICINE WHERE category = ?', ['Antibiotic']);
 *   json_response(['success' => true, 'data' => $stmt->fetchAll()]);
 */

declare(strict_types=1);

const DB_HOST    = 'localhost';
const DB_NAME    = 'medicycle_final';
const DB_USER    = 'root';
const DB_PASS    = '';           // XAMPP default: empty password
const DB_CHARSET = 'utf8mb4';

// Set to true while developing to expose the raw MySQL message in JSON.
const APP_DEBUG = false;

/**
 * Lazy, cached PDO connection.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);

    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ]);

    return $pdo;
}

/**
 * Send a JSON response and stop execution.
 */
function json_response(array $payload, int $status = 200): void
{
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Translate a PDOException into a user-safe message that SweetAlert2 can display.
 */
function pdo_error_message(PDOException $e): string
{
    $sqlState   = (string) $e->getCode();
    // Connection failures (e.g. 2002, 1045) put the driver code in getCode().
    $driverCode = (int) ($e->errorInfo[1] ?? (is_numeric($sqlState) ? $sqlState : 0));
    $raw        = $e->getMessage();

    // Named constraints give the most precise, friendliest messages.
    $constraintMessages = [
        'chk_batch_expiry_after_manufacture' => 'Expiry date must be later than the manufacturing date.',
        'chk_batch_received_positive'        => 'Quantity received must be greater than zero.',
        'chk_batch_available_nonneg'         => 'Available quantity cannot be negative.',
        'chk_batch_available_lte_received'   => 'Available quantity cannot exceed quantity received.',
        'chk_request_item_qty_positive'      => 'Requested quantity must be greater than zero.',
        'chk_distribution_item_qty_positive' => 'Distributed quantity must be greater than zero.',
        'uq_donor_email'                     => 'That donor email address is already registered.',
        'uq_admin_email'                     => 'That admin email address is already registered.',
        'uq_medicine_name_manufacturer'      => 'This medicine name and manufacturer are already registered.',
        'uq_batch_medicine_number'           => 'This batch number already exists for that medicine.',
        'uq_request_item'                    => 'This medicine is already listed on that request.',
        'uq_distribution_item'               => 'This batch is already listed on that distribution.',
    ];

    foreach ($constraintMessages as $constraint => $message) {
        if (str_contains($raw, $constraint)) {
            return $message;
        }
    }

    return match (true) {
        // Referential integrity
        $driverCode === 1451 => 'This record cannot be deleted because other records depend on it.',
        $driverCode === 1452 => 'A related record does not exist. Please refresh the page and try again.',
        // Uniqueness
        $driverCode === 1062 => 'A record with these details already exists.',
        // Missing required value
        $driverCode === 1048 => 'A required field is missing.',
        // CHECK constraint violated (MariaDB 4025 / MySQL 3819)
        $driverCode === 4025 || $driverCode === 3819 => 'The data failed a validation rule. Please check the values and try again.',
        // Numeric / length limits
        $driverCode === 1264 => 'A numeric value is out of the allowed range.',
        $driverCode === 1406 => 'One of the values is too long for its field.',
        // Connection problems
        $sqlState === '08006' || $sqlState === '08001' || $driverCode === 2002 => 'Cannot reach the database server. Make sure MySQL is running in XAMPP.',
        $driverCode === 1045 => 'Database access denied. Check the credentials in config/db_connect.php.',
        $driverCode === 1146 => 'A required database table is missing. Import schema.sql first.',
        default => 'A database error occurred. Please try again.',
    };
}

/**
 * Log, convert to JSON and stop. No raw SQL details leak unless APP_DEBUG is on.
 */
function json_pdo_error(PDOException $e, int $status = 422): void
{
    error_log('[MediCycle DB] ' . $e->getMessage());

    $payload = [
        'success' => false,
        'message' => pdo_error_message($e),
    ];

    if (APP_DEBUG) {
        $payload['detail'] = $e->getMessage();
    }

    json_response($payload, $status);
}

/**
 * Prepare + execute with automatic JSON error handling.
 */
function execute_query(string $sql, array $params = []): PDOStatement
{
    try {
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        json_pdo_error($e);
    }
}

// Fail with a JSON error (not a white screen) if the database is unreachable.
try {
    $pdo = db();
} catch (PDOException $e) {
    json_pdo_error($e, 500);
}
