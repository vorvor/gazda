<?php // Shared read-only personal cards; QR generation stays local.
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/person_qr_vendor.php'; ?>
<style>
button.person-qr{display:inline-flex;vertical-align:middle;align-items:center;justify-content:center;width:44px;height:44px;min-width:44px;padding:2px;margin:0 0 0 8px;border:1px solid #d5dad7;border-radius:5px;background:#fff;color:#000;cursor:pointer;box-shadow:none;line-height:1;flex-shrink:0}
button.person-qr:hover{background:#fff;border-color:#1B4B47}
button.person-qr:focus-visible,.person-qr-close:focus-visible{outline:3px solid #D98F3B;outline-offset:3px}
.person-qr svg{display:block;width:38px;height:38px;shape-rendering:crispEdges}
.person-photo-placeholder{display:grid;place-content:center;width:110px;height:110px;margin:12px auto;border:2px dashed #aab7b0;border-radius:12px;background:#f2f5f3;color:#58645E;font-size:14px}
.person-card-data{text-align:left;overflow-wrap:anywhere}
.person-card-data dl{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.5fr);gap:8px;margin:12px 0 24px}
.person-card-data dt{font-weight:600}.person-card-data dd{margin:0;white-space:pre-wrap}
.person-card-data h3{border-top:1px solid #d5dad7;padding-top:16px}
#person-qr-dialog{box-sizing:border-box;width:min(520px,calc(100vw - 24px));max-width:calc(100vw - 24px);max-height:calc(100dvh - 24px);overflow:auto;margin:auto;padding:20px;border:0;border-radius:14px;background:#fff;color:#1E2422;text-align:center;font:16px Inter,system-ui,sans-serif;box-shadow:0 20px 80px #0005}
#person-qr-dialog::backdrop{background:#000a}
#person-qr-dialog .person-qr-close{display:block;margin:0 0 16px auto;padding:10px 16px;border:1px solid #58645E;border-radius:7px;background:#fff;color:#1E2422;cursor:pointer;font:inherit}
#person-qr-dialog .person-qr-name{font-size:22px;margin:8px 0;overflow-wrap:anywhere}
#person-qr-dialog .person-qr-id{margin:8px 0;overflow-wrap:anywhere}
#person-qr-dialog .person-qr-large{width:100%;max-width:440px;margin:16px auto;background:white}
#person-qr-dialog .person-qr-large svg{display:block;width:100%;height:auto;shape-rendering:crispEdges}
#person-qr-dialog .person-qr-hint{font-size:14px;color:#58645E;margin:12px 0}
</style>
<dialog id="person-qr-dialog" aria-labelledby="person-qr-title" aria-describedby="person-qr-help">
  <button type="button" class="person-qr-close" autofocus>Bezárás ✕</button>
  <h2 id="person-qr-title" class="person-qr-name"></h2>
  <p class="person-qr-id"></p>
  <div class="person-photo-placeholder" role="img" aria-label="Fénykép helye – nincs feltöltött fotó">Fénykép helye<br>Nincs fotó</div>
  <div class="person-qr-large" role="img" aria-label="Személy QR-kódja"></div>
  <p id="person-qr-help" class="person-qr-hint">Olvasd be a telefon kamerájával. A kód a nevet és az egyedi azonosítót tartalmazza.</p>
  <p class="person-card-status" role="status" aria-live="polite"></p>
  <div class="person-card-data"></div>
</dialog>
<script>
window.FelixPersonQR = (function () {
  'use strict';
  const dialog = document.getElementById('person-qr-dialog');
  const cache = new Map();
  let opener;
  let requestNumber = 0;
  const status = dialog.querySelector('.person-card-status');
  const details = dialog.querySelector('.person-card-data');
  const labels = {id:'Azonosító',name:'Név',city:'Város',fictional:'Fiktív személy',allergy:'Allergia / étrend',cls:'Étrendjelölés',time:'Időpont',arrived:'Megérkezett',late:'Késik',cancelled:'Lemondva',reason:'Indok',notes:'Megjegyzés',checkedOut:'Check out',flag:'Jelölés',date:'Dátum',title:'Forgatás',assigned_by:'Beosztotta',created_at:'Beosztás mentése'};
  function section(title, values) {
    const heading = document.createElement('h3');
    heading.textContent = title;
    const list = document.createElement('dl');
    Object.entries(values).forEach(function ([key, value]) {
      const term = document.createElement('dt');
      const description = document.createElement('dd');
      term.textContent = labels[key] || key;
      if (['fictional','arrived','late','cancelled','checkedOut'].includes(key)) value = {'1':'Igen','0':'Nem'}[String(value)] || value;
      description.textContent = value === '' || value === null ? 'Nincs megadva' : String(value);
      list.append(term, description);
    });
    details.append(heading, list);
  }
  function graphic(id, name) {
    const payload = JSON.stringify({id: String(id), name: String(name)});
    if (!cache.has(payload)) {
      const code = qrcode(0, 'M');
      code.addData(payload, 'Byte');
      code.make();
      // Four white modules on every edge are necessary for reliable scanning.
      const count = code.getModuleCount();
      const size = count + 8;
      const parts = [];
      for (let y = 0; y < count; y++) {
        for (let x = 0; x < count; x++) {
          if (code.isDark(y, x)) parts.push('M' + (x + 4) + ' ' + (y + 4) + 'h1v1h-1z');
        }
      }
      cache.set(payload, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + size + ' ' + size + '" aria-hidden="true"><path fill="#fff" d="M0 0h' + size + 'v' + size + 'H0z"/><path fill="#000" d="' + parts.join('') + '"/></svg>');
      if (cache.size > 500) cache.delete(cache.keys().next().value);
    }
    return cache.get(payload);
  }
  dialog.querySelector('.person-qr-close').addEventListener('click', function () { dialog.close(); });
  dialog.addEventListener('click', function (event) {
    const box = dialog.getBoundingClientRect();
    if (event.target === dialog && (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom)) dialog.close();
  });
  dialog.addEventListener('close', function () { ++requestNumber; if (opener && opener.isConnected) opener.focus(); });
  return {
    button: function (person, source) {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'person-qr person-card';
      button.setAttribute('aria-label', 'Személyes kártya: ' + person.name + ' (ID: ' + person.id + ')');
      button.setAttribute('aria-haspopup', 'dialog');
      button.title = 'Személyes kártya és QR-kód';
      button.innerHTML = '<svg viewBox="0 0 32 32" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="4" y="5" width="24" height="22" rx="3"/><circle cx="12" cy="13" r="3"/><path d="M7 23v-2a5 5 0 0 1 10 0v2M20 12h5M20 17h5M20 22h5"/></svg>';
      button.addEventListener('click', function (event) {
        event.stopPropagation();
        opener = button;
        dialog.querySelector('.person-qr-name').textContent = person.name;
        dialog.querySelector('.person-qr-id').textContent = 'ID: ' + person.id;
        dialog.querySelector('.person-qr-large').innerHTML = graphic(person.id, person.name);
        details.replaceChildren();
        status.textContent = 'Mentett személyadatok betöltése…';
        const request = ++requestNumber;
        const params = new URLSearchParams({id:String(person.id)});
        if (source) { params.set('file', source.file || ''); params.set('name', String(person.name)); }
        dialog.showModal();
        fetch('person_card.php?' + params, {cache:'no-store', credentials:'same-origin'})
          .then(async function (response) {
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'Nem sikerült betölteni a személyadatokat.');
            return data;
          }).then(function (data) {
            if (request !== requestNumber || !dialog.open) return;
            section('Nyilvántartási adatok', data.person);
            data.rosters.forEach(function (entry) { section('Mentett névsor · ' + entry.file, entry.row); });
            data.assignments.forEach(function (entry) { section('Mentett beosztás · ' + entry.date, entry); });
            status.textContent = 'Mentett adatok; a napi értékek dátumonként külön szerepelnek.' + (source && source.audit ? ' A fejléc és a QR a naplóban rögzített azonosságot mutatja.' : '') + (!data.rosters.length ? ' Nincs kapcsolt, elérhető napi névsoradat.' : '');
          }).catch(function (error) {
            if (request === requestNumber && dialog.open) status.textContent = error.message || 'Nem sikerült betölteni. Zárd be és próbáld újra.';
          });
      });
      return button;
    }
  };
}());
</script>
