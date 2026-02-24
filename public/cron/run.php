<?php
/**
 * Cron Job Runner
 * Executes scheduled posts for all enabled bots
 */

require_once __DIR__ . '/../lib/env.php';
require_once __DIR__ . '/../lib/db.php';
require_once __DIR__ . '/../lib/telegram.php';
require_once __DIR__ . '/../lib/deepseek.php';
require_once __DIR__ . '/../lib/scheduler.php';
require_once __DIR__ . '/../lib/helpers.php';

// Set timezone
date_default_timezone_set(env('TIMEZONE', 'UTC'));

try {
    $db = Database::getInstance();

    // Get bots that need to post
    $botsToPost = Scheduler::getBotsToPost();

    if (empty($botsToPost)) {
        logMessage('No bots to post at this time');
        exit('No bots to post');
    }

    // Process each bot
    foreach ($botsToPost as $bot) {
        try {
            // Generate content
            $content = DeepSeekAPI::generateBotContent($bot);

            // Send to Telegram
            $messageId = TelegramAPI::sendMessage(
                $bot['token'],
                $bot['channel_id'],
                $content
            );

            // Log success
            $db->insert('logs', [
                'bot_id' => $bot['id'],
                'status' => 'success',
                'message' => 'Scheduled post: ' . substr($content, 0, 100),
                'telegram_message_id' => $messageId,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // Update last post time
            $db->update('bots', [
                'last_post_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$bot['id']]);

            logMessage('Posted for bot: ' . $bot['name']);

        } catch (Exception $e) {
            // Log error
            $db->insert('logs', [
                'bot_id' => $bot['id'],
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage(),
                'created_at' => date('Y-m-d H:i:s')
            ]);

            logMessage('Error posting for bot ' . $bot['name'] . ': ' . $e->getMessage(), 'ERROR');
        }
    }

    echo 'Cron job completed successfully';

} catch (Exception $e) {
    logMessage('Cron Error: ' . $e->getMessage(), 'ERROR');
    http_response_code(500);
    exit('Error');
}
