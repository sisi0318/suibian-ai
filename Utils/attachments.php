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

    // ==================== 视频上传 ====================

    public static function upload_video_authkey()
    {
        $headers = [
            'User-Agent' => 'com.ss.android.spark/370702',
            'Content-Type' => 'application/json',
            'Cookie' => self::$cookie,
        ];

        $response = cURL::get("https://api-normal.amemv.com/aweme/v1/upload/authkey/")->header($headers)->body();
        $data = json_decode($response, true);

        return $data['video_config']['aiCreationAuthorization2'];
    }

    private static function extract_uid_from_token($sessionToken)
    {
        $tokenStr = substr($sessionToken, 4);
        $tokenJson = json_decode(base64_decode($tokenStr), true);
        if (!$tokenJson || !isset($tokenJson['PolicyString'])) return '';
        $policy = json_decode($tokenJson['PolicyString'], true);
        if (!$policy || !isset($policy['Statement'][0]['Condition'])) return '';
        $condition = json_decode($policy['Statement'][0]['Condition'], true);
        return $condition['UserId'] ?? '';
    }

    public static function apply_video_upload($uploadInfo, $UploadNum = 1)
    {
        $serviceName = "vod";
        $regionName = "sdwdmwlll";
        $host = "api-core.amemv.com";
        $awsAccessKeyId = $uploadInfo['access_key_id'];
        $awsSecretKey = $uploadInfo['secret_access_key'];
        $sessionToken = $uploadInfo['session_token'];
        $spaceName = $uploadInfo['space_name'];
        $uid = self::extract_uid_from_token($sessionToken);
        $deviceId = Config::DEVICE_ID;

        $method = 'GET';
        $canonicalUri = '/top/v1';
        $requestDateTime = gmdate('Ymd\THis\Z');
        $dateStamp = substr($requestDateTime, 0, 8);

        $kSecret = "AWS4" . $awsSecretKey;
        $kDate = hash_hmac('sha256', $dateStamp, $kSecret, true);
        $kRegion = hash_hmac('sha256', $regionName, $kDate, true);
        $kService = hash_hmac('sha256', $serviceName, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

        $canonicalQueryString = "Action=ApplyUploadInner"
            . "&NeedFallback=true"
            . "&Region=CN"
            . "&Region=sdwdmwlll"
            . "&SpaceName={$spaceName}"
            . "&UploadNum={$UploadNum}"
            . "&UseQuic=false"
            . "&Version=2020-11-19"
            . "&appid=8712"
            . "&channel=xiaomi_8712_64"
            . "&device_platform=android"
            . "&device_type=M2012K11AC"
            . "&did={$deviceId}"
            . "&net_type=wifi"
            . "&uid={$uid}"
            . "&update_version_code=37719900"
            . "&version_code=370702";

        $headersToSign = [
            'x-amz-date' => $requestDateTime,
            'x-amz-security-token' => $sessionToken
        ];
        ksort($headersToSign);
        $canonicalHeaders = '';
        foreach ($headersToSign as $key => $value) {
            $canonicalHeaders .= strtolower($key) . ':' . trim($value) . "\n";
        }
        $signedHeaders = implode(';', array_map('strtolower', array_keys($headersToSign)));

        $hashedPayload = hash('sha256', '');

        $canonicalRequest = $method . "\n"
            . $canonicalUri . "\n"
            . $canonicalQueryString . "\n"
            . $canonicalHeaders . "\n"
            . $signedHeaders . "\n"
            . $hashedPayload;

        $credentialScope = $dateStamp . '/' . $regionName . '/' . $serviceName . '/aws4_request';
        $hashedCanonicalRequest = hash('sha256', $canonicalRequest);

        $stringToSign = "AWS4-HMAC-SHA256" . "\n"
            . $requestDateTime . "\n"
            . $credentialScope . "\n"
            . $hashedCanonicalRequest;

        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authorizationHeader = "AWS4-HMAC-SHA256 Credential=" . $awsAccessKeyId . '/' . $credentialScope
            . ",SignedHeaders=" . $signedHeaders
            . ",Signature=" . $signature;

        $url = "https://{$host}{$canonicalUri}?{$canonicalQueryString}";
        $headers = [
            'User-Agent' => 'BDFileUpload(' . ((int)(microtime(true) * 1000)) . ')',
            'Accept-Encoding' => 'identity',
            'authorization' => $authorizationHeader,
            'date' => gmdate('D, d M Y H:i:s') . ' GMT',
            'x-amz-date' => $requestDateTime,
            'x-amz-expires' => '31536000',
            'x-amz-security-token' => $sessionToken,
            'x-ss-dp' => '8712',
        ];

        $get = cURL::get($url)->header($headers)->jsonobject();

        return $get;
    }

    public static function video_snssdk_signs($UploadNum = 1)
    {
        $uploadInfo = self::upload_video_authkey();
        $applyResponse = self::apply_video_upload($uploadInfo, $UploadNum);

        $node = $applyResponse['Result']['InnerUploadAddress']['UploadNodes'][0] ?? null;
        if (!$node) return null;

        $vid = $node['Vid'] ?? '';
        $uploadHost = $node['UploadHost'] ?? '';
        $storeInfo = $node['StoreInfos'][0] ?? null;
        if (!$storeInfo) return null;

        return [
            "header" => ["Authorization" => $storeInfo['Auth']],
            "url" => "https://{$uploadHost}/upload/v1/" . $storeInfo['StoreUri'],
            "StoreUri" => $storeInfo['StoreUri'],
            "UploadID" => $storeInfo['UploadID'] ?? '',
            "Vid" => $vid,
            "SessionKey" => $node['SessionKey'] ?? '',
            "UploadHost" => $uploadHost,
            "uploadInfo" => $uploadInfo,
        ];
    }

    private static function uuid_v4()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private static function sign_vod_headers($uploadInfo, $method, $canonicalQueryString, $body = '')
    {
        $regionName = "sdwdmwlll";
        $serviceName = "vod";
        $canonicalUri = '/top/v1';

        $awsAccessKeyId = $uploadInfo['access_key_id'];
        $awsSecretKey = $uploadInfo['secret_access_key'];
        $sessionToken = $uploadInfo['session_token'];

        $requestDateTime = gmdate('Ymd\THis\Z');
        $dateStamp = substr($requestDateTime, 0, 8);

        $kSecret = "AWS4" . $awsSecretKey;
        $kDate = hash_hmac('sha256', $dateStamp, $kSecret, true);
        $kRegion = hash_hmac('sha256', $regionName, $kDate, true);
        $kService = hash_hmac('sha256', $serviceName, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

        $headersToSign = [
            'x-amz-date' => $requestDateTime,
            'x-amz-security-token' => $sessionToken
        ];
        ksort($headersToSign);
        $canonicalHeaders = '';
        foreach ($headersToSign as $key => $value) {
            $canonicalHeaders .= strtolower($key) . ':' . trim($value) . "\n";
        }
        $signedHeaders = implode(';', array_map('strtolower', array_keys($headersToSign)));

        $hashedPayload = hash('sha256', $body);

        $canonicalRequest = $method . "\n"
            . $canonicalUri . "\n"
            . $canonicalQueryString . "\n"
            . $canonicalHeaders . "\n"
            . $signedHeaders . "\n"
            . $hashedPayload;

        $credentialScope = $dateStamp . '/' . $regionName . '/' . $serviceName . '/aws4_request';
        $stringToSign = "AWS4-HMAC-SHA256\n" . $requestDateTime . "\n" . $credentialScope . "\n" . hash('sha256', $canonicalRequest);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authorizationHeader = "AWS4-HMAC-SHA256 Credential=" . $awsAccessKeyId . '/' . $credentialScope
            . ",SignedHeaders=" . $signedHeaders
            . ",Signature=" . $signature;

        return [
            'url' => "https://api-core.amemv.com{$canonicalUri}?{$canonicalQueryString}",
            'headers' => [
                'User-Agent' => 'BDFileUpload(' . ((int)(microtime(true) * 1000)) . ')',
                'Accept-Encoding' => 'identity',
                'authorization' => $authorizationHeader,
                'date' => gmdate('D, d M Y H:i:s') . ' GMT',
                'x-amz-date' => $requestDateTime,
                'x-amz-expires' => '31536000',
                'x-amz-security-token' => $sessionToken,
                'x-ss-dp' => '8712',
            ]
        ];
    }

    public static function commit_video_upload($upload, $crc32)
    {
        $uploadInfo = $upload['uploadInfo'];
        $uid = self::extract_uid_from_token($uploadInfo['session_token']);
        $deviceId = Config::DEVICE_ID;
        $spaceName = $uploadInfo['space_name'];

        $queryString = "Action=CommitUploadInner"
            . "&Region=CN"
            . "&SpaceName={$spaceName}"
            . "&Version=2020-11-19"
            . "&appid=8712"
            . "&batch_commit=true"
            . "&channel=xiaomi_8712_64"
            . "&device_platform=android"
            . "&device_type=M2012K11AC"
            . "&did={$deviceId}"
            . "&net_type=wifi"
            . "&uid={$uid}"
            . "&update_version_code=37719900"
            . "&version_code=370702";

        $transporterBase = [
            "Header" => [
                "Authorization" => $upload['header']['Authorization'],
                "Host" => $upload['UploadHost']
            ],
            "Url" => "/upload/v1/" . $upload['StoreUri']
                . "?uploadmode=part&phase=batch_finish&uploadid=" . $upload['UploadID']
                . "&batchid=" . self::uuid_v4() . "&device_type=mobile"
        ];

        $commitBody1 = json_encode([
            "CommitRequest" => [
                "Functions" => [["Input" => ["SnapshotTime" => 0.0], "Name" => "Snapshot"]],
                "SessionKey" => $upload['SessionKey'],
                "UsedHosts" => null
            ],
            "CommitTransporterReq" => array_merge($transporterBase, [
                "Body" => [[
                    "encrypt_key" => "",
                    "encrypt_mode" => 0,
                    "part_info" => "0:{$crc32}",
                    "storeid" => $upload['StoreUri'],
                    "uploadid" => $upload['UploadID'],
                    "vid" => $upload['Vid']
                ]]
            ])
        ], JSON_UNESCAPED_SLASHES);

        $signed1 = self::sign_vod_headers($uploadInfo, 'POST', $queryString, $commitBody1);
        $result = cURL::json($signed1['url'], $commitBody1)->header($signed1['headers'])->timeout(999)->jsonobject();

        $transporterBase['Url'] = "/upload/v1/" . $upload['StoreUri']
            . "?uploadmode=part&phase=batch_finish&uploadid=" . $upload['UploadID']
            . "&batchid=" . self::uuid_v4() . "&device_type=mobile";

        $commitBody2 = json_encode([
            "CommitRequest" => [
                "CancelUpload" => true,
                "SessionKey" => $upload['SessionKey']
            ],
            "CommitTransporterReq" => array_merge($transporterBase, [
                "Body" => [[
                    "part_info" => "0:{$crc32}",
                    "storeid" => $upload['StoreUri'],
                    "uploadid" => $upload['UploadID'],
                    "vid" => $upload['Vid']
                ]]
            ])
        ], JSON_UNESCAPED_SLASHES);

        $signed2 = self::sign_vod_headers($uploadInfo, 'POST', $queryString, $commitBody2);
        cURL::json($signed2['url'], $commitBody2)->header($signed2['headers'])->timeout(999)->body();

        return $result;
    }

    /**
     * 从URL下载并上传视频，完成 CommitUploadInner
     * @param string $fileUrl 视频URL
     * @param array $upload video_snssdk_signs 返回的上传配置
     * @return array|false 成功返回 ["vid", "store_uri", "video_meta"], 失败返回 false
     */
    public static function web_upload_video(string $fileUrl, array $upload)
    {
        $save = cURL::get($fileUrl)->timeout(999)->body();

        $tempDir = __DIR__ . "/temp";
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $file = $tempDir . "/" . md5($fileUrl) . ".mp4";
        file_put_contents($file, $save);

        if (json_decode($save)) {
            return false;
        }
        if (!file_exists($file) || !is_readable($file)) {
            return false;
        }

        $fileContent = file_get_contents($file);
        $crc32 = sprintf("%08x", crc32($fileContent));

        $uploadUrl = $upload['url']
            . "?uploadid=" . $upload['UploadID']
            . "&device_type=mobile"
            . "&part_number=0"
            . "&phase=transfer"
            . "&part_offset=0";

        $headers = [
            'User-Agent' => 'BDFileUpload(' . ((int)(microtime(true) * 1000)) . ')',
            'Accept-Encoding' => 'identity',
            'Content-Type' => 'application/octet-stream',
            'Authorization' => $upload['header']['Authorization'],
            'Date' => gmdate('D, d M Y H:i:s') . ' GMT',
            'X-Upload-Content-CRC32' => $crc32,
        ];

        $up = cURL::postupload($uploadUrl, $fileContent)->header($headers)->timeout(999)->body();
        unlink($file);

        $responseData = json_decode($up, true);
        if (!isset($responseData['data']['crc32']) || $responseData['data']['crc32'] !== $crc32) {
            return false;
        }

        $commitResult = self::commit_video_upload($upload, $crc32);
        $videoMeta = $commitResult['Result']['SuccessResponse']['Results'][0]['VideoMeta'] ?? null;

        return [
            "vid" => $upload['Vid'],
            "store_uri" => $upload['StoreUri'],
            "video_meta" => $videoMeta,
        ];
    }
}
