<?php
/**
 * Wallet Helper Functions
 * Manage user wallet balance and transactions
 */

/**
 * Get user wallet balance
 */
function getWalletBalance($userId, $pdo) {
    $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    return $user ? floatval($user['wallet_balance']) : 0;
}

/**
 * Add funds to wallet
 * 
 * @param int $userId User ID
 * @param int $telegramId Telegram ID
 * @param float $amount Amount to add (VND)
 * @param string $type Transaction type (topup, promo, refund, admin_adjust)
 * @param string $description Transaction description
 * @param int|null $referenceId Reference ID (order, topup request, promo code)
 * @param PDO $pdo Database connection
 * @return bool Success status
 */
function addToWallet($userId, $telegramId, $amount, $type, $description, $referenceId = null, $pdo) {
    try {
        // Get current balance
        $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            error_log("Add to wallet error: User not found (ID: $userId)");
            return false;
        }
        
        $balanceBefore = floatval($user['wallet_balance']);
        $balanceAfter = $balanceBefore + $amount;
        
        // Update balance
        $stmt = $pdo->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
        $stmt->execute([$balanceAfter, $userId]);
        
        // Record transaction
        $stmt = $pdo->prepare("
            INSERT INTO wallet_transactions 
            (user_id, telegram_id, type, amount, balance_before, balance_after, description, reference_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $telegramId,
            $type,
            $amount,
            $balanceBefore,
            $balanceAfter,
            $description,
            $referenceId
        ]);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Add to wallet error: " . $e->getMessage());
        throw $e; // Re-throw so caller can handle
    }
}

/**
 * Deduct funds from wallet
 * 
 * @param int $userId User ID
 * @param int $telegramId Telegram ID
 * @param float $amount Amount to deduct (positive number)
 * @param string $type Transaction type (purchase)
 * @param string $description Transaction description
 * @param int $referenceId Optional reference ID (order ID)
 * @param PDO $pdo Database connection
 * @return array ['success' => bool, 'message' => string, 'new_balance' => float]
 */
function deductFromWallet($userId, $telegramId, $amount, $type, $description, $referenceId = null, $pdo) {
    try {
        $pdo->beginTransaction();
        
        // Get current balance
        $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $pdo->rollBack();
            return ['success' => false, 'message' => 'User not found', 'new_balance' => 0];
        }
        
        $balanceBefore = floatval($user['wallet_balance']);
        
        // Check sufficient balance
        if ($balanceBefore < $amount) {
            $pdo->rollBack();
            return [
                'success' => false, 
                'message' => 'Insufficient balance', 
                'new_balance' => $balanceBefore
            ];
        }
        
        $balanceAfter = $balanceBefore - $amount;
        
        // Update balance
        $stmt = $pdo->prepare("UPDATE users SET wallet_balance = ? WHERE id = ?");
        $stmt->execute([$balanceAfter, $userId]);
        
        // Record transaction (negative amount for debit)
        $stmt = $pdo->prepare("
            INSERT INTO wallet_transactions 
            (user_id, telegram_id, type, amount, balance_before, balance_after, description, reference_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId,
            $telegramId,
            $type,
            -$amount, // Negative for debit
            $balanceBefore,
            $balanceAfter,
            $description,
            $referenceId
        ]);
        
        $pdo->commit();
        return [
            'success' => true, 
            'message' => 'Success', 
            'new_balance' => $balanceAfter
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Deduct from wallet error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'new_balance' => 0];
    }
}

/**
 * Get wallet transaction history
 */
function getWalletTransactions($userId, $limit = 10, $pdo) {
    $stmt = $pdo->prepare("
        SELECT * FROM wallet_transactions 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
}
?>

