<?php
include(dirname(__FILE__).'/data/onez.php');
ob_clean();
$params=json_decode(file_get_contents('php://input'),1);
if($params){
  $action=$params['action'];
  // try{
    include(dirname(__FILE__).'/api.php');
  // }catch(Exception $e){
  //   $A=[
  //     'status'=>'error',
  //     'error'=>$e->getMessage(),
  //   ];
  // }
  onez()->output($A);
}
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>MBTI-Clash AI创作台 | 佳蓝造物</title>
<style>
/* ============================================================
   MBTI-Clash AI 创作台 (左右工作流专业版)
   纯 HTML + 本地打包第三方库（离线可用）/ JSON 驱动 + 固定模板解析
   佳蓝造物 · 每日一造
   全功能对齐：LLM 对话框 + 双布局 + 音画播放 + 长图导出
   ============================================================ */
:root {
  --font-sans: -apple-system, BlinkMacSystemFont, "PingFang SC", "Helvetica Neue", sans-serif;
  --font-serif: "Songti SC", "Noto Serif SC", STSong, serif;
  --primary: #7C6CF0;
  --primary-hover: #6a5be0;
  --bg-dark: #1A1A24;
  --panel-bg: #FFFFFF;
  --text-main: #2B2B33;
  --text-sub: #8A8A99;
  --border: #E5E5F0;
  --soft: #8A8A99;
}
* { margin:0; padding:0; box-sizing:border-box; }
body {
  font-family: var(--font-sans);
  background: var(--bg-dark);
  color: var(--text-main);
  height: 100vh;
  display: flex;
  overflow: hidden;
}

/* ---------- 核心布局：左侧画布区 / 右侧 AI 控制台 ---------- */
.workspace {
  flex: 1;
  position: relative;
  display: flex;
  align-items: center;
  justify-content: center;
  /* 网格背景，专业工具的标配 */
  background-image:
    linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
  background-size: 30px 30px;
}
.panel-right {
  width: 380px;
  background: var(--panel-bg);
  border-left: 1px solid rgba(0,0,0,0.1);
  box-shadow: -10px 0 30px rgba(0,0,0,0.1);
  display: flex;
  flex-direction: column;
  z-index: 10;
}

