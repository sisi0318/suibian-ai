<?php

require './public.php';

use Utils\tools;
use Utils\attachments;

set_time_limit(0);
ini_set('max_execution_time', 300); // 5分钟
// 启动时间，用于计算API响应时间
$startTime = microtime(true);
define("startTime", $startTime);

$params = tools::GetParams();

$type = $params['type'] ?? 'process';

$model = $params['model'] ?? 'SuiBian/process';

// $full_word = $params['full_word'] ?? '镜头1：（特写）暗调暗室背景，顶部聚光灯垂直打亮<图片1>（年轻人）的面部，他画着清透眼妆和冷调唇色，神情凌厉。身穿黑色西装外套、白色衬衫，打着黑色大蝴蝶结领结。蓝绿色机械荧光特效在背景中如流体般右中间向上下发散快速流动，顶部不时有一块白色反光板晃动，照亮面部细节，充满冷调电影质感；镜头2：（中景）场景切换至阴天冷调的纽约时代广场高楼层的天台，图片1站在天台上，高楼林立，巨型电子广告牌（显示人物海报）环绕。<图片1>身穿黑色西装外套、西裤、白色衬衫、黑色大蝴蝶结及马甲，神情冷峻。镜头缓慢推进，高楼间的风猛烈吹动他的头发，<图片1>突然转身向后，张开双臂，毫不犹豫地快速跃下高楼；镜头3：（全景/仰拍）<图片1>张开双臂处于高楼坠落状态，背景切换为璀璨的城市夜景，超赛博华丽的夜景，摩天大楼与巨大的霓虹广告牌（含“NEW YORK”字样）交错闪烁，灯光拉出动态模糊，呈现电影级别的空中下坠特效；镜头4：（近景特写）在傍晚暗调滤镜的城市背景下，<图片1>保持张开双臂的姿势下坠，头发和衣服呈现出明显的反重力和风的影响状态；镜头5：（全景->特写->循环）<图片1>从空中帅气落地在摩天大楼下的街道，黄色出租车在背景穿梭，路人匆匆。他迅速站起，镜头跟随。人物慢慢站起来的，图片1突然手部震动接着镜头切至手部特写，蓝色的光流能量顺着<图片1>的手臂快速流动到胸口位置，突然他向镜头抬手，掌心凭空出现一团伴随蓝色荧光流动的半透明发光的蓝色火焰流动），镜头极速推进蓝色火焰燃烧整个画面（蓝白色特效流动）图片1的人物慢慢漂浮飞起来手张开对着镜头帅气炸裂的动作，接着镜头360快速围着图1旋转，在空中慢镜头翻了个跟头最后ending pose站立1s结尾。剧情紧凑完整[BGM: 沉浸式赛博朋克电子电影配乐，节奏紧凑且充满张力';
// $content = $params['content'] ?? '异能世界';
// $full_word1 = $params['full_word1'] ?? '图片1作为图1  ，这是一个极具反差感的抖音短视频。变装前后有巨大的反差感。镜头1：图1的主体保留原有穿着，不改变原有环境，背景音带有可爱轻快的BGM，图1  正低头弯腰，伸手捡起地板上一个深红色的气球。 分镜2：图1  一直手托着深红色气球，气球上用白色的记号笔画着一个夸张且诡异的笑脸（细长的眼睛和锯齿状的大嘴），镜头随着图1  的动作拉近，猛地将气球扣向镜头遮挡视线。视频第3秒分镜3（变装触发）：伴随着一声清脆的爆裂声卡点，画面瞬间撕裂，进入一个完全不同的暗黑电影质感的世界。原本的居家环境消失，取而代之的是充满烟雾、光影陆离的虚拟空间，仿佛加了一层电影感的滤镜（暗部加强，高对比度色彩），天空中飘落着大量鲜红的玫瑰花瓣（子弹时间，慢镜头）。图1  完成华丽变身，化身为一名超高颜值的人物，穿着高级感的暗色晚礼服，（高级定制的晚礼服）五官立体，好看的发型，发丝有轻微透光效，超写实质感，最后，图1 在飞舞的红花瓣与浓烟中保持着具有张力的姿势，画面在极具视觉冲击力的氛围中结束。';
// $content1 = $params['content1'] ?? '气球爆炸变身';

$ugc_text = $params['ugc_text'] ?? [];
$taskId = $params['task_id'] ?? "";
$url = $params['url'] ?? "";

