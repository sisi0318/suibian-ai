# 随便AI - API调用文档

## 📖 项目简介
### 别问为什么是php 我乐意
---

## 🚀 快速开始

### 接口地址

```
POST/GET http://your-domain.com/index.php
```

### 认证方式

需要在 `config.php` 中配置抖音账号的认证信息：
- `DEVICE_ID`: 默认别动
- `INSTALL_ID`: 默认别动
- `COOKIE`: 会话Cookie 从 douyin.com 网页提取

---

## 📝 API接口说明

### 1. 提交AI视频生成任务

#### 接口说明
提交一个AI视频生成任务，返回任务ID供后续查询使用。

#### 请求参数

| 参数名 | 类型 | 必填 | 默认值 | 说明 |
|--------|------|------|--------|------|
| `type` | string | 否 | `process` | 操作类型，固定为 `process` |
| `model` | string | 否 | `SuiBian/process` | 模型名称，格式：`模块名/方法名` |
| `url` | string | 是 | - | 图片URL，多个用逗号分隔 |
| `ugc_text` | array | 否 | - | 自定义提示词数组（见下方说明） |

#### ugc_text 参数说明

`ugc_text` 是一个数组，用于传递多段提示词。每个数组项包含以下字段：
- `full_word` (必填): 完整的提示词描述
- `content` (必填): 内容简述
- `type` (可选): 类型标识，系统会自动添加为 2

**ugc_text 格式示例：**
```json
[
  {
    "full_word": "第一段详细提示词",
    "content": "第一段主题"
  },
  {
    "full_word": "第二段详细提示词",
    "content": "第二段主题"
  }
]
```


#### 请求示例

**JSON格式（使用 ugc_text）：**
```bash
curl -X POST "http://your-domain.com/index.php" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "process",
    "model": "SuiBian/process",
    "url": "https://example.com/image1.jpg,https://example.com/image2.jpg",
    "ugc_text": [
      {
        "full_word": "第一段详细的AI视频生成提示词",
        "content": "第一段主题描述"
      },
      {
        "full_word": "第二段详细的AI视频生成提示词",
        "content": "第二段主题描述"
      }
    ]
  }'
```

#### 响应示例

**成功响应：**
```json
{
  "code": 200,
  "msg": "请求成功",
  "data": {
    "task_id": "7456789012345678901"
  }
}
```

**失败响应：**
```json
{
  "code": 400,
  "msg": "模型格式错误",
  "data": null
}
```

---

### 2. 查询任务进度

#### 接口说明
根据任务ID查询AI视频生成任务的进度和结果。

#### 请求参数

| 参数名 | 类型 | 必填 | 默认值 | 说明 |
|--------|------|------|--------|------|
| `type` | string | 是 | - | 操作类型，固定为 `query` |
| `model` | string | 否 | `SuiBian/query` | 模型名称 |
| `task_id` | string | 是 | - | 任务ID（从提交接口获取） |

#### 请求示例

**GET方式：**
```
GET http://your-domain.com/index.php?type=query&model=SuiBian/query&task_id=7456789012345678901
```

**POST方式：**
```bash
curl -X POST "http://your-domain.com/index.php" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "type=query" \
  -d "model=SuiBian/query" \
  -d "task_id=7456789012345678901"
```

#### 响应示例

**任务进行中：**
```json
{
  "code": 200,
  "msg": "请求成功",
  "data": {
    "task_list": [
      {
        "task_id": "7456789012345678901",
        "status": 1,
        "progress": 45,
        "videos": [],
        "covers": [],
        "wait_seconds": 30,
        "wait_minutes": 0,
        "wait_time_tip": "预计还需30秒",
        "raw": {...}
      }
    ]
  }
}
```

**任务完成：**
```json
{
  "code": 200,
  "msg": "请求成功",
  "data": {
    "task_list": [
      {
        "task_id": "7456789012345678901",
        "status": 2,
        "progress": 100,
        "videos": [
          "https://video-url.com/video.mp4"
        ],
        "covers": [
          "https://image-url.com/cover.jpg"
        ],
        "wait_seconds": 0,
        "wait_minutes": 0,
        "wait_time_tip": "",
        "raw": {...}
      }
    ]
  }
}
```

#### 状态说明

| status | 说明 | progress | videos/covers |
|--------|------|----------|---------------|
| 1 | 任务进行中 | 当前进度百分比 | 为空 |
| 2 | 任务完成 | 100 | 包含视频和封面URL |

