<?php
include(dirname(__FILE__).'/data/onez.php');
global $G;
$item = (isset($item) && is_array($item)) ? $item : [];
$G    = (isset($G) && is_array($G)) ? $G : [];
ob_clean();
$params=json_decode(file_get_contents('php://input'),1);
if($params){
  $action=$params['action'];
  if($action=='settings_read'){
    $settings=json_decode(onez()->read(dirname(__FILE__).'/data/settings.json'),1)?:new stdClass();
    $A['settings']=$settings;
  }elseif($action=='settings_save'){
    // print_r($params);exit();
    onez()->write(dirname(__FILE__).'/data/settings.json',json_encode($params['settings'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    $A['message']='保存成功';
  }
  onez()->output($A);
}
$DEFAULTS = [
  'name'    => 'MBTI-Clash',
  'slogan'  => '社交对撞图文生成器',
  'icon'    => 'fa-comments',
  'token'   => 'mbti-clash',
  'id'      => 2130,
  'github'  => 'https://github.com/onezcn/mbti-clash-2130',
  'site'    => 'https://factory.onez.cn',
  'market'  => 'https://factory.onez.cn/page.php?path=%2Fmarket%2Ftool&id=2130',
  'wechat'  => '佳蓝AI',
  'email'   => 'www@onez.cn',
  'copyright' => '© 2026 沧州佳蓝网络科技有限公司',
  'desc'    => '一句话生成 E/I 人格对撞剧本，渲染 iMessage 气泡风对比图文，一键导出高清竖版图，直接发小红书。',
  'feats'   => ['一句话生成 E/I 对撞剧本','双布局：split 双栏 / chat 对话流','3x 高清长图导出','零生图成本 · 离线可用'],
  'license' => 'MIT',
];
$name      = !empty($item['title'])   ? $item['title']   : $DEFAULTS['name'];
$slogan    = !empty($item['slogan'])  ? $item['slogan']  : $DEFAULTS['slogan'];
$icon      = !empty($item['icon'])    ? $item['icon']    : $DEFAULTS['icon'];
$token     = !empty($item['token'])   ? $item['token']   : $DEFAULTS['token'];
$id        = !empty($item['id'])      ? $item['id']      : $DEFAULTS['id'];
$github    = !empty($item['github'])  ? $item['github']  : $DEFAULTS['github'];
$site      = !empty($item['site'])    ? $item['site']    : $DEFAULTS['site'];
$market    = !empty($item['market'])  ? $item['market']  : $DEFAULTS['market'];
$wechat    = $DEFAULTS['wechat'];
$email     = $DEFAULTS['email'];
$copyright = $DEFAULTS['copyright'];
$desc      = !empty($item['summary']) ? $item['summary'] : $DEFAULTS['desc'];
$feats     = $DEFAULTS['feats'];
$license   = $DEFAULTS['license'];
$tabs = [
  ['key'=>'settings','label'=>'设置','icon'=>'fa-sliders'],
  ['key'=>'about',   'label'=>'关于','icon'=>'fa-circle-info'],
];
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($name) ?> · 设置</title>
<?php include(dirname(__FILE__).'/_ui.php'); ?>
<style>
  body{ background:#F3F4F6 !important; }
</style>
</head>
<body>
<div class="w-full max-w-3xl mx-auto px-6 py-8">
  <nav class="flex flex-wrap gap-2 mb-6" id="jlNav">
    <?php foreach($tabs as $i=>$nv){ $on=$i===0?'bg-gc-cyan text-white':'bg-white text-gc-dark hover:bg-gc-yellow/40'; ?>
    <button data-tab="<?= htmlspecialchars($nv['key']) ?>" class="jl-tab font-bold text-sm border-3 border-gc-dark rounded-2xl py-2 px-4 shadow-gc-btn transition-all active:translate-y-1 active:translate-x-1 active:shadow-none <?= $on ?>"><i class="fa-solid <?= htmlspecialchars($nv['icon']) ?> mr-1"></i><?= htmlspecialchars($nv['label']) ?></button>
    <?php } ?>
  </nav>

  <!-- ===== 设置 ===== -->
  <section id="jl-settings" class="jl-pane">
    <div class="bg-white border-3 border-gc-dark rounded-3xl shadow-gc-card p-6">
      <div class="flex items-center justify-between flex-wrap gap-2 mb-4">
        <h3 class="text-xl font-black text-gc-dark"><i class="fa-solid fa-sliders mr-2 text-gc-yellow"></i>设置</h3>
        <span class="text-xs font-bold text-gc-dark/50">三项缺一不可，保存后立即生效</span>
      </div>
      <div class="space-y-3 mb-5">
        <div>
          <label class="block text-xs font-bold text-gc-dark/70 mb-1" for="setApiurl">接口地址</label>
          <input id="setApiurl" type="text" placeholder="https://api.deepseek.com/v1/chat/completions" class="w-full bg-white border-3 border-gc-dark rounded-2xl py-2.5 px-4 text-sm text-gc-dark font-medium placeholder:text-gc-dark/40 focus:outline-none" />
          <p class="text-xs font-bold text-gc-dark/40 mt-1">OpenAI 兼容 chat/completions 完整地址</p>
        </div>
        <div>
          <label class="block text-xs font-bold text-gc-dark/70 mb-1" for="setApikey">API Key</label>
          <input id="setApikey" type="password" placeholder="sk-…" class="w-full bg-white border-3 border-gc-dark rounded-2xl py-2.5 px-4 text-sm text-gc-dark font-medium placeholder:text-gc-dark/40 focus:outline-none" />
          <p class="text-xs font-bold text-gc-dark/40 mt-1">保存在 lib/data/settings.json</p>
        </div>
        <div>
          <label class="block text-xs font-bold text-gc-dark/70 mb-1" for="setModel">模型</label>
          <input id="setModel" type="text" placeholder="deepseek-v4-flash" class="w-full bg-white border-3 border-gc-dark rounded-2xl py-2.5 px-4 text-sm text-gc-dark font-medium placeholder:text-gc-dark/40 focus:outline-none" />
          <p class="text-xs font-bold text-gc-dark/40 mt-1">模型名</p>
        </div>
      </div>
      <button id="jlSaveSet" class="bg-gc-cyan text-white font-bold text-sm border-3 border-gc-dark rounded-2xl py-2 px-6 shadow-gc-btn transition-all active:translate-y-1 active:translate-x-1 active:shadow-none">保存设置</button>
    </div>
  </section>

  <!-- ===== 关于 ===== -->
  <section id="jl-about" class="jl-pane hidden">
    <div class="bg-white border-3 border-gc-dark rounded-3xl shadow-gc-card p-6 mb-6">
      <h3 class="text-xl font-black text-gc-dark mb-3"><i class="fa-solid fa-circle-info mr-2 text-gc-cyan"></i>关于本工具</h3>
      <p class="text-sm font-medium text-gc-dark/70 leading-relaxed mb-4"><?= htmlspecialchars($desc) ?></p>
      <div class="flex flex-wrap gap-2 mb-5">
        <?php foreach($feats as $ft){ ?><span class="bg-gc-yellow text-gc-dark font-bold text-xs border-2 border-gc-dark rounded-full px-3 py-1"><?= htmlspecialchars($ft) ?></span><?php } ?>
      </div>
      <div class="flex flex-wrap gap-2">
        <a href="<?= htmlspecialchars($market) ?>" target="_blank" class="bg-gc-cyan text-white font-bold text-sm border-3 border-gc-dark rounded-2xl py-2.5 px-4 shadow-gc-btn transition-all active:translate-y-1 active:translate-x-1 active:shadow-none"><i class="fa-solid fa-arrow-up-right-from-square mr-2"></i>返回佳蓝造物 · 在线使用</a>
        <a href="<?= htmlspecialchars($github) ?>" target="_blank" class="bg-white text-gc-dark font-bold text-sm border-3 border-gc-dark rounded-2xl py-2.5 px-4 shadow-gc-btn transition-all active:translate-y-1 active:translate-x-1 active:shadow-none hover:bg-gc-yellow/40"><i class="fa-brands fa-github mr-2 text-gc-cyan"></i>开源仓库</a>
      </div>
    </div>

    <div class="bg-white border-3 border-gc-dark rounded-3xl shadow-gc-card p-6 mb-6">
      <h3 class="text-xl font-black text-gc-dark mb-3"><i class="fa-solid fa-address-book mr-2 text-gc-yellow"></i>官网与联系方式</h3>
      <div class="space-y-2">
        <div class="flex items-center gap-3 bg-gc-bg border-3 border-gc-dark rounded-2xl px-4 py-2.5">
          <i class="fa-solid fa-globe text-gc-cyan w-5 text-center"></i>
          <span class="text-sm font-bold text-gc-dark/60 w-24 shrink-0">官网</span>
          <a href="<?= htmlspecialchars($site) ?>" target="_blank" class="text-sm font-bold text-gc-cyan hover:underline"><?= htmlspecialchars($site) ?></a>
        </div>
        <div class="flex items-center gap-3 bg-gc-bg border-3 border-gc-dark rounded-2xl px-4 py-2.5">
          <i class="fa-brands fa-weixin text-gc-cyan w-5 text-center"></i>
          <span class="text-sm font-bold text-gc-dark/60 w-24 shrink-0">微信公众号</span>
          <span class="text-sm font-bold text-gc-dark"><?= htmlspecialchars($wechat) ?></span>
        </div>
        <div class="flex items-center gap-3 bg-gc-bg border-3 border-gc-dark rounded-2xl px-4 py-2.5">
          <i class="fa-solid fa-envelope text-gc-cyan w-5 text-center"></i>
          <span class="text-sm font-bold text-gc-dark/60 w-24 shrink-0">邮箱</span>
          <a href="mailto:<?= htmlspecialchars($email) ?>" class="text-sm font-bold text-gc-cyan hover:underline"><?= htmlspecialchars($email) ?></a>
        </div>
      </div>
    </div>

    <div class="bg-white border-3 border-gc-dark rounded-3xl shadow-gc-card p-6">
      <h3 class="text-xl font-black text-gc-dark mb-3"><i class="fa-solid fa-shield-cat mr-2 text-gc-pink"></i>版权与许可</h3>
      <p class="text-xs font-medium text-gc-dark/60 leading-relaxed">源码 <?= htmlspecialchars($license) ?>（见 LICENSE）；第三方依赖与分发注意义务见 THIRD_PARTY_NOTICES.md。<?= htmlspecialchars($copyright) ?></p>
    </div>
  </section>

</div>

<script src="/js/onez.market.js"></script>
<script>
(function(){
  "use strict";
  var $=function(id){ return document.getElementById(id); };
  function switchTab(t){
    document.querySelectorAll('.jl-tab').forEach(function(b){
      var on=b.getAttribute('data-tab')===t;
      b.className='jl-tab font-bold text-sm border-3 border-gc-dark rounded-2xl py-2 px-4 shadow-gc-btn transition-all active:translate-y-1 active:translate-x-1 active:shadow-none '+(on?'bg-gc-cyan text-white':'bg-white text-gc-dark hover:bg-gc-yellow/40');
    });
    document.querySelectorAll('.jl-pane').forEach(function(x){ x.classList.add('hidden'); });
    var p=$('jl-'+t); if(p){ p.classList.remove('hidden'); }
  }
  document.querySelectorAll('.jl-tab').forEach(function(b){
    b.addEventListener('click', function(){ switchTab(b.getAttribute('data-tab')); });
  });
  function msg(t,ok){ if(window.showToast){ showToast(t, ok?'success':'error'); } else { alert(t); } }
  async function fill(){
    const d=await onez_market.post({action:'settings_read'});
    if(d.error){
      msg(d.error);
    }else{
      $('setApiurl').value=d.settings.apiurl||'https://api.deepseek.com/v1/chat/completions';
      $('setApikey').value=d.settings.apikey||'';
      $('setModel').value=d.settings.model||'deepseek-v4-flash';
    }
  }
  $('jlSaveSet').addEventListener('click', async function(){
    var s={
      apiurl:$('setApiurl').value.trim(),
      apikey:$('setApikey').value.trim(),
      model:$('setModel').value.trim(),
    };
    // if(!s.apiurl || !s.apikey || !s.model){ msg('三项都要填',false); return; }
    const r=await onez_market.post({action:'settings_save',settings:s});
    if(r.error){
      msg(r.error);
    }else{
      msg(r.message||'已保存，立即生效');
    }
  });
  fill();
})();
</script>
</body>
</html>
