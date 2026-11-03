<?php
/**
 * =====================================================================
 * admin/activity_log.php
 * Hospital Appointment Management System (HAMS)
 * ---------------------------------------------------------------------
 * Read-only, searchable, paginated view of the activity_logs audit
 * trail — every login, CRUD action, and status change recorded by
 * log_activity() throughout the app.
 * =====================================================================
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_role('admin');

/* =====================================================================
   FILTERS + PAGINATION
   ===================================================================== */
$search = sanitize($_GET['search'] ?? '');
$actionFilter = sanitize($_GET['action_type'] ?? '');
$dateFrom = sanitize($_GET['date_from'] ?? '');
$dateTo = sanitize($_GET['date_to'] ?? '');

$where = ['1=1'];
$types = '';
$params = [];

if ($search !== '') {
    $where[] = '(l.description LIKE ? OR u.full_name LIKE ? OR l.ip_address LIKE ?)';
    $like = '%' . $search . '%';
    $types .= 'sss';
    $params = array_merge($params, [$like, $like, $like]);
}
if ($actionFilter !== '') {
    $where[] = 'l.action = ?';
    $types .= 's';
    $params[] = $actionFilter;
}
if ($dateFrom !== '') {
    $where[] = 'DATE(l.created_at) >= ?';
    $types .= 's';
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $where[] = 'DATE(l.created_at) <= ?';
    $types .= 's';
    $params[] = $dateTo;
}
$whereSql = implode(' AND ', $where);

$baseFrom = "FROM activity_logs l LEFT JOIN users u ON u.id = l.user_id";

$countSql = "SELECT COUNT(*) c $baseFrom WHERE $whereSql";
$stmt = $types ? db_query($conn, $countSql, $types, $params) : db_query($conn, $countSql);
$totalRecords = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];
$pagination = paginate($totalRecords, 20);

$listSql = "SELECT l.*, u.full_name, u.role, u.profile_photo
            $baseFrom
            WHERE $whereSql
            ORDER BY l.created_at DESC
            LIMIT {$pagination['limit']} OFFSET {$pagination['offset']}";
$stmt = $types ? db_query($conn, $listSql, $types, $params) : db_query($conn, $listSql);
$logs = [];
if ($stmt) {
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $logs[] = $row;
    }
}

// Distinct action types for the filter dropdown
$actionTypes = [];
$res = mysqli_query($conn, "SELECT DISTINCT action FROM activity_logs ORDER BY action");
if ($res) while ($row = mysqli_fetch_assoc($res)) $actionTypes[] = $row['action'];

/**
 * Map an action string to a Bootstrap Icon + color class for the timeline.
 */
function activity_icon(string $action): array
{
    return match (true) {
        str_contains($action, 'login_success') => ['bi-box-arrow-in-right', 'text-success'],
        str_contains($action, 'login_failed')  => ['bi-shield-exclamation', 'text-danger'],
        str_contains($action, 'logout')        => ['bi-box-arrow-right', 'text-muted'],
        str_contains($action, 'added') || str_contains($action, 'register') => ['bi-plus-circle', 'text-success'],
        str_contains($action, 'updated')       => ['bi-pencil-square', 'text-primary'],
        str_contains($action, 'deleted')       => ['bi-trash', 'text-danger'],
        str_contains($action, 'cancelled') || str_contains($action, 'rejected') => ['bi-x-circle', 'text-danger'],
        str_contains($action, 'confirmed') || str_contains($action, 'completed') => ['bi-check-circle', 'text-success'],
        str_contains($action, 'status_changed') => ['bi-toggle2-on', 'text-warning'],
        default => ['bi-clock-history', 'text-muted'],
    };
}

$pageTitle = 'Activity Log';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/sidebar.php';
?>
<div class="hams-main">
    <?php require_once __DIR__ . '/../includes/navbar.php'; ?>

    <main class="hams-content">

        <div class="mb-4">
            <h2 class="h4 fw-bold mb-1">Activity Log</h2>
            <p class="text-muted mb-0"><?php echo $totalRecords; ?> recorded event<?php echo $totalRecords === 1 ? '' : 's'; ?>.</p>
        </div>

        <!-- Filters -->
        <form method="GET" class="hams-card p-3 mb-4">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label mb-1">Search</label>
                    <input type="text" class="form-control" name="search" value="<?php echo e($search); ?>" placeholder="User, description or IP...">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">Action Type</label>
                    <select class="form-select" name="action_type">
                        <option value="">All Actions</option>
                        <?php foreach ($actionTypes as $type): ?>
                            <option value="<?php echo e($type); ?>" <?php echo $actionFilter === $type ? 'selected' : ''; ?>><?php echo e(ucwords(str_replace('_', ' ', $type))); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">From</label>
                    <input type="date" class="form-control" name="date_from" value="<?php echo e($dateFrom); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label mb-1">To</label>
                    <input type="date" class="form-control" name="date_to" value="<?php echo e($dateTo); ?>" max="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-1 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-search"></i></button>
                </div>
            </div>
            <?php if ($search !== '' || $actionFilter !== '' || $dateFrom !== '' || $dateTo !== ''): ?>
                <div class="mt-2"><a href="<?php echo BASE_URL; ?>admin/activity_log.php" class="small">Clear all filters <i class="bi bi-x-circle"></i></a></div>
            <?php endif; ?>
        </form>

        <!-- Timeline -->
        <div class="hams-card p-4">
            <?php if (empty($logs)): ?>
                <div class="empty-state"><i class="bi bi-clock-history"></i><p class="mb-0">No activity recorded for these filters.</p></div>
            <?php else: ?>
                <?php foreach ($logs as $log): [$icon, $colorClass] = activity_icon($log['action']); ?>
                <div class="d-flex gap-3 py-3 border-bottom">
                    <div class="flex-shrink-0">
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary-soft <?php echo $colorClass; ?>" style="width:38px;height:38px;">
                            <i class="bi <?php echo $icon; ?>"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                            <div>
                                <span class="fw-semibold"><?php echo e($log['full_name'] ?? 'System / Unknown user'); ?></span>
                                <?php if (!empty($log['role'])): ?>
                                    <span class="badge bg-secondary-soft text-secondary-accent text-capitalize ms-1"><?php echo e($log['role']); ?></span>
                                <?php endif; ?>
                                <span class="text-muted small ms-1"><?php echo e(str_replace('_', ' ', $log['action'])); ?></span>
                            </div>
                            <span class="text-muted small" title="<?php echo format_datetime($log['created_at']); ?>"><?php echo time_ago($log['created_at']); ?></span>
                        </div>
                        <?php if (!empty($log['description'])): ?>
                            <p class="text-muted small mb-1 mt-1"><?php echo e($log['description']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($log['ip_address'])): ?>
                            <span class="text-muted" style="font-size:.72rem;"><i class="bi bi-hdd-network me-1"></i>IP: <?php echo e($log['ip_address']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="mt-4">
            <?php render_pagination($pagination['currentPage'], $pagination['totalPages']); ?>
        </div>

    </main>

    <?php require_once __DIR__ . '/../includes/footer.php'; ?>
</div>