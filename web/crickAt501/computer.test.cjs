const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
function game(){
 const elements=new Map(),timers=new Map();let next=0;
 const element=id=>{if(!elements.has(id))elements.set(id,{dataset:{},style:{setProperty(){}},classList:{toggle(){}},checked:false});return elements.get(id)};
 const context=vm.createContext({confirm:()=>true,document:{getElementById:element,querySelectorAll:()=>[]},setTimeout:fn=>{timers.set(++next,fn);return next},clearTimeout:id=>timers.delete(id)});
 vm.runInContext(fs.readFileSync(__dirname+'/index.html','utf8').match(/<script>([\s\S]*?)<\/script>/)[1],context);
 return {run:code=>vm.runInContext(code,context),element,timers,tick(){const [id,fn]=timers.entries().next().value;timers.delete(id);fn()}};
}
test('round history records turns, busts, undo and reset',()=>{
 const g=game();assert.equal(g.run('S.round'),1);
 g.run('dart(20);dart(20);dart(20)');
 assert.equal(g.run('S.log[0].score'),60);assert.equal(g.run('S.log[0].left'),441);
 assert.equal(g.run('S.log[0].round'),1);assert.equal(g.run('S.round'),1);
 g.element('end').onclick();assert.equal(g.run('S.round'),2);
 assert.equal(g.run('S.log[1].score'),0);
 g.element('undo').onclick();assert.equal(g.run('S.round'),1);assert.equal(g.run('S.log.length'),1);
 g.run('S.p[1].left=30;dart(20);dart(20)');
 assert.equal(g.run('S.log[1].score'),0);assert.equal(g.run('S.log[1].left'),30);
 assert.equal(g.run('S.log[1].bust'),true);
 assert.match(g.element('history').innerHTML,/Bust/);
 g.element('foot').onclick({target:{id:'new',dataset:{}}});
 assert.equal(g.run('S.round'),1);assert.equal(g.run('S.log.length'),0);
});
test('history includes Cricket points, winning turns and computer turns',()=>{
 const g=game();g.run('S.p[0].m[20]=3;dart(20)');g.element('end').onclick();
 assert.equal(g.run('S.log[0].cricket'),20);
 g.run('S.p[1].left=40;N.forEach(n=>S.p[1].m[n]=3);S.mult=2;dart(20)');
 assert.equal(g.run('S.log[1].left'),0);assert.equal(g.run('S.log[1].score'),40);
 g.element('computer').checked=true;g.element('computer').onchange();
 g.run('computerDart=()=>({n:20,m:1})');g.element('end').onclick();g.tick();g.tick();g.tick();
 assert.equal(g.run('S.log[1].player'),1);assert.equal(g.run('S.log[1].score'),60);
 assert.match(g.element('history').innerHTML,/Computer/);
});
test('lower number buttons run from 14 down to 1',()=>{
 const g=game();
 const numbers=()=>Array.from(g.element('oth').innerHTML.matchAll(/data-n="(\d+)"/g),match=>Number(match[1]));
 assert.deepEqual(numbers(),[14,13,12,11,10,9,8,7,6,5,4,3,2,1]);
 g.element('oth').onclick({target:{dataset:{n:'14'}}});
 assert.equal(g.run('S.p[0].left'),487);
 assert.deepEqual(numbers(),[14,13,12,11,10,9,8,7,6,5,4,3,2,1]);
});
test('cancelled restart and settings changes preserve the entire active game',()=>{
 const g=game();g.run('dart(20);let confirmations=0;confirm=()=>{confirmations++;return false}');
 const before=g.run('JSON.stringify({S,H,count,computer})');
 g.element('foot').onclick({target:{id:'new',dataset:{}}});
 assert.equal(g.run('JSON.stringify({S,H,count,computer})'),before);
 g.element('foot').onclick({target:{dataset:{k:'3'}}});
 assert.equal(g.run('JSON.stringify({S,H,count,computer})'),before);
 g.element('computer').checked=true;g.element('computer').onchange();
 assert.equal(g.run('JSON.stringify({S,H,count,computer})'),before);
 assert.equal(g.element('computer').checked,false);
 assert.equal(g.run('confirmations'),3);
 g.element('foot').onclick({target:{dataset:{k:'2'}}});
 assert.equal(g.run('confirmations'),3,'Current player count is a no-op');
 assert.equal(g.run('JSON.stringify({S,H,count,computer})'),before);
 g.run('confirm=()=>true');g.element('foot').onclick({target:{dataset:{k:'3'}}});
 assert.equal(g.run('S.p.length'),3);assert.equal(g.run('H.length'),0);
 assert.equal(g.element('settings').open,false);
});
test('cancel during a computer turn preserves its pending dart',()=>{
 const g=game();g.element('computer').checked=true;g.element('computer').onchange();
 g.element('end').onclick();g.run('confirm=()=>false');
 g.element('foot').onclick({target:{id:'new',dataset:{}}});
 assert.equal(g.run('S.cur'),1);assert.equal(g.timers.size,1);
});
test('winning celebrates once, and undo or new game clears the celebration',()=>{
 const g=game();
 assert.equal(g.element('victory').hidden,true);
 g.run('S.p[0].left=40;N.forEach(n=>S.p[0].m[n]=3);S.mult=2;dart(20)');
 assert.equal(g.element('victory').hidden,false);
 assert.equal(g.element('winner-name').textContent,'Player 1');
 assert.match(g.element('fireworks').innerHTML,/spark/);
 const fireworks=g.element('fireworks').innerHTML;
 g.run('render()');assert.equal(g.element('fireworks').innerHTML,fireworks);
 g.element('undo').onclick();
 assert.equal(g.element('victory').hidden,true);
 assert.equal(g.element('fireworks').innerHTML,'');
 g.run('S.mult=2;dart(20)');assert.equal(g.element('victory').hidden,false);
 g.element('play-again').onclick();
 assert.equal(g.element('victory').hidden,true);
 assert.equal(g.element('fireworks').innerHTML,'');
 assert.equal(g.run('S.p[0].left'),501);
});
test('finishing only one discipline does not trigger fireworks',()=>{
 const g=game();g.run('S.p[0].left=40;S.mult=2;dart(20)');
 assert.equal(g.element('victory').hidden,true);
 assert.equal(g.element('fireworks').innerHTML,'');
});
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
  assert.match(g.element('computer-result').textContent,/Computer \(medium\):/);
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
 assert.match(g.element('ban').textContent,/Computer \(medium\) wins/);
});
test('human-only mode stays manual and computer occupies last slot',()=>{
 const g=game();g.run('dart(20);dart(20);dart(20)');
 assert.equal(g.run('S.p[0].left'),441);assert.equal(g.run('S.cur'),1);assert.equal(g.timers.size,0);
 g.element('computer').checked=true;g.element('computer').onchange();
 g.element('foot').onclick({target:{dataset:{k:'5'}}});
 assert.equal(g.element('n4').value,'Computer (medium)');
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
 assert.ok(totals[0].hits>500&&totals[0].hits<800,'Basic should hit its target roughly 30% of the time');
 assert.ok(totals[1].hits>1000&&totals[1].hits<1250,'Medium should hit its target roughly 55% of the time');
 assert.ok(totals[0].marks<950,'Basic should mostly hit singles');
 assert.ok(totals[1].marks<1700,'Medium should have fewer multi-hits');
 assert.ok(totals[0].hits<totals[1].hits&&totals[1].hits<totals[2].hits);
 assert.ok(totals[0].marks<totals[1].marks&&totals[1].marks<totals[2].marks);
});
test('all levels vary intended Cricket targets and favour nearly closed fields',()=>{
 for(const level of ['basic','medium','pro']){
  const g=game();g.element('difficulty').value=level;g.element('difficulty').onchange();
  const hits={};
  for(let i=0;i<140;i++){
   g.run(`let rolls${i}=[${(i+.5)/140},.5,0];Math.random=()=>rolls${i}.shift()??0`);
   const n=g.run('computerDart().n');hits[n]=(hits[n]||0)+1;
  }
  assert.equal(Object.keys(hits).length,7,'Every open Cricket field can be targeted');
  g.run('S.p[0].m[20]=3;S.p[0].m[16]=2');
  const focused={};
  for(let i=0;i<140;i++){
   g.run(`let focus${i}=[${(i+.5)/140},.5,0];Math.random=()=>focus${i}.shift()??0`);
   const n=g.run('computerDart().n');focused[n]=(focused[n]||0)+1;
  }
  assert.equal(focused[20],undefined,'Do not deliberately target closed fields');
  assert.ok(focused[16]>focused[19],'Prefer completing a nearly closed field');
 }
});
test('off-target darts can land in neighbouring board segments',()=>{
 const g=game();
 g.run('let rolls=[0,.5,.99,.7,.1,0];Math.random=()=>rolls.shift()??0');
 assert.equal(g.run('computerDart().n'),5,'A shot aimed at 20 can slip into adjacent 5');
});
test('computer name includes the selected level in the scoreboard and turn display',()=>{
 for(const level of ['basic','medium','pro']){
  const g=game();g.element('difficulty').value=level;g.element('difficulty').onchange();
  g.element('computer').checked=true;g.element('computer').onchange();
  assert.equal(g.element('n1').value,`Computer (${level})`);
  assert.equal(g.element('n0').value,'Player 1');
  g.element('end').onclick();
  assert.ok(g.element('who').innerHTML.includes(`Computer (${level})`));
  g.element('difficulty').value='pro';g.element('difficulty').onchange();
  assert.equal(g.element('n1').value,'Computer (pro)');
 }
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
