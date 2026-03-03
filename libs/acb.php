<?php
class ACB
{
    public $clientId = 'iuSuHYVufIUuNIREV0FB9EoLn9kHsDbm';
    private $URL = array(
        "LOGIN" => "https://apiapp.acb.com.vn/mb/v2/auth/tokens",
        "getBalance" => "https://apiapp.acb.com.vn/mb/legacy/ss/cs/bankservice/transfers/list/account-payment",
        "INFO" => "https://mobile.mbbank.com.vn/retail_lite/loan/getUserInfo",
        "GET_TOKEN" => "https://mobile.mbbank.com.vn/retail_lite/loyal/getToken",
        "GET_NOTI" => "https://mobile.mbbank.com.vn/retail_lite/notification/getNotificationDataList",
        "GET_TRANS" => "https://apiapp.acb.com.vn/mb/legacy/ss/cs/bankservice/saving/tx-history?maxRows=20&account=4650511",
    );
    public function login_acb($username, $password)
    {
        $header = array(
            'Content-Type: application/json; charset=utf-8',
            'Host: apiapp.acb.com.vn',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0.4183.26 Safari/537.36 Edg/85.0.564.13',
        );
        $data = '{
            "clientId" : "'.$this->clientId.'",
            "username" : "'.$username.'",
            "password" : "'.$password.'"
        }';
        return $this->CURL("LOGIN", $header, $data);
    }
     public function getBankName($tranferTo, $bankCode,$stk,$token)
    {

       $header = array (
            'Content-Type: application/json; charset=utf-8',
            'Host: apiapp.acb.com.vn',
            "Authorization: bearer $token"
        );
        $result = $this->CURL2("https://apiapp.acb.com.vn/mb/legacy/ss/cs/bankservice/transfers/accounts/".$tranferTo."?bankCode=".$bankCode."&accountNumber=".$stk."", $header,$data = null);
        return json_encode($result);
    }
    public function get_balance($token) {
        $header = array (
            'Content-Type: application/json; charset=utf-8',
            'Host: apiapp.acb.com.vn',
            "Authorization: bearer $token",
        );
        $result = $this->CURL("getBalance", $header,$data = null);
        return json_encode($result);
    }
    public function LSGD($accountNo,$rows,$token) {
        $header = array (
            'Content-Type: application/json; charset=utf-8',
            'Host: apiapp.acb.com.vn',
            "Authorization: bearer $token"
        );
        $result = $this->CURL2("https://apiapp.acb.com.vn/mb/legacy/ss/cs/bankservice/saving/tx-history?maxRows=".$rows."&account=".$accountNo."", $header,$data = null);
        return json_encode($result);
    }
    public function CURL2($Action, $header, $data)
    {
        $Data = is_array($data) ? json_encode($data) : $data;
        $curl = curl_init();
        $opt = array(
            CURLOPT_URL => $Action,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => empty($data) ? false : true,
            CURLOPT_POSTFIELDS => $Data,
            CURLOPT_CUSTOMREQUEST => empty($data) ? 'GET' : 'POST',
            CURLOPT_HTTPHEADER => $header,
            CURLOPT_ENCODING => "",
            CURLOPT_HEADER => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2,
            CURLOPT_TIMEOUT => 20,
        );
        curl_setopt_array($curl, $opt);
        $body = curl_exec($curl);
        if (is_object(json_decode($body))) {
            return json_decode($body, true);
        }
        return $body;
    }

    private function CURL($Action, $header, $data)
    {
        $Data = is_array($data) ? json_encode($data) : $data;
        $curl = curl_init();
        $header[] = 'Content-Type: application/json; charset=utf-8';
        $header[] = 'accept: application/json';
        $header[] = 'Content-Length: ' . strlen($Data);
        $opt = array(
            CURLOPT_URL => $this->URL[$Action],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => empty($data) ? false : true,
            CURLOPT_POSTFIELDS => $Data,
            CURLOPT_CUSTOMREQUEST => empty($data) ? 'GET' : 'POST',
            CURLOPT_HTTPHEADER => $header,
            CURLOPT_ENCODING => "",
            CURLOPT_HEADER => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_TIMEOUT => 20,
        );
        curl_setopt_array($curl, $opt);
        $body = curl_exec($curl);
        if (is_object(json_decode($body))) {
            return json_decode($body, true);
        }
        return json_decode($body, true);
    }

}