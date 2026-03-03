<?php
/**
 * Telegram Bot Helper Class
 */

class TelegramBot {
    private $token;
    private $apiUrl;

    public function __construct($token) {
        $this->token = $token;
        $this->apiUrl = "https://api.telegram.org/bot{$token}/";
    }

    /**
     * Send message to user
     */
    public function sendMessage($chatId, $text, $options = []) {
        $data = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $options['parse_mode'] ?? 'HTML'
        ];

        // Handle keyboard if provided
        if (isset($options['reply_markup'])) {
            $data['reply_markup'] = $options['reply_markup'];
        } elseif (isset($options['keyboard'])) {
            // Legacy support for keyboard parameter
            $data['reply_markup'] = json_encode([
                'inline_keyboard' => $options['keyboard']
            ]);
        }

        return $this->apiRequest('sendMessage', $data);
    }

    /**
     * Answer callback query
     */
    public function answerCallbackQuery($callbackQueryId, $text = '') {
        return $this->apiRequest('answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text' => $text
        ]);
    }

    /**
     * Send photo
     */
    public function sendPhoto($chatId, $photoUrl, $caption = '') {
        $data = [
            'chat_id' => $chatId,
            'photo' => $photoUrl
        ];
        
        if ($caption) {
            $data['caption'] = $caption;
            $data['parse_mode'] = 'HTML';
        }
        
        return $this->apiRequest('sendPhoto', $data);
    }

    /**
     * Edit message
     */
    public function editMessage($chatId, $messageId, $text, $keyboard = null) {
        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'HTML'
        ];

        if ($keyboard) {
            $data['reply_markup'] = json_encode([
                'inline_keyboard' => $keyboard
            ]);
        }

        return $this->apiRequest('editMessageText', $data);
    }

    /**
     * Get bot info
     */
    public function getMe() {
        $result = $this->apiRequest('getMe', []);
        return $result['result'] ?? null;
    }

    /**
     * Get webhook info
     */
    public function getWebhookInfo() {
        $result = $this->apiRequest('getWebhookInfo', []);
        return $result['result'] ?? null;
    }

    /**
     * Set webhook
     */
    public function setWebhook($url) {
        $result = $this->apiRequest('setWebhook', ['url' => $url]);
        return $result['ok'] ?? false;
    }

    /**
     * Delete webhook
     */
    public function deleteWebhook() {
        $result = $this->apiRequest('deleteWebhook', []);
        return $result['ok'] ?? false;
    }

    /**
     * Delete message
     * @param int $chatId Chat ID
     * @param int $messageId Message ID to delete
     * @return bool Success status
     */
    public function deleteMessage($chatId, $messageId) {
        $data = [
            'chat_id' => $chatId,
            'message_id' => $messageId
        ];
        
        $result = $this->apiRequest('deleteMessage', $data);
        return $result['ok'] ?? false;
    }

    /**
     * Send document (file) to user
     * @param int    $chatId   Chat ID
     * @param string $filePath Đường dẫn file trên server
     * @param string $caption  Caption cho file (hỗ trợ HTML)
     * @param array  $options  Tuỳ chọn thêm (reply_markup, parse_mode...)
     * @return array|null
     */
    public function sendDocument($chatId, $filePath, $caption = '', $options = []) {
        $data = [
            'chat_id' => $chatId,
            'document' => new \CURLFile($filePath),
        ];

        if ($caption) {
            $data['caption'] = $caption;
            $data['parse_mode'] = $options['parse_mode'] ?? 'HTML';
        }

        if (isset($options['reply_markup'])) {
            $data['reply_markup'] = $options['reply_markup'];
        }

        return $this->apiRequestMultipart('sendDocument', $data);
    }

    /**
     * API request dạng multipart/form-data (dùng cho upload file)
     */
    private function apiRequestMultipart($method, $data) {
        $url = $this->apiUrl . $method;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $data, // CURLFile cần truyền array, không encode
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            // Không set Content-Type header → cURL tự dùng multipart/form-data
        ]);

        $result = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($result === false || $curlError) {
            error_log("ERROR: TelegramBot cURL multipart failed [{$method}]: " . $curlError);
            return null;
        }

        $decoded = json_decode($result, true);

        if (!$decoded) {
            error_log("ERROR: TelegramBot failed to decode JSON [{$method}]");
            return null;
        }

        if (!isset($decoded['ok']) || !$decoded['ok']) {
            error_log("ERROR: Telegram API error [{$method}]: " . ($decoded['description'] ?? 'Unknown error'));
        }

        return $decoded;
    }

    /**
     * Make API request using cURL with timeout
     */
    private function apiRequest($method, $data) {
        $url = $this->apiUrl . $method;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,          // Max 10 giây cho toàn bộ request
            CURLOPT_CONNECTTIMEOUT => 5,           // Max 5 giây để kết nối
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $result = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($result === false || $curlError) {
            error_log("ERROR: TelegramBot cURL failed [{$method}]: " . $curlError);
            return null;
        }

        $decoded = json_decode($result, true);

        if (!$decoded) {
            error_log("ERROR: TelegramBot failed to decode JSON [{$method}]");
            return null;
        }

        if (!isset($decoded['ok']) || !$decoded['ok']) {
            error_log("ERROR: Telegram API error [{$method}]: " . ($decoded['description'] ?? 'Unknown error'));
        }

        return $decoded;
    }
}
