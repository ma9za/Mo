<?php
/**
 * Bot Edit/Add Page
 */

require_once __DIR__ . '/../lib/env.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/telegram.php';
require_once __DIR__ . '/../lib/deepseek.php';
require_once __DIR__ . '/../lib/scheduler.php';
require_once __DIR__ . '/../lib/helpers.php';

Auth::requireLogin();

$db = Database::getInstance();
$bot = null;
$botId = getGet('id');
$errors = [];
$csrfToken = Auth::generateCsrfToken();

if ($botId) {
    $bot = $db->fetch("SELECT * FROM bots WHERE id = ?", [(int)$botId]);
    if (!$bot) {
        redirect('/admin/dashboard.php');
    }
}

if (isPost()) {
    if (!Auth::verifyCsrfToken(getPost('csrf_token'))) {
        $errors[] = 'Invalid security token';
    }

    $name = trim(getPost('name', ''));
    $token = trim(getPost('token', ''));
    $channelInput = trim(getPost('channel_input', ''));
    $generalPrompt = trim(getPost('general_prompt', ''));
    $scheduleJson = getPost('schedule_json', '[]');
    $isEnabled = (int)getPost('is_enabled', 0);

    // Validation
    if (empty($name)) {
        $errors[] = 'Bot name is required';
    }
    if (empty($token)) {
        $errors[] = 'Telegram bot token is required';
    }
    if (empty($channelInput)) {
        $errors[] = 'Channel ID is required';
    }
    if (empty($generalPrompt)) {
        $errors[] = 'General prompt is required';
    }

    // Parse schedule
    $schedule = [];
    $scheduleInput = getPost('schedule_times', '');
    if (!empty($scheduleInput)) {
        $times = array_filter(array_map('trim', explode(',', $scheduleInput)));
        $schedule = $times;
        $scheduleJson = json_encode($schedule);

        if (!Scheduler::validateSchedule($scheduleJson)) {
            $errors[] = 'Invalid schedule format. Use HH:MM format (e.g., 09:00, 14:30)';
        }
    }

    if (empty($errors)) {
        try {
            // Verify bot token
            $botInfo = TelegramAPI::getMe($token);

            if ($botId) {
                // Update existing bot
                $db->update('bots', [
                    'name' => $name,
                    'token' => $token,
                    'channel_input' => $channelInput,
                    'general_prompt' => $generalPrompt,
                    'schedule_json' => $scheduleJson,
                    'is_enabled' => $isEnabled,
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [(int)$botId]);

                setFlash('success', 'Bot updated successfully', 'success');
            } else {
                // Create new bot
                $webhookSecret = bin2hex(random_bytes(16));
                $botId = $db->insert('bots', [
                    'name' => $name,
                    'token' => $token,
                    'webhook_secret' => $webhookSecret,
                    'channel_input' => $channelInput,
                    'general_prompt' => $generalPrompt,
                    'schedule_json' => $scheduleJson,
                    'is_enabled' => $isEnabled,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

                setFlash('success', 'Bot created successfully', 'success');
            }

            redirect('/admin/bot_edit.php?id=' . $botId);
        } catch (Exception $e) {
            $errors[] = 'Error: ' . $e->getMessage();
        }
    }
}

// Handle verify channel
if (isPost() && getPost('action') === 'verify_channel') {
    if (!Auth::verifyCsrfToken(getPost('csrf_token'))) {
        $errors[] = 'Invalid security token';
    } else {
        try {
            $token = $bot['token'];
            $channelInput = $bot['channel_input'];
            $channelId = TelegramAPI::parseChannelId($channelInput);

            // Get chat info
            $chatInfo = TelegramAPI::getChat($token, $channelId);

            // Get bot info
            $botInfo = TelegramAPI::getMe($token);

            // Check if bot is member
            $chatMember = TelegramAPI::getChatMember($token, $channelId, $botInfo['id']);

            if (in_array($chatMember['status'], ['administrator', 'member'])) {
                $db->update('bots', [
                    'channel_id' => $chatInfo['id'],
                    'channel_title' => $chatInfo['title'] ?? $chatInfo['username'] ?? '',
                    'is_verified' => 1,
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [(int)$bot['id']]);

                setFlash('success', 'Channel verified successfully', 'success');
                redirect('/admin/bot_edit.php?id=' . $bot['id']);
            } else {
                $errors[] = 'Bot is not a member of the channel';
            }
        } catch (Exception $e) {
            $errors[] = 'Verification failed: ' . $e->getMessage();
        }
    }
}

// Handle set webhook
if (isPost() && getPost('action') === 'set_webhook') {
    if (!Auth::verifyCsrfToken(getPost('csrf_token'))) {
        $errors[] = 'Invalid security token';
    } else {
        try {
            $baseUrl = env('BASE_URL');
            $webhookUrl = $baseUrl . '/webhook.php?bot_id=' . $bot['id'];
            $secret = $bot['webhook_secret'];

            TelegramAPI::setWebhook($bot['token'], $webhookUrl, $secret);

            setFlash('success', 'Webhook set successfully', 'success');
            redirect('/admin/bot_edit.php?id=' . $bot['id']);
        } catch (Exception $e) {
            $errors[] = 'Failed to set webhook: ' . $e->getMessage();
        }
    }
}

// Handle post now
if (isPost() && getPost('action') === 'post_now') {
    if (!Auth::verifyCsrfToken(getPost('csrf_token'))) {
        $errors[] = 'Invalid security token';
    } else {
        try {
            // Generate content
            $content = DeepSeekAPI::generateBotContent($bot);

            // Send to Telegram
            $messageId = TelegramAPI::sendMessage(
                $bot['token'],
                $bot['channel_id'],
                $content
            );

            // Log the action
            $db->insert('logs', [
                'bot_id' => $bot['id'],
                'status' => 'success',
                'message' => 'Posted: ' . substr($content, 0, 100),
                'telegram_message_id' => $messageId,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // Update last post time
            $db->update('bots', [
                'last_post_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [(int)$bot['id']]);

            setFlash('success', 'Post sent successfully', 'success');
            redirect('/admin/bot_edit.php?id=' . $bot['id']);
        } catch (Exception $e) {
            $errors[] = 'Failed to post: ' . $e->getMessage();
        }
    }
}

// Refresh bot data
if ($botId) {
    $bot = $db->fetch("SELECT * FROM bots WHERE id = ?", [(int)$botId]);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $botId ? 'Edit' : 'Add'; ?> Bot - Telegram AI Bot Manager</title>
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
                    <a class="nav-link active" href="/admin/bot_edit.php">
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
                    <a href="/admin/dashboard.php" class="btn btn-sm btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>

            <!-- Page Content -->
            <div class="container-fluid">
                <h2 class="mb-4"><?php echo $botId ? 'Edit Bot' : 'Add New Bot'; ?></h2>

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
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Bot Information</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?php echo escape($csrfToken); ?>">

                                    <div class="mb-3">
                                        <label class="form-label">Bot Name</label>
                                        <input type="text" name="name" class="form-control" placeholder="e.g., AI News Bot" 
                                               value="<?php echo escape($bot['name'] ?? ''); ?>" required>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Telegram Bot Token</label>
                                        <input type="text" name="token" class="form-control" placeholder="123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11" 
                                               value="<?php echo escape($bot['token'] ?? ''); ?>" required>
                                        <small class="text-muted">Get this from @BotFather</small>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Channel ID</label>
                                        <input type="text" name="channel_input" class="form-control" placeholder="@channel_name or -1001234567890" 
                                               value="<?php echo escape($bot['channel_input'] ?? ''); ?>" required>
                                        <small class="text-muted">Use @channel_name or numeric ID</small>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">General Prompt</label>
                                        <textarea name="general_prompt" class="form-control" rows="4" placeholder="Enter the prompt for content generation" required><?php echo escape($bot['general_prompt'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Posting Schedule (HH:MM format)</label>
                                        <input type="text" name="schedule_times" class="form-control" placeholder="09:00, 14:30, 20:00" 
                                               value="<?php echo escape(implode(', ', Scheduler::parseSchedule($bot['schedule_json'] ?? '[]'))); ?>">
                                        <small class="text-muted">Comma-separated times (e.g., 09:00, 14:30, 20:00)</small>
                                    </div>

                                    <div class="mb-3 form-check">
                                        <input type="checkbox" name="is_enabled" value="1" class="form-check-input" 
                                               <?php echo ($bot['is_enabled'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label">Enable this bot</label>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save"></i> <?php echo $botId ? 'Update' : 'Create'; ?> Bot
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <?php if ($botId): ?>
                        <div class="col-lg-4">
                            <!-- Bot Actions -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0">Actions</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" class="mb-2">
                                        <input type="hidden" name="csrf_token" value="<?php echo escape($csrfToken); ?>">
                                        <input type="hidden" name="action" value="verify_channel">
                                        <button type="submit" class="btn btn-warning w-100 mb-2">
                                            <i class="fas fa-check"></i> Verify Channel
                                        </button>
                                    </form>

                                    <form method="POST" class="mb-2">
                                        <input type="hidden" name="csrf_token" value="<?php echo escape($csrfToken); ?>">
                                        <input type="hidden" name="action" value="set_webhook">
                                        <button type="submit" class="btn btn-info w-100 mb-2">
                                            <i class="fas fa-link"></i> Set Webhook
                                        </button>
                                    </form>

                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?php echo escape($csrfToken); ?>">
                                        <input type="hidden" name="action" value="post_now">
                                        <button type="submit" class="btn btn-success w-100">
                                            <i class="fas fa-paper-plane"></i> Post Now
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <!-- Bot Status -->
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Status</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="text-muted">Enabled</label>
                                        <div>
                                            <span class="badge bg-<?php echo $bot['is_enabled'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $bot['is_enabled'] ? 'Yes' : 'No'; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="text-muted">Verified</label>
                                        <div>
                                            <span class="badge bg-<?php echo $bot['is_verified'] ? 'success' : 'warning'; ?>">
                                                <?php echo $bot['is_verified'] ? 'Yes' : 'No'; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="text-muted">Last Post</label>
                                        <div><?php echo formatRelativeTime($bot['last_post_at']); ?></div>
                                    </div>
                                    <div>
                                        <label class="text-muted">Created</label>
                                        <div><?php echo formatDate($bot['created_at']); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
