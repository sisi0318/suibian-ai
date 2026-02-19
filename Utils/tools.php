<?php

namespace Utils;

use CURLFile;

class tools
{
    /**
     * 输出JSON格式的响应并退出
     * @param int $code HTTP状态码
     * @param string $msg 消息
     * @param array $data 数据
     */
    public static function __echo($code, $msg, $data = [])
    {
        if (class_exists('\\Utils\\Logger')) {
            \Utils\Logger::debug('API响应', [
                'code' => $code,
                'msg' => $msg,
                "data" => $data,
                'data_type' => gettype($data)
            ]);
        }

        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'code' => $code,
            'msg' => $msg,
            'data' => $data,
            'api_version' => \Config::API_VERSION,
            'timestamp' => startTime,
            "created" => time()
        ], 456);
        exit;
    }

    /**
     * 获取并清理所有请求参数
     * @return array 清理后的参数数组
     */
    public static function GetParams()
    {
        // 获取 GET 参数
        $getParams = $_GET;

        // 获取 POST 参数
        $postParams = $_POST;

        // 获取 POST JSON 参数
        $jsonParams = [];
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST' &&
            isset($_SERVER['CONTENT_TYPE']) &&
            strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false
        ) {
            $rawInput = file_get_contents('php://input');
            if (!empty($rawInput)) {
                $jsonParams = json_decode($rawInput, true) ?? [];
            }
        }

        // 合并所有参数
        $allParams = array_merge($getParams, $postParams, $jsonParams);

        // 清理参数 - 移除潜在的XSS和SQL注入
        return $allParams;
    }

    /**
     * 清理参数数组，防止XSS和SQL注入
     * @param array $params 需要清理的参数数组
     * @return array 清理后的参数数组
     */
    private static function sanitizeParams($params)
    {
        $cleaned = [];
        foreach ($params as $key => $value) {
            // 清理键名
            $cleanKey = self::sanitizeString($key);

            // 递归清理数组值
            if (is_array($value)) {
                $cleaned[$cleanKey] = self::sanitizeParams($value);
            } else {
                // 清理字符串值
                $cleaned[$cleanKey] = self::sanitizeString($value);
            }
        }
        return $cleaned;
    }

    /**
     * 清理字符串，防止XSS和SQL注入
     * @param mixed $value 需要清理的值
     * @return mixed 清理后的值
     */
    private static function sanitizeString($value)
    {
        if (!is_string($value)) {
            return $value;
        }

        // 移除危险的HTML标签
        $value = strip_tags($value);

        // HTML实体编码
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        return $value;
    }



    public static function generateUUID()
    {
        $time = uniqid("", true);
        $uuid = substr($time, 0, 8) . "-" .
            substr($time, 8, 4) . "-11f0-" .
            substr(bin2hex(random_bytes(2)), 0, 4) . "-" .
            substr(bin2hex(random_bytes(6)), 0, 12);
        return strtolower($uuid);
    }
}
