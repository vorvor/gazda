const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
test('history occupies its own panel below game settings',()=>{
 const html=fs.readFileSync(__dirname+'/index.html','utf8');
 assert.ok(html.indexOf('<section class="history-panel"')>html.indexOf('</details>'));
 assert.ok(/\.score-detail\{[^}]*grid-template-columns:minmax\(0,1fr\);/.test(html));
 assert.equal((html.match(/id="history"/g)||[]).length,1);
});
