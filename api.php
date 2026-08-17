<?php
if($action=='json'){
  $r=openai_tool([
    'items'=>[
      'json'=>'完整json代码',
    ],
    'name'=>'submit',
    'desc'=>'提交生成的json代码',
    'prompt_system'=>onez()->read(dirname(__FILE__).'/prompt.md'),
    'prompt'=>$params['prompt'],
    'stream'=>false,
    'max_tokens'=>10000,
  ]);
  $json=$r['json'];
  !$json && bad('生成失败');
  if(is_string($json)){
    $json=json_decode($json,1);
  }
  $A['json']=$json;
  $A['message']='生成成功！';
  if(isset($A['balance'])){
    $A['message'].="当前余额:{$A['balance']} 积分";
  }
}
onez()->output($A);