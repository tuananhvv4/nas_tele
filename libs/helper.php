<?php

include_once 'acb.php';

function getAcbTransaction($bank)
{
    $acb = new ACB();

    // Tài khoản, mật khẩu, số tài khoản ngân hàng ACB
    $username = $bank['account_username']; 
    $password = $bank['account_password'];
    $accountNumber = $bank['account_number'];

    $loginResult = $acb->login_acb($username, $password);

    if ($loginResult) {
        // Nếu $loginResult là một mảng, không cần giải mã
        if (is_array($loginResult)) {
            $response = $loginResult;
        } else {
            // Nếu là chuỗi JSON, cần giải mã
            $response = json_decode($loginResult, true);
        }

        if (isset($response['message'])) {
            // Trường hợp đăng nhập thất bại
            return [
                "success" => false,
                "message" => "Đăng nhập thất bại: " . $response['message']
            ];
        } else {
            // Đăng nhập thành công
            // Kiểm tra nếu accessToken có tồn tại
            if (isset($response['accessToken'])) {
                $accessToken = $response['accessToken'];

                // Lấy thông tin số dư và giao dịch bằng hàm get_balance
                $balanceResult = $acb->LSGD($accountNumber, 10, $accessToken);

                if ($balanceResult) {
                    // Kiểm tra nếu $balanceResult là mảng
                    if (is_array($balanceResult)) {
                        $balanceData = $balanceResult;
                    } else {
                        // Nếu là chuỗi JSON, cần giải mã
                        $balanceData = json_decode($balanceResult, true);
                    }

                    if (isset($balanceData['message'])) {
                        // Trường hợp lấy số dư thất bại
                        return [
                            "success" => false,
                            "message" => "Không thể lấy số dư: " . $balanceData['message']
                        ];
                    } else {
                        // Trả về kết quả theo định dạng yêu cầu
                        $response = [
                            "time" => date("c"),  // Thời gian hiện tại
                            "codeStatus" => 200,
                            "messageStatus" => "success",
                            "description" => "success",
                            "took" => 61,  // Thời gian thực tế có thể được tính từ server nếu cần
                            "data" => isset($balanceData['data']) ? $balanceData['data'] : [],  // Dữ liệu giao dịch
                            "redisTook" => 0  // Thông tin thêm về thời gian Redis nếu có
                        ];
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            // Nếu có lỗi, in ra lỗi
                            return [
                                "success" => false,
                                "message" => "Lỗi khi mã hóa JSON: " . json_last_error_msg()
                            ];
                        } else {
                            // Kết quả
                            return [
                                "success" => true,
                                "data" => $response,
                            ];
                        }
                    }
                } else {
                    return [
                        "success" => false,
                        "message" => "Không thể lấy thông tin số dư."
                    ];
                }
            } else {
                return [
                    "success" => false,
                    "message" => "Không tìm thấy accessToken trong dữ liệu phản hồi đăng nhập."
                ];
            }
        }
    } else {
        return [
            "success" => false,
            "message" => "Không thể thực hiện đăng nhập."
        ];
    }
}

/**
 * Tính toán thông tin phân trang.
 *
 * @param  PDO    $pdo          Kết nối database
 * @param  string $countQuery   Câu query COUNT(*) để đếm tổng bản ghi (không có LIMIT/OFFSET)
 * @param  array  $countParams  Tham số bind cho $countQuery
 * @param  int    $perPage      Số bản ghi mỗi trang (0 = hiển thị tất cả)
 * @param  int    $currentPage  Trang hiện tại (1-based), mặc định lấy từ $_GET['page']
 * @return array {
 *   total        int   - tổng số bản ghi
 *   perPage      int   - số bản ghi/trang
 *   currentPage  int   - trang hiện tại
 *   totalPages   int   - tổng số trang
 *   offset       int   - OFFSET để dùng trong query chính
 *   limit        int|null - LIMIT (null khi hiển thị tất cả)
 * }
 */
function paginate(PDO $pdo, string $countQuery, array $countParams = [], int $perPage = 10, int $currentPage = 0): array
{
    $stmt = $pdo->prepare($countQuery);
    $stmt->execute($countParams);
    $total = (int) $stmt->fetchColumn();

    if ($currentPage <= 0) {
        $currentPage = max(1, (int) ($_GET['page'] ?? 1));
    }

    if ($perPage <= 0) {
        // Hiển thị tất cả
        return [
            'total'       => $total,
            'perPage'     => $total,
            'currentPage' => 1,
            'totalPages'  => 1,
            'offset'      => 0,
            'limit'       => null,
        ];
    }

    $totalPages  = max(1, (int) ceil($total / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset      = ($currentPage - 1) * $perPage;

    return [
        'total'       => $total,
        'perPage'     => $perPage,
        'currentPage' => $currentPage,
        'totalPages'  => $totalPages,
        'offset'      => $offset,
        'limit'       => $perPage,
    ];
}

/**
 * Render HTML phân trang (Bootstrap-like).
 *
 * @param  array  $pagination  Kết quả trả về từ paginate()
 * @param  string $baseUrl     URL cơ sở (ví dụ: "orders.php?status=completed")
 *                             Hàm sẽ tự thêm &page=X vào cuối.
 * @param  int    $delta       Số trang hiển thị mỗi bên trang hiện tại (mặc định 2)
 * @return string HTML của thanh phân trang
 */
function renderPagination(array $pagination, string $baseUrl = '', int $delta = 2): string
{
    if ($pagination['totalPages'] <= 1) {
        return '';
    }

    $current    = $pagination['currentPage'];
    $total      = $pagination['totalPages'];
    $totalItems = $pagination['total'];
    $perPage    = $pagination['perPage'];

    // Nếu baseUrl chưa có tham số thì dùng ? ngược lại dùng &
    $sep = (str_contains($baseUrl, '?') ? '&' : '?');

    // Tạo link helper
    $link = fn(int $page) => htmlspecialchars($baseUrl . $sep . 'page=' . $page);

    // Tính dải trang hiển thị
    $start = max(1, $current - $delta);
    $end   = min($total, $current + $delta);

    // Luôn hiển thị đủ (2*delta+1) trang nếu có thể
    if ($end - $start < $delta * 2) {
        if ($start === 1) {
            $end = min($total, $start + $delta * 2);
        } else {
            $start = max(1, $end - $delta * 2);
        }
    }

    // Thông tin "Hiển thị X – Y / Z bản ghi"
    $from = ($current - 1) * $perPage + 1;
    $to   = min($current * $perPage, $totalItems);

    $html  = '<div class="pagination-wrapper">';
    $html .= '<div class="pagination-info">Hiển thị <strong>' . $from . ' – ' . $to . '</strong> / <strong>' . $totalItems . '</strong> bản ghi</div>';
    $html .= '<nav class="pagination-nav"><ul class="pagination">';

    // Nút "Trước"
    if ($current > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $link($current - 1) . '">‹</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">‹</span></li>';
    }

    // Trang đầu + dấu ...
    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $link(1) . '">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
    }

    // Dải trang chính
    for ($i = $start; $i <= $end; $i++) {
        if ($i === $current) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $link($i) . '">' . $i . '</a></li>';
        }
    }

    // Dấu ... + trang cuối
    if ($end < $total) {
        if ($end < $total - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">…</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . $link($total) . '">' . $total . '</a></li>';
    }

    // Nút "Sau"
    if ($current < $total) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $link($current + 1) . '">›</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">›</span></li>';
    }

    $html .= '</ul></nav></div>';

    return $html;
}

