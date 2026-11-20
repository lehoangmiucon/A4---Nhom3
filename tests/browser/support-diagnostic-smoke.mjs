import {readFileSync, writeFileSync, mkdirSync, mkdtempSync} from 'node:fs';
import {tmpdir} from 'node:os';
import {join, resolve} from 'node:path';
import {pathToFileURL} from 'node:url';
import {execFileSync} from 'node:child_process';
import assert from 'node:assert/strict';

const chrome = process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe';
const temporary = mkdtempSync(join(tmpdir(), 'helpdesk-cr13-'));
const output = resolve('evidence/cr13-smoke'); mkdirSync(output, {recursive:true});
const service = JSON.parse(execFileSync('php', ['tests/browser/diagnostic-fixture.php'], {encoding:'utf8', windowsHide:true, timeout:30000}));
assert.equal(service.database, 'sqlite::memory:');
const asset = (path) => pathToFileURL(resolve(path)).href;
let markup = readFileSync('resources/views/customer/partials/support-conversation.blade.php', 'utf8');
markup = markup.replace(/<select id="conversation-room">[\s\S]*?<\/select>/, `<select id="conversation-room"><option value="">Chưa chọn phòng</option><option value="${service.context.room_id}">A201</option></select>`)
    .replace(/<select id="conversation-category">[\s\S]*?<\/select>/, `<select id="conversation-category"><option value="">Chưa chọn danh mục</option><option value="${service.context.category_id}">Máy tính</option></select>`);
const results = [];
for (const width of [1440, 390]) {
    const fixture = `<!doctype html><html lang="vi"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="${asset('public/customer/assets/css/support-conversation.css')}">
<style>html{width:${width}px;max-width:100%}body{font:15px Arial;background:#eff6fc;color:#17324d;margin:16px}main{max-width:1120px;margin:auto}.report-card{background:white;border:1px solid #dbe8f4;border-radius:16px}.report-card-heading{padding:24px;display:flex;justify-content:space-between;gap:20px}h2{font-size:24px}.report-fields{display:flex;flex-wrap:wrap;gap:12px}.report-field{flex:1;min-width:140px}.report-field label{display:block;margin-bottom:6px}.report-field select{box-sizing:border-box;width:100%;padding:10px}#smoke-result{white-space:pre-wrap;overflow-wrap:anywhere}</style>
<main class="report-portal"><button id="report-mode-ai">Nhờ AI hỗ trợ</button><button id="report-mode-manual">Báo cáo thủ công</button><section id="report-manual-view"></section>${markup}</main><pre id="smoke-result">RUNNING</pre>
<script>
window.HELPDESK_CONFIG={currentUserId:1};window.matchMedia=()=>({matches:true});
const fixtureData=${JSON.stringify(service).replaceAll('<','\\u003c')};const calls=[];const errors=[];
window.addEventListener('error',e=>errors.push(e.message));window.addEventListener('unhandledrejection',e=>errors.push(String(e.reason)));
window.fetch=async(url,options={})=>{
 if(url.includes('?page='))return {ok:true,status:200,json:async()=>({data:[],meta:{pagination:{current_page:1,last_page:1,total:0}}})};
 const body=JSON.parse(options.body);calls.push({url,body});const response=fixtureData.responses[calls.length-1];
 if(!response)throw new Error('Unexpected extra request');
 return {ok:true,status:response.status,json:async()=>({data:response.data})};
};
</script><script src="${asset('public/shared/js/page-feedback.js')}"></script><script src="${asset('public/customer/assets/js/support-conversation.js')}"></script>
<script>
const get=id=>document.getElementById(id);const wait=ms=>new Promise(resolve=>setTimeout(resolve,ms));const check=(condition,message)=>{if(!condition)throw new Error(message);};
const send=async text=>{get('conversation-input').value=text;get('conversation-form').requestSubmit();await wait(50);};
(async()=>{try{
 get('report-mode-ai').click();await wait(50);get('conversation-room').value=String(fixtureData.context.room_id);get('conversation-category').value=String(fixtureData.context.category_id);
 await send('Máy tính không mở lên');check(get('conversation-log').lastElementChild.textContent.includes('Nguồn điện'),'missing initial observation question');
 await send('nguồn điện bình thường');const reply=get('conversation-log').lastElementChild.textContent;
 check(reply.includes('đèn báo'),'did not advance to indicator');check(!reply.includes('kiểm tra lại nguồn điện'),'repeated model advice');
 await send('hãy báo cáo lên KTV cho mình');check(calls.length===3,'extra write request');
 check(!get('conversation-report').hidden,'report receipt hidden');check(get('conversation-report').textContent.includes('Đã gửi báo cáo'),'no actual report receipt');
 check(get('conversation-send').disabled,'handed-off conversation still writable');
 check(fixtureData.responses[2].data.report.status==='new','report was not created by backend');
 check(document.querySelector('main').scrollWidth<=document.querySelector('main').clientWidth,'horizontal overflow');check(errors.length===0,errors.join(';'));
 get('smoke-result').textContent='PASS: observation question; power result preserved; advanced to indicator; natural IT handoff; actual backend receipt; no runtime errors';
}catch(error){get('smoke-result').textContent='FAIL: '+error.message;}})();
</script></html>`;
    const file = join(temporary, `${width}.html`); writeFileSync(file,fixture);
    const dom = execFileSync(chrome, ['--headless','--no-sandbox','--disable-gpu','--no-first-run','--no-default-browser-check','--allow-file-access-from-files',
        `--user-data-dir=${join(temporary,`profile-${width}`)}`,`--window-size=${width},1600`,'--virtual-time-budget=9000','--dump-dom',`--screenshot=${join(output,`${width}.png`)}`,pathToFileURL(file).href],
        {encoding:'utf8',timeout:30000,maxBuffer:2_000_000,windowsHide:true,stdio:['ignore','pipe','pipe']});
    const result=dom.match(/<pre id="smoke-result">([^<]*)<\/pre>/)?.[1];
    results.push({layoutWidth:width,result,backend:service.database,provider:service.provider,transport:'fixture fetch using actual service responses',localDataChanged:false});
    writeFileSync(join(output,'result.json'),JSON.stringify(results,null,2));assert.match(result||'',/^PASS:/);console.log(`${width}: ${result}`);
}
