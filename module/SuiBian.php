<?php

namespace module;

use Config;
use Utils\cURL;
use Utils\gorgon04;
use Utils\tools;

class SuiBian
{
    public static $imageList = [];
    public static $text = "";
    public static $error = "";
    public static $hasChunkDelta = false;

    public static function process($ugc_text, $resource_list = [])
    {
        $deviceId = Config::DEVICE_ID;
        $installId = Config::INSTALL_ID;
        $ts = time();
        $_rticket = ((int)(microtime(true) * 1000)) . rand(111, 999);
        $createTimeMs = (string)((int)(microtime(true) * 1000));
        $api = "https://api-normal.amemv.com/aweme/v1/ai/process/?iid={$installId}&device_id={$deviceId}&ac=wifi&channel=&aid=8712&app_name=douyin_spark&version_code=370701&version_name=37.7.1&device_platform=android&os=android&ssmix=a&device_type=&device_brand=&language=zh&os_api=33&os_version=13&manifest_version_code=370702&resolution=1080*2276&dpi=440&update_version_code=37719900&_rticket={$_rticket}&package=com.ss.android.spark&first_launch_timestamp=1771500148&last_deeplink_update_version_code=0&cpu_support64=true&host_abi=arm64-v8a&is_guest_mode=0&app_type=normal&minor_status=0&appTheme=light&is_preinstall=0&need_personal_recommend=1&is_android_pad=0&is_android_fold=0&ts={$ts}";

        // 构建 ugc_text JSON 字符串（确保每项都有 type = 2）
        $ugc_text_json = json_encode($ugc_text, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $body = [
            "aigc_request_meta" => [
                "aigc_business_param" => "{\"source\":\"inhouse\",\"scene\":[\"ai_portrait\",\"ai_video\"],\"sub_scene\":[\"ai_portrait_other\",\"ai_video\"],\"effect_id\":\"116651344\",\"sync_type\":1,\"client_sub_scene\":\"ai_effect_ugc\",\"output_resource_type\":\"Video\",\"input_resource_type\":1,\"ugc_version\":0,\"algorithm_label_chain\":[[4924,4945,4958,5083,5528],[4924,4945,4946,4959,5478],[4924,4945,4958,5083,5577]]}",
                "aigc_performance_param" => "{\"client_start_time\":\"{$createTimeMs}\"}"
            ],
            "is_async" => true,
            "req_json" => "{\"biz_param\":{\"id\":\"116651344\",\"need_generate_sticker\":true,\"user_monitor\":{\"UserScene\":\"116651344\"},\"effect_ugc_extra\":{\"ugc_tags\":{\"ugc_text\":{$ugc_text_json}}},\"ai_auto_prompt_info\":{\"need_intent_detect\":true},\"resource_type\":\"video\",\"user_action\":{\"is_prompt_changed\":true},\"device_score\":\"8.7635\"}}",
            "resource_list" => $resource_list,
            "scene" => [
                "biz_type" => "multiple_ai_creation",
                "custom_params" => [
                    "biz_callback_args" => new \stdClass(),
                    "checkin" => false,
                    "multi_portrait" => "false",
                    "new_interaction" => "true",
                    "retry_scene" => "custom_ugc_lora",
                    "use_binary" => false,
                    "use_v2" => true
                ],
                "step_name" => "generate"
            ]
        ];

        $options = [
            'headers' => [
                "content-type" => "application/json; encoding=utf-8",
                "user-agent" => "com.ss.android.spark/370702",
                "Cookie" => Config::COOKIE,
            ],
            'body' => json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ];
        // $gorgon = gorgon04::gorgon($api, $options);
        // $body = $gorgon['body'];
        // print_r($gorgon);
        $post = self::post($api, $options['body'], $options['headers']);
        if (empty($post)) {
            tools::__echo(500, "请求失败");
        }

        $taskId = json_decode($post, true)['task_id'] ?? "";

        tools::__echo(200, "请求成功", ['task_id' => $taskId]);
    }

    public static function query($taskId)
    {
        $installId = Config::INSTALL_ID;
        $ts        = time();
        $_rticket  = ((int)(microtime(true) * 1000)) . rand(111, 999);

        $task_queries = "[{\"aigc_type\":2,\"extra\":\"{\\\"need_task_group\\\":false}\",\"task_id\":\"{$taskId}\",\"query_scene\":0}]";
        $api = "https://api-normal.amemv.com/aweme/v1/ai/generation/query/?task_queries={$task_queries}&need_raw_media=true&query_source=edit_page&use_new=true&iid={$installId}&ac=wifi&channel=xiaomi_8712_64&aid=8712&app_name=douyin_spark&version_code=370701&version_name=37.7.1&device_platform=android&os=android&ssmix=a&device_type=&device_brand=&language=zh&os_api=33&os_version=13&manifest_version_code=370702&resolution=1080*2276&dpi=440&update_version_code=37719900&_rticket={$_rticket}&package=com.ss.android.spark&first_launch_timestamp=1771500148&last_deeplink_update_version_code=0&cpu_support64=true&host_abi=arm64-v8a&is_guest_mode=0&app_type=normal&minor_status=0&appTheme=light&is_preinstall=0&need_personal_recommend=1&is_android_pad=0&is_android_fold=0&ts={$ts}";

        $headers = [
            "content-type" => "application/json; encoding=utf-8",
            "user-agent"   => "com.ss.android.spark/370702",
            "Cookie"       => Config::COOKIE,
        ];

        $get = self::get($api, $headers);
        if (empty($get)) {
            tools::__echo(500, "请求失败");
        }

        $raw_list  = json_decode($get, true)['task_list'] ?? [];
        $task_list = [];

        foreach ($raw_list as $task) {
            /*
             * task_status 说明（来自真实返回样本）：
             *   1 → 任务进行中，generate_progress 为当前百分比进度
             *   2 → 任务完成，resource_map 里有 videos / cover_images
             */
            $status   = $task['task_status']      ?? 0;
            $progress = $task['generate_progress'] ?? 0;

            // ── 提取视频直链 ──────────────────────────────────
            $videos = [];
            foreach ($task['resource_map']['videos'] ?? [] as $v) {
                $url_list = $v['url']['url_list'] ?? [];
                if (!empty($url_list)) {
                    $videos[] = $url_list[0]; // 取第一个镜像即可
                }
            }

            // ── 提取封面图直链 ────────────────────────────────
            $covers = [];
            foreach ($task['resource_map']['cover_images'] ?? [] as $c) {
                $url_list = $c['url']['url_list'] ?? [];
                if (!empty($url_list)) {
                    $covers[] = $url_list[0];
                }
            }

            // ── 组装易用结构 ──────────────────────────────────
            $item = [
                'task_id'      => $task['task_id']      ?? '',
                'status'       => $status,               // 1=进行中, 2=完成
                'progress'     => $progress,             // 百分比，status==1 时有意义
                'videos'       => $videos,               // 视频直链，status==2 时有内容
                'covers'       => $covers,               // 封面直链，status==2 时有内容
                'wait_seconds' => $task['wait_seconds']  ?? 0,
                'wait_minutes' => $task['wait_minutes']  ?? 0,
                'wait_time_tip'=> $task['wait_time_tip'] ?? '',
                'raw'          => $task,                 // 保留原始数据
            ];

            $task_list[] = $item;
        }

        tools::__echo(200, "请求成功", ['task_list' => $task_list]);
    }

    public static function get($api, $header)
    {
        for ($i = 0; $i < 10; $i++) {
            $post = cURL::get($api)->timeout(999)->header($header)->body();
            if (empty($post)) {
                // echo "请求失败，重试中...\n";
                sleep(1);
                continue;
            }
            return $post;
        }
    }

    public static function post($api, $body, $header)
    {
        for ($i = 0; $i < 5; $i++) {
            $post = cURL::json($api, $body)->timeout(999)->header($header)->body();
            if (empty($post)) {
                // echo "请求失败，重试中...\n";
                sleep(1);
                continue;
            }
            return $post;
        }
    }
}
