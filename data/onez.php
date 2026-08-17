<?php
error_reporting(E_ERROR | E_PARSE);
define('IN_ONEZ', TRUE);
if(!defined('ONEZ_ROOT')){
  define('ONEZ_ROOT', dirname(dirname(__FILE__)));
}
define('ONEZ_VERSION', '2.0.1');
define('ONEZ_NODE_PATH', '/plugins');
define('ONEZ_MYNODE_PATH', '/myplugins');
define('ONEZ_AUTO_FETCH', 1);
define('ONEZPHP_FETCH_NOTIP', 1);
ob_start();
if(version_compare(PHP_VERSION, '7.0.0') == -1) {
  if(function_exists(session_cache_limiter))session_cache_limiter('private, must-revalidate');
}
$G=[];
class onezphp{
  var $vars=array();
  var $parents=array();
  function __call($name,$arguments){
    if($name=='root'){
      return ONEZ_ROOT;
    }
    // 从“父类"中查找方法
    foreach ($this->parents as $p) {
      if(is_string($p)){
        if(!onez()->exists($p)){
          continue;
        }
        $p=onez($p);
      }
      if (is_callable(array($p, $name))) {
        return call_user_func_array(array($p, $name), $arguments);
      }
    }
  }
  function set($key,$value){
    $this->vars[$key]=$value;
    return $this;
  }
  function get($key,$def=false){
    $value=$this->vars[$key];
    if($def!==false && !isset($this->vars[$key])){
      return $def;
    }
    return $value;
  }
}

