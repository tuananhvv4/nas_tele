<?php
/**
 * MBBank Payment Verification Helper
 * Connects to Node.js service on Render.com
 */

class MBBankPayment {
    private $serviceUrl;
    private $timeout = 30;
    private $credentials = null;
    private $pdo;
    
    public function __construct($pdo = null, $serviceUrl = null) {
        $this->pdo = $pdo ?: getDB();
        
        // Load settings from database
        $settings = $this->loadSettings();
        
        if ($settings) {
            $this->serviceUrl = $settings['service_url'] ?: 'https://mbbank-payment-service.onrender.com';
            
            // Load and decrypt credentials
            require_once __DIR__ . '/encryption.php';
            $this->credentials = [
                'username' => $settings['username'],
                'password' => decryptPassword($settings['password_encrypted']),
                'accountNumber' => $settings['account_number']
            ];
        } else {
            // Fallback to environment variables
            $this->serviceUrl = $serviceUrl ?: getenv('MBBANK_SERVICE_URL') ?: 'https://mbbank-payment-service.onrender.com';
        }
    }
    
    /**
     * Load MBBank settings from database
     */
    private function loadSettings() {
        try {
            $stmt = $this->pdo->query("SELECT * FROM mbbank_settings WHERE is_enabled = 1 LIMIT 1");
            return $stmt->fetch();
        } catch (Exception $e) {
            error_log("Error loading MBBank settings: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check single payment
     */
    public function checkPayment($transactionCode, $amount, $accountNumber = null) {
        $data = [
            'transactionCode' => $transactionCode,
            'amount' => $amount,
        ];
        
        // Add credentials if available
        if ($this->credentials) {
            $data['username'] = $this->credentials['username'];
            $data['password'] = $this->credentials['password'];
            $data['accountNumber'] = $accountNumber ?: $this->credentials['accountNumber'];
        } elseif ($accountNumber) {
            $data['accountNumber'] = $accountNumber;
        }
        
        $response = $this->makeRequest('/check-payment', $data);
        
        if ($response && $response['success']) {
            return [
                'verified' => $response['verified'] ?? false,
                'transaction' => $response['transaction'] ?? null,
            ];
        }
        
        return ['verified' => false, 'transaction' => null];
    }
    
    /**
     * Check multiple payments (batch)
     */
    public function checkPaymentsBatch($orders) {
        $data = ['orders' => $orders];
        
        // Add credentials if available
        if ($this->credentials) {
            $data['username'] = $this->credentials['username'];
            $data['password'] = $this->credentials['password'];
            $data['accountNumber'] = $this->credentials['accountNumber'];
        }
        
        $response = $this->makeRequest('/check-payments-batch', $data);
        
        if ($response && $response['success']) {
            return $response['results'] ?? [];
        }
        
        return [];
    }
    
    /**
     * Get account balance
     */
    public function getBalance() {
        $response = $this->makeRequest('/balance', null, 'GET');
        
        if ($response && $response['success']) {
            return $response['balance'] ?? null;
        }
        
        return null;
    }
    
    /**
     * Make HTTP request to service
     */
    private function makeRequest($endpoint, $data = null, $method = 'POST') {
        $url = $this->serviceUrl . $endpoint;
        
        $ch = curl_init();
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen(json_encode($data))
            ]);
        }
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            error_log("MBBank API Error: " . $error);
            return null;
        }
        
        if ($httpCode !== 200) {
            error_log("MBBank API HTTP Error: " . $httpCode);
            return null;
        }
        
        return json_decode($response, true);
    }
}
