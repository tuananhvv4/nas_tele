/**
 * Real-Time Payment Checker
 * Add this script to your QR code display page
 */

class PaymentChecker {
    constructor(transactionCode, amount, onSuccess) {
        this.transactionCode = transactionCode;
        this.amount = amount;
        this.onSuccess = onSuccess;
        this.checkInterval = null;
        this.checkCount = 0;
        this.maxChecks = 120; // 10 minutes (120 * 5 seconds)
    }

    start() {
        console.log('Starting payment checker...');
        this.updateStatus('⏳ Đang chờ thanh toán...');

        // Check immediately
        this.checkPayment();

        // Then check every 5 seconds
        this.checkInterval = setInterval(() => {
            this.checkPayment();
        }, 5000);
    }

    stop() {
        if (this.checkInterval) {
            clearInterval(this.checkInterval);
            this.checkInterval = null;
        }
    }

    async checkPayment() {
        this.checkCount++;

        if (this.checkCount > this.maxChecks) {
            this.stop();
            this.updateStatus('⏰ Hết thời gian chờ. Vui lòng làm mới trang.');
            return;
        }

        try {
            const response = await fetch(`/api/check-payment.php?transaction_code=${this.transactionCode}&amount=${this.amount}`);
            const data = await response.json();

            console.log('Payment check result:', data);

            if (data.success && data.verified) {
                this.stop();
                this.updateStatus('✅ Thanh toán thành công!');

                if (this.onSuccess) {
                    this.onSuccess(data.transaction);
                }
            } else {
                // Update status with time elapsed
                const elapsed = Math.floor(this.checkCount * 5 / 60);
                this.updateStatus(`⏳ Đang chờ thanh toán... (${elapsed} phút)`);
            }
        } catch (error) {
            console.error('Payment check error:', error);
        }
    }

    updateStatus(message) {
        const statusElement = document.getElementById('payment-status');
        if (statusElement) {
            statusElement.textContent = message;
            statusElement.className = 'payment-status ' + (message.includes('✅') ? 'success' : 'pending');
        }
    }
}

// Usage example:
// const checker = new PaymentChecker('ORDER12345678', 50000, (transaction) => {
//     console.log('Payment received!', transaction);
//     // Redirect to success page or show account
//     window.location.href = '/order-success.php?id=' + orderId;
// });
// checker.start();
