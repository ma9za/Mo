<?php
/**
 * Admin Dashboard
 */

require_once __DIR__ . '/../lib/env.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/helpers.php';

Auth::requireLogin();

$db = Database::getInstance();
$user = Auth::getUser();

// Get statistics
$totalBots = $db->fetch("SELECT COUNT(*) as count FROM bots")['count'];
$enabledBots = $db->fetch("SELECT COUNT(*) as count FROM bots WHERE is_enabled = 1")['count'];
$verifiedBots = $db->fetch("SELECT COUNT(*) as count FROM bots WHERE is_verified = 1")['count'];
$recentLogs = $db->fetchAll("SELECT l.*, b.name as bot_name FROM logs l JOIN bots b ON l.bot_id = b.id ORDER BY l.created_at DESC LIMIT 10");

// Get all bots
$bots = $db->fetchAll("SELECT * FROM bots ORDER BY created_at DESC");

// Handle delete action
if (isPost() && getPost('action') === 'delete') {
    if (!Auth::verifyCsrfToken(getPost('csrf_token'))) {
        setFlash('error', 'Invalid security token', 'danger');
    } else {
        $botId = (int)getPost('bot_id');
        try {
            $db->delete('bots', 'id = ?', [$botId]);
            setFlash('success', 'Bot deleted successfully', 'success');
            redirect('/admin/dashboard.php');
        } catch (Exception $e) {
            setFlash('error', 'Failed to delete bot: ' . $e->getMessage(), 'danger');
        }
    }
}

// Handle enable/disable action
if (isPost() && getPost('action') === 'toggle') {
    if (!Auth::verifyCsrfToken(getPost('csrf_token'))) {
        setFlash('error', 'Invalid security token', 'danger');
    } else {
        $botId = (int)getPost('bot_id');
        $bot = $db->fetch("SELECT * FROM bots WHERE id = ?", [$botId]);
        
        if ($bot) {
            $newStatus = $bot['is_enabled'] ? 0 : 1;
            $db->update('bots', ['is_enabled' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$botId]);
            setFlash('success', 'Bot status updated successfully', 'success');
            redirect('/admin/dashboard.php');
        }
    }
}

$csrfToken = Auth::generateCsrfToken();
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Telegram AI Bot Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="/assets/app.css" rel="stylesheet">
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="sidebar-header">
                <h3><i class="fas fa-robot"></i> AI Bot Manager</h3>
            </div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link active" href="/admin/dashboard.php">
                        <i class="fas fa-chart-line"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/admin/bot_list.php">
                        <i class="fas fa-list"></i> Bots
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/admin/bot_edit.php">
                        <i class="fas fa-plus"></i> Add Bot
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/admin/settings.php">
                        <i class="fas fa-cog"></i> Settings
                    </a>
                </li>
            </ul>
            <div class="sidebar-footer">
                <a href="/admin/logout.php" class="btn btn-sm btn-danger w-100">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="user-info">
                    <span>Welcome, <strong><?php echo escape($user['username']); ?></strong></span>
                </div>
            </div>

            <!-- Page Content -->
            <div class="container-fluid">
                <?php if (!empty($flash)): ?>
                    <?php foreach ($flash as $key => $item): ?>
                        <div class="alert alert-<?php echo escape($item['type']); ?> alert-dismissible fade show" role="alert">
                            <?php echo escape($item['message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #e3f2fd;">
                                <i class="fas fa-robot" style="color: #1976d2;"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo $totalBots; ?></div>
                                <div class="stat-label">Total Bots</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #f3e5f5;">
                                <i class="fas fa-check-circle" style="color: #7b1fa2;"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo $enabledBots; ?></div>
                                <div class="stat-label">Enabled</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #e8f5e9;">
                                <i class="fas fa-shield-alt" style="color: #388e3c;"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo $verifiedBots; ?></div>
                                <div class="stat-label">Verified</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card">
                            <div class="stat-icon" style="background: #fff3e0;">
                                <i class="fas fa-history" style="color: #f57c00;"></i>
                            </div>
                            <div class="stat-content">
                                <div class="stat-value"><?php echo count($recentLogs); ?></div>
                                <div class="stat-label">Recent Logs</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bots Table -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">All Bots</h5>
                        <a href="/admin/bot_edit.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus"></i> Add Bot
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($bots)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No bots found. <a href="/admin/bot_edit.php">Create one</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Channel</th>
                                            <th>Status</th>
                                            <th>Verified</th>
                                            <th>Last Post</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($bots as $bot): ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo escape($bot['name']); ?></strong>
                                                </td>
                                                <td><?php echo escape($bot['channel_title'] ?? $bot['channel_id'] ?? '-'); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $bot['is_enabled'] ? 'success' : 'secondary'; ?>">
                                                        <?php echo $bot['is_enabled'] ? 'Enabled' : 'Disabled'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $bot['is_verified'] ? 'success' : 'warning'; ?>">
                                                        <?php echo $bot['is_verified'] ? 'Verified' : 'Pending'; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatRelativeTime($bot['last_post_at']); ?></td>
                                                <td>
                                                    <a href="/admin/bot_edit.php?id=<?php echo $bot['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="csrf_token" value="<?php echo escape($csrfToken); ?>">
                                                        <input type="hidden" name="action" value="toggle">
                                                        <input type="hidden" name="bot_id" value="<?php echo $bot['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-<?php echo $bot['is_enabled'] ? 'warning' : 'success'; ?>">
                                                            <i class="fas fa-<?php echo $bot['is_enabled'] ? 'pause' : 'play'; ?>"></i>
                                                        </button>
                                                    </form>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo escape($csrfToken); ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="bot_id" value="<?php echo $bot['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Logs -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Recent Logs</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentLogs)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No logs found
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Bot</th>
                                            <th>Status</th>
                                            <th>Message</th>
                                            <th>Time</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentLogs as $log): ?>
                                            <tr>
                                                <td><?php echo escape($log['bot_name']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $log['status'] === 'success' ? 'success' : 'danger'; ?>">
                                                        <?php echo escape($log['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo escape(truncate($log['message'], 50)); ?></td>
                                                <td><?php echo formatRelativeTime($log['created_at']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
