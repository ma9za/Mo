<?php
/**
 * Telegram API Manager
 * Handles all Telegram API interactions
 */

require_once __DIR__ . '/env.php';

class TelegramAPI {
    const API_BASE = 'https://api.telegram.org';

    /**
     * Make API request
     */
    private static function request($method, $token, $endpoint, $data = []) {
        $url = self::API_BASE . "/bot{$token}/{$endpoint}";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        if ($httpCode !== 200 || !$result['ok']) {
            throw new Exception("Telegram API Error: " . ($result['description'] ?? 'Unknown error'));
        }

        return $result['result'] ?? null;
    }

    /**
     * Set webhook
     */
    public static function setWebhook($token, $url, $secret) {
        return self::request($token, $token, 'setWebhook', [
            'url' => $url . '?secret=' . urlencode($secret),
            'allowed_updates' => ['message', 'channel_post']
        ]);
    }

    /**
     * Get chat information
     */
    public static function getChat($token, $chatId) {
        return self::request($token, $token, 'getChat', [
            'chat_id' => $chatId
        ]);
    }

    /**
     * Get chat member
     */
    public static function getChatMember($token, $chatId, $userId) {
        return self::request($token, $token, 'getChatMember', [
            'chat_id' => $chatId,
            'user_id' => $userId
        ]);
    }

    /**
     * Get bot info
     */
    public static function getMe($token) {
        return self::request($token, $token, 'getMe', []);
    }

    /**
     * Send message to channel
     */
    public static function sendMessage($token, $chatId, $text, $parseMode = 'HTML') {
        $response = self::request($token, $token, 'sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $parseMode
        ]);

        return $response['message_id'] ?? null;
    }

    /**
     * Edit message
     */
    public static function editMessage($token, $chatId, $messageId, $text, $parseMode = 'HTML') {
        return self::request($token, $token, 'editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => $parseMode
        ]);
    }

    /**
     * Delete message
     */
    public static function deleteMessage($token, $chatId, $messageId) {
        return self::request($token, $token, 'deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => $messageId
        ]);
    }

    /**
     * Verify webhook secret
     */
    public static function verifyWebhookSecret($providedSecret, $storedSecret) {
        return hash_equals($storedSecret, $providedSecret);
    }

    /**
     * Parse channel identifier (convert @channel to chat_id if needed)
     */
    public static function parseChannelId($channelInput) {
        // If it starts with @, it's a username
        if (strpos($channelInput, '@') === 0) {
            return $channelInput;
        }

        // Otherwise assume it's a numeric ID
        return (int)$channelInput;
    }
}
