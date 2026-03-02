<?php
/**
 * SePay API Helper Class
 * Official banking API integration for payment verification
 */

class SePay {
    private $db;
    private $apiToken;
    private $accountNumber;
    private $bankName;
    private $isEnabled;
    private $baseUrl = 'https://my.sepay.vn/userapi';
    
    public function __construct($db) {
        $this->db = $db;
        $this->loadSettings();
    }
    
    /**
     * Load SePay settings from database
     */
    private function loadSettings() {
        $stmt = $this->db->query("SELECT * FROM sepay_settings ORDER BY id DESC LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($settings) {
            $this->apiToken = $settings['api_token'];
            $this->accountNumber = $settings['account_number'];
            $this->bankName = $settings['bank_name'];
            $this->isEnabled = (bool)$settings['is_enabled'];
        }
    }
    
    /**
     * Make API request to SePay
     */
    private function apiRequest($endpoint, $params = []) {
        if (empty($this->apiToken)) {
            throw new Exception('SePay API token not configured');
        }
        
        $url = $this->baseUrl . $endpoint;
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiToken,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('SePay API error: ' . $error);
        }
        
        if ($httpCode !== 200) {
            throw new Exception('SePay API returned HTTP ' . $httpCode);
        }
        
        $data = json_decode($response, true);
        
        if (!$data || $data['status'] !== 200) {
            throw new Exception('SePay API error: ' . ($data['error'] ?? 'Unknown error'));
        }
        