// 如果没有传入 ugc_text 直接报错
if (empty($ugc_text) && empty($taskId)) {
    tools::__echo(400, "缺少ugc_text参数");
} else {
    // 为每个 ugc_text 项自动添加 type = 2
    foreach ($ugc_text as $key => &$item) {
        if (!isset($item['type'])) {
            $item['type'] = 2;
        }
        // 验证必填字段
        if (empty($item['full_word']) || empty($item['content'])) {
            tools::__echo(400, "ugc_text 第" . ($key + 1) . "项缺少 full_word 或 content 字段");
        }
    }
    unset($item); // 解除引用
}


// if (empty($url[0])) {
//     tools::__echo(500, "url参数为空");
// }

$resource_list = [];


// exit();
switch ($type) {
    case 'process':

        $m = explode("/", $model);

        if (count($m) < 2) {
            tools::__echo(400, "模型格式错误");
        }

        // 根据model自动调用指定类的process函数
        $moduleName = $m[0];  // 模块名称
        $modelType = "process";   // 模型类型

        // 构造类名（首字母大写 + App后缀）
        $className = 'module\\' . ucfirst($moduleName);

        // 检查类是否存在
        if (!class_exists($className)) {
            tools::__echo(400, "不支持的模型: {$moduleName}");
        }
        // 检查类是否有process方法
        if (!method_exists($className, 'process')) {
            tools::__echo(400, "模块 {$moduleName} 不支持process方法");
        }

        // 调用指定类的process方法

        $urls = explode(",", $url);
        $upload = attachments::snssdk_signs("", "", count($urls));
        foreach ($urls as $key => $value) {
            if (empty($value)) {
                tools::__echo(500, "第" . ($key + 1) . "个url参数为空");
            }
            $up = attachments::web_upload($value, $upload[$key]);
            $resource_list[] = [
                "extra" => "{\"ImageInfo\":{\"faceCount\":1,\"pet_count\":0,\"face_count\":1,\"width\":{$up['width']},\"height\":{$up['height']}}}",
                "material_type" => 1,
                "media_type" => 1,
                "uri" => $up['uri']
            ];
        }
        $className::process($ugc_text, $resource_list);
        break;

    case 'video_process':
        $m = explode("/", $model);

        if (count($m) < 2) {
            tools::__echo(400, "模型格式错误");
        }

        $moduleName = $m[0];
        $className = 'module\\' . ucfirst($moduleName);

        if (!class_exists($className)) {
            tools::__echo(400, "不支持的模型: {$moduleName}");
        }
        if (!method_exists($className, 'process')) {
            tools::__echo(400, "模块 {$moduleName} 不支持process方法");
        }

        if (empty($url)) {
            tools::__echo(400, "缺少视频url参数");
        }

        $videoUpload = attachments::video_snssdk_signs(1);
        if (empty($videoUpload)) {
            tools::__echo(500, "获取视频上传签名失败");
        }

        $up = attachments::web_upload_video($url, $videoUpload);
        if (!$up) {
            tools::__echo(500, "视频上传失败");
        }

        $storeUri = $up['store_uri'];
        $videoMeta = $up['video_meta'] ?? [];
        $vWidth = $videoMeta['Width'] ?? 720;
        $vHeight = $videoMeta['Height'] ?? 720;

        $resource_list = [
            [
                "extra" => "{\"VideoInfo\":{\"frame_info\":[{\"uri\":\"tos-cn-i-hv477ye453\\/ca7431dffaeb4b12bb102be7dfcac198\",\"faceCount\":0,\"pet_count\":0,\"face_count\":0,\"age\":0,\"width\":{$vWidth},\"height\":{$vHeight}}],\"reference_type\":\"video_edit\"}}",
                "material_type" => 1,
                "media_type" => 2,
                "uri" => $up['vid']
            ]
        ];

        $className::process($ugc_text, $resource_list);
        break;

    case 'query':
        $m = explode("/", $model);

        if (count($m) < 2) {
            tools::__echo(400, "模型格式错误");
        }

        if (empty($taskId)) {
            tools::__echo(400, "缺少task_id参数");
        }

        // 根据model自动调用指定类的process函数
        $moduleName = $m[0];  // 模块名称
        $modelType = "query";   // 模型类型

        // 构造类名（首字母大写 + App后缀）
        $className = 'module\\' . ucfirst($moduleName);

        // 检查类是否存在
        if (!class_exists($className)) {
            tools::__echo(400, "不支持的模型: {$moduleName}");
        }
        // 检查类是否有query方法
        if (!method_exists($className, 'query')) {
            tools::__echo(400, "模块 {$moduleName} 不支持query方法");
        }

        // 调用指定类的query方法
        $className::query($taskId);
        break;
    default:
        tools::__echo(400, "不支持的操作类型: {$type}");
}
