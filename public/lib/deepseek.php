<?php
/**
 * DeepSeek API Manager
 * Handles AI content generation using DeepSeek API
 */

require_once __DIR__ . '/env.php';

class DeepSeekAPI {
    const API_BASE = 'https://api.deepseek.com';
    const DEFAULT_MODEL = 'deepseek-chat';

    /**
     * Generate content using DeepSeek
     */
    public static function generateContent($prompt, $apiKey = null, $model = null) {
        if ($apiKey === null) {
            $apiKey = env('DEEPSEEK_API_KEY');
        }

        if ($model === null) {
            $model = self::DEFAULT_MODEL;
        }

        if (empty($apiKey)) {
            throw new Exception("DeepSeek API key is not configured");
        }

        $systemPrompt = "You are an autonomous AI agent posting futuristic, controversial but safe Telegram posts.";

        $payload = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $systemPrompt
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.7,
            'max_tokens' => 500,
            'top_p' => 0.95
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::API_BASE . '/chat/completions');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("DeepSeek API Error: " . $error);
        }

        $result = json_decode($response, true);

        if ($httpCode !== 200) {
            $errorMsg = $result['error']['message'] ?? 'Unknown error';
            throw new Exception("DeepSeek API Error ({$httpCode}): " . $errorMsg);
        }

        if (empty($result['choices'][0]['message']['content'])) {
            throw new Exception("DeepSeek API returned empty content");
        }

        return trim($result['choices'][0]['message']['content']);
    }

    /**
     * Generate content for a bot
     */
    public static function generateBotContent($botData) {
        $apiKey = !empty($botData['deepseek_key_override']) 
            ? $botData['deepseek_key_override'] 
            : env('DEEPSEEK_API_KEY');

        $model = !empty($botData['model_override']) 
            ? $botData['model_override'] 
            : self::DEFAULT_MODEL;

        $prompt = $botData['general_prompt'] ?? 'Generate a futuristic Telegram post';

        return self::generateContent($prompt, $apiKey, $model);
    }

    /**
     * Test API connection
     */
    public static function testConnection($apiKey = null) {
        try {
            $content = self::generateContent('Say "API Connection Successful"', $apiKey);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
