const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
function game(){
 const elements=new Map(),timers=new Map();let next=0;
 const element=id=>{if(!elements.has(id))elements.set(id,{dataset:{},style:{setProperty(){}},classList:{toggle(){}},checked:false});return elements.get(id)};
 const context=vm.createContext({document:{getElementById:element,querySelectorAll:()=>[]},setTimeout:fn=>{timers.set(++next,fn);return next},clearTimeout:id=>timers.delete(id)});
 vm.runInContext(fs.readFileSync(__dirname+'/index.html','utf8').match(/<script>([\s\S]*?)<\/script>/)[1],context);
 return {run:code=>vm.runInContext(code,context),element,timers,tick(){const [id,fn]=timers.entries().next().value;timers.delete(id);fn()}};
}
test('computer automatically throws and uses the same Cricket and 501 scoring',()=>{
 const g=game();
 assert.equal(typeof g.element('computer').onchange,'function');
 g.element('computer').checked=true;g.element('computer').onchange();
 g.run('Math.random=()=>0.5');
 g.element('end').onclick();
 assert.equal(g.timers.size,1);
 g.tick();
 assert.ok(g.run('S.p[1].left<501'));
 assert.ok(g.run('N.some(n=>S.p[1].m[n]>0)'));
 g.tick();g.tick();
 assert.equal(g.run('S.cur'),0);
 assert.equal(g.timers.size,0);
});
test('all Cricket targets including bull update marks and deduct points',()=>{
 for(const n of [15,16,17,18,19,20,25]){
  const g=game();g.element('computer').checked=true;g.element('computer').onchange();
  g.run(`computerDart=()=>({n:${n},m:2})`);
  g.element('end').onclick();g.tick();
  const m=g.run(`S.p[1].m[${n}]`);
  assert.ok(m>=1&&m<=(n===25?2:3));
  assert.equal(g.run('S.p[1].left'),501-n*m);
  assert.match(g.element('computer-result').textContent,/Computer:/);
 }
});
test('undo and new game cancel pending computer turns',()=>{
 const g=game();g.element('computer').checked=true;g.element('computer').onchange();
 g.element('end').onclick();g.tick();g.element('undo').onclick();
 assert.equal(g.run('S.cur'),0);assert.equal(g.run('S.p[1].left'),501);assert.equal(g.timers.size,0);
 g.element('end').onclick();g.element('foot').onclick({target:{id:'new',dataset:{}}});
 assert.equal(g.timers.size,0);assert.equal(g.run('S.cur'),0);
});
test('computer respects busts and double-out wins',()=>{
 const g=game();g.element('computer').checked=true;g.element('computer').onchange();
 g.run('computerDart=()=>({n:20,m:3});S.p[1].left=10');g.element('end').onclick();g.tick();
 assert.equal(g.run('S.p[1].left'),10);assert.equal(g.run('S.cur'),0);assert.equal(g.timers.size,0);
 g.run('S.p[1].left=50;N.forEach(n=>S.p[1].m[n]=3);computerDart=()=>({n:25,m:2})');
 g.element('end').onclick();g.tick();
 assert.equal(g.run('S.win'),1);assert.equal(g.timers.size,0);
 assert.match(g.element('ban').textContent,/Computer wins/);
});
test('human-only mode stays manual and computer occupies last slot',()=>{
 const g=game();g.run('dart(20);dart(20);dart(20)');
 assert.equal(g.run('S.p[0].left'),441);assert.equal(g.run('S.cur'),1);assert.equal(g.timers.size,0);
 g.element('computer').checked=true;g.element('computer').onchange();
 g.element('foot').onclick({target:{dataset:{k:'5'}}});
 assert.equal(g.element('n4').value,'Computer');
 for(let i=0;i<4;i++)g.element('end').onclick();
 assert.equal(g.run('S.cur'),4);assert.equal(g.timers.size,1);
 g.element('computer').checked=false;g.element('computer').onchange();
 assert.equal(g.timers.size,0);assert.equal(g.element('n4').value,'Player 5');
});
test('levels increasingly hit and close the remaining Cricket field',()=>{
 const totals=[];
 for(const level of ['basic','medium','pro']){
  const g=game();assert.equal(typeof g.element('difficulty').onchange,'function');
  g.element('difficulty').value=level;g.element('difficulty').onchange();
  g.run('S.cur=1;N.forEach(n=>S.p[1].m[n]=3);S.p[1].m[18]=0;let seed=42;Math.random=()=>((seed=(1664525*seed+1013904223)>>>0)/4294967296)');
  totals.push(g.run('(()=>{let hits=0,marks=0;for(let i=0;i<2000;i++){const d=computerDart();if(d.n===18){hits++;marks+=d.m}if(d.n===25&&d.m===3)throw Error("Triple bull")}return {hits,marks}})()'));
 }
 assert.ok(totals[0].hits>1000);
 assert.ok(totals[0].hits<totals[1].hits&&totals[1].hits<totals[2].hits);
 assert.ok(totals[0].marks<totals[1].marks&&totals[1].marks<totals[2].marks);
});
test('after Cricket all levels aim at double-out and safe setup shots',()=>{
 for(const level of ['basic','medium','pro']){
  const g=game();g.element('difficulty').value=level;g.element('difficulty').onchange();
  g.run('S.cur=1;N.forEach(n=>S.p[1].m[n]=3);Math.random=()=>0');
  for(const left of [2,16,40,50]){
   g.run(`S.p[1].left=${left}`);
   assert.equal(g.run('computerDart().n*computerDart().m'),left);
   assert.equal(g.run('computerDart().m'),2);
  }
  g.run('S.p[1].left=3');assert.equal(g.run('computerDart().n*computerDart().m'),1);
 }
});
