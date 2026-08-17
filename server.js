import http from 'http';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { WebSocketServer } from 'ws';
import { PHP, PHPRequestHandler, LatestSupportedPHPVersion } from '@php-wasm/universal';
import { loadNodeRuntime, createNodeFsMountHandler } from '@php-wasm/node';
import { onezRemoteDB } from './data/db.js';
import { createHash } from 'crypto';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const PORT = parseInt(process.env.PORT, 10) || 3000;
const VFS_DOC_ROOT = '/srv';
// WebSocket 消息转发给 PHP 的入口脚本（可替换成你自己的游戏逻辑入口）
const WS_PHP_ENDPOINT = process.env.WS_PHP_ENDPOINT || '/ws.php';

// ---------- 1. 启动 PHP.wasm 运行时 ----------
const runtime = await loadNodeRuntime(LatestSupportedPHPVersion, {
    emscriptenOptions: {
        processId: (process.pid || Date.now()) % 2147483647,
    },
});
const php = new PHP(runtime);
await php.mount(VFS_DOC_ROOT, createNodeFsMountHandler(__dirname));
const requestHandler = new PHPRequestHandler({
    php,
    documentRoot: VFS_DOC_ROOT,
    absoluteUrl: `http://localhost:${PORT}`,
});

// 文件数据库（MongoDB 风格 API，数据落在 ./data 目录）
const db = new onezRemoteDB('onez', path.join(__dirname, 'data'));

// ---------- 2. 极简服务端 JS 模板引擎（无沙箱，等同 eval） ----------
// 两种写法，所有代码段共享同一作用域（类似 PHP）：
//   A) <script server> ... </script>  —— 服务端 JS 块，编辑器按 JS 高亮/校验，
//      运行时整块执行并替换为 echo() 的输出，客户端看不到这段 JS。
//   B) <?js ... ?> 代码块 / <?= expr ?> 输出表达式 —— PHP 混合写法（HTML 里用）。
// 模板代码通过 ctx 访问请求上下文：ctx.req / ctx.res / ctx.url / ctx.pathname / ctx.query
const TEMPLATE_EXTS = new Set(['.html', '.htm', '.js']);
const templateCache = new Map(); // path -> { mtimeMs, size, render }

// 找 ?> 的结束位置：跳过字符串(' " `)和注释，避免误截断
function findTagEnd(src, from) {
    let i = from;
    const n = src.length;
    let quote = null;
    let lineComment = false;
    let blockComment = false;
    while (i < n) {
        const c = src[i];
        const next = src[i + 1];
        if (lineComment) {
            if (c === '\n') lineComment = false;
            i++; continue;
        }
        if (blockComment) {
            if (c === '*' && next === '/') { blockComment = false; i += 2; continue; }
            i++; continue;
        }
        if (quote) {
            if (c === '\\') { i += 2; continue; }
            if (c === quote) quote = null;
            i++; continue;
        }
        if (c === '/' && next === '/') { lineComment = true; i += 2; continue; }
        if (c === '/' && next === '*') { blockComment = true; i += 2; continue; }
        if (c === '"' || c === "'" || c === '`') { quote = c; i++; continue; }
        if (c === '?' && next === '>') return i;
        i++;
    }
    return -1;
}

// 从 from 开始找 '</script>' 结束标签，跳过 JS 字符串/注释（代码里含 '</script>' 不会误判）；返回 '<' 的位置
function findScriptClose(src, from) {
    let i = from;
    const n = src.length;
    let quote = null, lineComment = false, blockComment = false;
    while (i < n) {
        const c = src[i], next = src[i + 1];
        if (lineComment) {
            if (c === '\n') lineComment = false;
            i++; continue;
        }
        if (blockComment) {
            if (c === '*' && next === '/') { blockComment = false; i += 2; continue; }
            i++; continue;
        }
        if (quote) {
            if (c === '\\') { i += 2; continue; }
            if (c === quote) quote = null;
            i++; continue;
        }
        if (c === '/' && next === '/') { lineComment = true; i += 2; continue; }
        if (c === '/' && next === '*') { blockComment = true; i += 2; continue; }
        if (c === '"' || c === "'" || c === '`') { quote = c; i++; continue; }
        if (c === '<' && src.startsWith('</script', i)) {
            let j = i + 8;
            while (j < n && /\s/.test(src[j])) j++;
            if (src[j] === '>') return i;
        }
        i++;
    }
    return -1;
}

