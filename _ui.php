
<!-- Tailwind CSS CDN -->
<script src="/js/tailwindcss.js"></script>
<!-- FontAwesome (用于图标) -->
<link rel="stylesheet" href="/static/font-awesome/6.7.2/css/all.min.css">
<script src="/js/jquery-3.6.0.min.js" type="text/javascript"></script>
<script>
    const onez={};
    onez.loadJS=function(url,isModule=false){
        return new Promise((resolve,reject)=>{
        let script = document.createElement('script');
        script.type = isModule ? 'module' : 'text/javascript';
        script.src = url;
        script.onload = resolve;
        script.onerror = (e)=>{
            delete jsCaches[cacheKey];
            reject(e);
        };
        document.head.appendChild(script);
        });
    };
    onez.iframe=function(key){
        if(!key){
        return $('#onez-win-frame')[0].contentWindow;
        }
        return new Promise((resolve,reject)=>{
        let url=`/page.php?path=${key}`;
        window.last_iframe=$(`<div class="fixed inset-0 bg-black/50 flex items-center justify-center z-50" style="position: fixed;z-index: 6200103;">
        <iframe id="onez-win-frame"
            src="${url}" 
            allowtransparency="true" 
            class="w-full h-full border-none bg-transparent"
        ></iframe>
    </div>`).appendTo('body');
        window.last_iframe_callback=resolve;
        });
    };
    onez.loadCSS=function(url){
        return new Promise((resolve,reject)=>{
        let link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = url;
        document.head.appendChild(link);
        resolve();
        });
    };
    const win=top.window.last_iframe;
    const win_callback=top.window.last_iframe_callback;
    setTimeout(()=>{
        win?.show();
        top.onez?.game?.loading?.hide();
    },1000);
    function closeWin(result){
        win.remove();
        if(win_callback){
            win_callback(result);
        }
    }
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    'gc-bg': '#FFFFFF', 'gc-cyan': '#2F6F66', 'gc-pink': '#4B5563',
                    'gc-yellow': '#F3F4F6', 'gc-hot': '#B91C1C', 'gc-dark': '#111827', 'gc-text': '#374151',
                },
                borderWidth: { '2': '1px', '3': '1px' },
                borderRadius: { 'xl': '0.375rem', '2xl': '0.5rem', '3xl': '0.625rem' },
                boxShadow: {
                    'gc-card': '0 1px 2px 0 rgba(16,24,40,.06), 0 1px 3px 0 rgba(16,24,40,.10)',
                    'gc-btn': '0 1px 2px 0 rgba(16,24,40,.10)',
                    'gc-btn-pressed': '0 0 0 0 transparent',
                }
            }
        }
    }
    async function post($path,postdata){
        
        const res=await fetch(window.location.href,{
        method:'POST',
        body:JSON.stringify({...postdata,$path}),
        headers:{
            'Content-Type':'application/json',
        },
        });
        return await res.json();
    }
    (async function(){
        $(document).on('click','[data-url]',function(){
            let url=$(this).attr('data-url');
            window.open(url,'','');
        });
        function copyText(text){
            if(navigator.clipboard){
                return navigator.clipboard.writeText(text).catch(()=>fallbackCopy(text));
            }
            return fallbackCopy(text);
        }
        function fallbackCopy(text){
            const textarea=document.createElement('textarea');
            textarea.value=text;
            textarea.setAttribute('readonly','');
            textarea.style.position='fixed';
            textarea.style.opacity='0';
            document.body.appendChild(textarea);
            textarea.select();
            let copied=false;
            try{
                copied=document.execCommand('copy');
            }catch(e){}
            textarea.remove();
            return copied ? Promise.resolve() : Promise.reject(new Error('copy failed'));
        }
        let toastBox=null;
        function showToast(message,type='success',duration=1800){
            const icons={
                success:'fa-circle-check',
                error:'fa-circle-xmark',
                warning:'fa-triangle-exclamation',
                info:'fa-circle-info',
            };
            const colors={
                success:'#5CA69C',
                error:'#F48E9B',
                warning:'#F2C94C',
                info:'#6C9BD4',
            };
            const key=icons[type] ? type : (type==='danger' ? 'error' : 'success');
            if(!toastBox){
                toastBox=document.createElement('div');
                toastBox.className='gc-toast-box';
                toastBox.setAttribute('aria-live','polite');
                toastBox.style.cssText='position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:99999;display:flex;flex-direction:column;align-items:center;gap:10px;pointer-events:none;';
                document.body.appendChild(toastBox);
            }
            const toast=document.createElement('div');
            const icon=document.createElement('i');
            icon.className='fa-solid '+icons[key];
            icon.style.color=colors[key];
            const text=document.createElement('span');
            text.textContent=message;
            toast.appendChild(icon);
            toast.appendChild(text);
            toast.style.cssText=[
                'display:flex;align-items:center;gap:10px;',
                'max-width:min(90vw,480px);padding:12px 18px;',
                'background:#fff;border:3px solid #4A3E3D;border-left:6px solid '+colors[key]+';',
                'border-radius:12px;box-shadow:6px 6px 0 #4A3E3D;',
                'font-size:15px;font-weight:700;color:#4A3E3D;',
                'opacity:0;transform:translateY(-8px);',
                'transition:opacity .2s ease,transform .2s ease;',
            ].join('');
            toastBox.appendChild(toast);
            requestAnimationFrame(()=>{
                toast.style.opacity='1';
                toast.style.transform='translateY(0)';
            });
            setTimeout(()=>{
                toast.style.opacity='0';
                toast.style.transform='translateY(-8px)';
                setTimeout(()=>toast.remove(),200);
            },duration);
        }
        window.toast=showToast;
        window.showToast=showToast;
        $(document).on('click','[data-copy]',function(){
            const text=$(this).attr('data-copy') || '';
            if(!text)return;
            copyText(text).then(()=>showToast('已复制')).catch(()=>showToast('复制失败','error'));
        });
        $(document).on('click','[data-action]',async function(){
            const data=$(this).data();
            const res=await post(data.action||'submit',data);
            showToast(res?.error||res?.message||'提示成功');
        });
    })();