class onezphp_onezphp extends onezphp{
  function mypost($url,$fields='',$options=null){
    !$options && $options=array();
    if($fields){
      $opt = array(
        'http' => array(
          'method' => 'POST',
          'header' => 'content-type:application/x-www-form-urlencoded'.($options['headers']?(';'.implode(';',$options['headers'])):''),
          'content' => is_array($fields)?http_build_query($fields):$fields
        )
      );
      $context = stream_context_create($opt);
      $mydata = file_get_contents($url, false, $context);
    }else{
      if($options['headers']){
        $opt = array(
          'http' => array(
            'method' => 'GET',
            'header' => implode(';',$options['headers']),
          )
        );
        $context = stream_context_create($opt);
        $mydata = file_get_contents($url, false, $context);
      }else{
        $mydata=file_get_contents($url);
      }
    }
    return $mydata;
  }
  /**
  * 读取远程网址代码
  * 
  * @param string $url 请求的网址
  * @param mixed $fields 需要post的参数
  * @param array $options 附加选项
  * 
  * @return mixed 直接返回目标输出的内容
  */
  function post($url,$fields='',$options=null){
    global $G;
    if(!function_exists('curl_init')){
      return onez()->mypost($url,$fields,$options);
    }
    global $G;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    if(strpos($url,'https://')!==false){
      curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
      curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    }
    if($options['useragent']){
      curl_setopt($ch, CURLOPT_USERAGENT, $options['useragent']);
    }else{
      curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; zh-CN; rv:1.9.0.19) Gecko/2010031422 Firefox/3.0.19');
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, $options['timeout'] ? $options['timeout'] : 10);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    $options['headers'] && curl_setopt($ch, CURLOPT_HTTPHEADER, $options['headers']);
    if($options['showheader']){
      curl_setopt($ch,CURLOPT_HEADER,1);
    }else{
      curl_setopt($ch,CURLOPT_HEADER,0);
    }
    if($options['cookie']){
      if(file_exists($options['cookie'])){
        curl_setopt($ch, CURLOPT_COOKIEJAR, $options['cookie']);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $options['cookie']);
      }else{
        curl_setopt($ch, CURLOPT_COOKIE, $options['cookie']);
      }
    }
    curl_setopt($ch, CURLOPT_REFERER,$options['baseurl'] ? $options['baseurl'] : $url);
    if($fields){
      curl_setopt($ch, CURLOPT_POSTFIELDS,$fields);
      curl_setopt($ch, CURLOPT_POST,1);
    }
    $output = curl_exec($ch);
    $G['error_post']=curl_error($ch);
    if($options['showheader']){
      $pos=strpos($output,"\r\n\r\n");
      $this->set('post.header',substr($output,0,$pos));
      $output=substr($output,$pos+4);
    }
    curl_close($ch);
    return $output;
  }
  /**
  * 规范token
  * @param undefined $token
  * 
  * @return
  */
  function getToken($token){
    $token=preg_replace('/[^0-9a-zA-Z_\.]+/i','_',$token);
    return $token;
  }
  /**
  * 判断插件是否存在
  * @param undefined $token
  * 
  * @return
  */
  function exists($token,$canRewrite=1){
    $token=$this->getToken($token);
    
    $PATH=onez()->root().ONEZ_MYNODE_PATH;
    foreach($this->plugin_paths as $v){
      $classFile=$v[0].'/'.$token.'/'.$token.'.php';
      if(file_exists($classFile)){
        return $classFile;
      }
    }
    $classFile=$PATH.'/'.$token.'/'.$token.'.php';
    if(file_exists($classFile)){
      return $classFile;
    }
    
    $PATH=onez()->root().ONEZ_NODE_PATH;
    $classFile=$PATH.'/'.$token.'/'.$token.'.php';
    if($canRewrite && function_exists('_plugin_rewrite')){
      _plugin_rewrite($token,$classFile);
    }
    
    if(!file_exists($classFile)){
      return false;
    }
    return $classFile;
  }
  /**
  * 读取本地文件数据
  * 
  * @param string $filename 文件名
  * @param string $method 默认rb
  * 
  * @return mixed 文件数据
  */
  function read($filename,$method="rb"){
    if(!file_exists($filename)){
      return;
    }
    if($handle=@fopen($filename,$method)){
      flock($handle,LOCK_SH);
      $size=filesize($filename);
      if($size>0){
        $filedata=fread($handle,$size);
      }
      fclose($handle);
    }
    return $filedata;
  }
  /**
  * 写入本地文件
  * 
  * @param string $filename 文件名
  * @param mixed $data 文件内容
  * @param string $method 写入方式,a+为追加
  * @param boolean $iflock
  * 
  * @return
  */
  function write($filename,$data,$method="rb+",$iflock=1){
    $this->mkdirs(dirname($filename));
    touch($filename);
    $handle=fopen($filename,$method);
    if($iflock){
      flock($handle,LOCK_EX);
    }
    fwrite($handle,$data);
    if($method=="rb+") ftruncate($handle,strlen($data));
    fclose($handle);
  }
  /**
   * 创建多级目录
   * 
   * @param string $dir 要创建的完整路径
   * 
   * @return
   */
  function mkdirs($dir){
    if(!is_dir($dir)){
      $this->mkdirs(dirname($dir));
      mkdir($dir,0777);
    }
    return;
  }
  /**
  * 编码转换
  * 
  * @param string $from 当前编码
  * @param string $to 目标编码
  * @param string $string 字符串
  * 
  * @return string
  */
  function iconv($from,$to,$string){
    if(is_array($string)){
      foreach($string as $k=>$v){
        $string[$k]=$this->iconv($from,$to,$v);
      }
      return $string;
    }
    if(function_exists('mb_convert_encoding')){
      return mb_convert_encoding($string,$to,$from);
    }else{
      return iconv($from,$to,$string);
    }
  }
  
  /**
  * 加解密字符串
  * 
  * @param string $string 字符串
  * @param string $action ENCODE加密,DECODE解密
  * @param string $rndKey 密钥
  * 
  * @return mixed
  */
  function strcode($string,$action='ENCODE',$rndKey='onez'){
    global $G;
    $G['rndKey'] && $rndKey=$G['rndKey'];
    $action != 'ENCODE' && $string = base64_decode($string);
    $code = '';
    $key  = substr(md5($rndKey),8,18);
    $keylen = strlen($key); $strlen = strlen($string);
    for ($i=0;$i<$strlen;$i++) {
      $k		= $i % $keylen;
      $code  .= $string[$i] ^ $key[$k];
    }
    return ($action!='DECODE' ? base64_encode($code) : $code);
  }
  /**
  * 读写cookie信息
  * 
  * @param string $var 键
  * @param string $value 值(null时为读取，其他为写入)
  * @param int $life
  * @param boolean $prefix
  * 
  * @return
  */
  function cookie($var, $value=null,$life=0,$prefix=1) {
    global $G,$_COOKIE;
    $time=time();
    if(!isset($G['cookiepre'])){
      $G['cookiepre']='onez_cn_';
    }
    if($value==null){
      if(isset($_COOKIE[$G['cookiepre'].$var])){
        return $_COOKIE[$G['cookiepre'].$var];
      }else{
        return '';
      }
    }elseif($value=='del'||$value=='remove'){
      $value='';
      $life=-20;
    }
    $cookiedomain=$G['cookiedomain'];
    $cookiepath='/';
    setcookie(($prefix ? $G['cookiepre'] : '').$var, $value,
      $life ? $time + $life : 0, $cookiepath,
      $cookiedomain, $_SERVER['SERVER_PORT'] == 443 ? 1 : 0);
  }
  /**
  * 读取用户get或post的信息
  * 
  * @param string $keys 键
  * @param string $method 方法:G get,P post
  * @param boolean $cvtype 是否为数字
  * 
  * @return string
  */
  function gp($keys,$cvtype=1,$method=null){
    global $G;
    if($method=='G'){
      $value=$_GET[$keys];
    }elseif($method=='P'){
      $value=$_POST[$keys];
    }else{
      $value=$_REQUEST[$keys];
    }
    $G['gp_'.$keys]=$value;
    if (!empty($cvtype) || $cvtype==2) {
      $value = $this->charcv($value,$cvtype==2,true);
    }
    $value=='undefined' && $value='';
    return $value;
  }
  /**
  * 读取变量
  * 
  * @param mixed $mixed 字符串
  * @param boolean $isint 是否为数字
  * @param boolean $istrim 是否去除空格
  * 
  * @return
  */
  function charcv($mixed,$isint=false,$istrim=false) {
    if (is_array($mixed)) {
      foreach ($mixed as $key => $value) {
        $mixed[$key] = $this->charcv($value,$isint,$istrim);
      }
    } elseif ($isint) {
      $mixed = (int)$mixed;
    } elseif (!is_numeric($mixed) && ($istrim ? $mixed = trim($mixed) : $mixed) && $mixed) {
      $mixed = str_replace(array("\0","%00","\r"),'',$mixed);
      $mixed = preg_replace(
        array('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F]/','/&(?!(#[0-9]+|[a-z]+);)/is'),
        array('','&amp;'),
        $mixed
      );
      $mixed = str_replace(array("%3C",'<'),'&lt;',$mixed);
      $mixed = str_replace(array("%3E",'>'),'&gt;',$mixed);
      $mixed = str_replace('&amp;','&',$mixed);
      $mixed = str_replace(array('"',"'","\t",'  '),array('&quot;','&#39;','    ','&nbsp;&nbsp;'),$mixed);
    }
    return $mixed;
  }
  function stripslashes($string, $force = 0) {
    if(is_array($string)) {
      foreach($string as $key => $val) {
        $string[$key] = $this->stripslashes($val, $force);
      }
    } else {
      $string = stripslashes($string);
    }
    return $string;
  }
  /**
  * 截取utf-8格式的部分字符串
  * 
  * @param string $str
  * @param int $start
  * @param int $length
  * @param string $charset
  * @param boolean $suffix
  * 
  * @return string
  */
  function substr($str, $start, $length, $charset="utf-8", $suffix=true){
    if(function_exists("mb_substr")){
      if(mb_strlen($str, $charset) <= $length) return $str;
      $slice = mb_substr($str, $start, $length, $charset);
    }else{
      $re['utf-8']  = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
      $re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
      $re['gbk&']     = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
      $re['big5']     = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
      preg_match_all($re[$charset], $str, $match);
      if(count($match[0]) <= $length) return $str;
      $slice = join("",array_slice($match[0], $start, $length));
    }
    if($suffix) return $slice."...";
    return $slice;
  }
  /**
  * 获取utf-8字符串的长度
  * 
  * @param string $string
  * 
  * @return string
  */
  function strlen($string = null) {
    preg_match_all("/[0-9]{1}/",$string,$arrNum);  
    preg_match_all("/[a-zA-Z]{1}/",$string,$arrAl);  
    preg_match_all("/./us",$string,$arrCh); 
    return count($arrNum[0]+$arrAl[0]+$arrCh[0]);
  }
  /**
  * 获取当前用户的IP地址
  * 
  * @return
  */
  function ip(){
    global $G;
    if($G['onlineip']){
      return $G['onlineip'];
    }
    if(getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
      $onlineip = getenv('HTTP_CLIENT_IP');
    } elseif(getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
      $onlineip = getenv('HTTP_X_FORWARDED_FOR');
    } elseif(getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
      $onlineip = getenv('REMOTE_ADDR');
    } elseif(isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
      $onlineip = $_SERVER['REMOTE_ADDR'];
    }
    $onlineip = preg_replace("/^([\d\.]+).*/", "\\1", $onlineip);
    $G['onlineip']=$onlineip;
    return $onlineip;
  }
  /**
  * 自动获取当前程序根目录网址
  * 
  * @return
  */
  function homepage(){
    global $G;
    #分析当前网址
    if(!$G['homepage']){
      if(!$_SERVER['REQUEST_SCHEME']){
        $_SERVER['REQUEST_SCHEME']='http';
      }
      if($_SERVER['HTTPS']=='on'){
        $_SERVER['REQUEST_SCHEME']='https';
      }
      $homepage=$_SERVER['REQUEST_SCHEME'].'://';
      $homepage.=$_SERVER['HTTP_HOST'];
      if(strpos($homepage,':')===false){
        $_SERVER['SERVER_PORT']!='80' && $_SERVER['SERVER_PORT']!='443' && $homepage.=':'.$_SERVER['SERVER_PORT'];
      }
      $key=substr(onez()->root(),strlen($_SERVER['DOCUMENT_ROOT']));
      $key=str_replace('\\','/',$key);
      $homepage.=$key;
      $G['homepage']=$homepage;
    }
    return $G['homepage'];
  }
  /**
  * 自动获取当前网址
  * 
  * @return
  */
  function cururl($add=false,$del=false){
    $o=explode('/',onez()->homepage());
    list($filename,$args)=explode('?',$_SERVER['REQUEST_URI']);
    $get=array();
    parse_str($args,$get);
    if($add){
      foreach($add as $k=>$v){
        $get[$k]=$v;
      }
    }
    if($del){
      foreach($del as $k){
        unset($get[$k]);
      }
    }
    $url=$filename;
    if($get){
      $url.='?'.http_build_query($get);
    }
    return $url;
  }
  function output($A){
    ob_clean();
    echo json_encode($A,JSON_UNESCAPED_UNICODE);
    exit();
  }
  function ok($text,$url){
    $A=array(
      'status'=>'success',
      'message'=>$text?$text:'操作成功',
      'goto'=>$url,
    );
    $this->output($A);
  }
  function error($text){
    $A=array(
      'error'=>$text,
    );
    $this->output($A);
  }
}
function onez($token='onezphp',$id=0){
  global $G;
  if($token=='onezphp'){
    if(!$G['onezphp']){
      $G['onezphp']=new onezphp_onezphp;
      $G['onezphp']->token='onezphp';
    }
    return $G['onezphp'];
  }
  $fName='_select_'.$token;
  if(function_exists($fName)){
    $r=$fName($token);
    if($r){
      return $r;
    }
  }
  return onez()->load($token,$id);
}
function onez_lib($file,$method='echo'){
  global $G;
  $ext=pathinfo($file,PATHINFO_EXTENSION);
  if($ext=='js'){
    $html.='<script src="/'.$file.'?t='.filemtime(dirname(__FILE__).'/'.$file).'"></script>';
  }
  if($method=='echo'){
    echo $html;
  }else{
    return $html;
  }
}