// 把模板切成 文本 / 代码块 / 表达式 三段（<script server> 也作为代码段，共享同一作用域）
function tokenizeTemplate(src) {
    const parts = [];
    let i = 0, textStart = 0;
    const n = src.length;
    while (i < n) {
        if (src.startsWith('<script', i)) {
            const gt = src.indexOf('>', i + 7);
            if (gt < 0) { i++; continue; }
            const attrs = src.slice(i + 7, gt);
            const cleanAttrs = attrs.replace(/"[^"]*"|'[^']*'/g, '');
            if (/\bserver\b/.test(cleanAttrs)) {
                if (i > textStart) parts.push({ type: 'text', value: src.slice(textStart, i) });
                const closeLt = findScriptClose(src, gt + 1);
                if (closeLt < 0) throw new Error('未闭合的 <script server> 标签');
                const closeEnd = src.indexOf('>', closeLt);
                parts.push({ type: 'code', value: src.slice(gt + 1, closeLt) });
                i = closeEnd + 1; textStart = i;
            } else {
                i++;
            }
        } else if (src.startsWith('<?js', i)) {
            if (i > textStart) parts.push({ type: 'text', value: src.slice(textStart, i) });
            const end = findTagEnd(src, i + 4);
            if (end < 0) throw new Error('未闭合的 <?js 标签');
            parts.push({ type: 'code', value: src.slice(i + 4, end) });
            i = end + 2; textStart = i;
        } else if (src.startsWith('<?=', i)) {
            if (i > textStart) parts.push({ type: 'text', value: src.slice(textStart, i) });
            const end = findTagEnd(src, i + 3);
            if (end < 0) throw new Error('未闭合的 <?= 标签');
            parts.push({ type: 'expr', value: src.slice(i + 3, end) });
            i = end + 2; textStart = i;
        } else {
            i++;
        }
    }
    if (textStart < n) parts.push({ type: 'text', value: src.slice(textStart) });
    return parts;
}

// 编译整个模板文件（new Function = 当前进程全权限执行，无沙箱；所有代码段共享同一作用域，类似 PHP）
function compileTemplate(source) {
    const parts = tokenizeTemplate(source);
    let body = 'let __out = "";\n';
    body += 'const echo = (...args) => { __out += args.map(String).join(" "); };\n';
    for (const part of parts) {
        if (part.type === 'text') body += `__out += ${JSON.stringify(part.value)};\n`;
        else if (part.type === 'code') body += part.value + '\n';
        else body += `__out += String(await (${part.value}));\n`;
    }
    body += 'return __out;';
    // eslint-disable-next-line no-new-func
    return new Function('ctx', `return (async () => {\n${body}\n})();`);
}

async function renderTemplate(filePath, content, ctx) {
    const stat = await fs.promises.stat(filePath);
    let entry = templateCache.get(filePath);
    if (!entry || entry.mtimeMs !== stat.mtimeMs || entry.size !== stat.size) {
        entry = { mtimeMs: stat.mtimeMs, size: stat.size, render: compileTemplate(content) };
        templateCache.set(filePath, entry);
    }
    return await entry.render(ctx);
}

// ---------- 2.5 onez.run RPC：客户端 <script> 里的服务端代码段 ----------
// 客户端写：const r = await onez.run({参数}, function(params){ 服务端JS });
// 渲染时服务端精准提取 function 体，注册到随机 action；客户端只看到 fetch 请求。
const rpcHandlers = new Map();
const RPC_TTL_MS = 30 * 60 * 1000;
setInterval(() => {
    const now = Date.now();
    for (const [k, v] of rpcHandlers) {
        if (now - v.createdAt > RPC_TTL_MS) rpcHandlers.delete(k);
    }
}, 5 * 60 * 1000);

// 从 openIdx（'{'）开始，跳过字符串/注释，返回匹配的 '}' 位置
function findMatchingBrace(src, openIdx) {
    let depth = 0, i = openIdx;
    const n = src.length;
    let quote = null, lineComment = false, blockComment = false;
    while (i < n) {
        const c = src[i], next = src[i + 1];
        if (lineComment) { if (c === '\n') lineComment = false; i++; continue; }
        if (blockComment) { if (c === '*' && next === '/') { blockComment = false; i += 2; continue; } i++; continue; }
        if (quote) { if (c === '\\') { i += 2; continue; } if (c === quote) quote = null; i++; continue; }
        if (c === '/' && next === '/') { lineComment = true; i += 2; continue; }
        if (c === '/' && next === '*') { blockComment = true; i += 2; continue; }
        if (c === '"' || c === "'" || c === '`') { quote = c; i++; continue; }
        if (c === '{') depth++;
        else if (c === '}') { depth--; if (depth === 0) return i; }
        i++;
    }
    return -1;
}

// 读取 onez.run( 的第一参数：返回 { end(顶层逗号位置), code(参数代码) }
function readFirstArg(src, from) {
    let depthParen = 1, depthBrace = 0, depthBracket = 0;
    let i = from;
    const n = src.length;
    let quote = null, lineComment = false, blockComment = false;
    while (i < n) {
        const c = src[i], next = src[i + 1];
        if (lineComment) { if (c === '\n') lineComment = false; i++; continue; }
        if (blockComment) { if (c === '*' && next === '/') { blockComment = false; i += 2; continue; } i++; continue; }
        if (quote) { if (c === '\\') { i += 2; continue; } if (c === quote) quote = null; i++; continue; }
        if (c === '/' && next === '/') { lineComment = true; i += 2; continue; }
        if (c === '/' && next === '*') { blockComment = true; i += 2; continue; }
        if (c === '"' || c === "'" || c === '`') { quote = c; i++; continue; }
        if (c === '(') depthParen++;
        else if (c === ')') { depthParen--; if (depthParen === 0) break; }
        else if (c === '{') depthBrace++;
        else if (c === '}') depthBrace--;
        else if (c === '[') depthBracket++;
        else if (c === ']') depthBracket--;
        else if (c === ',' && depthParen === 1 && depthBrace === 0 && depthBracket === 0) {
            return { end: i, code: src.slice(from, i).trim() };
        }
        i++;
    }
    return { end: -1, code: '' };
}

