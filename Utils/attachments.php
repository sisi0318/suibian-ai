<?php

namespace Utils;

use Config;

class attachments
{
    public static $cookie = Config::COOKIE;

    public static function upload_authkey()
    {
        $headers = [
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36 Edg/134.0.0.0',
            'Content-Type' => 'application/json',
            'Cookie' => self::$cookie,
        ];


        $response = cURL::get("https://api-normal.amemv.com/aweme/v1/upload/authkey/")->header($headers)->body();

        $uploadInfo = json_decode($response, true)['aigc_img_config'];

        return $uploadInfo;
    }

    public static function apply_image_upload($uploadInfo, $Prefix = "ciallo", $FileExtension = "", $UploadNum = 1)
    {
        $serviceName = "imagex";
        $regionName = "sdwdmwlll";
        $host = $uploadInfo['imageHostName'];
        $awsAccessKeyId = $uploadInfo['authorization2']['access_key_id'];
        $awsSecretKey = $uploadInfo['authorization2']['secret_access_key'];
        $sessionToken = $uploadInfo['authorization2']['session_token'];
        $serviceId = "hv477ye453";
        $method = 'GET';
        $canonicalUri = '/';
        $requestDateTime = gmdate('Ymd\THis\Z');
        $dateStamp = substr($requestDateTime, 0, 8);

        // --- 2. 派生签名密钥 (Signing Key) ---
        $kSecret = "AWS4" . $awsSecretKey;
        $kDate = hash_hmac('sha256', $dateStamp, $kSecret, true);
        $kRegion = hash_hmac('sha256', $regionName, $kDate, true);
        $kService = hash_hmac('sha256', $serviceName, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true); // 二进制签名密钥

        $queryParams = [
            'Action' => 'ApplyImageUpload',
            'FileType' => "image",
            'ServiceId' => $serviceId,
            "UploadNum" => $UploadNum,
            'Version' => '2018-08-01',
            'device_platform' => 'android',
        ];
        ksort($queryParams); // 按 key 字母排序
        $canonicalQueryString = http_build_query($queryParams);
        // 3.2 Canonical Headers (小写，按字母排序，包含 host)
        $headersToSign = [
            'host' => $host,
            'x-amz-date' => $requestDateTime,
            'x-amz-security-token' => $sessionToken
        ];
        ksort($headersToSign); // 按 key (header name) 字母排序
        $canonicalHeaders = '';
        foreach ($headersToSign as $key => $value) {
            $canonicalHeaders .= strtolower($key) . ':' . trim($value) . "\n";
        }

        // 3.3 Signed Headers (小写，按字母排序，用分号分隔)
        $signedHeaders = implode(';', array_map('strtolower', array_keys($headersToSign)));

        // 3.4 Hashed Payload (GET 请求通常为空 payload)
        $payload = '';
        $hashedPayload = hash('sha256', $payload);

        // 3.5 组合 Canonical Request
        $canonicalRequest = $method . "\n"
            . $canonicalUri . "\n"
            . $canonicalQueryString . "\n"
            . $canonicalHeaders . "\n" // 注意末尾已有换行符
            . $signedHeaders . "\n"
            . $hashedPayload;

        // --- 4. 构建待签字符串 (String To Sign) ---
        $credentialScope = $dateStamp . '/' . $regionName . '/' . $serviceName . '/aws4_request';
        $hashedCanonicalRequest = hash('sha256', $canonicalRequest);

        $stringToSign = "AWS4-HMAC-SHA256" . "\n"
            . $requestDateTime . "\n"
            . $credentialScope . "\n"
            . $hashedCanonicalRequest;

        // --- 5. 计算签名 (Signature) ---
        $signature = hash_hmac('sha256', $stringToSign, $kSigning); // 默认输出小写十六进制

        // --- 6. 构建 Authorization Header ---
        $authorizationHeader = "AWS4-HMAC-SHA256 Credential=" . $awsAccessKeyId . '/' . $credentialScope
            . ", SignedHeaders=" . $signedHeaders
            . ", Signature=" . $signature;


        // Step 5: Make the Request
        $url = "https://$host/?" . $canonicalQueryString;
        $headers = [
            'Authorization' => $authorizationHeader,
            'x-amz-date' => $requestDateTime,
            'x-amz-security-token' => $sessionToken,
        ];

        $get = cURL::get($url)->header($headers)->jsonobject();

        return $get;
    }

