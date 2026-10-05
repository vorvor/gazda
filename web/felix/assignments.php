<?php require_once __DIR__ . '/assignments_auth.php'; ?>
<!doctype html>
<html lang="hu">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Forgatási nap beosztása · Felix</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<style>
:root{--bg:#F7F4EE;--surface:#fff;--ink:#1E2422;--muted:#58645E;--teal:#1B4B47;--line:#E3DED0;--accent:#EEF3EC}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:15px Inter,system-ui,sans-serif}main{max-width:1050px;margin:auto;padding:28px 22px 60px}nav{display:flex;justify-content:space-between;gap:15px;align-items:center;margin-bottom:32px}a{color:var(--teal);text-underline-offset:4px}.badge{font-size:12px;color:var(--teal);background:var(--accent);padding:7px 11px;border-radius:99px}h1,h2{font-family:'Space Grotesk',sans-serif;letter-spacing:-.04em}h1{font-size:clamp(28px,5vw,40px);margin:10px 0}h2{font-size:21px;margin:0 0 6px}.eyebrow{font-size:12px;font-weight:600;letter-spacing:.12em;color:var(--teal)}.intro{color:var(--muted);line-height:1.7;max-width:750px}.day{display:flex;gap:18px;align-items:center;background:var(--teal);color:#fff;padding:22px;border-radius:14px;margin:24px 0}.calendar{border:1px solid #ffffff60;border-radius:10px;text-align:center;padding:8px 15px;font-size:12px}.calendar strong{display:block;font:32px 'Space Grotesk',sans-serif}.day p{margin:6px 0 0;font-size:13px;opacity:.85}.grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}.panel{background:var(--surface);border:1px solid var(--line);border-radius:14px;padding:22px;min-width:0;box-shadow:0 6px 20px #12343008}.hint{font-size:13px;color:var(--muted);line-height:1.6;margin:6px 0 18px}label{display:block;font-size:13px;font-weight:600;margin-bottom:7px}input{width:100%;padding:13px;border-radius:8px;border:1px solid var(--line);background:var(--bg);color:var(--ink);font:inherit}input:focus,button:focus-visible,a:focus-visible{outline:3px solid #D98F3B;outline-offset:3px}.person{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 0;border-bottom:1px solid var(--line)}.person:last-child{border:0}.person strong{display:block;font-size:14px}.person small{display:block;margin-top:5px;color:var(--muted);font-size:12px}button{border:1px solid var(--teal);border-radius:8px;background:var(--teal);color:white;padding:10px 12px;font:600 12px Inter,system-ui;cursor:pointer;white-space:nowrap}button.remove{color:var(--teal);background:transparent}button:disabled{opacity:.5;cursor:default}#status{min-height:24px;margin:12px 0;font-size:14px;color:var(--teal)}#status.error{color:#A02C23}.empty{padding:22px 0;color:var(--muted);line-height:1.6;font-size:14px}.note{border-left:3px solid #D98F3B;padding-left:14px;font-size:13px;color:var(--muted);line-height:1.7;margin-top:24px}@media(max-width:700px){main{padding:20px 16px}.grid{grid-template-columns:1fr}.panel{padding:18px}nav{font-size:13px;margin-bottom:26px}.day{padding:18px}}@media(prefers-color-scheme:dark){:root{--bg:#12120F;--surface:#1B1D1B;--ink:#EFEEE8;--muted:#B7BDB8;--teal:#6FBBA8;--line:#353A34;--accent:#263A32}.day{background:#1B4B47}button{color:#12120F}}
</style>
</head>
<body><main>
<nav><a id="back-to-roster" href="index.php">← Vissza a névsorhoz</a><span class="badge">Adminisztrátor · <?= htmlspecialchars($authenticatedUser, ENT_QUOTES, 'UTF-8') ?></span></nav>
<div class="eyebrow">FELIX / FORGATÁSSZERVEZÉS</div>
<h1>Forgatási nap beosztása</h1>
<p class="intro">Keressen a személyek között, és adja hozzá őket a forgatáshoz. A beosztás automatikusan mentődik, és újratöltés után is megmarad.</p>
<label for="filming-date">Forgatási nap dátuma</label>
<input id="filming-date" type="date" min="1000-01-01" max="9999-12-31" required disabled>
<section class="day" aria-label="Kiválasztott forgatási nap"><div class="calendar" id="day-calendar"></div><div><strong id="day-date">Dátum betöltése…</strong><p id="day-title"></p></div></section>
<p id="status" role="status" aria-live="polite">Betöltés…</p>
<div class="grid">
<section class="panel" aria-labelledby="pool-title"><h2 id="pool-title">Személyek</h2><p class="hint">Közös személynyilvántartás az összes névsorból.</p><label for="person-search">Keresés név vagy település alapján</label><input id="person-search" type="search" maxlength="120" placeholder="Pl. Almavirág vagy Budapest" autocomplete="off"><div id="people" aria-live="polite"></div></section>
<section class="panel" aria-labelledby="assigned-title"><h2 id="assigned-title">Aznapra beosztva <span id="count" class="badge">0 fő</span></h2><p class="hint">Egy személy csak egyszer szerepelhet ezen a napon.</p><div id="assigned" aria-live="polite"></div></section>
</div>
<p class="note"><strong>CSV-alapú személynyilvántartás.</strong> Az azonos nevű személyeket külön azonosító különbözteti meg. Az első személy beosztásakor a naphoz CSV-névsor készül, amely a névsor dátumfülei között választható. A beosztás módosítása megőrzi a névsorban maradó személyek Check in / Check out állapotát, jelöléseit és megjegyzéseit. Lezárt nap beosztása csak újranyitás után módosítható.</p>
</main>
<script>
$(function(){
  const csrf = <?= json_encode($csrfToken) ?>;
  let date = new URLSearchParams(location.search).get('date') || '';
  let requestNumber = 0, pending = false, loading = true, timer;
  function status(text, error){ $('#status').text(text).toggleClass('error', !!error); }
  function rememberDate(){
    const url = new URL(location.href);
    url.searchParams.set('date',date);
    history.replaceState(null,'',url);
    $('#back-to-roster').attr('href','index.php?date=' + encodeURIComponent(date));
  }
  function showDate(day){
    date = day.date;
    $('#filming-date').val(date);
    const parsed = new Date(date + 'T12:00:00');
    $('#day-date').text(parsed.toLocaleDateString('hu-HU',{year:'numeric',month:'long',day:'numeric',weekday:'long'}));
    $('#day-calendar').empty().append(document.createTextNode(parsed.toLocaleDateString('hu-HU',{month:'short'})), $('<strong>').text(parsed.getDate()), document.createTextNode(parsed.getFullYear()));
    $('#day-title').text(day.title);
    rememberDate();
  }
  function card(person, action){
    const row = $('<div>').addClass('person');
    $('<div>').append($('<strong>').text(person.name), $('<small>').text((person.city ? person.city + ' · ' : '') + 'ID: ' + person.id + (Number(person.fictional) ? ' · Fiktív személy' : ''))).appendTo(row);
    const button = $('<button>').attr('type','button').text(action === 'remove' ? 'Eltávolítás' : action === 'assigned' ? 'Beosztva ✓' : 'Hozzáadás');
    button.attr('aria-label', (action === 'remove' ? 'Eltávolítás: ' : 'Hozzáadás: ') + person.name);
    if(action === 'remove') button.addClass('remove');
    button.prop('disabled', pending || action === 'assigned');
    button.on('click', function(){ change(person.id, action); });
    return row.append(button);
  }
  function load(message){
    const number = ++requestNumber;
    const params = {q:$('#person-search').val()};
    if(date) params.date = date;
    loading = true;
    $('#people button, #assigned button').prop('disabled',true);
    $('#filming-date').prop('disabled',pending);
    status('Beosztás betöltése…');
    return $.getJSON('assignments_api.php', params).done(function(data){
      if(number !== requestNumber) return;
      showDate(data.day);
      const assigned = new Set(data.assigned.map(p => p.id));
      $('#people').empty(); $('#assigned').empty();
      data.people.forEach(p => $('#people').append(card(p, assigned.has(p.id) ? 'assigned' : 'assign')));
      data.assigned.forEach(p => $('#assigned').append(card(p,'remove')));
      if(!data.people.length) $('#people').append($('<p>').addClass('empty').text('Nincs találat. Próbáljon másik nevet vagy települést.'));
      if(!data.assigned.length) $('#assigned').append($('<p>').addClass('empty').text('Még nincs beosztott személy. A Hozzáadás gombbal kezdheti a tervezést.'));
      $('#count').text(data.assigned.length + ' fő');
      status(message || 'A beosztás naprakész.');
    }).fail(function(xhr){
      if(number !== requestNumber) return;
      $('#people, #assigned').empty();
      $('#count').text('—');
      status(xhr.responseJSON?.error || 'A betöltés sikertelen. Válasszon dátumot, vagy töltse újra az oldalt.', true);
    }).always(function(){
      if(number === requestNumber){ loading=false; $('#filming-date').prop('disabled',pending); }
    });
  }
  function change(id,action){
    if(pending || loading) return;
    const saveDate = date;
    ++requestNumber;
    clearTimeout(timer);
    pending = true; $('button').prop('disabled',true); $('#filming-date, #person-search').prop('disabled',true); status('Mentés…');
    $.ajax({url:'assignments_api.php',method:'POST',dataType:'json',data:{csrf:csrf,date:saveDate,person_id:id,action:action}})
      .done(function(){ pending=false; load(action === 'remove' ? 'A személyt eltávolítottuk a beosztásból.' : 'A személy beosztását elmentettük.'); })
      .fail(function(xhr){ pending=false; load().always(function(){status(xhr.responseJSON?.error || 'A mentés sikertelen. Próbálja újra.',true);}); })
      .always(function(){ $('#person-search').prop('disabled',false); });
  }
  $('#filming-date').on('change',function(){
    if(pending) return;
    ++requestNumber;
    clearTimeout(timer);
    $('#people, #assigned, #day-calendar, #day-title').empty();
    $('#count').text('—');
    $('#day-date').text('Dátum betöltése…');
    if(!this.value || !this.checkValidity()){
      loading=true; $('#person-search').prop('disabled',true); status('Válasszon érvényes dátumot.',true); return;
    }
    $('#person-search').prop('disabled',false);
    date=this.value;
    rememberDate();
    load();
  });
  $('#person-search').on('input',function(){clearTimeout(timer);timer=setTimeout(function(){load();},180);});
  load();
});
</script></body></html>