// 扫描代码里的 onez.run(...) 调用（跳过字符串/注释），返回 [{start, end, paramsCode, paramsName, body}]
function scanOnezCalls(code) {
    const calls = [];
    let i = 0;
    const n = code.length;
    let quote = null, lineComment = false, blockComment = false;
    while (i < n) {
        const c = code[i], next = code[i + 1];
        if (lineComment) { if (c === '\n') lineComment = false; i++; continue; }
        if (blockComment) { if (c === '*' && next === '/') { blockComment = false; i += 2; continue; } i++; continue; }
        if (quote) { if (c === '\\') { i += 2; continue; } if (c === quote) quote = null; i++; continue; }
        if (c === '/' && next === '/') { lineComment = true; i += 2; continue; }
        if (c === '/' && next === '*') { blockComment = true; i += 2; continue; }
        if (c === '"' || c === "'" || c === '`') { quote = c; i++; continue; }
        if (code.startsWith('onez.run(', i)) {
            const prev = code[i - 1];
            if (prev && /[\w$]/.test(prev)) { i++; continue; }
            const arg = readFirstArg(code, i + 9);
            if (arg.end < 0) throw new Error('onez.run: 无法解析第一参数');
            const rest = code.slice(arg.end + 1);
            const m = /^\s*function\s*\(([^)]*)\)\s*\{/.exec(rest);
            if (!m) throw new Error('onez.run: 第二个参数必须是 function(params){...}');
            const bodyOpenIdx = arg.end + 1 + m[0].lastIndexOf('{');
            const bodyEnd = findMatchingBrace(code, bodyOpenIdx);
            if (bodyEnd < 0) throw new Error('onez.run: 函数体未闭合');
            // end 要延伸到 onez.run(...) 结尾的 ')' 之后，避免残留括号
            let closeParen = bodyEnd + 1;
            while (closeParen < n && /\s/.test(code[closeParen])) closeParen++;
            if (code[closeParen] === ')') closeParen++;
            calls.push({
                start: i,
                end: closeParen,
                paramsCode: arg.code,
                paramsName: m[1].trim() || 'params',
                body: code.slice(bodyOpenIdx + 1, bodyEnd),
            });
            i = closeParen;
        } else {
            i++;
        }
    }
    return calls;
}

// 渲染后处理：onez.run(...) -> fetch 调用，并在服务端注册 action -> 已编译函数
function processOnezRpc(html, ctx) {
    const calls = scanOnezCalls(html);
    if (!calls.length) return html;
    let out = html;
    for (let k = calls.length - 1; k >= 0; k--) {
        const call = calls[k];
        const action = (globalThis.crypto && crypto.randomUUID)
            ? crypto.randomUUID()
            : Math.random().toString(36).slice(2) + Date.now().toString(36);
        // eslint-disable-next-line no-new-func
        const fn = new Function('ctx', call.paramsName, `return (async () => {\n${call.body}\n})();`);
        rpcHandlers.set(action, { fn, createdAt: Date.now() });
        const replacement = `_post({ action: '${action}', params: ${call.paramsCode} })`;
        out = out.slice(0, call.start) + replacement + out.slice(call.end);
    }
    // 页面顶部附加 _post 辅助函数，让替换后的代码更简洁易读
    const helperScript =
        '<script>\n' +
        'async function _post({ action, params }) {\n' +
        "  return fetch(location.href, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action, params }) }).then(r => r.json());\n" +
        '}\n' +
        '</script>\n';
    const headMatch = /<head[^>]*>/i.exec(out);
    if (headMatch) {
        out = out.slice(0, headMatch.index + headMatch[0].length) + helperScript + out.slice(headMatch.index + headMatch[0].length);
    } else {
        out = helperScript + out;
    }
    return out;
}

// ---------- 2.6 配音 API（Edge TTS：微软在线语音合成） ----------
// 路由：
//   GET  /api/tts/voices?locale=zh-CN  —— 可用音色列表（可选按 Locale 过滤）
//   GET  /api/tts?text=你好&voice=zh-CN-XiaoxiaoNeural&rate=%2B0%25&pitch=%2B0Hz&volume=%2B0%25
//   POST /api/tts                       —— JSON / 表单 body：{ text, voice, rate, pitch, volume }
// 返回：audio/mpeg（MP3 音频流）；出错返回 JSON { ok:false, error }

// ---------- 2.7 AI 剧本生成（Node 直连 LLM：佳蓝官方 / OpenAI，支持流式） ----------
const AI_JIALAN_BASE = 'https://onez.cn/ai/v1';
const AI_JIALAN_MODEL = 'aifenjing-2105';
const AI_SCRIPT_TIMEOUT = 300000;          // 单次生成超时
const AI_SCRIPT_STALE_MS = 5 * 60 * 1000;  // generating 标记超过 5 分钟视为失效


const DATA_DIR = path.join(__dirname, 'data');

