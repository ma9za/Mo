<?php
/**
 * Settings Page
 */

require_once __DIR__ . '/../lib/env.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/deepseek.php';
require_once __DIR__ . '/../lib/helpers.php';

Auth::requireLogin();

$db = Database::getInstance();
$user = Auth::getUser();
$errors = [];
$csrfToken = Auth::generateCsrfToken();

// Get current settings
$settings = [];
$settingsRows = $db->fetchAll("SELECT * FROM settings");
foreach ($settingsRows as $row) {
    $settings[$row['key']] = $row['value'];
}

if (isPost()) {
    if (!Auth::verifyCsrfToken(getPost('csrf_token'))) {
        $errors[] = 'Invalid security token';
    } else {
        $action = getPost('action', '');

        if ($action === 'update_settings') {
            $baseUrl = trim(getPost('base_url', ''));
            $timezone = trim(getPost('timezone', ''));
            $deepseekKey = trim(getPost('deepseek_key', ''));

            if (empty($baseUrl)) {
                $errors[] = 'Base URL is required';
            }
            if (empty($timezone)) {
                $errors[] = 'Timezone is required';
            }

            if (empty($errors)) {
                try {
                    // Update or insert settings
                    $db->execute("DELETE FROM settings");
                    
                    $db->insert('settings', ['key' => 'base_url', 'value' => $baseUrl]);
                    $db->insert('settings', ['key' => 'timezone', 'value' => $timezone]);
                    
                    if (!empty($deepseekKey)) {
                        $db->insert('settings', ['key' => 'deepseek_key', 'value' => $deepseekKey]);
                    }

                    setFlash('success', 'Settings updated successfully', 'success');
                    redirect('/admin/settings.php');
                } catch (Exception $e) {
                    $errors[] = 'Error updating settings: ' . $e->getMessage();
                }
            }
        } elseif ($action === 'test_deepseek') {
            $deepseekKey = trim(getPost('deepseek_key', ''));
            
            if (empty($deepseekKey)) {
                $deepseekKey = env('DEEPSEEK_API_KEY');
            }

            try {
                if (DeepSeekAPI::testConnection($deepseekKey)) {
                    setFlash('success', 'DeepSeek API connection successful', 'success');
                } else {
                    setFlash('error', 'DeepSeek API connection failed', 'danger');
                }
                redirect('/admin/settings.php');
            } catch (Exception $e) {
                setFlash('error', 'Error testing connection: ' . $e->getMessage(), 'danger');
                redirect('/admin/settings.php');
            }
        }
    }
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Telegram AI Bot Manager</title>
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
                    <a class="nav-link active" href="/admin/settings.php">
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
                    <span>Settings</span>
                </div>
            </div>

            <!-- Page Content -->
            <div class="container-fluid">
                <h2 class="mb-4">Application Settings</h2>

                <?php if (!empty($flash)): ?>
                    <?php foreach ($flash as $key => $item): ?>
                        <div class="alert alert-<?php echo escape($item['type']); ?> alert-dismissible fade show" role="alert">
                            <?php echo escape($item['message']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <?php foreach ($errors as $error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle"></i> <?php echo escape($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-8">
                        <!-- General Settings -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">General Settings</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo escape($csrfToken); ?>">
                                    <input type="hidden" name="action" value="update_settings">

                                    <div class="mb-3">
                                        <label class="form-label">Base URL</label>
                                        <input type="url" name="base_url" class="form-control" placeholder="https://yourdomain.com" 
                                               value="<?php echo escape($settings['base_url'] ?? env('BASE_URL')); ?>" required>
                                        <small class="text-muted">Used for webhook URLs</small>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Timezone</label>
                                        <select name="timezone" class="form-select" required>
                                            <option value="">Select timezone</option>
                                            <option value="UTC" <?php echo ($settings['timezone'] ?? env('TIMEZONE')) === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                            <option value="Asia/Riyadh" <?php echo ($settings['timezone'] ?? env('TIMEZONE')) === 'Asia/Riyadh' ? 'selected' : ''; ?>>Asia/Riyadh</option>
                                            <option value="Asia/Dubai" <?php echo ($settings['timezone'] ?? env('TIMEZONE')) === 'Asia/Dubai' ? 'selected' : ''; ?>>Asia/Dubai</option>
                                            <option value="Asia/Baghdad" <?php echo ($settings['timezone'] ?? env('TIMEZONE')) === 'Asia/Baghdad' ? 'selected' : ''; ?>>Asia/Baghdad</option>
                                            <option value="Europe/London" <?php echo ($settings['timezone'] ?? env('TIMEZONE')) === 'Europe/London' ? 'selected' : ''; ?>>Europe/London</option>
                                            <option value="America/New_York" <?php echo ($settings['timezone'] ?? env('TIMEZONE')) === 'America/New_York' ? 'selected' : ''; ?>>America/New_York</option>
                                        </select>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> Save Settings
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- API Settings -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">API Settings</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo escape($csrfToken); ?>">
                                    <input type="hidden" name="action" value="update_settings">

                                    <div class="mb-3">
                                        <label class="form-label">DeepSeek API Key</label>
                                        <input type="password" name="deepseek_key" class="form-control" placeholder="sk-..." 
                                               value="<?php echo escape($settings['deepseek_key'] ?? ''); ?>">
                                        <small class="text-muted">Leave empty to use .env value</small>
                                    </div>

                                    <div class="mb-3">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Save API Settings
                                        </button>
                                        <button type="button" class="btn btn-secondary" onclick="testDeepSeek()">
                                            <i class="fas fa-flask"></i> Test Connection
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Info Panel -->
                    <div class="col-lg-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">System Info</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="text-muted">PHP Version</label>
                                    <div><?php echo phpversion(); ?></div>
                                </div>
                                <div class="mb-3">
                                    <label class="text-muted">Current Timezone</label>
                                    <div><?php echo date_default_timezone_get(); ?></div>
                                </div>
                                <div class="mb-3">
                                    <label class="text-muted">Database</label>
                                    <div>SQLite</div>
                                </div>
                                <div>
                                    <label class="text-muted">Environment</label>
                                    <div><?php echo escape(env('APP_ENV')); ?></div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Help</h5>
                            </div>
                            <div class="card-body">
                                <p class="small">
                                    <strong>Base URL:</strong> The domain where your application is hosted. Used for generating webhook URLs.
                                </p>
                                <p class="small">
                                    <strong>Timezone:</strong> Used for scheduling posts at specific times.
                                </p>
                                <p class="small">
                                    <strong>DeepSeek API Key:</strong> Required for AI content generation.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function testDeepSeek() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="csrf_token" value="<?php echo escape($csrfToken); ?>">
                <input type="hidden" name="action" value="test_deepseek">
                <input type="hidden" name="deepseek_key" value="">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>
