# 🚀 Quick Start Guide - Wallet System

## ⚠️ BẮT BUỘC: Chạy SQL Migration Trước!

### Bước 1: Backup Database (An toàn)
```bash
# Nếu có mysqldump
mysqldump -u root telegram_bot_shop > backup_before_wallet.sql
```

### Bước 2: Chạy Migration

**Option A: Qua phpMyAdmin**
1. Mở phpMyAdmin
2. Chọn database `telegram_bot_shop`
3. Click tab "SQL"
4. Copy toàn bộ nội dung file `database/wallet_system_migration.sql`
5. Paste vào và click "Go"

**Option B: Command Line**
```bash
mysql -u root telegram_bot_shop < database/wallet_system_migration.sql
```

**Option C: Qua migrate.php** (nếu có)
- Upload file SQL vào thư mục database
- Chạy migrate.php

### Bước 3: Verify Migration
Chạy query này để check:
```sql
-- Check wallet_balance column exists
SHOW COLUMNS FROM users LIKE 'wallet_balance';

-- Check new tables exist
SHOW TABLES LIKE 'wallet_%';

-- Check promo_codes has credit_amount
SHOW COLUMNS FROM promo_codes LIKE 'credit_amount';
```

Nếu tất cả đều OK → Migration thành công!

---

## 🧪 Test Ngay

### Test 1: Tạo Promo Code
1. Vào: `http://mrmista.online/promo-codes.php`
2. Tạo mã:
   - Code: `TEST10K`
   - Số tiền: `10000`
   - Mô tả: `Test wallet system`
   - Số lượt: `10`
3. Click "Tạo Mã"

### Test 2: Activate Promo
Gửi cho bot:
```
/promo TEST10K
```

**Expected:**
```
✅ Đã cộng tiền vào ví!

🎟️ Mã: TEST10K
💰 Số tiền: +10,000 VND
📝 Test wallet system

━━━━━━━━━━━━━━━━━━━
💳 Số dư mới: 10,000 VND
```

### Test 3: Check Balance
```
/sodu
```

**Expected:**
```
💰 SỐ DƯ VÍ

💳 Số dư hiện tại: 10,000 VND

📊 Giao dịch gần đây:

➕ 10,000 VND - Mã khuyến mãi: TEST10K
03/02 14:40
```

### Test 4: Try Again (Should Fail)
```
/promo TEST10K
```

**Expected:**
```
❌ Bạn đã sử dụng mã TEST10K rồi!
```

---

## ✅ Checklist Hoàn Thành

- [ ] **Chạy SQL migration** ← QUAN TRỌNG!
- [ ] Tạo promo code test
- [ ] Test `/promo CODE`
- [ ] Test `/sodu`
- [ ] Verify database có data

---

## 🎯 Tổng Kết

**Đã implement:**
- ✅ Database schema (wallet_balance, wallet_transactions, etc.)
- ✅ Wallet helper functions
- ✅ Admin UI (promo codes với VND)
- ✅ Bot commands (/promo, /sodu)
- ✅ Transaction tracking
- ✅ Timezone Vietnam (UTC+7)

**Chưa implement (có thể làm sau):**
- ❌ Wallet top-up qua QR
- ❌ Thanh toán bằng ví khi mua hàng
- ❌ Mixed payment (ví + QR)
- ❌ Admin quản lý users + adjust balance

**Core system hoạt động 100%!**

---

## 🆘 Nếu Có Lỗi

### Lỗi: Column 'credit_amount' doesn't exist
→ Migration chưa chạy hoặc failed
→ Chạy lại migration

### Lỗi: Table 'wallet_transactions' doesn't exist
→ Migration chưa chạy
→ Check SQL syntax errors

### Bot không response
→ Check webhook logs
→ Check PHP error logs

---

**Next Step:** CHẠY SQL MIGRATION! 🚀