        return $data;
    }
    
    /**
     * Get transactions list
     */
    public function getTransactions($params = []) {
        return $this->apiRequest('/transactions/list', $params);
    }
    
    /**
     * Get transaction details by ID
     */
    public function getTransactionDetails($transactionId) {
        return $this->apiRequest('/transactions/details/' . $transactionId);
    }
    
    /**
     * Count transactions
     */
    public function countTransactions($params = []) {
        return $this->apiRequest('/transactions/count', $params);
    }
    
    /**
     * Check if a specific transaction exists by transaction code
     */
    public function checkPayment($transactionCode, $amount) {
        if (!$this->isEnabled) {
            return [
                'success' => false,
                'message' => 'SePay is disabled'
            ];
        }
        
        try {
            // Get recent transactions (last 2 hours)
            $fromDate = date('Y-m-d H:i:s', strtotime('-2 hours'));
            
            $result = $this->getTransactions([
                'account_number' => $this->accountNumber,
                'transaction_date_min' => $fromDate,
                'limit' => 100
            ]);
            
            if (!isset($result['transactions'])) {
                return [
                    'success' => false,
                    'message' => 'No transactions found'
                ];
            }
            
            // Find matching transaction
            foreach ($result['transactions'] as $transaction) {
                // Check if transaction content contains the code
                $content = strtoupper($transaction['transaction_content'] ?? $transaction['content'] ?? '');
                $code = strtoupper($transactionCode);
                
                if (strpos($content, $code) !== false) {
                    // Check amount matches (allow small difference)
                    $transactionAmount = floatval($transaction['amount_in']);
                    $expectedAmount = floatval($amount);
                    
                    if (abs($transactionAmount - $expectedAmount) < 1) {
                        return [
                            'success' => true,
                            'verified' => true,
                            'transaction' => $transaction
                        ];
                    }
                }
            }
            
            return [
                'success' => true,
                'verified' => false,
                'message' => 'Transaction not found'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Process webhook data
     */
    public function processWebhook($data) {
        try {
            // Log webhook
            $this->logWebhook($data);
            
            // Extract transaction code from content
            $content = $data['content'] ?? $data['description'] ?? '';
            $transactionCode = $this->extractTransactionCode($content);
            
            if (!$transactionCode) {
                return [
                    'success' => true,
                    'message' => 'No transaction code found'
                ];
            }
            
            // First, try to find matching wallet topup request
            $stmt = $this->db->prepare("
                SELECT * FROM wallet_topup_requests 
                WHERE transaction_code = ? 
                AND payment_status = 'pending'
                LIMIT 1
            ");
            $stmt->execute([$transactionCode]);
            $walletTopup = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($walletTopup) {
                // Process wallet topup
                return $this->processWalletTopup($walletTopup, $data);
            }
            
            // If not wallet topup, try to find matching order
            $stmt = $this->db->prepare("
                SELECT * FROM orders 
                WHERE transaction_code = ? 
                AND payment_status = 'pending'
                LIMIT 1
            ");
            $stmt->execute([$transactionCode]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$order) {
                return [
                    'success' => true,
                    'message' => 'No matching order or wallet topup found'
                ];
            }
            
            // Verify amount
            $transactionAmount = floatval($data['transferAmount']);
            $orderAmount = floatval($order['total_price']);
            
            if (abs($transactionAmount - $orderAmount) >= 1) {
                return [
                    'success' => true,
                    'message' => 'Amount mismatch'
                ];
            }
            
            // Mark order as paid
            $this->completeOrder($order['id'], $data);
            
            // Update webhook log status
            $this->updateWebhookStatus($data['id'], 'matched', $order['id']);
            
            return [
                'success' => true,
                'message' => 'Order completed successfully'
            ];
            
        } catch (Exception $e) {
            error_log('SePay webhook error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Log webhook to database
     */
    private function logWebhook($data) {
        $stmt = $this->db->prepare("
            INSERT INTO sepay_webhook_logs 
            (sepay_transaction_id, gateway, account_number, amount, content, 
             reference_code, transaction_date, transfer_type, raw_data)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            raw_data = VALUES(raw_data)
        ");
        
        $stmt->execute([
            $data['id'],
            $data['gateway'] ?? 'Unknown',
            $data['accountNumber'] ?? '',
            $data['transferAmount'] ?? 0,
            $data['content'] ?? $data['description'] ?? '',
            $data['referenceCode'] ?? null,
            $data['transactionDate'] ?? date('Y-m-d H:i:s'),
            $data['transferType'] ?? 'in',
            json_encode($data)
        ]);
    }
    
    /**
     * Update webhook log status
     */
    private function updateWebhookStatus($transactionId, $status, $orderId = null) {
        $stmt = $this->db->prepare("
            UPDATE sepay_webhook_logs 
            SET status = ?, order_id = ?
            WHERE sepay_transaction_id = ?
        ");
        $stmt->execute([$status, $orderId, $transactionId]);
    }
    
    /**
     * Extract transaction code from content
     */
    private function extractTransactionCode($content) {
        // Get prefix from payment settings
        $stmt = $this->db->query("SELECT transaction_prefix FROM payment_settings WHERE id = 1");
        $prefix = $stmt->fetchColumn() ?: 'ORDER';
        
        // Look for PREFIX followed by numbers (e.g., QUOCCHEAI12345678)
        $pattern = '/' . preg_quote($prefix, '/') . '[A-Z0-9]+/i';
        if (preg_match($pattern, $content, $matches)) {
            return strtoupper($matches[0]);
        }
        return null;
    }
    
    /**
     * Complete order and send account
     */
    private function completeOrder($orderId, $transactionData) {
        // Get order details first
        $stmt = $this->db->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            return;
        }
        
        // Assign account if not already assigned
        if (empty($order['account_id'])) {
            $quantity = $order['quantity'] ?? 1;
            $productId = $order['product_id'];
            
            $accountStmt = $this->db->prepare("
                SELECT id FROM product_accounts 
                WHERE product_id = ? AND is_sold = 0 
                LIMIT ?
            ");
            $accountStmt->execute([$productId, $quantity]);
            $accounts = $accountStmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($accounts) >= $quantity) {
                // Assign first account to order (for backward compatibility)
                $this->db->prepare("UPDATE orders SET account_id = ? WHERE id = ?")->execute([$accounts[0], $orderId]);
                
                // Mark all accounts as sold AND link them to this order
                foreach ($accounts as $accountId) {
                    $this->db->prepare("
                        UPDATE product_accounts 
                        SET is_sold = 1, sold_at = NOW(), order_id = ? 
                        WHERE id = ?
                    ")->execute([$orderId, $accountId]);
                }
            }
        }
        
        // Update order status AND payment_status
        $stmt = $this->db->prepare("
            UPDATE orders 
            SET status = 'completed',
                payment_status = 'completed',
                payment_verified_at = NOW(),
                payment_reference = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $transactionData['referenceCode'] ?? $transactionData['id'],
            $orderId
        ]);
        
        // Get updated order with account data
        $stmt = $this->db->prepare("
            SELECT o.*, p.name as product_name, pa.account_data
            FROM orders o
            JOIN products p ON o.product_id = p.id
            LEFT JOIN product_accounts pa ON o.account_id = pa.id
            WHERE o.id = ?
        ");
        $stmt->execute([$orderId]);
        $orderData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Send account to user via Telegram
        if ($orderData && $orderData['telegram_id']) {
            require_once __DIR__ . '/telegram.php';
            $telegram = new TelegramNotifier();
            $telegram->sendAccountToUser($orderData);
        }
    }
    
    /**
     * Process wallet topup request
     */
    private function processWalletTopup($walletTopup, $webhookData) {
        try {
            // Verify amount
            $transactionAmount = floatval($webhookData['transferAmount']);
            $topupAmount = floatval($walletTopup['amount']);
            
            if (abs($transactionAmount - $topupAmount) >= 1) {
                return [
                    'success' => true,
                    'message' => 'Amount mismatch for wallet topup'
                ];
            }
            
            $this->db->beginTransaction();
            
            // Credit user wallet
            require_once __DIR__ . '/wallet_helper.php';
            $creditResult = addToWallet(
                $walletTopup['user_id'],
                $walletTopup['telegram_id'],
                $walletTopup['amount'],
                'topup',
                "Nạp tiền vào ví - Mã GD: {$walletTopup['transaction_code']}",
                $walletTopup['id'],
                $this->db
            );
            
            if (!$creditResult) {
                throw new Exception("Failed to credit wallet");
            }
            
            // Update wallet topup request status
            $stmt = $this->db->prepare("
                UPDATE wallet_topup_requests 
                SET payment_status = 'completed', 
                    completed_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$walletTopup['id']]);
            
            // Update webhook log status
            $this->updateWebhookStatus($webhookData['id'], 'matched_wallet', $walletTopup['id']);
            
            $this->db->commit();
            
            // Send success notification to user via Telegram
            if ($walletTopup['telegram_id']) {
                require_once __DIR__ . '/../bot/TelegramBot.php';
                $botConfig = $this->db->query("SELECT * FROM bots WHERE id = 1")->fetch();
                
                if ($botConfig && $botConfig['is_configured']) {
                    $bot = new TelegramBot($botConfig['bot_token']);
                    
                    // Delete old QR and payment messages
                    if (!empty($walletTopup['qr_message_id'])) {
                        try {
                            $bot->deleteMessage($walletTopup['telegram_id'], $walletTopup['qr_message_id']);
                        } catch (Exception $e) {
                            error_log("Failed to delete QR message: " . $e->getMessage());
                        }
                    }
                    
                    if (!empty($walletTopup['payment_message_id'])) {
                        try {
                            $bot->deleteMessage($walletTopup['telegram_id'], $walletTopup['payment_message_id']);
                        } catch (Exception $e) {
                            error_log("Failed to delete payment message: " . $e->getMessage());
                        }
                    }
                    
                    // Get new balance
                    $stmt = $this->db->prepare("SELECT wallet_balance FROM users WHERE id = ?");
                    $stmt->execute([$walletTopup['user_id']]);
                    $newBalance = $stmt->fetchColumn();
                    
                    $message = "✅ <b>NẠP TIỀN THÀNH CÔNG!</b>\n\n";
                    $message .= "💰 <b>Số tiền:</b> " . number_format($walletTopup['amount'], 0, ',', '.') . " VNĐ\n";
                    $message .= "💳 <b>Số dư mới:</b> " . number_format($newBalance, 0, ',', '.') . " VNĐ\n\n";
                    $message .= "📋 <b>Mã GD:</b> <code>{$walletTopup['transaction_code']}</code>\n";
                    $message .= "⏰ " . date('d/m/Y H:i') . "\n\n";
                    $message .= "━━━━━━━━━━━━━━━━━━━\n";
                    $message .= "🎉 Tiền đã được cộng vào ví của bạn!\n";
                    $message .= "Sử dụng /sodu để kiểm tra số dư.";
                    
                    $bot->sendMessage($walletTopup['telegram_id'], $message);
                }
            }
            
            return [
                'success' => true,
                'message' => 'Wallet topup completed successfully'
            ];
            
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log('Wallet topup error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Test API connection
     */
    public function testConnection() {
        try {
            $result = $this->countTransactions();
            return [
                'success' => true,
                'message' => 'Kết nối thành công! Tổng số giao dịch: ' . ($result['count_transactions'] ?? 0)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
