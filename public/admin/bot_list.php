<?php
/**
 * Bot List Page
 */

require_once __DIR__ . '/../lib/env.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/helpers.php';

Auth::requireLogin();

$db = Database::getInstance();
$user = Auth::getUser();

// Get all bots with pagination
$page = (int)getGet('page', 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

$totalBots = $db->fetch("SELECT COUNT(*) as count FROM bots")['count'];
$totalPages = ceil($totalBots / $perPage);

$bots = $db->fetchAll(
    "SELECT * FROM bots ORDER BY created_at DESC LIMIT ? OFFSET ?",
    [$perPage, $offset]
);

$csrfToken = Auth::generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bots List - Telegram AI Bot Manager</title>
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
                    <a class="nav-link" href="/admin/dashboard.php">
                        <i class="fas fa-chart-line"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="/admin/bot_list.php">
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
                    <a href="/admin/bot_edit.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> Add Bot
                    </a>
                </div>
            </div>

            <!-- Page Content -->
            <div class="container-fluid">
                <h2 class="mb-4">All Bots</h2>

                <div class="card">
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
                                            <th>Schedule</th>
                                            <th>Last Post</th>
                                            <th>Created</th>
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
                                                <td><?php echo escape(truncate(implode(', ', json_decode($bot['schedule_json'], true) ?? []), 30)); ?></td>
                                                <td><?php echo formatRelativeTime($bot['last_post_at']); ?></td>
                                                <td><?php echo formatDate($bot['created_at'], 'M d, Y'); ?></td>
                                                <td>
                                                    <a href="/admin/bot_edit.php?id=<?php echo $bot['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <nav aria-label="Page navigation" class="mt-4">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=1">First</a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                                            </li>
                                        <?php endif; ?>

                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>

                                        <?php if ($page < $totalPages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?php echo $totalPages; ?>">Last</a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