/* ---------- 左侧：手机画布区 ---------- */
.stage { transform-origin: center center; }
.phone-shell {
  width: 424px; height: 769px;
  background: #111;
  border-radius: 54px;
  padding: 16px 16px 22px;
  display: flex; flex-direction: column; align-items: center; gap: 10px;
  box-shadow: 0 40px 100px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.1);
}
.phone-island { width: 112px; height: 26px; background: #000; border-radius: 99px; flex-shrink: 0; }
/* 9:16 画幅约束；长内容在画布内部滚动，导出时动态撑开完整截图 */
.capture-area {
  width: 390px; height: 693px; flex: 0 0 auto;
  border-radius: 36px; overflow: hidden; position: relative;
  background: #fff;
}

/* ---------- 右侧：AI Copilot 控制台 UI ---------- */
.panel-header {
  padding: 24px 24px 20px;
  border-bottom: 1px solid var(--border);
}
.panel-title { font-size: 18px; font-weight: 800; display: flex; align-items: center; gap: 8px; }
.panel-title i { font-size: 22px; }
.panel-subtitle { font-size: 12px; color: var(--text-sub); margin-top: 6px; line-height: 1.5; }

.panel-body {
  flex: 1; overflow-y: auto; padding: 24px;
  display: flex; flex-direction: column; gap: 24px;
}

/* AI 提示词输入区 */
.ai-section {
  background: #F6F6FA; border-radius: 16px; padding: 16px;
  border: 1px solid transparent; transition: 0.3s;
}
.ai-section:focus-within { border-color: var(--primary); background: #fff; box-shadow: 0 4px 20px rgba(124,108,240,0.1); }
.section-label { font-size: 13px; font-weight: 700; margin-bottom: 12px; display: flex; align-items: center; gap: 6px; }
.ai-textarea {
  width: 100%; height: 80px; background: transparent; border: none; outline: none;
  font-family: inherit; font-size: 14px; line-height: 1.6; resize: none; color: var(--text-main);
}
.ai-textarea::placeholder { color: #A0A0AB; }
.ai-btn {
  width: 100%; height: 44px; border-radius: 12px; border: none;
  background: linear-gradient(135deg, #7C6CF0, #5E8BFF);
  color: #fff; font-size: 14px; font-weight: 700; cursor: pointer;
  display: flex; align-items: center; justify-content: center; gap: 8px;
  box-shadow: 0 8px 20px rgba(124,108,240,0.3); transition: 0.2s;
}
.ai-btn:hover { filter: brightness(1.1); transform: translateY(-1px); }
.ai-btn:active { transform: translateY(0); }
.ai-btn.loading { opacity: 0.7; cursor: wait; }

/* 预设灵感胶囊（由 JSON 动态生成） */
.presets { display: flex; flex-wrap: wrap; gap: 8px; margin-top: -8px; }
.preset-tag {
  font-size: 12px; padding: 6px 12px; background: #fff; border: 1px solid var(--border);
  border-radius: 99px; cursor: pointer; color: var(--text-sub); transition: 0.2s;
}
.preset-tag:hover { color: var(--primary); border-color: var(--primary); background: #F3EEFF; }

/* 播放与导出控制条 */
.panel-footer {
  padding: 20px 24px; border-top: 1px solid var(--border);
  background: #F8F8FC; display: flex; flex-direction: column; gap: 12px;
}
.progress-row { display: flex; align-items: center; gap: 10px; }
.progress { flex: 1; height: 4px; background: #E5E5F0; border-radius: 4px; overflow: hidden; }
.progress-fill { height: 100%; width: 0%; background: var(--primary); border-radius: 4px; transition: 0.3s; }
.progress-label { font-size: 11px; color: var(--text-sub); font-weight: 600; font-variant-numeric: tabular-nums; white-space: nowrap; }
.control-row { display: flex; gap: 10px; }
.btn-sm {
  flex: 1; height: 38px; border-radius: 10px; border: 1px solid var(--border);
  background: #fff; color: var(--text-main); font-size: 13px; font-weight: 600;
  cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: 0.2s;
}
.btn-sm:hover { background: #f0f0f5; }
.btn-export {
  width: 100%; height: 44px; border-radius: 12px; border: none;
  background: #1D1D26; color: #fff; font-size: 14px; font-weight: 700;
  cursor: pointer; box-shadow: 0 8px 20px rgba(0,0,0,0.15); transition: 0.2s;
}
.btn-export:hover { background: #2A2A36; }
.btn-settings {
  width: 44px; height: 44px; border-radius: 12px; border: 1px solid var(--border);
  background: #fff; color: var(--text-main); font-size: 17px; cursor: pointer;
  display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: 0.2s;
}
.btn-settings:hover { background: #f0f0f5; }

/* ============================================================
   画布内部视觉（导出区：仅用 html2canvas 安全属性）
   ============================================================ */
.mbti-clash{
  position:relative;width:100%;height:100%;
  display:flex;flex-direction:column;
  overflow-y:auto;overflow-x:hidden;
  font-family:var(--font-sans);
  color:var(--text-main);
  transition:background .6s ease;
}
.mbti-clash::-webkit-scrollbar{width:0;height:0;display:none;}
.mbti-clash{scrollbar-width:none;}
/* CSS 微颗粒噪点（模拟胶片磨砂，可被 html2canvas 安全导出） */
.mbti-clash::after{
  content:"";position:absolute;inset:0;z-index:40;pointer-events:none;
  background-image:
    radial-gradient(rgba(20,20,40,.035) .5px, transparent .6px),
    radial-gradient(rgba(255,255,255,.06) .5px, transparent .6px);
  background-size:3px 3px, 5px 5px;
  background-position:0 0, 1px 2px;
}
/* 导出长图态：解除滚动限制，完整呈现全部气泡 */
.mbti-clash.exporting,.mbti-clash.exporting .chat-stream{overflow:visible !important;}

/* 场景头部 */
.scene-header{padding:26px 22px 0;position:relative;z-index:5;}
.scene-kicker{
  display:inline-flex;align-items:center;gap:6px;
  font-size:10px;font-weight:800;letter-spacing:2.5px;color:#fff;
  background:linear-gradient(135deg,#7C6CF0,#5E8BFF);
  padding:5px 12px;border-radius:999px;
  box-shadow:0 8px 18px rgba(124,108,240,.28);
}
.scene-title{
  font-family:var(--font-serif);
  font-size:23px;font-weight:900;line-height:1.42;letter-spacing:1px;
  margin-top:14px;white-space:pre-line;
}
.scene-emoji{
  position:absolute;top:20px;right:22px;z-index:6;
  font-size:44px;filter:drop-shadow(0 8px 16px rgba(0,0,0,.12));
  animation:floaty 3.6s ease-in-out infinite alternate;
}
@keyframes floaty{0%{transform:translateY(0) rotate(-3deg);}100%{transform:translateY(-7px) rotate(4deg);}}
.scene-hint{font-size:11px;color:var(--soft);margin-top:8px;letter-spacing:1.2px;font-weight:600;}

/* E/I 人格对比胶囊 */
.vs-row{display:flex;align-items:center;gap:14px;margin:18px 22px 14px;position:relative;z-index:5;}
.persona-chip{
  flex:1;display:flex;align-items:center;gap:10px;
  background:rgba(255,255,255,.78);
  border:1px solid rgba(255,255,255,.9);
  border-radius:16px;padding:10px 12px;
  box-shadow:0 10px 24px rgba(40,40,80,.07);
}
.mini-avatar{
  width:34px;height:34px;border-radius:50%;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
  color:#fff;font-size:9px;font-weight:900;letter-spacing:.5px;
  box-shadow:0 4px 10px rgba(0,0,0,.12), inset 0 1px 0 rgba(255,255,255,.25);
}
.p-name{font-size:13px;font-weight:800;line-height:1.2;}
.p-type{font-size:10px;font-weight:700;opacity:.72;letter-spacing:.6px;margin-top:2px;}
.vs-badge{
  width:36px;height:36px;border-radius:50%;flex-shrink:0;
  background:linear-gradient(135deg,#FF6B81,#7C6CF0);
  color:#fff;font-size:11px;font-weight:900;letter-spacing:.5px;
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 8px 18px rgba(124,108,240,.35), inset 0 1px 0 rgba(255,255,255,.3);
}

/* 双栏对比 */
.compare-grid{flex:1;display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:0 14px 0;min-height:0;margin-bottom:10px;}
.persona-panel{
  display:flex;flex-direction:column;min-height:0;
  background:rgba(255,255,255,.66);
  border:1px solid rgba(255,255,255,.9);
  border-radius:20px;overflow:hidden;
  box-shadow:0 14px 34px rgba(40,40,80,.08);
}
.panel-head{
  display:flex;align-items:center;gap:8px;
  padding:12px 12px 10px;border-bottom:1px solid rgba(0,0,0,.05);
  background:rgba(255,255,255,.55);
}
.avatar-lg{
  width:30px;height:30px;border-radius:50%;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
  color:#fff;font-size:8px;font-weight:900;letter-spacing:.4px;
  box-shadow:0 4px 10px rgba(0,0,0,.12), inset 0 1px 0 rgba(255,255,255,.28);
}
.ph-name{font-size:12px;font-weight:800;line-height:1.15;}
.ph-tag{font-size:9px;font-weight:600;opacity:.68;margin-top:2px;letter-spacing:.4px;}
.ph-count{margin-left:auto;font-size:9px;font-weight:800;opacity:.5;letter-spacing:.5px;}

/* 对话流：支持纵向滚动，隐藏滚动条 */
.chat-stream{
  flex:1;overflow-y:auto;overflow-x:hidden;
  padding:12px 12px 14px;
  display:flex;flex-direction:column;gap:10px;
}
.chat-stream::-webkit-scrollbar{width:0;height:0;display:none;}
.chat-stream{scrollbar-width:none;}
.chat-stream.flow{padding:14px 16px 16px;gap:9px;}

/* 单行聊天：头像 + 气泡 */
.chat-row{display:flex;gap:8px;align-items:flex-start;opacity:0;transform:translateY(10px) scale(.96);}
.chat-row.visible{opacity:1;transform:translateY(0) scale(1);transition:opacity .4s ease,transform .42s cubic-bezier(.2,.9,.3,1.4);}
.chat-row.right{flex-direction:row-reverse;}
.chat-row.right .msg-col{align-items:flex-end;text-align:right;}
.avatar{
  width:30px;height:30px;border-radius:50%;flex-shrink:0;margin-top:14px;
  display:flex;align-items:center;justify-content:center;
  color:#fff;font-size:8px;font-weight:900;letter-spacing:.4px;
  box-shadow:0 4px 10px rgba(0,0,0,.12), inset 0 1px 0 rgba(255,255,255,.28);
}
.msg-col{display:flex;flex-direction:column;gap:4px;min-width:0;}
.chat-label{font-size:9px;color:var(--soft);font-weight:600;letter-spacing:.3px;}
.bubble{max-width:100%;padding:9px 11px;font-size:12.5px;line-height:1.55;color:var(--text-main);font-weight:500;word-break:break-word;}
/* iMessage 风格：非对称圆角 + 极低透明度彩色阴影 */
.bubble-e{background:#fff;border-radius:4px 16px 16px 16px;box-shadow:0 4px 15px rgba(124,108,240,.07);}
.bubble-i{background:#fff;border-radius:16px 4px 16px 16px;box-shadow:0 4px 15px rgba(94,139,255,.07);}

/* 底部常驻 */
.card-footer{
  margin-top:auto;padding:13px 22px 18px;position:relative;z-index:5;
  display:flex;align-items:center;justify-content:space-between;gap:10px;
}
.foot-brand{font-size:10px;font-weight:800;letter-spacing:1.6px;opacity:.55;}
.foot-line{font-size:9px;font-weight:600;letter-spacing:.6px;opacity:.45;text-align:right;line-height:1.5;}
.foot-chip{
  font-size:9px;font-weight:800;letter-spacing:.5px;color:#fff;
  padding:4px 10px;border-radius:999px;white-space:nowrap;
}

/* Toast */
.toast{
  position:fixed;left:50%;top:24px;transform:translateX(-50%) translateY(-20px);
  background:rgba(29,29,38,.92);color:#fff;font-size:13px;font-weight:600;
  padding:12px 24px;border-radius:14px;opacity:0;pointer-events:none;
  transition:0.3s;z-index:999;box-shadow:0 12px 30px rgba(0,0,0,.25);max-width:86vw;text-align:center;
}
.toast.show{opacity:1;transform:translateX(-50%) translateY(0);}
</style></head>
<body>

<!-- 左侧画布区 -->
<div class="workspace" id="workspace">
  <div class="stage" id="stage">
    <div class="phone-shell">
      <div class="phone-island"></div>
      <div class="capture-area" id="captureArea">
        <div class="mbti-clash" id="sceneRoot">
          <!-- JS 渲染区 -->
        </div>
      </div>
    </div>
  </div>
</div>

<!-- 右侧 AI 控制台 -->
<div class="panel-right">
  <div class="panel-header">
    <div class="panel-title">🧠 MBTI-Clash 创作台</div>
    <div class="panel-subtitle">佳蓝造物 · 每日一造</div>
  </div>

  <div class="panel-body">
    <div class="ai-section">
      <div class="section-label">⚡ 一句话生成对撞剧本</div>
      <textarea id="aiPrompt" class="ai-textarea" placeholder="例如：帮我写一个 E 人和 I 人去食堂打饭时，遇到阿姨手抖的爆笑场景..."></textarea>
      <button class="ai-btn" id="generateBtn">✨ AI 智能生成</button>
    </div>

    <div>
      <div class="section-label">💡 或选择预设场景测试</div>
      <div class="presets" id="presets"><!-- JSON 动态生成 --></div>
    </div>
  </div>

  <div class="panel-footer">
    <div class="progress-row">
      <div class="progress"><div class="progress-fill" id="progressFill"></div></div>
      <span class="progress-label" id="progressLabel">0 / 0</span>
    </div>
    <div class="control-row">
      <button class="btn-sm" id="playBtn">▶ 播放动画</button>
      <button class="btn-sm" id="replayBtn">↺ 重置排版</button>
      <button class="btn-sm" id="soundBtn">🔊 音效</button>
    </div>
    <div style="display: flex;gap: 10px;">
      <button class="btn-export" id="exportBtn" style="flex:1">⬇ 导出高清图片</button>
      <button class="btn-settings" id="settingsBtn" title="设置">⚙️</button>
      <?=$G['buttons']?>
    </div>
  </div>
</div>

<div class="toast" id="toast" ></div>

<?onez_lib('js/html2canvas.min.js');?>

<script>
(function () {
  "use strict";
  /* ============================================================
   * JSON 模板数据源（PRESET_DATA / AI_DEMO_SCENE）
   * —— 更换/增删即可生成全新作品，文案与链接零硬编码。
   * 契约（渲染器对缺省字段有兜底，LLM 可输出精简版）：
   *   id      : 场景唯一标识（导出文件名）
   *   layout  : "split" 双栏对比（E vs I） | "chat" 单栏对话流
   *   theme   : { bg 画布渐变 / ink 正文色 / soft 次要色 }
   *   scene   : { emoji / kicker / title(\n 换行) / hint / label(预设胶囊文案) }
   *   vs      : { left / right: { type / name / tagline / gradient / accent / ink } }
   *   messages: [{ sender:"e"|"i", time:"HH:MM", text }]
   * ============================================================ */
  const PRESET_DATA = [
    {
      id: "boss-message",
      layout: "split",
      theme: { bg: "linear-gradient(165deg, #F6F1FF, #EAF1FF, #FDF0F3)", ink: "#2B2B33", soft: "#8A8A99" },
      scene: { emoji: "💼", kicker: "SCENE 01 · 职场生存", title: "老板周末晚上11点\n突然发来工作消息", hint: "同一件事 · 两种人格的顶级拉扯", label: "💼 老板半夜发消息" },
      vs: {
        left:  { type:"ENFP", name:"快乐小狗", tagline:"E人 · 社交充电型", gradient:["#FF9A9E","#FECFEF"], accent:"#FF6B81", ink:"#8A2B3D" },
        right: { type:"INTJ", name:"高冷统帅", tagline:"I人 · 独处回血型", gradient:["#a1c4fd","#c2e9fb"], accent:"#4F7DF9", ink:"#2B4A8A" }
      },
      messages: [
        { sender:"e", time:"23:01", text:"老板这个点发消息，肯定有大事！" },
        { sender:"i", time:"23:01", text:"……" },
        { sender:"e", time:"23:02", text:"先激情回复：马上安排！" },
        { sender:"i", time:"23:02", text:"已读。装死。" },
        { sender:"e", time:"23:03", text:"顺便拉个群，大家一起头脑风暴！" },
        { sender:"i", time:"23:03", text:"明天再回。" }
      ]
    },
    {
      id: "weekend-invite",
      layout: "split",
      theme: { bg: "linear-gradient(165deg, #FFFAF3, #F3F7FF, #FFEEF4)", ink: "#33302B", soft: "#9A958C" },
      scene: { emoji: "⛺️", kicker: "SCENE 02 · 周末邀约", title: "朋友问：\n周末出来露营吗？", hint: "一个心动到飞起 · 一个只想躺平", label: "⛺️ 突然的周末邀约" },
      vs: {
        left:  { type:"ESFP", name:"派对动物", tagline:"E人 · 现场氛围制造机", gradient:["#FFC371","#FF5F6D"], accent:"#FF7A45", ink:"#9A3B1F" },
        right: { type:"INTP", name:"冷静观察员", tagline:"I人 · 电量管理大师", gradient:["#89F7FE","#66A6FF"], accent:"#3D9BE9", ink:"#1F5D8A" }
      },
      messages: [
        { sender:"e", time:"11:00", text:"！！！去去去！我马上查攻略！" },
        { sender:"i", time:"11:02", text:"……" },
        { sender:"e", time:"11:02", text:"再带只柯基，氛围直接拉满！" },
        { sender:"i", time:"11:05", text:"那天约了床。" },
        { sender:"e", time:"11:06", text:"你到了只负责快乐就行！" },
        { sender:"i", time:"11:07", text:"谢谢，我在家浇花。" }
      ]
    },    {
      // 模拟 AI 动态生成的场景
      id: "canteen-shake",
      layout: "split",
      theme: { bg: "linear-gradient(165deg, #FFF0F3, #EEF6FF, #FFF7E8)", ink: "#3A2E33", soft: "#9A858E" },
      scene: { emoji: "🍚", kicker: "SCENE 03 · 校园日常", title: "食堂打肉时\n遇到阿姨严重手抖", hint: "一个当面开麦 · 一个内心狂飙", label: "🍚 食堂打饭遇手抖" },
      vs: {
        left:  { type:"ENTP", name:"社交悍匪", tagline:"E人 · 当场开麦", gradient:["#F6D365","#FDA085"], accent:"#F2994A", ink:"#8A4B12" },
        right: { type:"ISFJ", name:"讨好型人格", tagline:"I人 · 内心剧场", gradient:["#A18CD1","#FBC2EB"], accent:"#B06AB3", ink:"#6B2D73" }
      },
      messages: [
        { sender:"e", time:"12:10", text:"阿姨！你这手抖得是帕金森前兆吗？！" },
        { sender:"i", time:"12:10", text:"（内心）好少…算了，不敢说。" },
        { sender:"e", time:"12:11", text:"再给我添一勺！我这体格吃不饱啊！" },
        { sender:"i", time:"12:11", text:"阿姨辛苦了，谢谢阿姨。" },
        { sender:"e", time:"12:12", text:"（直接拿过勺子）阿姨我帮你打，你歇着！" },
        { sender:"i", time:"12:12", text:"（默默端着半碗白饭找角落坐下）" }
      ]
    },
    {
      id: "group-chat",
      layout: "chat",
      theme: { bg: "linear-gradient(160deg, #F4F6FF, #FBF0FF, #F0FFF4)", ink: "#2E2B38", soft: "#8E8A9C" },
      scene: { emoji: "🎉", kicker: "SCENE 04 · 大群现场", title: "E 人把 I 人\n拉进了 50 人大群", hint: "满屏消息轰炸 vs 一键静音保平安", label: "🎉 被拉进 50 人大群" },
      vs: {
        left:  { type:"ENFJ", name:"气氛组组长", tagline:"E人 · 群聊发动机", gradient:["#FFD86F","#FC5C7D"], accent:"#F77F2E", ink:"#8A4A12" },
        right: { type:"INFJ", name:"隐身大师", tagline:"I人 · 消息折叠专业户", gradient:["#A18CD1","#FBC2EB"], accent:"#7C6CF0", ink:"#4A3B8A" }
      },
      messages: [
        { sender:"e", time:"16:00", text:"家人们！！我把咱们部门的快乐星球群建好了！🎉" },
        { sender:"i", time:"16:01", text:"？谁把我拉进来的。" },
        { sender:"e", time:"16:01", text:"是我是我！群里每天都有梗，保证你笑到腹肌疼！" },
        { sender:"i", time:"16:02", text:"消息已折叠。" },
        { sender:"e", time:"16:04", text:"好嘞！以后群里的气氛组就交给我了！" },
        { sender:"i", time:"16:05", text:"我已开启免打扰。有事请私聊，没事别私聊。" }
      ]
    },
    {
      id: "project-lead",
      layout: "split",
      theme: { bg: "linear-gradient(165deg, #EEF6FF, #F6F1FF, #FFF4E8)", ink: "#26303E", soft: "#7D8799" },
      scene: { emoji: "🚀", kicker: "SCENE 05 · 职场修罗场", title: "领导说：\n「这个项目你牵头」", hint: "一个热血上头 · 一个冷静拆解", label: "🚀 领导说项目你牵头" },
      vs: {
        left:  { type:"ENFP", name:"梦想家", tagline:"E人 · 脑暴永动机", gradient:["#F6D365","#FDA085"], accent:"#F2994A", ink:"#8A4B12" },
        right: { type:"ISTJ", name:"风险管理员", tagline:"I人 · 流程守护者", gradient:["#84FAB0","#8FD3F4"], accent:"#2FB57A", ink:"#116B45" }
      },
      messages: [
        { sender:"e", time:"09:00", text:"！！！领导选我当负责人！这是认可我啊！" },
        { sender:"i", time:"09:01", text:"收到。先确认三点：目标、期限、预算。" },
        { sender:"e", time:"09:02", text:"我昨晚已经脑暴了 20 个创意方向，领导您看哪个行！" },
        { sender:"i", time:"09:02", text:"我自己列了风险清单和里程碑，下午给结论。" },
        { sender:"e", time:"09:03", text:"冲就完了！我们一定能做出爆款！" },
        { sender:"i", time:"09:03", text:"希望 deadline 是明年。完毕。" }
      ]
    }
  ];

  /* AI 生成演示剧本（对话流，展示 chat 布局） */
  const AI_DEMO_SCENE = {
    id: "mbti-test",
    layout: "chat",
    theme: { bg: "linear-gradient(160deg, #FFF0F3, #F0F4FF, #F3EEFF)", ink: "#3A2E33", soft: "#9A858E" },
    scene: { emoji: "🧬", kicker: "AI 剧本 · 人格觉醒", title: "MBTI 测试链接\n被甩进群里之后", hint: "测完的人都在认亲 · 只有 I 人想消失" },
    vs: {
      left:  { type:"ENFP", name:"快乐小狗", tagline:"E人 · 测试狂热粉", gradient:["#FF9A9E","#FECFEF"], accent:"#FF6B81", ink:"#8A2B3D" },
      right: { type:"INFP", name:"诗意隐士", tagline:"I人 · 测完就消失", gradient:["#FBC2EB","#A18CD1"], accent:"#B06AB3", ink:"#6B2D73" }
    },
    messages: [
      { sender:"e", time:"21:00", text:"姐妹们快去测！我测出来是 ENFP，快乐小狗本狗！" },
      { sender:"i", time:"21:03", text:"测了。INFP。勿扰。" },
      { sender:"e", time:"21:04", text:"哇 INFP 好稀有！来让我蹭蹭文艺气息！" },
      { sender:"i", time:"21:05", text:"我只是路过。退下了。" }
    ]
  };

  /* ---------- 状态 ---------- */
  let root, captureEl, stage;
  let currentData = null, currentLayout = "split";
  let timeline = [];          // [{ el: DOM, panel: 'e'|'i' }]
  let playing = false, soundOn = true;
  let tIndex = 0, timerId = null, resizeTimer = null;
  let audioCtx = null;
  const STEP_DELAY = 800;

  /* ---------- DOM 工具（全程 textContent，杜绝 XSS） ---------- */
  function el(tag, cls, text){
    const node = document.createElement(tag);
    if (cls) node.className = cls;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
  }
  function $(id){ return document.getElementById(id); }
  function pick(obj, key, fallback){
    return obj && obj[key] !== undefined && obj[key] !== null ? obj[key] : fallback;
  }
  function grad(p){
    return "linear-gradient(135deg," + (p.gradient && p.gradient[0] || "#d8d8e0") + "," + (p.gradient && p.gradient[1] || "#b9b9c8") + ")";
  }

  /* ---------- Toast ---------- */
  let toastTimer = null;
  function toast(msg){
    const t = $("toast");
    t.textContent = msg;
    t.classList.add("show");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function(){ t.classList.remove("show"); }, 2200);
  }

  /* ---------- 音频：点击播放时在用户手势栈内解锁（Safari Autoplay Policy） ---------- */
  function ensureAudioUnlocked(){
    if (!soundOn) return;
    try {
      if (!audioCtx) {
        const AC = window.AudioContext || window.webkitAudioContext;
        if (!AC) return;
        audioCtx = new AC();
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        gain.gain.value = 0.0001;
        osc.connect(gain); gain.connect(audioCtx.destination);
        osc.start(0); osc.stop(0.001);
      }
      if (audioCtx.state === "suspended") audioCtx.resume();
    } catch (e) { /* 忽略音频异常 */ }
  }
  /* 气泡弹出提示音（视觉与听觉匹配） */
  function popSound(){
    if (!soundOn || !audioCtx) return;
    try {
      const now = audioCtx.currentTime;
      const osc = audioCtx.createOscillator();
      const gain = audioCtx.createGain();
      osc.type = "sine";
      osc.frequency.setValueAtTime(540, now);
      osc.frequency.exponentialRampToValueAtTime(940, now + 0.09);
      gain.gain.setValueAtTime(0.0001, now);
      gain.gain.exponentialRampToValueAtTime(0.07, now + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.18);
      osc.connect(gain); gain.connect(audioCtx.destination);
      osc.start(now); osc.stop(now + 0.2);
    } catch (e) { /* 忽略音频异常 */ }
  }

  /* ---------- 渲染引擎：JSON → 固定模板（兼容精简版 LLM 输出） ---------- */
  function renderScene(json){
    if (!json || !json.vs || !json.vs.left || !json.vs.right) {
      toast("数据格式不正确：缺少 vs.left / vs.right");
      return;
    }
    currentData = json;
    currentLayout = pick(json, "layout", "split");
    const theme = json.theme || {};
    root.innerHTML = "";
    root.style.background = pick(theme, "bg", "linear-gradient(165deg,#F6F1FF,#EAF1FF)");
    root.style.color = pick(theme, "ink", "#2B2B33");

    const left = json.vs.left, right = json.vs.right;
    const scene = json.scene || {};

    /* 头部 */
    const header = el("div", "scene-header");
    header.appendChild(el("span", "scene-kicker", pick(scene, "kicker", "MBTI CLASH")));
    const emoji = el("div", "scene-emoji", pick(scene, "emoji", "⚡"));
    const title = el("h2", "scene-title", pick(scene, "title", "同一件事\n两种人格"));
    header.appendChild(emoji);
    header.appendChild(title);
    if (scene.hint) header.appendChild(el("div", "scene-hint", scene.hint));
    root.appendChild(header);

    timeline = [];

    if (currentLayout === "chat") {
      /* 单栏对话流（隐藏 vs 胶囊，为长对话让出纵向空间） */
      const flow = el("div", "chat-stream flow");
      (json.messages || []).forEach(function (m) {
        const row = buildRow(json, m);
        row.classList.add(m.sender === "e" ? "left" : "right");
        flow.appendChild(row);
        timeline.push({ el: row, panel: m.sender === "e" ? "e" : "i" });
      });
      root.appendChild(flow);
    } else {
      /* E / I 对比胶囊 */
      const vsRow = el("div", "vs-row");
      vsRow.appendChild(buildChip(left));
      vsRow.appendChild(el("div", "vs-badge", "VS"));
      vsRow.appendChild(buildChip(right));
      root.appendChild(vsRow);

      /* 双栏对比：e 消息进左栏，i 消息进右栏，交错播放 */
      const grid = el("div", "compare-grid");
      const panelE = buildPanel(left);
      const panelI = buildPanel(right);
      grid.appendChild(panelE.panel);
      grid.appendChild(panelI.panel);
      root.appendChild(grid);

      const rowsE = [], rowsI = [];
      (json.messages || []).forEach(function (m) {
        const isE = m.sender === "e";
        const row = buildRow(json, m);
        (isE ? panelE.stream : panelI.stream).appendChild(row);
        (isE ? rowsE : rowsI).push(row);
      });
      panelE.count.textContent = rowsE.length + " 条";
      panelI.count.textContent = rowsI.length + " 条";

      const maxLen = Math.max(rowsE.length, rowsI.length);
      for (let k = 0; k < maxLen; k++) {
        if (rowsE[k]) timeline.push({ el: rowsE[k], panel: "e" });
        if (rowsI[k]) timeline.push({ el: rowsI[k], panel: "i" });
      }
    }

    /* 底部 */
    const footer = el("div", "card-footer");
    footer.appendChild(el("span", "foot-brand", "MBTI-CLASH"));
    const footLine = el("span", "foot-line", "佳蓝造物 · 每日一造");
    const chip = el("span", "foot-chip", "🔥 顶级拉扯");
    chip.style.background = "linear-gradient(135deg," + pick(left, "accent", "#FF6B81") + "," + pick(right, "accent", "#7C6CF0") + ")";
    footer.appendChild(footLine);
    footer.appendChild(chip);
    root.appendChild(footer);

    resetPlayback();
  }

  function buildChip(p){
    const chip = el("div", "persona-chip");
    const av = el("div", "mini-avatar", p.type);
    av.style.background = grad(p);
    const info = el("div");
    info.appendChild(el("div", "p-name", p.name));
    if (p.tagline) info.appendChild(el("div", "p-type", p.tagline));
    chip.appendChild(av); chip.appendChild(info);
    return chip;
  }

  function buildPanel(p){
    const panel = el("div", "persona-panel");
    const head = el("div", "panel-head");
    const av = el("div", "avatar-lg", p.type);
    av.style.background = grad(p);
    const info = el("div");
    info.appendChild(el("div", "ph-name", p.name));
    if (p.tagline) info.appendChild(el("div", "ph-tag", p.tagline));
    const count = el("span", "ph-count", "0 条");
    head.appendChild(av); head.appendChild(info); head.appendChild(count);
    const stream = el("div", "chat-stream");
    panel.appendChild(head); panel.appendChild(stream);
    return { panel: panel, stream: stream, count: count };
  }

  function buildRow(json, msg){
    const isE = msg.sender === "e";
    const persona = isE ? json.vs.left : json.vs.right;
    const row = el("div", "chat-row");
    const av = el("div", "avatar", persona.type);
    av.style.background = grad(persona);
    const col = el("div", "msg-col");
    col.appendChild(el("span", "chat-label", persona.name + (msg.time ? " · " + msg.time : "")));
    const bubble = el("div", "bubble " + (isE ? "bubble-e" : "bubble-i"), msg.text);
    if (currentLayout === "chat" && !isE) {
      /* 对话流右侧：人格强调色渐变气泡 */
      bubble.style.background = grad(persona);
      bubble.style.color = "#fff";
      bubble.style.boxShadow = "0 6px 16px rgba(30,30,60,.18)";
    }
    col.appendChild(bubble);
    row.appendChild(av); row.appendChild(col);
    return row;
  }
  /* ---------- 播放 / 暂停 / 自动停止 ---------- */
  function play(){
    if (playing) return;
    if (!timeline.length) return;
    ensureAudioUnlocked();   // 真实 Click 手势栈内解锁音频（Safari 兼容）
    if (tIndex >= timeline.length || tIndex === 0) {
      /* 未开始或已播完：从头播放 */
      tIndex = 0;
      timeline.forEach(function (x) { x.el.classList.remove("visible"); });
    }
    /* 暂停中：从当前位置继续 */
    playing = true;
    syncPlayUI();
    scheduleNext();
  }

  function scheduleNext(){
    if (tIndex >= timeline.length) {
      /* 播放完毕 → 自动停止 */
      playing = false;
      syncPlayUI();
      toast("播放完毕 · 已自动停止");
      return;
    }
    const step = timeline[tIndex];
    step.el.classList.add("visible");
    scrollToStep(step.el);   // 长对话自动滚动到当前气泡
    popSound();
    tIndex++;
    updateProgress();
    timerId = setTimeout(scheduleNext, STEP_DELAY);
  }

  /* 将当前气泡滚动到其最近滚动容器可见区域（不滚动页面本身） */
  function scrollToStep(stepEl){
    const scroller = stepEl.closest(".chat-stream") || root;
    if (!scroller) return;
    const elRect = stepEl.getBoundingClientRect();
    const scRect = scroller.getBoundingClientRect();
    const target = scroller.scrollTop + (elRect.top - scRect.top) - (scroller.clientHeight - elRect.height) * 0.55;
    try {
      scroller.scrollTo({ top: Math.max(0, target), behavior: "smooth" });
    } catch (e) {
      scroller.scrollTop = Math.max(0, target);
    }
  }

  function pausePlayback(){
    if (!playing) return;
    playing = false;
    clearTimeout(timerId);
    timerId = null;
    syncPlayUI();
  }

  function resetPlayback(){
    playing = false;
    clearTimeout(timerId);
    timerId = null;
    tIndex = 0;
    timeline.forEach(function (x) { x.el.classList.add("visible"); });
    updateProgress();
    syncPlayUI();
  }

  function syncPlayUI(){
    const btn = $("playBtn");
    if (playing) btn.textContent = "⏸ 暂停";
    else if (tIndex > 0 && tIndex < timeline.length) btn.textContent = "▶ 继续";
    else btn.textContent = "▶ 播放动画";
  }

  function updateProgress(){
    const total = timeline.length;
    const pct = total ? Math.round(tIndex / total * 100) : 0;
    $("progressFill").style.width = pct + "%";
    $("progressLabel").textContent = tIndex + " / " + total;
  }

  /* 按进度恢复显示状态（导出后还原用） */
  function restoreProgress(idx){
    for (let i = 0; i < timeline.length; i++) {
      timeline[i].el.classList.toggle("visible", i < idx);
    }
  }

  /* ---------- 预设胶囊（JSON 动态生成） ---------- */
  function buildPresetTabs(){
    const box = $("presets");
    box.innerHTML = "";
    PRESET_DATA.forEach(function (s, i) {
      const sc = s.scene || {};
      const label = sc.label ||
        (sc.emoji ? sc.emoji + " " : "") + String(sc.title || ("预设 " + (i + 1))).split("\n")[0];
      const tag = el("span", "preset-tag", label);
      tag.addEventListener("click", function () { loadPreset(i); });
      box.appendChild(tag);
    });
  }

  function loadPreset(index){
    if (!PRESET_DATA[index]) { toast("预设不存在"); return; }
    renderScene(PRESET_DATA[index]);
    toast("已加载预设场景");
  }

  /* ---------- LLM 对接全局接口（onez-server 注入 JSON 即渲染） ---------- */
  window.updateCanvasFromLLM = function (jsonData) {
    renderScene(jsonData);
  };
  window.loadPreset = function (index) {
    loadPreset(index);
  };

  /* ---------- AI 生成 ---------- */
  async function generateHandler(){
    const btn = $("generateBtn");
    const prompt = $("aiPrompt").value.trim();
    if (!prompt) return toast("请输入提示词");
    btn.classList.add("loading");
    btn.textContent = "AI 编剧中...";

    let r=await onez_market.post({action:'json',prompt});
    btn.classList.remove("loading");
    btn.textContent = "✨ AI 智能生成";
    if(r.error){
      toast("AI 生成成功！");
    }else if(!r.json){
      toast("未知错误");
    }else{
      toast(r.message||"AI 生成成功！");
      window.updateCanvasFromLLM(r.json);   // 返回一段对话流剧本
    }
  }

  /* ---------- 手机壳自适应缩放（resize 防抖） ---------- */
  function fitStage(){
    const avail = window.innerHeight - 80;
    const scale = Math.min(1, Math.max(0.5, avail / 769));
    stage.style.transform = scale === 1 ? "" : "scale(" + scale + ")";
  }

  /* ---------- 导出 PNG（3x 高清；长对话动态撑开高度完整截图） ---------- */
  function exportPNG(){
    if (!window.html2canvas) {
      toast("截图库未就绪：请确认 assets/html2canvas.min.js 已随页面一起部署");
      return;
    }
    const wasPlaying = playing;
    const savedIdx = tIndex;
    if (playing) pausePlayback();

    toast("正在渲染高清图片...");
    /* ① 全部气泡完整显示 */
    restoreProgress(timeline.length);
    root.classList.add("exporting");
    captureEl.classList.add("exporting");
    /* ② 解除固定高度与滚动限制，按真实内容高度撑开（长图不截断） */
    const origHeight = captureEl.style.height;
    const origOverflow = captureEl.style.overflow;
    captureEl.style.height = captureEl.scrollHeight + "px";
    captureEl.style.overflow = "visible";
    root.scrollTop = 0;
    const streams = root.querySelectorAll(".chat-stream");
    for (let i = 0; i < streams.length; i++) streams[i].scrollTop = 0;
    stage.style.transform = "";

    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        html2canvas(captureEl, {
          scale: 3,
          useCORS: true,
          backgroundColor: null,
          logging: false
        }).then(function (canvas) {
          const a = document.createElement("a");
          a.download = "mbti-clash-" + (currentData && currentData.id ? currentData.id : Date.now()) + ".png";
          a.href = canvas.toDataURL("image/png");
          a.click();
          toast("导出成功 · " + canvas.width + "×" + canvas.height);
        }).catch(function (err) {
          console.error(err);
          toast("导出失败，请重试");
        }).finally(function () {
          /* ③ 导出后恢复原状 */
          captureEl.style.height = origHeight;
          captureEl.style.overflow = origOverflow;
          root.classList.remove("exporting");
          captureEl.classList.remove("exporting");
          restoreProgress(savedIdx);
          fitStage();
          if (wasPlaying) play();
        });
      });
    });
  }

  /* ---------- 事件绑定与初始化 ---------- */
  function init(){
    root = $("sceneRoot");
    captureEl = $("captureArea");
    stage = $("stage");

    $("playBtn").addEventListener("click", function () {
      if (playing) pausePlayback();
      else play();
    });
    $("replayBtn").addEventListener("click", resetPlayback);
    $("soundBtn").addEventListener("click", function () {
      soundOn = !soundOn;
      this.textContent = soundOn ? "🔊 音效" : "🔇 静音";
      if (soundOn) ensureAudioUnlocked();
    });
    $("exportBtn").addEventListener("click", exportPNG);
    $("generateBtn").addEventListener("click", generateHandler);

    buildPresetTabs();
    renderScene(PRESET_DATA[0]);
    fitStage();
    /* resize 防抖：避免窗口拖拽时频繁重排（Layout Thrashing） */
    window.addEventListener("resize", function () {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(fitStage, 100);
    });
  }

  document.getElementById("settingsBtn").addEventListener("click", function () {
    window.open("panel.php", "mbtiSettings", "width=640,height=760,menubar=no,toolbar=no,location=no,status=no,scrollbars=yes");
  });
  document.addEventListener("DOMContentLoaded", init);
})();
</script>
<?=$G['footer']?>
<script src="/js/onez.market.js?t=1786985616"></script></body>
</html>