</script>
<style type="text/tailwindcss">
    @layer base {
        /* 【核心】这里让 iframe 内部的底色变成全透明 */
        body {
            background-color: transparent; 
            font-family: 'PingFang SC', sans-serif;
            color: #111827;
            /* 居中弹窗内容 */
            @apply min-h-screen flex items-center justify-center p-6; 
        }
        button {
            @apply bg-gc-cyan text-white font-semibold text-sm border-3 border-gc-dark rounded-2xl py-2 px-4 shadow-gc-btn transition-colors cursor-pointer inline-flex items-center justify-center;
        }
        button:active { @apply opacity-80; }
        button.primary { @apply bg-gc-pink; }
    }

    /* 呼吸灯特效，用于印章 */
    @keyframes pulse-slow {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.72; }
    }
    .stamp-anim { animation: pulse-slow 3s infinite; }

    /* 卡片描述强制两行，超长省略（避免卡片高度错乱） */
    .line-clamp-2 {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    /* ===== 极简交互阈值：克制但有反馈 ===== */
    .tool-card { transition: border-color .15s ease, box-shadow .15s ease; }
    .tool-card:hover {
        transform: none !important;
        border-color: #111827;
        box-shadow: 0 2px 10px 0 rgba(16,24,40,.12);
    }
    a { transition: color .15s ease; }
    input:focus, textarea:focus, select:focus {
        outline: none;
        border-color: #111827;
        box-shadow: 0 0 0 2px rgba(17,24,39,.18);
    }



</style>
<style>
body{padding:0!important;align-items: start!important;}
</style>
