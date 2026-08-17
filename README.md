# MBTI-Clash · 社交对撞图文生成器

> 每日一造
> 输入一句话场景，生成 E 人 vs I 人的「社交对撞」聊天图文，一键导出 9:16 高清竖版图，直接发小红书。

---

## 它是什么

MBTI-Clash 把「MBTI 人格反差」做成可量产的小红书图文：

1. **一句话生成剧本**：输入任意场景（或留空随机），LLM 返回一份结构化的「对撞剧本」JSON——E 人话痨亢奋、I 人精简冷淡，4–8 条对撞台词，反差即流量。
2. **双布局渲染**：`split` 双栏对比（E / I 同屏对撞）或 `chat` 单栏对话流（左右收发），iMessage 气泡风、非对称圆角、低饱和莫兰迪渐变，全程 CSS 渲染。
3. **一键导出**：3x 高清 PNG（默认约 1170×2079 竖版），长对话自动撑开高度完整截长图，文件名含场景 id。

**零生图成本**：所有视觉（头像/气泡/背景/噪点）都是代码生成，不需要任何生图 API。

## 快速开始

需要 Node.js 20+，无需自备 PHP（内置 PHP 运行时）。

```bash
npm install
npm start        # 默认 http://127.0.0.1:3000 ，PORT=8080 npm start 可换端口
```

1. 浏览器打开 `http://127.0.0.1:3000/` 进入工作台。
2. 首次使用先配置接口：点工作台右上角 **⚙ 设置**（或直接打开 `panel.php`），填入三项：

| 字段 | 说明 | 示例 |
| --- | --- | --- |
| 接口地址 | OpenAI 兼容的 chat/completions 完整地址 | `https://api.deepseek.com/v1/chat/completions` |
| API Key | 你的大模型 Key | `sk-…` |
| 模型 | 模型名 | `deepseek-v4-flash` |

3. 回到工作台，输入一句场景（如「E 人和 I 人抢最后一份烤肠」），点「AI 智能生成」，再点「导出高清图片」。

> 设置保存在 `data/settings.json`（首次保存自动生成，含 Key，已被 `.gitignore` 排除，不会提交），与 `data/onez.php` 的 `openai()` 读取字段完全对齐。

## 也可以直接用内置预设

不配 Key 也能完整体验：工作台右侧有 6 个预设场景（老板半夜发消息 / 周末邀约 / 食堂手抖 / 50 人大群 / 领导派活 / AI 演示），点选即可渲染与导出。

## JSON 数据契约（LLM 返回结构）

```json
{
  "id": "boss-message",
  "layout": "split",
  "theme": { "bg": "linear-gradient(...)", "ink": "#2B2B33", "soft": "#8A8A99" },
  "scene": { "emoji": "💼", "kicker": "SCENE 01 · 职场生存", "title": "老板周末晚上11点\n突然发来工作消息", "hint": "同一件事 · 两种人格的顶级拉扯", "label": "💼 老板半夜发消息" },
  "vs": {
    "left":  { "type": "ENFP", "name": "快乐小狗", "tagline": "E人 · 社交充电型", "gradient": ["#FF9A9E", "#FECFEF"], "accent": "#FF6B81", "ink": "#8A2B3D" },
    "right": { "type": "INTJ", "name": "高冷统帅", "tagline": "I人 · 独处回血型", "gradient": ["#a1c4fd", "#c2e9fb"], "accent": "#4F7DF9", "ink": "#2B4A8A" }
  },
  "messages": [
    { "sender": "e", "time": "23:01", "text": "老板这个点发消息，肯定有大事！" },
    { "sender": "i", "time": "23:01", "text": "……" }
  ]
}
```

- `layout`：`split` 双栏对比 / `chat` 单栏对话流。
- `sender`：`e` = E 人，`i` = I 人；4–8 条，E/I 交错。
- 文案 1–2 行短句（≤30 字）；E 人多感叹号与 emoji，I 人精简冷淡。
- 配色低饱和高级感渐变；完整生成规则见 `prompt.md`（LLM 系统提示词，唯一真源），接口实现见 `api.php`。

## 目录结构

```
github/
├── index.php          # 工作台主页（画布 + AI 控制台 + 导出）
├── panel.php          # 设置面板（选项卡：设置 / 关于）
├── api.php            # 生成接口（action=json：openai_tool + prompt.md）
├── prompt.md          # LLM 系统提示词（剧本生成规则，唯一真源）
├── data/
│   ├── onez.php       # 运行时库（openai() / openai_tool() 等）
│   ├── db.js          # 内嵌文件数据库（@seald-io/nedb）
│   └── settings.json  # 用户设置（含 Key，gitignore，不入库）
├── js/
│   ├── html2canvas.min.js  # 长图导出
│   └── onez.market.js      # 请求封装
├── static/
│   ├── font-awesome/  # 图标字体
│   └── qr/            # 赞赏二维码（wechat.png / alipay.jpg）
├── server.js          # 本地服务（静态 + 内置 PHP + WebSocket）
└── package.json
```

## 支持与赞赏

| 微信赞赏码 | 支付宝赞赏码 |
| --- | --- |
| <img src="static/qr/wechat.png" alt="微信赞赏码" width="160" /> | <img src="static/qr/alipay.jpg" alt="支付宝赞赏码" width="160" /> |

仅用于自愿赞赏，不涉及付费功能。

## 联系

- 官网：https://factory.onez.cn
- 微信公众号：佳蓝AI

## 许可与第三方

- 工具源码：MIT（见 `LICENSE`）。
- 第三方依赖与分发注意义务见 `THIRD_PARTY_NOTICES.md`。

---

由 沧州佳蓝网络科技有限公司出品。演示即真实产物，不画饼。