function readJsonFile(file) {
    try { return JSON.parse(fs.readFileSync(file, 'utf8')); } catch { return null; }
}
function writeJsonFile(file, obj) {
    fs.mkdirSync(path.dirname(file), { recursive: true });
    fs.writeFileSync(file, JSON.stringify(obj, null, 2));
}
function workFilePath(id) {
    return path.join(WORKS_DIR, String(id).replace(/[^a-zA-Z0-9\-_]/g, '') + '.json');
}
function readWork(id) { return readJsonFile(workFilePath(id)); }
function writeWork(id, rec) { writeJsonFile(workFilePath(id), rec); }
function readSettings() { return readJsonFile(path.join(DATA_DIR, 'settings.json')) || {}; }
function nowStr() {
    const d = new Date();
    const p = (n) => String(n).padStart(2, '0');
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + ' ' + p(d.getHours()) + ':' + p(d.getMinutes()) + ':' + p(d.getSeconds());
}

const AI_ROLES = ['c2013', 'c1993', 'c1998', 'c2014', 'c1999', 'c2042', 'c2039'];
const AI_EMOTIONS = ['normal', 'angry', 'shock', 'laugh', 'sad', 'sweat'];

// 解析 LLM 输出为剧本数组（与 PHP 端解析规则保持一致）
function parseScript(content) {
    if (typeof content !== 'string') return null;
    let s = content.trim().replace(/^\s*\x60\x60\x60(?:json)?\s*/i, '').replace(/\x60\x60\x60\s*$/, '').trim();
    const p1 = s.indexOf('[');
    const p2 = s.lastIndexOf(']');
    if (p1 >= 0 && p2 > p1) s = s.slice(p1, p2 - p1 + 1);
    let arr;
    try { arr = JSON.parse(s); } catch { return null; }
    if (!Array.isArray(arr)) return null;
    const out = [];
    for (const it of arr) {
        if (!it || typeof it !== 'object') continue;
        let role = typeof it.role === 'string' ? it.role : null;
        if (role !== null && !AI_ROLES.includes(role)) role = null;
        let text = typeof it.text === 'string' ? it.text.trim() : '';
        if (!text) text = '……';
        if (text.length > 60) text = text.slice(0, 60);
        let emotion = typeof it.emotion === 'string' ? it.emotion : 'normal';
        if (!AI_EMOTIONS.includes(emotion)) emotion = 'normal';
        out.push({ role, text, emotion });
    }
    if (!out.length) return null;
    return out.slice(0, 30);
}

// 未配置 key 时的内置示例剧本（保证流程可跑通）
function sampleScript() {
    return [
        { role: 'c2013', text: '各位大侠，门派推出功德贷，行侠仗义也能分期。', emotion: 'laugh' },
        { role: 'c2014', text: '啥？我救人一命还得还贷款？', emotion: 'shock' },
        { role: 'c1998', text: '据江湖热搜榜，利息按日算，逾期上征信。', emotion: 'normal' },
        { role: 'c1993', text: '那我昨天扶老奶奶过马路，算首付吗？', emotion: 'normal' },
        { role: 'c2013', text: '算！但首付不够，建议拉三个同门一起贷。', emotion: 'laugh' },
        { role: 'c1999', text: '噼里啪啦……功德贷年化利率三百八，比高利贷还黑！', emotion: 'angry' },
        { role: 'c2042', text: '听说还不上的，下辈子投胎当社畜。', emotion: 'shock' },
        { role: 'c2014', text: '我现在就去把昨天救的人打一顿，功德退货！', emotion: 'angry' },
        { role: 'c1998', text: '晚了，退货要收百分之二十功德折损费。', emotion: 'sweat' },
        { role: 'c2039', text: '阿弥陀佛，原来极乐世界的门票，也是按揭的。', emotion: 'normal' },
    ];
}

// 依据设置决定 LLM 通道（佳蓝官方 / OpenAI，二选一）
function resolveLLM(settings) {
    const provider = settings.ai_provider === 'jialan' ? 'jialan' : 'openai';
    let base, model, key;
    if (provider === 'jialan') {
        base = AI_JIALAN_BASE;
        model = AI_JIALAN_MODEL;
        key = String(settings.jialan_api_key || '').trim();
    } else {
        base = (String(settings.openai_base_url || '').trim() || 'https://api.openai.com/v1').replace(/\/+$/, '');
        model = String(settings.openai_model || '').trim() || 'gpt-4o-mini';
        key = String(settings.openai_api_key || '').trim();
    }
    return { provider, base, model, key };
}

// 保存剧本到作品文件（幂等：生成中标记控制并发，不重复）
function saveScript(rec, timelines, generating) {
    rec.timelines = timelines;
    rec.status = 'processing';
    rec.step = 'script';
    rec.generating = generating ? Date.now() : null;
    rec.updatetime = nowStr();
    writeWork(rec.id, rec);
}