    public static function snssdk_signs($Prefix = "ciallo", $FileExtension = "", $UploadNum = 1)
    {

        $uploadInfo = self::upload_authkey();

        $bodys = [];
        $applyResponse = self::apply_image_upload($uploadInfo, $Prefix, $FileExtension, $UploadNum);

        foreach ($applyResponse["Result"]["UploadAddress"]["StoreInfos"] as $storeInfo) {
            $upload = [
                "StoreUri" => $storeInfo["StoreUri"],
                "UploadHost" => $applyResponse['Result']['UploadAddress']['UploadHosts'][0],
                "Auth" => $storeInfo["Auth"],
            ];
            $header = [
                "Authorization" => $upload['Auth'],
                "X-Upload-Content-CRC32" => ""
            ];
            $bodys[] = [
                "header" => $header,
                "url" => "https://" . $upload['UploadHost'] . "/upload/v1/" . $upload['StoreUri'],
                "StoreUri" => $upload['StoreUri'],
            ];
        }

        // $storeUri = $applyResponse['Result']['UploadAddress']['StoreInfos'][0]['StoreUri'];
        // $uploadHost = $applyResponse['Result']['UploadAddress']['UploadHosts'][0];
        // $auth = $applyResponse['Result']['UploadAddress']['StoreInfos'][0]['Auth'];

        return $bodys;
    }

    /**
     * 上传文件
     * @param string $file
     * @param array $upload
     * @return string|bool
     */
    public static function upload(string $file, array $upload)
    {
        if (!file_exists($file) || !is_readable($file)) {
            return false;
        }

        $mimeType = mime_content_type($file);
        if ($mimeType === false) {
            $mimeType = 'application/octet-stream';
        }
        $crc32 = sprintf("%08x", crc32(file_get_contents($file)));

        $headers = [
            'Content-Type' => $mimeType,
            'Authorization' => $upload['header']['Authorization'],
            'X-Upload-Content-CRC32' => $crc32,
        ];

        $up = cURL::postupload($upload['url'], file_get_contents($file))->header($headers)->timeout(999)->body();

        $responseData = json_decode($up, true);
        if ($responseData['data']['crc32'] === $crc32) {
            return $upload['StoreUri'];
        } else {
            return false;
        }
    }

    /**
     * 上传文件
     * @param string $file
     * @param array $upload
     * @return string|bool
     */
    public static function web_upload(string $file, array $upload)
    {
        $save = cURL::get($file)->timeout(999)->body();

        // 创建 temp 目录（如果不存在）
        $tempDir = __DIR__ . "/temp";
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $file = $tempDir . "/" . md5($file);
        file_put_contents($file, $save);
        if (json_decode($save)) {
            return false;
        }
        if (!file_exists($file) || !is_readable($file)) {
            return false;
        }

        $mimeType = mime_content_type($file);
        if ($mimeType === false) {
            $mimeType = 'application/octet-stream';
        }
        $height = getimagesize($file)[1] ?? 0;
        $width = getimagesize($file)[0] ?? 0;
        $crc32 = sprintf("%08x", crc32(file_get_contents($file)));

        $headers = [
            'Content-Type' => $mimeType,
            'Authorization' => $upload['header']['Authorization'],
            'X-Upload-Content-CRC32' => $crc32,
        ];

        $up = cURL::postupload($upload['url'], file_get_contents($file))->header($headers)->timeout(999)->body();
        unlink($file); // 删除临时文件

        $responseData = json_decode($up, true);
        if ($responseData['data']['crc32'] === $crc32) {
            return ["uri" => $upload['StoreUri'], "height" => $height, "width" => $width];
        } else {
            return false;
        }
    }
}
