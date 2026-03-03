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