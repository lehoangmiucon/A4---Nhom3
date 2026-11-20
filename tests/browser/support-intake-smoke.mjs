import {readFileSync, writeFileSync, mkdirSync, mkdtempSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {join, resolve} from 'node:path';
import {pathToFileURL} from 'node:url';
import {execFileSync} from 'node:child_process';
import assert from 'node:assert/strict';

const chrome = process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const temporary = mkdtempSync(join(tmpdir(), 'helpdesk-cr12-'));
const output = resolve('evidence/cr12-smoke');
mkdirSync(output, {recursive:true});
const asset = (path) => pathToFileURL(resolve(path)).href;
let markup = readFileSync('resources/views/customer/partials/support-conversation.blade.php', 'utf8');
markup = markup.replace(/<select id="conversation-room">[\s\S]*?<\/select>/, '<select id="conversation-room"><option value="">Chưa chọn phòng</option><option value="2">A203</option></select>')
    .replace(/<select id="conversation-category">[\s\S]*?<\/select>/, '<select id="conversation-category"><option value="">Chưa chọn danh mục</option><option value="1">Máy chiếu</option></select>');
const results = [];
for (const width of [1440, 390]) {
    for (const ready of [false, true]) {
        const fixture = `<!doctype html><html lang="vi"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="${asset('public/customer/assets/css/support-conversation.css')}">
<style>html{width:${width}px;max-width:100%}body{font:15px Arial;background:#eff6fc;color:#17324d;margin:16px}main{max-width:1120px;margin:auto}.report-card{background:white;border:1px solid #dbe8f4;border-radius:16px}.report-card-heading{padding:24px;display:flex;justify-content:space-between;gap:20px}h2{font-size:24px}.report-fields{display:flex;flex-wrap:wrap;gap:12px}.report-field{flex:1;min-width:140px}.report-field label{display:block;margin-bottom:6px}.report-field select{box-sizing:border-box;width:100%;padding:10px}#smoke-result{white-space:pre-wrap;overflow-wrap:anywhere}</style>
<main class="report-portal"><button id="report-mode-ai">Nhờ AI hỗ trợ</button><button id="report-mode-manual">Báo cáo thủ công</button><section id="report-manual-view"></section>${markup}</main><pre id="smoke-result">RUNNING</pre>
<script>
window.HELPDESK_CONFIG={currentUserId:1};const calls=[];const messages=[];const errors=[];
window.addEventListener('error',e=>errors.push(e.message));window.addEventListener('unhandledrejection',e=>errors.push(String(e.reason)));
window.fetch=async(url,options={})=>{
 if(url.includes('?page='))return {ok:true,status:200,json:async()=>({data:[],meta:{pagination:{current_page:1,last_page:1,total:0}}})};
 const body=JSON.parse(options.body);calls.push({url,body});
 const known=!!body.room_id&&!!body.category_id;
 messages.push({role:'user',content_text:body.content},{role:'assistant',content_text:known?'Hướng dẫn đúng phòng và công việc.':'Bạn đang ở phòng nào và cần hỗ trợ công việc gì?'});
 return {ok:true,status:calls.length===1?201:200,json:async()=>({data:{session:{id:5,status:'collecting',version:calls.length*2+1,room_id:known?2:null,room_code:known?'A203':'',category_id:known?1:null},messages:[...messages],category_label:known?'Máy chiếu':null,needs_context:!known,needs_description:!known,report:null}})};
};
</script>
<script src="${asset('public/shared/js/page-feedback.js')}"></script><script src="${asset('public/customer/assets/js/support-conversation.js')}"></script>
<script>
const get=id=>document.getElementById(id);const wait=ms=>new Promise(resolve=>setTimeout(resolve,ms));const check=(condition,message)=>{if(!condition)throw new Error(message);};
(async()=>{try{
 get('report-mode-ai').click();await wait(50);
 check(get('conversation-context-picker').open,'initial context is hidden');
 check(calls.length===0,'entering AI sent a message');
 get('conversation-input').value='Xin chào';get('conversation-form').requestSubmit();await wait(1500);
 check(get('conversation-context-picker').open,'missing context picker closed');
 check(get('conversation-room').value==='','room was invented');
 check(get('conversation-log').textContent.includes('phòng nào'),'missing clarification');
 check(!get('conversation-log').textContent.includes('Hướng dẫn đúng'),'premature guidance');
 if(${ready}){
  get('conversation-room').value='2';get('conversation-category').value='1';get('conversation-input').value='Máy chiếu không có hình';get('conversation-form').requestSubmit();await wait(2000);
  check(calls[1].url==='/api/support-sessions/5/messages','wrong session');
  check(calls[1].body.room_id===2&&calls[1].body.category_id===1,'selected context not sent');
  check(calls[1].body.content==='Máy chiếu không có hình','problem lost');
  check(!get('conversation-context-picker').open,'complete context picker not collapsed');
  check(get('conversation-log').textContent.includes('Hướng dẫn đúng phòng'),'guidance not displayed');
 }
 const main=document.querySelector('main');check(main.scrollWidth<=main.clientWidth,'horizontal overflow');
 check(get('report-ai-view').getBoundingClientRect().right<=${width},'content extends outside screenshot');check(errors.length===0,errors.join(';'));
 get('smoke-result').textContent='PASS: ${ready ? 'complete' : 'missing'} context; no premature guidance; selected room and work; no runtime errors; no horizontal overflow';
}catch(error){get('smoke-result').textContent='FAIL: '+error.message;}})();
</script></html>`;
        const label = `${width}-${ready ? 'ready' : 'missing'}`;
        const file = join(temporary, `${label}.html`); writeFileSync(file, fixture);
        const dom = execFileSync(chrome, ['--headless','--no-sandbox','--disable-gpu','--no-first-run','--no-default-browser-check','--allow-file-access-from-files',
            `--user-data-dir=${join(temporary, `profile-${label}`)}`,`--window-size=${width},1400`,'--virtual-time-budget=9000','--dump-dom',`--screenshot=${join(output, `${label}.png`)}`,pathToFileURL(file).href],
            {encoding:'utf8',timeout:30000,maxBuffer:2_000_000,windowsHide:true,stdio:['ignore','pipe','pipe']});
        const result = dom.match(/<pre id="smoke-result">([^<]*)<\/pre>/)?.[1];
        results.push({label,result,api:'fake',browser:'Chrome headless',localDataChanged:false});
        writeFileSync(join(output,'result.json'), JSON.stringify(results,null,2));
        assert.match(result || '',/^PASS:/); console.log(`${label}: ${result}`);
    }
}