// 剧本接口：GET/POST /api/ai/script?id=&stream=1
async function handleAiScript(req, res, bodyBuffer) {
    let id = '';
    let stream = true;
    try {
        const url = new URL(req.url, 'http://localhost');
        id = url.searchParams.get('id') || '';
        stream = url.searchParams.get('stream') !== '0';
        if (bodyBuffer) {
            try {
                const body = JSON.parse(bodyBuffer.toString('utf8'));
                if (body.id) id = body.id;
                if (body.stream !== undefined) stream = !!Number(body.stream);
            } catch { /* 忽略非 JSON body */ }
        }
    } catch { /* ignore */ }
    id = String(id).replace(/[^a-zA-Z0-9\-_]/g, '');
    if (!id) {
        res.writeHead(400, { 'Content-Type': 'application/json; charset=utf-8' });
        res.end(JSON.stringify({ ok: false, error: '缺少作品 id' }));
        return;
    }
    const rec = readWork(id);
    if (!rec) {
        res.writeHead(404, { 'Content-Type': 'application/json; charset=utf-8' });
        res.end(JSON.stringify({ ok: false, error: '作品不存在' }));
        return;
    }

    const SSE_HEADERS = {
        'Content-Type': 'text/event-stream; charset=utf-8',
        'Cache-Control': 'no-cache',
        'Connection': 'keep-alive',
        'X-Accel-Buffering': 'no',
    };
    const sendSSE = (obj) => { res.write('data: ' + JSON.stringify(obj) + '\n\n'); };
    const sendJSON = (code, obj) => { res.writeHead(code, { 'Content-Type': 'application/json; charset=utf-8' }); res.end(JSON.stringify(obj)); };

    // 已生成过：直接返回缓存（脚本只生成一次）
    if (Array.isArray(rec.timelines) && rec.timelines.length) {
        if (stream) {
            res.writeHead(200, SSE_HEADERS);
            sendSSE({ done: true, cached: true, timelines: rec.timelines });
            res.end();
        } else {
            sendJSON(200, { ok: true, cached: true, timelines: rec.timelines });
        }
        return;
    }
    // 其他请求正在生成：流式给 wait 事件，一次性返回 busy
    if (rec.generating && (Date.now() - rec.generating) < AI_SCRIPT_STALE_MS) {
        if (stream) {
            res.writeHead(200, SSE_HEADERS);
            sendSSE({ wait: true, message: '正在生成中（其他页面），请稍候…' });
            res.end();
        } else {
            sendJSON(200, { ok: false, busy: true, error: '正在生成中，请稍候' });
        }
        return;
    }
    if (rec.generating) delete rec.generating; // 失效标记清理

    const settings = readSettings();
    const promptFile = path.join(__dirname, 'prompt.md');
    let prompt = fs.existsSync(promptFile) ? fs.readFileSync(promptFile, 'utf8') : '你是短视频漫剧编剧，根据用户主题生成对话剧本。';
    let userText = rec.params && rec.params.semantic ? String(rec.params.semantic).trim() : '';
    // 描述语留空：进入「盲盒生成」模式（自动补用户提示 + 追加系统盲盒指令）
    if (!userText) {
        userText = '请发挥一个符合《沙雕江湖》气质的离谱脑洞';
        prompt += AI_BLIND_BOX_PROMPT;
    }

    const llm = resolveLLM(settings);

    // 未配置 key：返回内置示例（保证流程可跑通）
    if (!llm.key) {
        const timelines = sampleScript();
        saveScript(rec, timelines, false);
        if (stream) {
            res.writeHead(200, SSE_HEADERS);
            sendSSE({ done: true, sample: true, timelines });
            res.end();
        } else {
            sendJSON(200, { ok: true, sample: true, timelines });
        }
        return;
    }

    // 标记生成中（幂等锁）
    rec.status = 'processing';
    rec.step = 'script';
    rec.generating = Date.now();
    rec.updatetime = nowStr();
    writeWork(rec.id, rec);

    const payload = {
        model: llm.model,
        temperature: 0.9,
        stream,
        messages: [
            { role: 'system', content: prompt },
            { role: 'user', content: userText },
        ],
    };
    const ctrl = new AbortController();
    const timer = setTimeout(() => ctrl.abort(), AI_SCRIPT_TIMEOUT);

    try {
        // 流式：先发 start 事件，客户端立刻感知已连接（避免长时间停留在“连接中”）
        if (stream) {
            res.writeHead(200, SSE_HEADERS);
            sendSSE({ start: true });
        }
        const resp = await fetch(llm.base + '/chat/completions', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + llm.key },
            body: JSON.stringify(payload),
            signal: ctrl.signal,
        });
        if (!resp.ok) {
            let msg = 'LLM 调用失败 HTTP ' + resp.status;
            try { const t = await resp.text(); if (t) msg += '：' + t.slice(0, 200); } catch { /* ignore */ }
            throw new Error(msg);
        }
        if (!stream) {
            const j = await resp.json();
            const content = j && j.choices && j.choices[0] && j.choices[0].message && j.choices[0].message.content;
            const timelines = parseScript(content);
            if (!timelines) throw new Error('剧本解析失败，请重试');
            saveScript(rec, timelines, false);
            sendJSON(200, { ok: true, timelines });
            return;
        }
        // 流式 SSE：逐字转发 LLM 增量（响应头已在调用前发出）
        const reader = resp.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';
        let full = '';
        while (true) {
            const step = await reader.read();
            if (step.done) break;
            buffer += decoder.decode(step.value, { stream: true });
            let idx;
            while ((idx = buffer.indexOf('\n\n')) >= 0) {
                const raw = buffer.slice(0, idx);
                buffer = buffer.slice(idx + 2);
                for (const line of raw.split('\n')) {
                    if (!line.startsWith('data:')) continue;
                    const data = line.slice(5).trim();
                    if (!data || data === '[DONE]') continue;
                    try {
                        const evt = JSON.parse(data);
                        const delta = evt.choices && evt.choices[0] && evt.choices[0].delta && evt.choices[0].delta.content;
                        if (typeof delta === 'string' && delta) {
                            full += delta;
                            sendSSE({ delta });
                        }
                    } catch { /* 跳过无法解析的 chunk */ }
                }
            }
        }
        const timelines = parseScript(full);
        if (!timelines) throw new Error('剧本解析失败，请重试');
        saveScript(rec, timelines, false);
        sendSSE({ done: true, timelines });
        res.end();
    } catch (error) {
        rec.status = 'error';
        rec.step = 'script';
        rec.generating = null;
        rec.error = error && error.message;
        rec.updatetime = nowStr();
        writeWork(rec.id, rec);
        console.error('AI script error:', error);
        if (!res.headersSent) {
            sendJSON(500, { ok: false, error: (error && error.message) || '剧本生成失败' });
        } else {
            try { sendSSE({ error: (error && error.message) || '剧本生成失败' }); res.end(); } catch { /* ignore */ }
        }
    } finally {
        clearTimeout(timer);
    }
}

