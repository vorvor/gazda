const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
test('number buttons have larger touch targets without widening the game',()=>{
 const html=fs.readFileSync(__dirname+'/index.html','utf8');
 assert.ok(/main\{max-width:420px/.test(html));
 assert.ok(/\.grid\{[^}]*repeat\(7,/.test(html));
 assert.ok(/\.grid button\{[^}]*min-height:56px/.test(html));
 assert.ok(/\.grid button\{[^}]*font-size:20px/.test(html));
 assert.ok(!/\.grid\.(hot|small) button\{[^}]*font-size:1[36]px/.test(html));
});