---

## 🔧 响应状态码

| Code | 说明 |
|------|------|
| 200 | 请求成功 |
| 400 | 参数错误 |
| 500 | 服务器错误 |

---

### 获取到的视频链接需要cookie获取 
``` bash
curl -X GET 'https://video-cn.snssdk.com/******' -H 'Cookie: sessionid=; sessionid_ss='
```

## 💡 使用示例

### 没有


---

## ⚠️ 注意事项

1. **图片要求**
   - 图片URL必须可公开访问
   - 支持多张图片，使用逗号分隔
   - 建议图片分辨率不低于720p

2. **提示词建议**
   - 推荐使用 `ugc_text` 参数，支持任意数量的提示词段落
   - 也可使用传统参数 `full_word`、`content`、`full_word1`、`content1`（仅支持2段）
   - `full_word` 应该是详细的镜头描述
   - `content` 是简短的主题概括
   - 提示词越详细，生成效果越好
   - 系统会自动为每个 `ugc_text` 项添加 `type = 2`

3. **参数优先级**
   - 如果同时传入 `ugc_text` 和传统参数，优先使用 `ugc_text`
   - `ugc_text` 数组中的每项必须包含 `full_word` 和 `content` 字段

4. **任务查询**
   - 视频生成通常需要30-120秒
   - 建议每隔10-15秒查询一次任务状态
   - 不建议过于频繁查询，避免给服务器造成压力

5. **性能限制**
   - 默认执行时间限制：300秒（5分钟）
   - 请求超时时间：999秒
   - 失败自动重试次数：最多5次（POST）或10次（GET）

---

## 🔐 配置说明

### config.php 配置项

```php
<?php
class Config
{
    // 设备信息（必填）
    const DEVICE_ID = "你的设备ID";
    const INSTALL_ID = "你的安装ID";
    const COOKIE = "你的Cookie信息";
    
    // API版本
    const API_VERSION = "1.0.0";
    
    // 请求相关常量
    const REQUEST_TIMEOUT = 10;
    
    // 日志设置
    const LOG_LEVEL = "INFO"; // 可选: DEBUG, INFO, WARNING, ERROR
}
```

### 如何获取配置信息

1. **COOKIE**
   - 登录抖音网页版或APP后抓包获取
   - 需要包含 `sessionid` 和 `install_id`

---

## 📋 免责声明

### DISCLAIMER

**重要提示：请仔细阅读以下免责声明**

1. **使用目的**
   - 本项目仅供学习和研究使用
   - 不得用于任何商业用途
   - 使用者应遵守相关法律法规和服务条款

2. **风险提示**
   - 本项目调用第三方API接口，可能存在不稳定性
   - 第三方服务可能随时更改接口或终止服务
   - 使用本项目可能违反第三方服务的用户协议

3. **账号安全**
   - 使用本项目需要配置第三方账号信息
   - 账号信息存在泄露风险
   - 可能导致账号被封禁或限制
   - 建议使用测试账号，不要使用重要账号

4. **数据责任**
   - 用户对上传的图片和生成的内容负全部责任
   - 不得上传违法、侵权、不当内容
   - 生成的视频内容使用权归属第三方平台

5. **法律责任**
   - 使用本项目即表示您已知晓并接受所有风险
   - 因使用本项目导致的任何损失，开发者不承担责任
   - 因违反法律法规导致的后果由使用者自行承担

6. **知识产权**
   - 生成的视频可能包含版权内容
   - 使用者需自行确保拥有使用权
   - 不得侵犯他人知识产权

7. **服务中断**
   - 本项目不保证服务的持续性和稳定性
   - 可能随时停止维护或更新
   - 第三方API变更可能导致功能失效

8. **隐私保护**
   - 用户应保护好自己的配置文件
   - 不要将包含敏感信息的配置分享给他人
   - 开发者不收集用户任何数据

**通过使用本项目，您表示已完全理解并同意上述所有条款。如果您不同意任何条款，请立即停止使用本项目。**


## 📄 许可证

本项目仅供学习交流使用，请勿用于商业用途。

---

**⚡️ 温馨提示：**
- 请妥善保管你的配置信息
- 建议使用测试账号进行调试
- 遵守相关法律法规和平台规则
- 理性使用，避免滥用

---

*最后更新: 2026年2月19日*
