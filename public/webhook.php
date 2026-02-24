<?php
/**
 * Telegram Webhook Handler
 * Receives updates from Telegram and processes them
 */

require_once __DIR__ . '/lib/env.php';
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/telegram.php';
require_once __DIR__ . '/lib/helpers.php';

// Get bot_id and secret from query parameters
$botId = (int)getGet('bot_id', 0);
$secret = getGet('secret', '');

if (empty($botId) || empty($secret)) {
    http_response_code(400);
    exit('Invalid request');
}

try {
    $db = Database::getInstance();

    // Get bot
    $bot = $db->fetch("SELECT * FROM bots WHERE id = ?", [$botId]);

    if (!$bot) {
        http_response_code(404);
        exit('Bot not found');
    }

    // Verify secret
    if (!TelegramAPI::verifyWebhookSecret($secret, $bot['webhook_secret'])) {
        http_response_code(403);
        exit('Invalid secret');
    }

    // Get webhook data
    $update = json_decode(file_get_contents('php://input'), true);

    if (empty($update)) {
        http_response_code(200);
        exit('OK');
    }

    // Log the update
    $db->insert('logs', [
        'bot_id' => $botId,
        'status' => 'received',
        'message' => json_encode($update),
        'created_at' => date('Y-m-d H:i:s')
    ]);

    // Process update
    if (isset($update['message'])) {
        $message = $update['message'];

        // Handle /start command
        if (isset($message['text']) && $message['text'] === '/start') {
            TelegramAPI::sendMessage(
                $bot['token'],
                $message['chat']['id'],
                "Welcome to " . escape($bot['name']) . "!\n\nThis is an AI-powered bot."
            );
        }
    }

    // Send OK response to Telegram
    http_response_code(200);
    echo 'OK';

} catch (Exception $e) {
    logMessage('Webhook Error: ' . $e->getMessage(), 'ERROR');
    http_response_code(500);
    exit('Error');
}