// ---------- 3. HTTP 服务器：静态资源 + 模板 + PHP 动态 ----------
const MIME = {
    '.html': 'text/html; charset=utf-8', '.htm': 'text/html; charset=utf-8',
    '.css': 'text/css; charset=utf-8', '.js': 'text/javascript; charset=utf-8',
    '.mjs': 'text/javascript; charset=utf-8', '.json': 'application/json; charset=utf-8',
    '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg',
    '.gif': 'image/gif', '.webp': 'image/webp', '.svg': 'image/svg+xml', '.ico': 'image/x-icon',
    '.mp3': 'audio/mpeg', '.wav': 'audio/wav', '.ogg': 'audio/ogg', '.oga': 'audio/ogg',
    '.m4a': 'audio/mp4', '.aac': 'audio/aac', '.flac': 'audio/flac',
    '.mp4': 'video/mp4', '.webm': 'video/webm', '.mov': 'video/quicktime',
    '.txt': 'text/plain; charset=utf-8', '.xml': 'application/xml',
    '.pdf': 'application/pdf', '.zip': 'application/zip', '.wasm': 'application/wasm',
    '.woff': 'font/woff', '.woff2': 'font/woff2', '.ttf': 'font/ttf', '.otf': 'font/otf',
};

// 尝试用 Node 直接发静态文件；返回 true 表示已处理
async function serveStatic(req, res, pathname) {
    if (pathname.endsWith('/')) return false;
    const filePath = path.resolve(__dirname, '.' + pathname);
    // 防目录穿越：解析后必须仍在服务目录内
    if (!filePath.startsWith(__dirname + path.sep) && filePath !== __dirname) return false;
    let stat;
    try {
        stat = await fs.promises.stat(filePath);
    } catch {
        return false; // 不是静态文件，交给 PHP
    }
    if (!stat.isFile()) return false;
    // .php 归 PHP 动态处理，不做静态分发
    const ext = path.extname(filePath).toLowerCase();
    if (['.php', '.phtml', '.phps'].includes(ext)) return false;

    const type = MIME[ext] || 'application/octet-stream';

    // 模板文件：含 <script server> / <?js / <?= 就渲染，否则原样返回
    if (TEMPLATE_EXTS.has(ext)) {
        const content = await fs.promises.readFile(filePath, 'utf8');
        if (content.includes('<script server') || content.includes('<?js') || content.includes('<?=') || content.includes('onez.run(')) {
            const url = new URL(req.url, 'http://localhost');
            const ctx = {
                req, res,
                url: req.url,
                pathname,
                query: Object.fromEntries(url.searchParams),
                PORT,
                __dirname,
                php: requestHandler,
                db,
            };
            try {
                let html = await renderTemplate(filePath, content, ctx);
                html = processOnezRpc(html, ctx);
                res.writeHead(200, {
                    'Content-Type': ext === '.js' ? 'text/javascript; charset=utf-8' : 'text/html; charset=utf-8',
                    'Content-Length': Buffer.byteLength(html),
                    'Cache-Control': 'no-cache',
                });
                res.end(html);
            } catch (error) {
                console.error('Template render error:', error);
                if (!res.headersSent) res.writeHead(500);
                res.end('Template Error: ' + (error && error.message));
            }
            return true;
        }
        // 无模板标记：按已读内容返回
        res.writeHead(200, {
            'Content-Type': type,
            'Content-Length': Buffer.byteLength(content),
            'Cache-Control': 'public, max-age=3600',
        });
        if (req.method === 'HEAD') { res.end(); return true; }
        res.end(content);
        return true;
    }

    // 普通静态文件（图片/音频等）：支持 Range/断点
    const range = req.headers.range;
    if (range) {
        const m = /bytes=(\d*)-(\d*)/.exec(range);
        let start = m && m[1] ? parseInt(m[1], 10) : 0;
        let end = m && m[2] ? parseInt(m[2], 10) : stat.size - 1;
        if (Number.isNaN(start) || start >= stat.size) {
            res.writeHead(416, { 'Content-Range': `bytes */${stat.size}` });
            res.end();
            return true;
        }
        end = Math.min(end, stat.size - 1);
        res.writeHead(206, {
            'Content-Type': type,
            'Content-Length': end - start + 1,
            'Content-Range': `bytes ${start}-${end}/${stat.size}`,
            'Accept-Ranges': 'bytes',
            'Cache-Control': 'public, max-age=3600',
        });
        if (req.method === 'HEAD') { res.end(); return true; }
        fs.createReadStream(filePath, { start, end }).pipe(res);
        return true;
    }
    res.writeHead(200, {
        'Content-Type': type,
        'Content-Length': stat.size,
        'Accept-Ranges': 'bytes',
        'Cache-Control': 'public, max-age=3600',
    });
    if (req.method === 'HEAD') { res.end(); return true; }
    fs.createReadStream(filePath).pipe(res);
    return true;
}

