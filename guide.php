<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pageTitle = 'Guide';
include __DIR__ . '/includes/header.php';
?>

<style>
        .standalone-guide {
            margin-left: var(--sidebar-width, 280px);
            padding: 40px;
            min-height: 100vh;
        }
        
        .guide-header {
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border-color, #2a2d3e);
        }
        
        .guide-header h1 {
            color: var(--text-primary, #fff);
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .guide-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid var(--border-color, #2a2d3e);
        }
        
        .guide-tab {
            padding: 15px 30px;
            background: none;
            border: none;
            color: var(--text-secondary, #a0a0a0);
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .guide-tab:hover {
            color: var(--text-primary, #fff);
        }
        
        .guide-tab.active {
            color: var(--primary, #667eea);
            border-bottom-color: var(--primary, #667eea);
        }
        
        .guide-content {
            display: none;
        }
        
        .guide-content.active {
            display: block;
            animation: fadeIn 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .guide-section {
            background: var(--card-bg, #1e2139);
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 25px;
            border: 1px solid var(--border-color, #2a2d3e);
        }
        
        .guide-section h3 {
            color: var(--primary, #667eea);
            margin-bottom: 20px;
            font-size: 24px;
        }
        
        .guide-section h4 {
            color: var(--text-primary, #fff);
            margin-top: 25px;
            margin-bottom: 15px;
            font-size: 18px;
            font-weight: 600;
        }
        
        .guide-section p, .guide-section li {
            color: var(--text-secondary, #a0a0a0);
            line-height: 1.6;
        }
        
        .guide-step {
            background: rgba(102, 126, 234, 0.05);
            border-left: 4px solid var(--primary, #667eea);
            padding: 20px;
            margin: 15px 0;
            border-radius: 8px;
        }
        
        .guide-step-number {
            display: inline-block;
            background: var(--primary, #667eea);
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            text-align: center;
            line-height: 30px;
            font-weight: 700;
            margin-right: 10px;
        }
        
        .guide-tip {
            background: rgba(255, 193, 7, 0.1);
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 15px 0;
            border-radius: 8px;
        }
        
        .faq-item {
            background: var(--card-bg, #1e2139);
            border: 1px solid var(--border-color, #2a2d3e);
            border-radius: 8px;
            margin-bottom: 15px;
            overflow: hidden;
        }
        
        .faq-question {
            padding: 20px;
            cursor: pointer;
            font-weight: 600;
            color: var(--text-primary, #fff);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.3s;
        }
        
        .faq-question:hover {
            background: rgba(102, 126, 234, 0.05);
        }
        
        .faq-answer {
            padding: 0 20px;
            max-height: 0;
            overflow: hidden;
            transition: all 0.3s;
            color: var(--text-secondary, #a0a0a0);
        }
        
        .faq-item.active .faq-answer {
            padding: 20px;
            max-height: 500px;
        }
        
        .faq-item.active .faq-toggle {
            transform: rotate(180deg);
        }
        
        .faq-toggle {
            transition: transform 0.3s;
            color: var(--primary, #667eea);
        }
        
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: var(--primary, #667eea);
            text-decoration: none;
            font-weight: 600;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>

    <div class="standalone-guide">
        <a href="dashboard.php" class="back-link">← Quay lại Dashboard</a>
        
        <div class="guide-header">
            <h1>📚 Hướng Dẫn Sử Dụng</h1>
            <p style="color: var(--text-secondary, #a0a0a0);">Tài liệu chi tiết về cách sử dụng hệ thống</p>
        </div>

        <!-- Tabs -->
        <div class="guide-tabs">
            <button class="guide-tab active" onclick="switchTab('admin')">
                🖥️ Quản Trị Admin
            </button>
            <button class="guide-tab" onclick="switchTab('bot')">
                🤖 Telegram Bot
            </button>
            <button class="guide-tab" onclick="switchTab('faq')">
                ❓ FAQ
            </button>
        </div>

        <!-- Admin Guide -->
        <div id="admin-guide" class="guide-content active">
            <!-- Dashboard -->
            <div class="guide-section">
                <h3>� Dashboard - Tổng Quan</h3>
                <p>Dashboard là trang chủ hiển thị thống kê tổng quan về hệ thống của bạn.</p>
                
                <h4>Các chỉ số quan trọng:</h4>
                <ul>
                    <li><strong>👥 Tổng Người Dùng:</strong> Số lượng người dùng đã đăng ký qua Telegram Bot</li>
                    <li><strong>�📦 Tổng Sản Phẩm:</strong> Số lượng sản phẩm đang có trong hệ thống</li>
                    <li><strong>📋 Đơn Hàng:</strong> Tổng số đơn hàng và trạng thái</li>
                    <li><strong>💰 Doanh Thu:</strong> Tổng doanh thu từ các đơn hàng đã hoàn thành</li>
                </ul>
            </div>

            <!-- Product Management -->
            <div class="guide-section">
                <h3>📦 Quản Lý Sản Phẩm</h3>
                
                <h4>Thêm sản phẩm mới:</h4>
                <div class="guide-step">
                    <span class="guide-step-number">1</span>
                    <strong style="color: var(--text-primary, #fff);">Click nút "+ Thêm Sản Phẩm"</strong>
                    <p>Ở góc trên bên phải trang Sản Phẩm</p>
                </div>
                
                <div class="guide-step">
                    <span class="guide-step-number">2</span>
                    <strong style="color: var(--text-primary, #fff);">Điền thông tin sản phẩm</strong>
                    <ul style="margin-top: 10px;">
                        <li><strong>Tên Sản Phẩm:</strong> Tên hiển thị trong bot (VD: Netflix Premium)</li>
                        <li><strong>Mô Tả:</strong> Mô tả chi tiết về sản phẩm</li>
                        <li><strong>Danh Mục:</strong> Phân loại sản phẩm (VD: Streaming, Music)</li>
                        <li><strong>Giá (VND):</strong> Giá bán (VD: 50000)</li>
                    </ul>
                </div>
                
                <div class="guide-step">
                    <span class="guide-step-number">3</span>
                    <strong style="color: var(--text-primary, #fff);">Click "Thêm" để lưu</strong>
                </div>
                
                <h4>Thêm accounts vào sản phẩm:</h4>
                <div class="guide-step">
                    <span class="guide-step-number">1</span>
                    <strong style="color: var(--text-primary, #fff);">Click nút "Sửa" ở sản phẩm</strong>
                </div>
                
                <div class="guide-step">
                    <span class="guide-step-number">2</span>
                    <strong style="color: var(--text-primary, #fff);">Cuộn xuống phần "Quản Lý Accounts"</strong>
                </div>
                
                <div class="guide-step">
                    <span class="guide-step-number">3</span>
                    <strong style="color: var(--text-primary, #fff);">Thêm accounts theo 2 cách:</strong>
                    <p><strong>Cách 1:</strong> Nhập trực tiếp (mỗi dòng 1 account)</p>
                    <p style="background: rgba(0,0,0,0.3); padding: 10px; border-radius: 5px; font-family: monospace; margin: 10px 0;">
                        username1 password1<br>
                        username2|password2<br>
                        username3:password3
                    </p>
                    <p><strong>Cách 2:</strong> Upload file .txt</p>
                </div>
                
                <div class="guide-tip">
                    💡 <strong style="color: var(--text-primary, #fff);">Mẹo:</strong> Hỗ trợ 3 format: <code>user pass</code>, <code>user|pass</code>, <code>user:pass</code>
                </div>
                
                <h4>Sắp xếp thứ tự sản phẩm:</h4>
                <div class="guide-step">
                    <span class="guide-step-number">1</span>
                    <strong style="color: var(--text-primary, #fff);">Kéo icon ⋮⋮ để di chuyển sản phẩm</strong>
                    <p>Thứ tự này sẽ hiển thị trong Telegram Bot</p>
                </div>
                
                <div class="guide-step">
                    <span class="guide-step-number">2</span>
                    <strong style="color: var(--text-primary, #fff);">Thả chuột để lưu tự động</strong>
                </div>
            </div>

            <!-- User Management -->
            <div class="guide-section">
                <h3>👥 Quản Lý Người Dùng</h3>
                
                <h4>Xem thông tin người dùng:</h4>
                <p>Trang Users hiển thị danh sách tất cả người dùng đã sử dụng bot với các thông tin:</p>
                <ul>
                    <li><strong>Telegram ID:</strong> ID duy nhất của người dùng</li>
                    <li><strong>Username:</strong> Tên người dùng trên Telegram</li>
                    <li><strong>Số Dư Ví:</strong> Số tiền hiện có trong ví</li>
                    <li><strong>Đơn Hàng:</strong> Số đơn hàng đã hoàn thành</li>
                    <li><strong>Ngày Tham Gia:</strong> Thời gian đăng ký</li>
                </ul>
                
                <h4>Điều chỉnh số dư ví:</h4>
                <div class="guide-step">
                    <span class="guide-step-number">1</span>
                    <strong style="color: var(--text-primary, #fff);">Click nút "Điều chỉnh" ở người dùng</strong>
                </div>
                
                <div class="guide-step">
                    <span class="guide-step-number">2</span>
                    <strong style="color: var(--text-primary, #fff);">Chọn loại giao dịch:</strong>
                    <ul style="margin-top: 10px;">
                        <li><strong>Cộng thêm:</strong> Tăng số dư (VD: nạp tiền thủ công)</li>
                        <li><strong>Trừ đi:</strong> Giảm số dư (VD: hoàn tiền)</li>
                    </ul>
                </div>
                
                <div class="guide-step">
                    <span class="guide-step-number">3</span>
                    <strong style="color: var(--text-primary, #fff);">Nhập số tiền và lý do</strong>
                    <p>Lý do sẽ hiển thị trong lịch sử giao dịch</p>
                </div>
                
                <div class="guide-tip" style="background: rgba(244, 67, 54, 0.1); border-left-color: #f44336;">
                    ⚠️ <strong style="color: var(--text-primary, #fff);">Lưu ý:</strong> Người dùng sẽ nhận được thông báo qua Telegram Bot
                </div>
                
                <h4>Xem lịch sử giao dịch:</h4>
                <div class="guide-step">
                    <span class="guide-step-number">1</span>
                    <strong style="color: var(--text-primary, #fff);">Click nút "Lịch sử" ở người dùng</strong>
                </div>
                
                <div class="guide-step">
                    <span class="guide-step-number">2</span>
                    <strong style="color: var(--text-primary, #fff);">Xem chi tiết:</strong>
                    <ul style="margin-top: 10px;">
                        <li>Giao dịch ví (nạp tiền, điều chỉnh)</li>
                        <li>Đơn hàng đã mua</li>
                        <li>Thời gian và số tiền</li>
                    </ul>
                </div>
            </div>

            <!-- Order Management -->
            <div class="guide-section">
                <h3>📋 Quản Lý Đơn Hàng</h3>
                
                <h4>Theo dõi đơn hàng:</h4>
                <p>Trang Orders hiển thị tất cả đơn hàng với các trạng thái:</p>
                <ul>
                    <li><strong>Pending:</strong> Đang chờ thanh toán</li>
                    <li><strong>Completed:</strong> Đã thanh toán và giao hàng</li>
                    <li><strong>Cancelled:</strong> Đã hủy</li>
                </ul>
                
                <h4>Thông tin đơn hàng:</h4>
                <ul>
                    <li><strong>📦 Sản Phẩm:</strong> Tên sản phẩm và số lượng</li>
                    <li><strong>👤 Khách Hàng:</strong> Telegram username</li>
                    <li><strong>💰 Tổng Tiền:</strong> Giá × Số lượng</li>
                    <li><strong>📝 Mã GD:</strong> Mã giao dịch để đối chiếu</li>
                    <li><strong>🕐 Thời Gian:</strong> Ngày giờ tạo đơn</li>
                </ul>
            </div>

            <!-- Payment Settings -->
            <div class="guide-section">
                <h3>💳 Cài Đặt Thanh Toán</h3>
                
                <h4>Cấu hình SePay API:</h4>
                <div class="guide-step">
                    <span class="guide-step-number">1</span>
                    <strong style="color: var(--text-primary, #fff);">Vào trang "Payment Settings"</strong>
                </div>
                
                <div class="guide-step">
                    <span class="guide-step-number">2</span>
                    <strong style="color: var(--text-primary, #fff);">Nhập thông tin SePay:</strong>
                    <ul style="margin-top: 10px;">
                        <li><strong>API Token:</strong> Lấy từ <a href="https://my.sepay.vn" target="_blank" style="color: var(--primary, #667eea);">my.sepay.vn</a></li>
                        <li><strong>Account Number:</strong> Số tài khoản ngân hàng</li>
                        <li><strong>Bank Name:</strong> Tên ngân hàng</li>
                    </ul>
                </div>
                
                <div class="guide-step">
                    <span class="guide-step-number">3</span>
                    <strong style="color: var(--text-primary, #fff);">Click "Test Connection"</strong>
                    <p>Kiểm tra kết nối trước khi lưu</p>
                </div>
                
                <div class="guide-step">
                    <span class="guide-step-number">4</span>
                    <strong style="color: var(--text-primary, #fff);">Bật "Enable SePay" và lưu</strong>
                </div>
                
                <h4>Cấu hình VietQR:</h4>
                <div class="guide-step">
                    <span class="guide-step-number">1</span>
                    <strong style="color: var(--text-primary, #fff);">Nhập thông tin ngân hàng:</strong>
                    <ul style="margin-top: 10px;">
                        <li><strong>Bank Code:</strong> Mã ngân hàng (VD: MB, VCB, TCB)</li>
                        <li><strong>Account Number:</strong> Số tài khoản</li>
                        <li><strong>Account Name:</strong> Tên chủ tài khoản</li>
                        <li><strong>Transaction Prefix:</strong> Tiền tố mã GD (VD: SHOP)</li>
                    </ul>
                </div>
                
                <div class="guide-tip">
                    💡 <strong style="color: var(--text-primary, #fff);">Mẹo:</strong> Mã GD sẽ có dạng: SHOP12345678
                </div>
            </div>

            <!-- Bot Settings -->
            <div class="guide-section">
                <h3>🤖 Cài Đặt Bot</h3>
                
                <h4>Cấu hình Telegram Bot:</h4>
                <div class="guide-step">
                    <span class="guide-step-number">1</span>
                    <strong style="color: var(--text-primary, #fff);">Tạo bot với @BotFather</strong>
                    <p>Gửi <code>/newbot</code> và làm theo hướng dẫn</p>
                </div>
                
                <div class="guide-step">
                    <span class="guide-step-number">2</span>
                    <strong style="color: var(--text-primary, #fff);">Copy Bot Token</strong>
                    <p>Token có dạng: <code>123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11</code></p>
                </div>
                
                <div class="guide-step">
                    <span class="guide-step-number">3</span>
                    <strong style="color: var(--text-primary, #fff);">Vào trang "Bot Settings"</strong>
                    <p>Dán token vào và click "Save"</p>
                </div>
                
                <div class="guide-step">
                    <span class="guide-step-number">4</span>
                    <strong style="color: var(--text-primary, #fff);">Click "Set Webhook"</strong>
                    <p>Kết nối bot với server</p>
                </div>
                
                <div class="guide-tip" style="background: rgba(244, 67, 54, 0.1); border-left-color: #f44336;">
                    ⚠️ <strong style="color: var(--text-primary, #fff);">Quan trọng:</strong> Webhook URL phải là HTTPS
                </div>
            </div>
        </div>

        <!-- Bot Guide -->
        <div id="bot-guide" class="guide-content">
            <!-- Getting Started -->
            <div class="guide-section">
                <h3>� Bắt Đầu Sử Dụng Bot</h3>
                
                <h4>Khởi động bot:</h4>
                <div class="guide-step">
                    <span class="guide-step-number">1</span>
                    <strong style="color: var(--text-primary, #fff);">Tìm bot trên Telegram</strong>
                    <p>Tìm theo username bot (VD: @YourShopBot)</p>
                </div>
                
                <div class="guide-step">
                    <span class="guide-step-number">2</span>
                    <strong style="color: var(--text-primary, #fff);">Click "Start" hoặc gửi /start</strong>
                </div>
                
                <div class="guide-step">
                    <span class="guide-step-number">3</span>
                    <strong style="color: var(--text-primary, #fff);">Bot sẽ hiển thị menu chính</strong>
                    <p>Với các tùy chọn: Mua hàng, Ví, Đơn hàng, Hỗ trợ</p>
                </div>
            </div>

            <!-- Buying Products -->
            <div class="guide-section">
                <h3>�🛍️ Mua Sản Phẩm</h3>
                
                <h4>Quy trình mua hàng:</h4>
                <div class="guide-step">
                    <span class="guide-step-number">1</span>
                    <strong style="color: var(--text-primary, #fff);">Gửi /mua hoặc click "🛍️ Mua hàng"</strong>
                </div>
                
                <div class="guide-step">
                    <span class="guide-step-number">2</span>
                    <strong style="color: var(--text-primary, #fff);">Chọn sản phẩm muốn mua</strong>
                    <p>Bot hiển thị danh sách sản phẩm với giá và số lượng còn</p>
                </div>
                
                <div class="guide-step">
                    <span class="guide-step-number">3</span>
                    <strong style="color: var(--text-primary, #fff);">Chọn số lượng</strong>
                    <p>Tối đa bằng số lượng còn trong kho</p>
                </div>
                
                <div class="guide-step">
                    <span class="guide-step-number">4</span>
                    <strong style="color: var(--text-primary, #fff);">Chọn phương thức thanh toán:</strong>
                    <ul style="margin-top: 10px;">
                        <li><strong>💰 Ví:</strong> Thanh toán bằng số dư ví (nhanh nhất)</li>
                        <li><strong>🏦 Chuyển khoản:</strong> Quét mã QR để thanh toán</li>
                    </ul>
                </div>
                
                <div class="guide-step">
                    <span class="guide-step-number">5</span>
                    <strong style="color: var(--text-primary, #fff);">Hoàn tất thanh toán</strong>
                    <p>Bot sẽ tự động gửi thông tin tài khoản sau khi xác nhận</p>
                </div>
                
                <div class="guide-tip">
                    💡 <strong style="color: var(--text-primary, #fff);">Mẹo:</strong> Thanh toán bằng ví nhanh hơn, nhận hàng ngay lập tức!
                </div>
            </div>

            <!-- Wallet Management -->
            <div class="guide-section">
                <h3>💰 Quản Lý Ví</h3>
                
                <h4>Nạp tiền vào ví:</h4>
                <div class="guide-step">
                    <span class="guide-step-number">1</span>
                    <strong style="color: var(--text-primary, #fff);">Gửi /vi hoặc click "💰 Ví của tôi"</strong>
                </div>
                
                <div class="guide-step">
                    <span class="guide-step-number">2</span>
                    <strong style="color: var(--text-primary, #fff);">Click "💳 Nạp tiền"</strong>
                </div>
                
                <div class="guide-step">
                    <span class="guide-step-number">3</span>
                    <strong style="color: var(--text-primary, #fff);">Nhập số tiền muốn nạp</strong>
                    <p>Tối thiểu: 10.000 VND</p>
                </div>
                
                <div class="guide-step">
                    <span class="guide-step-number">4</span>
                    <strong style="color: var(--text-primary, #fff);">Quét mã QR để chuyển khoản</strong>
                    <p>Nội dung chuyển khoản đã được điền sẵn</p>
                </div>
                
                <div class="guide-step">
                    <span class="guide-step-number">5</span>
                    <strong style="color: var(--text-primary, #fff);">Chờ xác nhận tự động</strong>
                    <p>Thường mất 1-2 phút</p>
                </div>
                
                <h4>Xem lịch sử giao dịch:</h4>
                <div class="guide-step">
                    <span class="guide-step-number">1</span>
                    <strong style="color: var(--text-primary, #fff);">Trong menu Ví, click "📊 Lịch sử"</strong>
                </div>
                
                <div class="guide-step">
                    <span class="guide-step-number">2</span>
                    <strong style="color: var(--text-primary, #fff);">Xem chi tiết:</strong>
                    <ul style="margin-top: 10px;">
                        <li>Nạp tiền</li>
                        <li>Mua hàng</li>
                        <li>Điều chỉnh từ admin</li>
                    </ul>
                </div>
            </div>

            <!-- Order Management -->
            <div class="guide-section">
                <h3>📋 Quản Lý Đơn Hàng</h3>
                
                <h4>Xem đơn hàng:</h4>
                <div class="guide-step">
                    <span class="guide-step-number">1</span>
                    <strong style="color: var(--text-primary, #fff);">Gửi /donhang hoặc click "📋 Đơn hàng"</strong>
                </div>
                
                <div class="guide-step">
                    <span class="guide-step-number">2</span>
                    <strong style="color: var(--text-primary, #fff);">Chọn loại đơn hàng:</strong>
                    <ul style="margin-top: 10px;">
                        <li><strong>✅ Đã hoàn thành:</strong> Đơn đã thanh toán</li>
                        <li><strong>⏳ Đang chờ:</strong> Đơn chưa thanh toán</li>
                    </ul>
                </div>
                
                <div class="guide-step">
                    <span class="guide-step-number">3</span>
                    <strong style="color: var(--text-primary, #fff);">Click vào đơn hàng để xem chi tiết</strong>
                    <p>Bao gồm thông tin tài khoản đã mua</p>
                </div>
                
                <div class="guide-tip" style="background: rgba(244, 67, 54, 0.1); border-left-color: #f44336;">
                    ⚠️ <strong style="color: var(--text-primary, #fff);">Lưu ý:</strong> Lưu thông tin tài khoản ngay sau khi nhận, bot không lưu trữ lâu dài
                </div>
            </div>

            <!-- Support -->
            <div class="guide-section">
                <h3>💬 Hỗ Trợ</h3>
                
                <h4>Liên hệ admin:</h4>
                <div class="guide-step">
                    <span class="guide-step-number">1</span>
                    <strong style="color: var(--text-primary, #fff);">Gửi /hotro hoặc click "💬 Hỗ trợ"</strong>
                </div>
                
                <div class="guide-step">
                    <span class="guide-step-number">2</span>
                    <strong style="color: var(--text-primary, #fff);">Xem thông tin liên hệ</strong>
                    <p>Telegram admin, email, hoặc các kênh hỗ trợ khác</p>
                </div>
            </div>
        </div>

        <!-- FAQ -->
        <div id="faq-guide" class="guide-content">
            <div class="guide-section">
                <h3>❓ Câu Hỏi Thường Gặp</h3>
                
                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>Làm sao để thêm sản phẩm mới?</span>
                        <span class="faq-toggle">▼</span>
                    </div>
                    <div class="faq-answer">
                        <p>Vào trang <strong>Products</strong> → Click <strong>"+ Thêm Sản Phẩm"</strong> → Điền thông tin → Click <strong>"Thêm"</strong></p>
                        <p>Sau đó vào <strong>"Sửa"</strong> sản phẩm để thêm accounts.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>Tại sao đơn hàng không tự động xác nhận?</span>
                        <span class="faq-toggle">▼</span>
                    </div>
                    <div class="faq-answer">
                        <p>Kiểm tra các điểm sau:</p>
                        <ul>
                            <li>SePay API đã được cấu hình đúng chưa?</li>
                            <li>Webhook đã được set chưa?</li>
                            <li>Cronjob payment check có chạy không?</li>
                            <li>Mã giao dịch có đúng format không?</li>
                        </ul>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>Làm sao để điều chỉnh số dư ví cho user?</span>
                        <span class="faq-toggle">▼</span>
                    </div>
                    <div class="faq-answer">
                        <p>Vào trang <strong>Users</strong> → Tìm user → Click <strong>"Điều chỉnh"</strong> → Chọn loại (Cộng/Trừ) → Nhập số tiền và lý do → <strong>"Lưu"</strong></p>
                        <p>User sẽ nhận thông báo qua bot.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>Bot không phản hồi khi gửi lệnh?</span>
                        <span class="faq-toggle">▼</span>
                    </div>
                    <div class="faq-answer">
                        <p>Kiểm tra:</p>
                        <ul>
                            <li>Bot token có đúng không?</li>
                            <li>Webhook đã được set chưa? (Bot Settings → Set Webhook)</li>
                            <li>Server có lỗi không? (Check webhook errors)</li>
                            <li>SSL certificate có hợp lệ không?</li>
                        </ul>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>Làm sao để sắp xếp thứ tự sản phẩm trong bot?</span>
                        <span class="faq-toggle">▼</span>
                    </div>
                    <div class="faq-answer">
                        <p>Vào trang <strong>Products</strong> → Kéo icon <strong>⋮⋮</strong> để di chuyển sản phẩm lên/xuống → Thả chuột để lưu tự động</p>
                        <p>Thứ tự này sẽ hiển thị trong bot ngay lập tức.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>Accounts bị hiển thị "null null" trong đơn hàng?</span>
                        <span class="faq-toggle">▼</span>
                    </div>
                    <div class="faq-answer">
                        <p>Đây là lỗi format account data. Đảm bảo accounts được thêm theo format:</p>
                        <p style="background: rgba(0,0,0,0.3); padding: 10px; border-radius: 5px; font-family: monospace; margin: 10px 0;">
                            username password<br>
                            username|password<br>
                            username:password
                        </p>
                        <p>Không để trống hoặc format sai.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>Làm sao để backup dữ liệu?</span>
                        <span class="faq-toggle">▼</span>
                    </div>
                    <div class="faq-answer">
                        <p><strong>Backup database MySQL:</strong></p>
                        <p style="background: rgba(0,0,0,0.3); padding: 10px; border-radius: 5px; font-family: monospace; margin: 10px 0;">
                            mysqldump -u username -p database_name > backup.sql
                        </p>
                        <p><strong>Backup files:</strong></p>
                        <p style="background: rgba(0,0,0,0.3); padding: 10px; border-radius: 5px; font-family: monospace; margin: 10px 0;">
                            tar -czf backup.tar.gz /path/to/bot-website
                        </p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>Cronjob payment check hoạt động như thế nào?</span>
                        <span class="faq-toggle">▼</span>
                    </div>
                    <div class="faq-answer">
                        <p>Cronjob chạy mỗi 1-2 phút để:</p>
                        <ul>
                            <li>Kiểm tra các đơn hàng pending</li>
                            <li>Gọi SePay API để verify thanh toán</li>
                            <li>Tự động xác nhận và gửi account cho user</li>
                            <li>Hủy đơn hàng quá hạn (>30 phút)</li>
                        </ul>
                        <p><strong>Setup cronjob:</strong></p>
                        <p style="background: rgba(0,0,0,0.3); padding: 10px; border-radius: 5px; font-family: monospace; margin: 10px 0;">
                            */2 * * * * php /path/to/cron-check-payments.php
                        </p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>Làm sao để thay đổi tỷ giá USD sang VND?</span>
                        <span class="faq-toggle">▼</span>
                    </div>
                    <div class="faq-answer">
                        <p>Vào trang <strong>Payment Settings</strong> → Tìm phần <strong>"Exchange Rate"</strong> → Nhập tỷ giá mới → Click <strong>"Save"</strong></p>
                        <p>Tỷ giá này sẽ áp dụng cho tất cả sản phẩm và giao dịch mới.</p>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>Làm sao để xem log lỗi của bot?</span>
                        <span class="faq-toggle">▼</span>
                    </div>
                    <div class="faq-answer">
                        <p>Kiểm tra file log tại:</p>
                        <ul>
                            <li><strong>PHP errors:</strong> /var/log/php_errors.log</li>
                            <li><strong>Webhook errors:</strong> Check qua Telegram Bot API</li>
                            <li><strong>Custom logs:</strong> Xem trong code có error_log() statements</li>
                        </ul>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>User không nhận được thông báo từ bot?</span>
                        <span class="faq-toggle">▼</span>
                    </div>
                    <div class="faq-answer">
                        <p>Kiểm tra:</p>
                        <ul>
                            <li>User đã block bot chưa?</li>
                            <li>Bot có quyền gửi tin nhắn không?</li>
                            <li>Telegram ID của user có đúng không?</li>
                            <li>Check error logs để xem lỗi gửi tin nhắn</li>
                        </ul>
                    </div>
                </div>

                <div class="faq-item">
                    <div class="faq-question" onclick="toggleFAQ(this)">
                        <span>Làm sao để thêm admin mới?</span>
                        <span class="faq-toggle">▼</span>
                    </div>
                    <div class="faq-answer">
                        <p>Hiện tại cần thêm trực tiếp vào database:</p>
                        <p style="background: rgba(0,0,0,0.3); padding: 10px; border-radius: 5px; font-family: monospace; margin: 10px 0;">
                            INSERT INTO admins (username, password, role)<br>
                            VALUES ('newadmin', PASSWORD('password123'), 'admin');
                        </p>
                        <p>Hoặc tạo trang admin management trong admin panel.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>

    <script>
        function switchTab(tab) {
            document.querySelectorAll('.guide-content').forEach(content => {
                content.classList.remove('active');
            });
            
            document.querySelectorAll('.guide-tab').forEach(tabBtn => {
                tabBtn.classList.remove('active');
            });
            
            document.getElementById(tab + '-guide').classList.add('active');
            event.target.classList.add('active');
        }
        
        function toggleFAQ(element) {
            const faqItem = element.parentElement;
            faqItem.classList.toggle('active');
        }
    </script>

<?php include __DIR__ . '/includes/footer.php'; ?>
