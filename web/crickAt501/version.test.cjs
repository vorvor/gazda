const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');

test('page displays a small version number in the top-right corner',()=>{
 const html=fs.readFileSync(__dirname+'/index.html','utf8');
 assert.match(html,/<small id="version" aria-label="Game version">v\d+\.\d+\.\d+<\/small>/);
 assert.match(html,/#version\{[^}]*text-align:right[^}]*font-size:10px/);
 assert.ok(html.indexOf('id="version"')<html.indexOf('<main>'));
});