function openai($messages,$option=[]){
  global $G;
  $settings=json_decode(onez()->read(dirname(__FILE__).'/settings.json'),1)?:[];
  $models=[];
  $models['deepseek']=[
    'apiurl'=>$settings['apiurl'],
    'models'=>[$settings['model']],
    'apiKey'=>$settings['apikey'],
  ];
  !$settings['apiurl'] && bad('请正确设置大模型参数');
  $mToken='deepseek';
  foreach($models as $key=>$model){
    if(in_array($option['model'],$model['models'])){
      $mToken=$key;
      break;
    }
  }
  // 准备API请求数据
  $apiRequestData = [
    'model' => $option['mymodel']['name']?:$option['model']?:'deepseek-v4-flash',
    // 'model' => $option['model']?:'gpt-5',
    'messages' => $messages,
    'stream' => $option['stream']??true,
    'temperature' => $option['temperature']?:0,
    'max_tokens' => $option['max_tokens']?:1024, // 增加最大token数
    // 'top_p' => $option['top_p']?:0.9,
  ];
  if($mToken=='deepseek' && $G['userid']){
    $apiRequestData['extra_body']['user_id']=(string)$G['userid'];
  }
  if(!isset($option['max_tokens']) && !empty($option['tools'])){
    $apiRequestData['max_tokens']=8192;
  }
  if($option['think']){
    $apiRequestData['thinking']=['type'=>'enabled'];
    $apiRequestData['reasoning_effort']=$option['reasoning_effort']?:'high';//high/max
  }else{
    $apiRequestData['thinking']=['type'=>'disabled'];
  }
  
  if($option['tools']){
    $apiRequestData['tools']=$option['tools'];
  }
  if($option['choose_tool']){
    $apiRequestData['choose_tool']=$option['choose_tool'];
  }

  if($option['merge']['model']){
    $models[$mToken]=array_merge($models[$mToken],$option['merge']['model']);
  }
  if($option['merge']['request']){
    $apiRequestData=array_merge($apiRequestData,$option['merge']['request']);
  }
  // 设置请求头
  $headers = [
    'Content-Type: application/json',
    'Authorization: Bearer '.($option['mymodel']?apikey($option['mymodel']['key']):$models[$mToken]['apiKey']),
    'Accept: text/event-stream'
  ];
  // print_r($headers);exit();
  if(isset($option['stream']) && !$option['stream']){
    $apiRequestData['stream']=false;
    $headers = [
      'Content-Type: application/json',
      'Authorization: Bearer '.($option['mymodel']?apikey($option['mymodel']['key']):$models[$mToken]['apiKey']),
    ];
    
    $r=onez()->post($models[$mToken]['apiurl'],json_encode($apiRequestData),['headers'=>$headers]);
    $res=json_decode($r,1)?:['error'=>$r];

    return [
      'content'=>$res['choices'][0]['message']['content'],
      'tools'=>$res['choices'][0]['message']['tool_calls'],
      'usage'=>$res['usage'],
      'reasoning_content'=>$res['choices'][0]['message']['reasoning_content'],
      'error'=>$res['error'],
      'response'=>$response,
    ];
  }
  // print_r($models[$mToken]['apiurl']);exit();
  // print_r($apiRequestData);exit();

  // 初始化cURL
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $option['mymodel']['apiurl']?:$models[$mToken]['apiurl']);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($apiRequestData));
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

  // 优化参数
  curl_setopt($ch, CURLOPT_TIMEOUT, 3600); // 5分钟超时
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30); // 30秒连接超时
  curl_setopt($ch, CURLOPT_TCP_KEEPALIVE, 1);
  curl_setopt($ch, CURLOPT_TCP_KEEPIDLE, 600);
  curl_setopt($ch, CURLOPT_TCP_KEEPINTVL, 300);

  $result=[];
  curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) use(&$result,$option){
    static $buffer = '';
    // print_r($data);
    $buffer .= $data;
    if(strpos($buffer,'{"error"')!==false){
      $result['error']=json_decode($buffer,1)['error'];
      if(isset($result['error']['message'])){
        if($option['onChunk']){
          $option['onChunk']($result['error']['message'],'error');
        }else{
          reply('API请求错误: ' . $result['error']['message'],'error');
        }
      }
      return strlen($data);
    }

    // 处理完整的SSE行
    while (($pos = strpos($buffer, "\n\n")) !== false) {
        $chunk = substr($buffer, 0, $pos);
        $buffer = substr($buffer, $pos + 2);

        if (empty(trim($chunk))) {
            continue;
        }

        // 解析SSE数据
        $lines = explode("\n", trim($chunk));
        $eventData = '';

        foreach ($lines as $line) {
            if (strpos($line, 'data: ') === 0) {
                $eventData = substr($line, 6);
                break;
            }
        }

        if (empty($eventData)) {
            continue;
        }

        // 解析JSON数据
        $jsonData = json_decode($eventData, true);
        if ($jsonData && isset($jsonData['usage'])) {
          $result['usage']=$jsonData['usage'];
        }
        if ($jsonData && isset($jsonData['choices'][0]['delta'])) {
            $delta = $jsonData['choices'][0]['delta'];
            // $result['delta']=$delta;

            // 处理推理内容
            if (isset($delta['reasoning_content'])) {
                $reasoningContent = $delta['reasoning_content'];
                $result['reasoning_content'] .= $reasoningContent;
                if($option['onChunk']){
                  $option['onChunk']($reasoningContent,'reasoning');
                }else{
                  reply($reasoningContent,'reasoning');
                }
            }

            // 处理文本内容
            if (isset($delta['content']) ) {
                $content = $delta['content'];
                $result['content'] .= $content;
                if($option['onChunk']){
                  $option['onChunk']($content,'content');
                }else{
                  reply($content,'content');
                }
            }

            // 处理工具调用
            if (isset($delta['tool_calls'])) {
                foreach ($delta['tool_calls'] as $toolCall) {
                    if (isset($toolCall['function'])) {
                        $function = $toolCall['function'];
                        // print_r($function);


                        // 工具名称
                        if (isset($function['name'])) {
                          $result['tools'][]=$toolCall;
                        }

                        // 工具参数
                        if (isset($function['arguments'])) {
                            $args = $function['arguments'];
                            $result['tools'][count($result['tools'])-1]['function']['arguments'].=$args;
                            if($option['onChunk']){
                              $option['onChunk']($args,'tool');
                            }else{
                              reply($args,'tool');
                            }
                        }
                    }
                }
            }
        }

        ob_flush();
        flush();
    }

    return strlen($data);
  });
  // 执行请求
  $response = curl_exec($ch);
  
  // 错误处理
  if (curl_errno($ch)) {
      $error = curl_error($ch);
      if($option['onChunk']){
        $option['onChunk']('API请求错误: ' . $error,'error');
      }else{
        reply('API请求错误: ' . $error,'error');
      }
      if($option['onChunk']){
        $option['onChunk']("data: " . json_encode(['error' => $error]) . "\n\n",'error');
      }else{
        reply("data: " . json_encode(['error' => $error]) . "\n\n");
      }
      $result['error']=$error;
  }
  $result['response']=$response;
  curl_close($ch);
  return $result;
}
function openai_tool($option=[]){
  $tools=[];
  $_tool=[
    'name'=>$option['tool_name']?:$option['name']?:'submit',
    'description'=>$option['summary']?:$option['desc']?:'提交处理结果',
    'parameters'=>[
      'type' => 'object',
    ],
  ];
  $_tool['name']=str_replace(['-',' '],'_',trim($_tool['name']));
  foreach($option['items']?:[] as $k=>$req){
    if(is_string($req)){
      $req=[
        'type'=>'string',
        'description'=>$req,
      ];
    }
    !$req['type'] && $req['type']='string';
    $_tool['parameters']['properties'][$k]=$req;
    $_tool['parameters']['required'][]=$k;
  }
  $option['prompt_system'].="\n# 绝对系统规则及流程：必须调用`{$_tool['name']}`工具提交，不要直接回复。\n";
  $messages=[
    ['role'=>'system','content'=>trim($option['prompt_system'])],
    ['role'=>'user','content'=>$option['prompt']],
  ];
  // print_r($messages);exit();
  $r=openai($messages,[
    'model'=>$option['model']?:'deepseek-v4-pro',
    'stream'=>$option['stream']??false,
    // 'max_tokens'=>$option['max_tokens']?:512,
    'tools'=>[
      [
        'type'=>'function',
        'function'=>$_tool,
      ],
    ],
    'choose_tool'=>true,
    'think'=>$option['think']??false,
  ]);

  $content=$r['choices'][0]['message']['content'];
  $tool_call=$r['choices'][0]['message']['tool_calls'][0]['function'];
  if(!$tool_call){
    $tool_call=$r['tools'][0]['function'];
  }

  $name=$tool_call['name'];
  $params=$tool_call['arguments'];
  if(is_string($params)){
    $params=json_decode($params,1);
  }
  if(!$params){
    $params=extractJsonFromLlm($r['content']);
  }
  $params['content']=$r['content'];
  $params['raw']=$r;
  return $params;
}