const server = http.createServer(async (req, res) => {
    try {
        let pathname;
        try {
            pathname = decodeURIComponent(new URL(req.url, 'http://localhost').pathname);
        } catch {
            res.writeHead(400); res.end('Bad Request'); return;
        }
        // 禁止访问 ./data 数据库目录（防下载），大小写不敏感
        const dataRoot = path.join(__dirname, 'data').toLowerCase();
        const reqPath = path.resolve(__dirname, '.' + pathname).toLowerCase();
        if (reqPath === dataRoot || reqPath.startsWith(dataRoot + path.sep)) {
            res.writeHead(403);
            res.end('Forbidden');
            return;
        }
        // 先读请求体（POST/PUT/PATCH），RPC 和 PHP 共用，避免重复读取
        let bodyBuffer;
        if (['POST', 'PUT', 'PATCH'].includes(req.method)) {
            bodyBuffer = await getRequestBody(req);
        }
        // onez.run RPC：POST JSON body 带 action 时，执行预注册的服务端代码
        if (bodyBuffer) {
            let parsed;
            try {
                parsed = JSON.parse(bodyBuffer.toString('utf8'));
            } catch { /* 非 JSON，忽略 */ }
            if (parsed && typeof parsed.action === 'string' && rpcHandlers.has(parsed.action)) {
                try {
                    const handler = rpcHandlers.get(parsed.action);
                    const rpcCtx = {
                        req, res,
                        url: req.url,
                        pathname,
                        query: Object.fromEntries(new URL(req.url, 'http://localhost').searchParams),
                        PORT,
                        __dirname,
                        php: requestHandler,
                        db,
                    };
                    const data = await handler.fn(rpcCtx, parsed.params);
                    res.writeHead(200, { 'Content-Type': 'application/json' });
                    res.end(JSON.stringify({ ok: true, data }));
                } catch (error) {
                    console.error('RPC error:', error);
                    res.writeHead(200, { 'Content-Type': 'application/json' });
                    res.end(JSON.stringify({ ok: false, error: error && error.message }));
                }
                return;
            }
        }
        // 配音 API：Edge TTS（GET/POST /api/tts，GET /api/tts/voices）
        if (req.method === 'GET' && pathname === '/api/tts/voices') {
            try {
                const voices = await getTTSVoices();
                const locale = new URL(req.url, 'http://localhost').searchParams.get('locale');
                const list = locale ? voices.filter(v => v.Locale === locale) : voices;
                res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
                res.end(JSON.stringify({ ok: true, data: list }));
            } catch (error) {
                console.error('TTS voices error:', error);
                res.writeHead(500, { 'Content-Type': 'application/json; charset=utf-8' });
                res.end(JSON.stringify({ ok: false, error: (error && error.message) || '获取音色列表失败' }));
            }
            return;
        }

        if (pathname === '/api/tts' || pathname === '/api/tts/') {
            if (req.method !== 'GET' && req.method !== 'POST') {
                res.writeHead(405, { 'Content-Type': 'application/json; charset=utf-8' });
                res.end(JSON.stringify({ ok: false, error: 'Method Not Allowed' }));
                return;
            }
            try {
                const query = Object.fromEntries(new URL(req.url, 'http://localhost').searchParams);
                const params = { ...query };
                if (bodyBuffer) {
                    const bodyStr = bodyBuffer.toString('utf8');
                    const ct = (req.headers['content-type'] || '').toLowerCase();
                    if (ct.includes('application/json')) {
                        Object.assign(params, JSON.parse(bodyStr));
                    } else if (ct.includes('application/x-www-form-urlencoded')) {
                        Object.assign(params, Object.fromEntries(new URLSearchParams(bodyStr)));
                    } else {
                        try { Object.assign(params, JSON.parse(bodyStr)); } catch { /* 忽略 */ }
                    }
                }

                const text = typeof params.text === 'string' ? params.text.trim() : '';
                if (!text) {
                    res.writeHead(400, { 'Content-Type': 'application/json; charset=utf-8' });
                    res.end(JSON.stringify({ ok: false, error: '缺少 text 参数（要合成的文本）' }));
                    return;
                }
                if (text.length > TTS_MAX_TEXT_LENGTH) {
                    res.writeHead(400, { 'Content-Type': 'application/json; charset=utf-8' });
                    res.end(JSON.stringify({ ok: false, error: 'text 过长，最多 ' + TTS_MAX_TEXT_LENGTH + ' 字' }));
                    return;
                }

                const voice = typeof params.voice === 'string' && params.voice ? params.voice : TTS_DEFAULT_VOICE;
                const rate = typeof params.rate === 'string' && params.rate ? params.rate : '+0%';
                const pitch = typeof params.pitch === 'string' && params.pitch ? params.pitch : '+0Hz';
                const volume = typeof params.volume === 'string' && params.volume ? params.volume : '+0%';

                // 防 SSML 注入：voice 用白名单格式，其余参数禁止出现 XML 特殊字符
                if (!/^[A-Za-z0-9-]{1,64}$/.test(voice)) {
                    res.writeHead(400, { 'Content-Type': 'application/json; charset=utf-8' });
                    res.end(JSON.stringify({ ok: false, error: 'voice 参数不合法（应为 Edge 音色 ShortName，如 zh-CN-XiaoxiaoNeural）' }));
                    return;
                }
                for (const [k, v] of [['rate', rate], ['pitch', pitch], ['volume', volume]]) {
                    if (v.length > 64 || /[<>&"']/.test(v)) {
                        res.writeHead(400, { 'Content-Type': 'application/json; charset=utf-8' });
                        res.end(JSON.stringify({ ok: false, error: k + ' 参数不合法' }));
                        return;
                    }
                }

                const audio = await synthesizeTTS(text, { voice, rate, pitch, volume });
                if (!audio || !audio.length) {
                    res.writeHead(500, { 'Content-Type': 'application/json; charset=utf-8' });
                    res.end(JSON.stringify({ ok: false, error: 'TTS 合成结果为空' }));
                    return;
                }
                res.writeHead(200, {
                    'Content-Type': 'audio/mpeg',
                    'Content-Length': audio.length,
                    'Cache-Control': 'no-cache',
                    'Content-Disposition': 'inline; filename="tts.mp3"',
                });
                res.end(audio);
            } catch (error) {
                console.error('TTS error:', error);
                if (!res.headersSent) {
                    res.writeHead(500, { 'Content-Type': 'application/json; charset=utf-8' });
                    res.end(JSON.stringify({ ok: false, error: (error && error.message) || 'TTS 合成失败' }));
                }
            }
            return;
        }
        // AI 剧本生成：流式/一次性（Node 直连 LLM）
        if (pathname === '/api/ai/script' && (req.method === 'GET' || req.method === 'POST')) {
            await handleAiScript(req, res, bodyBuffer);
            return;
        }
        // 静态资源（图片/音频/模板等）由 Node 直接发，不占 PHP
        if ((req.method === 'GET' || req.method === 'HEAD') && await serveStatic(req, res, pathname)) {
            return;
        }
        // 其余（.php 动态逻辑等）交给 PHP.wasm
        const response = await requestHandler.request({
            method: req.method,
            url: req.url,
            headers: req.headers,
            body: bodyBuffer,
        });
        res.writeHead(response.httpStatusCode || 500, response.headers);
        res.end(Buffer.from(response.bytes));
    } catch (error) {
        console.error('Error handling request:', error);
        if (!res.headersSent) res.writeHead(500);
        res.end('Internal Server Error');
    }
});

// ---------- 4. WebSocket：实时通信（/ws） ----------
const wss = new WebSocketServer({ server, path: '/ws' });
const wsClients = new Set();

function broadcast(message) {
    const data = typeof message === 'string' ? message : JSON.stringify(message);
    for (const client of wsClients) {
        if (client.readyState === 1) client.send(data);
    }
}

wss.on('connection', (ws) => {
    wsClients.add(ws);
    console.log('WS client connected, total:', wsClients.size);

    ws.on('message', async (data) => {
        try {
            // 把消息 POST 给 PHP 动态逻辑处理，结果回给该客户端
            const payload = data.toString();
            const response = await requestHandler.request({
                method: 'POST',
                url: WS_PHP_ENDPOINT,
                headers: { 'content-type': 'application/json' },
                body: payload,
            });
            if (response.httpStatusCode === 404) {
                ws.send(JSON.stringify({ error: `PHP endpoint ${WS_PHP_ENDPOINT} not found` }));
            } else {
                ws.send(response.text);
            }
        } catch (error) {
            console.error('WS handler error:', error);
            ws.send(JSON.stringify({ error: error.message }));
        }
    });

    ws.on('close', () => {
        wsClients.delete(ws);
        console.log('WS client disconnected, total:', wsClients.size);
    });

    ws.on('error', (err) => console.error('WS error:', err.message));
});

// ---------- 4.5 PHP -> WebSocket 广播 ----------
// PHP 里调用 post_message_to_js("...") 时，Node 会在这里收到，并广播给所有 WS 客户端。
// （PHP 端该函数是阻塞的：会等这个监听器返回，返回值会作为 post_message_to_js() 的返回值回给 PHP）
php.onMessage(async (data) => {
    console.log('[PHP->JS]', data);
    broadcast(data);
    // 不返回内容，PHP 端收到 ""
});

// ---------- 5. 启动 ----------
server.listen(PORT, () => {
    console.log(`Server running at http://localhost:${PORT}`);
    console.log(`Serving files from: ${__dirname}`);
    console.log(`WebSocket endpoint: ws://localhost:${PORT}/ws  -> PHP: ${WS_PHP_ENDPOINT}`);
});

// 辅助函数：读取请求体
function getRequestBody(req) {
    return new Promise((resolve, reject) => {
        const chunks = [];
        req.on('data', (chunk) => chunks.push(chunk));
        req.on('end', () => resolve(Buffer.concat(chunks)));
        req.on('error', reject);
    });
}



