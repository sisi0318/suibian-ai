<?php

namespace Utils;

class Logger
{
    // 日志文件路径
    private static $logFile = __loger;
    
    // 日志级别定义
    const LEVEL_DEBUG = 0;
    const LEVEL_INFO = 1;
    const LEVEL_WARNING = 2;
    const LEVEL_ERROR = 3;
    
    // 日志级别映射
    private static $levelMap = [
        'DEBUG' => self::LEVEL_DEBUG,
        'INFO' => self::LEVEL_INFO,
        'WARNING' => self::LEVEL_WARNING,
        'ERROR' => self::LEVEL_ERROR
    ];
    
    // 当前日志级别，默认为INFO（可通过配置文件修改）
    private static $currentLevel = self::LEVEL_INFO;
    
    /**
     * 设置当前日志级别
     * @param int $level 日志级别
     */
    public static function setLogLevel($level)
    {
        if (is_string($level) && isset(self::$levelMap[$level])) {
            self::$currentLevel = self::$levelMap[$level];
        } elseif (is_int($level) && $level >= 0 && $level <= 3) {
            self::$currentLevel = $level;
        }
    }
    
    /**
     * 记录日志信息
     * @param string $level 日志级别 (INFO, WARNING, ERROR, DEBUG)
     * @param string $message 日志信息
     * @param array $context 上下文数据
     */
    public static function log($level, $message, $context = [])
    {
        // 检查日志级别是否应该被记录
        if (!isset(self::$levelMap[$level]) || self::$levelMap[$level] < self::$currentLevel) {
            return;
        }
        
        $date = date('Y-m-d H:i:s');
        $contextString = !empty($context) ? ' - ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logEntry = "[$date] [$level] $message$contextString" . PHP_EOL;
        
        file_put_contents(self::$logFile, $logEntry, FILE_APPEND);
    }
    
    /**
     * 记录信息级别日志
     * @param string $message 日志信息
     * @param array $context 上下文数据
     */
    public static function info($message, $context = [])
    {
        self::log('INFO', $message, $context);
    }
    
    /**
     * 记录警告级别日志
     * @param string $message 日志信息
     * @param array $context 上下文数据
     */
    public static function warning($message, $context = [])
    {
        self::log('WARNING', $message, $context);
    }
    
    /**
     * 记录错误级别日志
     * @param string $message 日志信息
     * @param array $context 上下文数据
     */
    public static function error($message, $context = [])
    {
        self::log('ERROR', $message, $context);
    }
    
    /**
     * 记录调试级别日志
     * @param string $message 日志信息
     * @param array $context 上下文数据
     */
    public static function debug($message, $context = [])
    {
        self::log('DEBUG', $message, $context);
    }
}