/**
 * 从 LLM 生成的回复中精准提取并解析 JSON 数据
 *
 * @param string $text  LLM 返回的原始文本
 * @param bool   $assoc 是否将对象解析为关联数组（默认 true）
 * @return mixed        解析成功返回数组/对象，失败返回 null
 */
function extractJsonFromLlm($text, $assoc = true) {
  if (empty($text)) {
      return null;
  }

  // 1. 优先尝试匹配 Markdown 代码块 (例如 ```json ... ``` 或 ``` ... ```)
  // 使用 /s 修饰符让 . 能够匹配包含换行符在内的所有字符
  if (preg_match('/```(?:json|javascript|js)?\s*(.*?)\s*```/is', $text, $matches)) {
      $jsonStr = trim($matches[1]);
      $decoded = json_decode($jsonStr, $assoc);
      if (json_last_error() === JSON_ERROR_NONE) {
          return $decoded;
      }
  }

  // 2. 如果没有代码块，或者代码块内包含非纯 JSON（比如里面又有注释），尝试基于括号的截取
  // 寻找第一个 { 或 [，以及最后一个 } 或 ]
  $firstCurly  = strpos($text, '{');
  $lastCurly   = strrpos($text, '}');
  $firstSquare = strpos($text, '[');
  $lastSquare  = strrpos($text, ']');

  $candidates =[];

  // 提取可能是 JSON 对象的片段
  if ($firstCurly !== false && $lastCurly !== false && $lastCurly > $firstCurly) {
      $candidates[] = substr($text, $firstCurly, $lastCurly - $firstCurly + 1);
  }

  // 提取可能是 JSON 数组的片段
  if ($firstSquare !== false && $lastSquare !== false && $lastSquare > $firstSquare) {
      $candidates[] = substr($text, $firstSquare, $lastSquare - $firstSquare + 1);
  }

  // 按字符串长度降序排序，优先尝试更长的片段（以防有并列存在的括号）
  usort($candidates, function($a, $b) {
      return strlen($b) - strlen($a);
  });

  foreach ($candidates as $candidate) {
      $candidate = trim($candidate);
      
      // 尝试直接解析
      $decoded = json_decode($candidate, $assoc);
      if (json_last_error() === JSON_ERROR_NONE) {
          return $decoded;
      }

      // 3. 容错处理 A：LLM 有时会生成带有尾随逗号的非法 JSON，如 {"a": 1, }
      // 使用正则表达式移除紧跟在 } 或 ] 前面的逗号
      $cleaned = preg_replace('/,\s*([\}\]])/', '$1', $candidate);
      $decodedClean = json_decode($cleaned, $assoc);
      if (json_last_error() === JSON_ERROR_NONE) {
          return $decodedClean;
      }
      
      // 4. 容错处理 B：清理可能导致解析失败的不可见控制字符
      // 某些模型吐字时可能夹带非法的 ASCII 控制字符
      $cleanedControl = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $cleaned);
      $decodedControl = json_decode($cleanedControl, $assoc);
      if (json_last_error() === JSON_ERROR_NONE) {
          return $decodedControl;
      }
  }

  // 所有尝试均失败
  return null;
}
function bad($text,$detail=false){
  if(is_array($text)){
    $text=json_save($text);
  }
  if(is_array($detail)){
    $detail=json_save($detail);
  }
  if($detail){
    $text.="\n详情: $detail";
  }
  throw new Exception($text);
}
#强制编码
header('Content-Type:text/html;charset=utf-8');
@ini_set('date.timezone','Asia/Shanghai');
