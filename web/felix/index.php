<?php require_once __DIR__ . '/auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Check-in Roster</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<style>
  :root {
    --bg: #F7F4EE;
    --surface: #FFFFFF;
    --ink: #1E2422;
    --ink-soft: #4A524E;
    --muted: #8A9490;
    --teal: #1B4B47;
    --teal-deep: #123430;
    --amber: #D98F3B;
    --line: #E3DED0;
    --line-strong: #CFC8B5;
    --focus: #1B4B47;

    --pill-peanut-bg: #F4E2D8;   --pill-peanut-fg: #A44A26;
    --pill-shellfish-bg: #DCEAEE; --pill-shellfish-fg: #2E6478;
    --pill-dairy-bg: #EFE3D2;    --pill-dairy-fg: #8A5A22;
    --pill-pollen-bg: #E3ECD9;   --pill-pollen-fg: #4C6B34;
    --pill-bee-bg: #F3DCDB;      --pill-bee-fg: #A03B34;
    --pill-peni-bg: #E7E0EF;     --pill-peni-fg: #6A4F8C;
    --pill-none-bg: #EAEAE6;     --pill-none-fg: #757C78;
    --pill-gluten-bg: #F1E6B9;   --pill-gluten-fg: #8A6B1E;
    --pill-vegan-bg: #D9EBDD;    --pill-vegan-fg: #357A4C;
    --pill-nopork-bg: #EDE0EA;   --pill-nopork-fg: #7A4A74;
    --pill-diabetes-bg: #DCE3EF; --pill-diabetes-fg: #33517A;

    --selected-border: var(--teal);
    --selected-bg: rgba(27, 75, 71, 0.06);
    --arrived: #3C7A47;
    --arrived-bg: rgba(60, 122, 71, 0.12);
    --late: #B5502E;
    --late-bg: rgba(181, 80, 46, 0.10);
    --cancelled: #8B1E1E;
    --cancelled-bg: rgba(139, 30, 30, 0.10);
    --checkedout: #2B5FA8;
    --checkedout-deep: #1E4472;
    --checkedout-bg: rgba(43, 95, 168, 0.10);

    --shadow: 0 1px 2px rgba(18, 52, 48, 0.06), 0 6px 20px rgba(18, 52, 48, 0.05);
    --radius-s: 6px;
    --radius-m: 12px;

    box-sizing: border-box;
    padding-top: env(safe-area-inset-top, 0px);
    padding-bottom: env(safe-area-inset-bottom, 0px);
  }

  @media (prefers-color-scheme: dark) {
    :root:not([data-theme="light"]) {
      --bg: #12120F;
      --surface: #1B1D1B;
      --ink: #EFEEE8;
      --ink-soft: #B7BDB8;
      --muted: #7C847F;
      --teal: #6FBBA8;
      --teal-deep: #9FD6C7;
      --amber: #E8A85B;
      --line: #2C2F2C;
      --line-strong: #3B3F3B;
      --focus: #6FBBA8;

      --pill-peanut-bg: #3A2A20; --pill-peanut-fg: #E3A17E;
      --pill-shellfish-bg: #1F3238; --pill-shellfish-fg: #8FCBDC;
      --pill-dairy-bg: #362C1D;  --pill-dairy-fg: #DBB479;
      --pill-pollen-bg: #26301D; --pill-pollen-fg: #A9C98C;
      --pill-bee-bg: #34211F;    --pill-bee-fg: #E19A94;
      --pill-peni-bg: #2A2436;   --pill-peni-fg: #C6AEE0;
      --pill-none-bg: #262723;   --pill-none-fg: #90978F;
      --pill-gluten-bg: #3A331A;   --pill-gluten-fg: #E0C778;
      --pill-vegan-bg: #1F3025;    --pill-vegan-fg: #8FCC9F;
      --pill-nopork-bg: #322A34;   --pill-nopork-fg: #D4A9CC;
      --pill-diabetes-bg: #202B3A; --pill-diabetes-fg: #94B4DD;

      --selected-border: var(--teal);
      --selected-bg: rgba(111, 187, 168, 0.10);
      --arrived: #8FCB98;
      --arrived-bg: rgba(143, 203, 152, 0.14);
      --late: #E28B62;
      --late-bg: rgba(226, 139, 98, 0.14);
      --cancelled: #E2726B;
      --cancelled-bg: rgba(226, 114, 107, 0.16);
      --checkedout: #6FA8DC;
      --checkedout-deep: #8FBFE8;
      --checkedout-bg: rgba(111, 168, 220, 0.16);

      --shadow: 0 1px 2px rgba(0,0,0,0.3), 0 6px 20px rgba(0,0,0,0.25);
    }
  }
  :root[data-theme="dark"] {
    --bg: #12120F; --surface: #1B1D1B; --ink: #EFEEE8; --ink-soft: #B7BDB8; --muted: #7C847F;
    --teal: #6FBBA8; --teal-deep: #9FD6C7; --amber: #E8A85B; --line: #2C2F2C; --line-strong: #3B3F3B; --focus: #6FBBA8;
  }

  html { scroll-padding-top: env(safe-area-inset-top, 0px); height: 100%; }
  body {
    height: 100%;
    margin: 0;
    background: var(--bg);
    color: var(--ink);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    -webkit-font-smoothing: antialiased;
  }
  * { box-sizing: border-box; }

  .app {
    max-width: 720px;
    margin: 0 auto;
    min-height: 100%;
    display: flex;
    flex-direction: column;
  }

  /* ---- Header / search ---- */
  .head {
    position: relative;
    z-index: 5;
    padding: calc(20px + env(safe-area-inset-top, 0px)) 18px 14px;
    background: linear-gradient(180deg, var(--bg) 70%, transparent);
  }

  .file-tabs {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 10px;
    margin-bottom: 4px;
  }
  .file-tabs::-webkit-scrollbar { display: none; }
  .file-tab {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 7px;
    padding: 8px 14px;
    border-radius: 999px;
    border: 1.5px solid var(--line-strong);
    background: var(--surface);
    font-family: 'Space Grotesk', sans-serif;
    font-weight: 600;
    font-size: 13px;
    color: var(--ink-soft);
    cursor: pointer;
    white-space: nowrap;
  }
  .file-tab:hover { border-color: var(--line-strong); background: var(--selected-bg); }
  .file-tab .tab-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--muted);
    flex-shrink: 0;
  }
  .file-tab.session-checkout .tab-dot { background: var(--checkedout); }
  .file-tab .tab-state {
    padding: 5px 8px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.04em;
    background: #1B4B47;
    color: #fff;
  }
  .file-tab.session-checkout .tab-state { background: #2B5FA8; }
  .file-tab.active {
    border-color: var(--teal);
    background: var(--selected-bg);
    color: var(--ink);
  }
  .file-tab.active.session-checkout {
    border-color: var(--checkedout);
    background: var(--checkedout-bg);
  }
  .file-tab.tab-closed { opacity: 0.55; }
  .file-tab .tab-lock { flex-shrink: 0; }

  .eyebrow-row {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    margin-bottom: 14px;
  }
  .title {
    font-family: 'Space Grotesk', sans-serif;
    font-weight: 600;
    font-size: 21px;
    letter-spacing: -0.01em;
    color: var(--teal);
    margin: 0;
  }
  .count-badge {
    font-family: 'Space Grotesk', sans-serif;
    font-size: 13px;
    font-weight: 600;
    color: var(--muted);
    white-space: nowrap;
  }
  .count-badge b { color: var(--ink); font-weight: 600; }

  .stat-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 14px;
  }
  .stat {
    display: flex;
    align-items: center;
    gap: 6px;
    font-family: 'Space Grotesk', sans-serif;
    font-size: 13px;
    color: var(--muted);
  }
  .stat b {
    color: var(--ink);
    font-weight: 600;
    font-size: 14px;
  }
  .stat .dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
  }
  .stat .dot.all { background: var(--muted); }
  .stat .dot.waiting { background: var(--amber); }
  .stat .dot.arrived { background: var(--arrived); }
  .stat .dot.cancelled { background: var(--cancelled); }
  .stat .dot.checkedout { background: var(--checkedout); }

  .search-wrap {
    position: relative;
  }
  .search-wrap svg {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    width: 17px;
    height: 17px;
    stroke: var(--muted);
    pointer-events: none;
    transition: stroke 0.15s ease;
  }
  #search {
    width: 100%;
    padding: 13px 40px 13px 40px;
    font-family: 'Inter', sans-serif;
    font-size: 16px;
    color: var(--ink);
    background: var(--surface);
    border: 1.5px solid var(--line-strong);
    border-radius: var(--radius-m);
    outline: none;
    box-shadow: var(--shadow);
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
  }
  #search::placeholder { color: var(--muted); }
  #search:focus {
    border-color: var(--focus);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--focus) 18%, transparent), var(--shadow);
  }
  #search:focus + svg { stroke: var(--focus); }

  .clear-btn {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    width: 28px;
    height: 28px;
    border: none;
    background: var(--line);
    color: var(--ink-soft);
    border-radius: 50%;
    font-size: 15px;
    line-height: 1;
    cursor: pointer;
    display: none;
    align-items: center;
    justify-content: center;
  }
  .clear-btn:hover { background: var(--line-strong); }
  .clear-btn.show { display: flex; }

  .action-row {
    display: none;
    gap: 8px;
    margin-top: 10px;
  }
  .action-row.show {
    display: flex;
    animation: btn-in 0.2s ease;
  }
  .action-btn {
    align-items: center;
    gap: 7px;
    padding: 11px 18px;
    font-family: 'Space Grotesk', sans-serif;
    font-weight: 600;
    font-size: 14px;
    border: none;
    border-radius: var(--radius-m);
    cursor: pointer;
    box-shadow: var(--shadow);
  }
  .action-btn.arrived { color: #fff; background: var(--teal); }
  .action-btn.arrived:hover { background: var(--teal-deep); }
  .action-btn.late { color: var(--late); background: var(--surface); border: 1.5px solid var(--late); box-shadow: none; }
  .action-btn.late:hover { background: var(--late-bg); }
  .action-btn.cancelled { color: var(--cancelled); background: var(--surface); border: 1.5px solid var(--cancelled); box-shadow: none; }
  .action-btn.cancelled:hover { background: var(--cancelled-bg); }
  .action-btn.checkout { color: #fff; background: var(--checkedout); }
  .action-btn.checkout:hover { background: var(--checkedout-deep); }
  .action-btn.btn-hidden { display: none; }

  .lock-btn {
    display: none;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    flex-shrink: 0;
    border: none;
    background: var(--line);
    color: var(--ink-soft);
    border-radius: 50%;
    font-size: 15px;
    cursor: pointer;
  }
  .lock-btn:hover { background: var(--line-strong); }
  .lock-btn.show { display: flex; }

  .allergy-row {
    display: none;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
  }
  .allergy-row.show {
    display: flex;
    animation: btn-in 0.2s ease;
  }
  .allergy-label {
    font-family: 'Space Grotesk', sans-serif;
    font-size: 11.5px;
    font-weight: 600;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    color: var(--muted);
    margin-right: 2px;
  }
  .allergy-btn, .flag-btn {
    border: none;
    padding: 7px 13px;
    border-radius: 999px;
    font-family: 'Inter', sans-serif;
    font-weight: 500;
    font-size: 13px;
    cursor: pointer;
    opacity: 0.72;
    transition: opacity 0.15s ease, box-shadow 0.15s ease;
  }
  .allergy-btn:hover, .flag-btn:hover { opacity: 1; }
  .allergy-btn.active, .flag-btn.active {
    opacity: 1;
    font-weight: 600;
    box-shadow: 0 0 0 2px currentColor;
  }
  .allergy-btn.gluten     { background: var(--pill-gluten-bg);   color: var(--pill-gluten-fg); }
  .allergy-btn.vegan      { background: var(--pill-vegan-bg);    color: var(--pill-vegan-fg); }
  .allergy-btn.nopork     { background: var(--pill-nopork-bg);   color: var(--pill-nopork-fg); }
  .allergy-btn.diabetes   { background: var(--pill-diabetes-bg); color: var(--pill-diabetes-fg); }
  .flag-btn.prob, .pill.prob { background: var(--pill-bee-bg); color: var(--pill-bee-fg); }
  .flag-btn.data-error, .pill.data-error { background: var(--pill-gluten-bg); color: var(--pill-gluten-fg); }
  .flag-btn.prio, .pill.prio { background: var(--pill-diabetes-bg); color: var(--pill-diabetes-fg); }

  .late-panel {
    display: none;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
  }
  .late-panel.show {
    display: flex;
    animation: btn-in 0.2s ease;
  }
  #late-time {
    flex: 1;
    min-width: 0;
    padding: 10px 12px;
    font-family: 'Inter', sans-serif;
    font-size: 15px;
    color: var(--ink);
    background: var(--surface);
    border: 1.5px solid var(--line-strong);
    border-radius: var(--radius-m);
    outline: none;
  }
  #late-time:focus { border-color: var(--late); }
  .late-submit {
    padding: 10px 16px;
    font-family: 'Space Grotesk', sans-serif;
    font-weight: 600;
    font-size: 14px;
    color: #fff;
    background: var(--late);
    border: none;
    border-radius: var(--radius-m);
    cursor: pointer;
    white-space: nowrap;
  }
  .late-submit:disabled { opacity: 0.4; cursor: default; }
  .late-cancel {
    width: 38px;
    height: 38px;
    flex-shrink: 0;
    border: none;
    background: var(--line);
    color: var(--ink-soft);
    border-radius: 50%;
    font-size: 15px;
    cursor: pointer;
  }
  .late-cancel:hover { background: var(--line-strong); }

  .cancel-panel {
    display: none;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
  }
  .cancel-panel.show {
    display: flex;
    animation: btn-in 0.2s ease;
  }
  #cancel-reason {
    flex: 1;
    min-width: 0;
    padding: 10px 12px;
    font-family: 'Inter', sans-serif;
    font-size: 15px;
    color: var(--ink);
    background: var(--surface);
    border: 1.5px solid var(--line-strong);
    border-radius: var(--radius-m);
    outline: none;
  }
  #cancel-reason:focus { border-color: var(--cancelled); }
  .cancel-submit {
    padding: 10px 16px;
    font-family: 'Space Grotesk', sans-serif;
    font-weight: 600;
    font-size: 14px;
    color: #fff;
    background: var(--cancelled);
    border: none;
    border-radius: var(--radius-m);
    cursor: pointer;
    white-space: nowrap;
  }
  .cancel-submit:disabled { opacity: 0.4; cursor: default; }
  .cancel-close {
    width: 38px;
    height: 38px;
    flex-shrink: 0;
    border: none;
    background: var(--line);
    color: var(--ink-soft);
    border-radius: 50%;
    font-size: 15px;
    cursor: pointer;
  }
  .cancel-close:hover { background: var(--line-strong); }

  @keyframes btn-in {
    from { opacity: 0; transform: translateY(-6px); }
    to { opacity: 1; transform: translateY(0); }
  }

  /* ---- Table ---- */
  .table-scroll {
    flex: 1;
    padding: 4px 18px 28px;
    overflow: visible;
  }
  table {
    width: 100%;
    min-width: 700px;
    border-collapse: separate;
    border-spacing: 0;
  }
  thead th {
    text-align: left;
    font-family: 'Space Grotesk', sans-serif;
    font-weight: 600;
    font-size: 12.5px;
    color: var(--muted);
    padding: 0 10px 10px;
    border-bottom: 1.5px solid var(--line-strong);
    letter-spacing: 0.01em;
  }
  thead th.col-counter { width: 44px; }
  thead th.col-id { width: 92px; }
  thead th.col-time { width: 92px; text-align: right; }
  thead th.col-reason { width: 160px; }
  thead th.col-notes { width: 180px; }

  tbody tr {
    background: var(--surface);
    transition: opacity 0.15s ease;
  }
  tbody tr td {
    padding: 13px 10px;
    border-bottom: 1px solid var(--line);
    font-size: 14.5px;
    vertical-align: middle;
    position: relative;
  }
  tbody tr td:first-child { border-left: 3px solid transparent; }
  tbody tr.is-match td:first-child { border-left: 3px solid var(--amber); }

  .cell-counter {
    font-family: 'Space Grotesk', sans-serif;
    font-weight: 600;
    color: var(--muted);
    font-variant-numeric: tabular-nums;
  }
  .cell-id {
    font-family: 'Space Grotesk', sans-serif;
    font-size: 13px;
    color: var(--ink-soft);
    letter-spacing: 0.01em;
  }
  .cell-name { font-weight: 500; color: var(--ink); }
  .cell-name mark {
    background: color-mix(in srgb, var(--amber) 35%, transparent);
    color: var(--ink);
    border-radius: 3px;
    padding: 0 1px;
  }
  .cell-time {
    text-align: right;
    color: var(--muted);
    font-variant-numeric: tabular-nums;
    font-size: 13.5px;
  }
  .cell-time.is-empty { opacity: 0.5; }
  .cell-reason {
    color: var(--ink-soft);
    font-size: 13.5px;
  }
  .cell-reason.is-empty { color: var(--muted); opacity: 0.5; }
  .cell-notes {
    color: var(--ink-soft);
    font-size: 13.5px;
  }
  .cell-notes.is-empty { color: var(--muted); opacity: 0.5; }

  .pill {
    display: inline-block;
    font-size: 12.5px;
    font-weight: 500;
    padding: 4px 10px;
    border-radius: 999px;
    white-space: nowrap;
  }
  .pill.peanut     { background: var(--pill-peanut-bg);     color: var(--pill-peanut-fg); }
  .pill.shellfish  { background: var(--pill-shellfish-bg);  color: var(--pill-shellfish-fg); }
  .pill.dairy      { background: var(--pill-dairy-bg);      color: var(--pill-dairy-fg); }
  .pill.pollen     { background: var(--pill-pollen-bg);     color: var(--pill-pollen-fg); }
  .pill.bee        { background: var(--pill-bee-bg);        color: var(--pill-bee-fg); }
  .pill.penicillin { background: var(--pill-peni-bg);       color: var(--pill-peni-fg); }
  .pill.none       { background: var(--pill-none-bg);       color: var(--pill-none-fg); }
  .pill.gluten     { background: var(--pill-gluten-bg);      color: var(--pill-gluten-fg); }
  .pill.vegan      { background: var(--pill-vegan-bg);       color: var(--pill-vegan-fg); }
  .pill.nopork     { background: var(--pill-nopork-bg);      color: var(--pill-nopork-fg); }
  .pill.diabetes   { background: var(--pill-diabetes-bg);    color: var(--pill-diabetes-fg); }

  .cell-empty-dash { color: var(--muted); opacity: 0.5; }

  .notes-row {
    display: none;
    flex-direction: column;
    gap: 8px;
    margin-top: 10px;
  }
  .notes-row.show {
    display: flex;
    animation: btn-in 0.2s ease;
  }
  #notes-input {
    width: 100%;
    min-height: 56px;
    padding: 10px 12px;
    font-family: 'Inter', sans-serif;
    font-size: 14.5px;
    color: var(--ink);
    background: var(--surface);
    border: 1.5px solid var(--line-strong);
    border-radius: var(--radius-m);
    outline: none;
    resize: vertical;
  }
  #notes-input::placeholder { color: var(--muted); }
  #notes-input:focus { border-color: var(--focus); }
  .notes-save-btn {
    align-self: flex-end;
    padding: 8px 16px;
    font-family: 'Space Grotesk', sans-serif;
    font-weight: 600;
    font-size: 13.5px;
    color: #fff;
    background: var(--teal);
    border: none;
    border-radius: var(--radius-m);
    cursor: pointer;
  }
  .notes-save-btn:hover { background: var(--teal-deep); }

  tbody tr { cursor: pointer; }

  tr.is-selected { background: var(--selected-bg); }
  tr.is-selected td:first-child { border-left: 3px solid var(--selected-border); }

  tr.is-late { background: var(--late-bg); }
  tr.is-late td:first-child { border-left: 3px solid var(--late); }
  tr.is-late .cell-time { color: var(--late); font-weight: 500; }

  tr.is-arrived { background: var(--arrived-bg); }
  tr.is-arrived td:first-child { border-left: 3px solid var(--arrived); }
  tr.is-arrived .cell-name::before {
    content: '✓';
    display: inline-block;
    margin-right: 6px;
    color: var(--arrived);
    font-weight: 600;
  }

  tr.is-cancelled { background: var(--cancelled-bg); }
  tr.is-cancelled td:first-child { border-left: 3px solid var(--cancelled); }
  tr.is-cancelled .cell-name {
    color: var(--muted);
    text-decoration: line-through;
    text-decoration-color: var(--cancelled);
  }
  tr.is-cancelled .cell-name::before { content: none; }
  tr.is-cancelled .cell-reason { color: var(--cancelled); font-style: italic; }

  tr.is-checked-out { background: var(--checkedout-bg); }
  tr.is-checked-out td:first-child { border-left: 3px solid var(--checkedout); }
  tr.is-checked-out .cell-name::before {
    content: '✓';
    display: inline-block;
    margin-right: 6px;
    color: var(--checkedout);
    font-weight: 600;
  }

  tr.row-hidden { display: none; }

  /* ---- Check-out session (blue accent) ---- */
  body[data-session="checkout"] .title { color: var(--checkedout); }
  body[data-session="checkout"] #search:focus {
    border-color: var(--checkedout);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--checkedout) 18%, transparent), var(--shadow);
  }
  body[data-session="checkout"] #search:focus + svg { stroke: var(--checkedout); }
  body[data-session="checkout"] tr.is-selected { background: var(--checkedout-bg); }
  body[data-session="checkout"] tr.is-selected td:first-child { border-left: 3px solid var(--checkedout); }
  body:not([data-session="checkout"]) #checkout-controls,
  body:not([data-session="checkout"]) #stat-bar-checkout { display: none; }
  body[data-session="checkout"] #checkin-controls,
  body[data-session="checkout"] #stat-bar-checkin { display: none; }

  .session-switch {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin: 2px 0 14px;
  }
  .session-badge {
    display: none;
    align-items: center;
    gap: 7px;
    padding: 14px 22px;
    border-radius: var(--radius-m);
    font-family: 'Space Grotesk', sans-serif;
    font-weight: 700;
    font-size: 22px;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    background: #1B4B47;
    color: #fff;
  }
  .session-badge.show { display: inline-flex; }
  .session-badge.checkout { background: #2B5FA8; color: #fff; }
  .session-switch-btn {
    display: none;
    padding: 12px 20px;
    font-family: 'Space Grotesk', sans-serif;
    font-weight: 700;
    font-size: 14.5px;
    color: #fff;
    background: var(--teal);
    border: none;
    border-radius: var(--radius-m);
    cursor: pointer;
    box-shadow: var(--shadow);
  }
  .session-switch-btn.show { display: inline-flex; animation: btn-in 0.2s ease; }
  .undo-btn {
    padding: 10px 16px;
    border: 2px solid var(--teal);
    border-radius: var(--radius-m);
    background: var(--surface);
    color: var(--ink);
    font: 700 14px 'Space Grotesk', sans-serif;
    cursor: pointer;
  }
  .undo-btn:disabled { opacity: 0.4; cursor: default; }
  .undo-btn:not(:disabled):hover { background: var(--selected-bg); }
  .account-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 14px; }
  .account-greeting { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; }
  .role-badge { padding: 4px 9px; border-radius: 999px; background: var(--selected-bg); color: var(--teal); font-size: 12px; font-weight: 600; }
  .logout-form { margin: 0; flex-shrink: 0; }

  .day-lock { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin: 2px 0 14px; }
  .day-lock-badge {
    display: none;
    align-items: center;
    gap: 7px;
    padding: 9px 16px;
    border-radius: 999px;
    font-family: 'Space Grotesk', sans-serif;
    font-weight: 700;
    font-size: 13px;
    background: var(--cancelled-bg);
    color: var(--cancelled);
  }
  .day-lock-badge.show { display: inline-flex; }
  .day-lock-btn {
    display: none;
    padding: 9px 16px;
    border: none;
    border-radius: var(--radius-m);
    font-family: 'Space Grotesk', sans-serif;
    font-weight: 700;
    font-size: 13px;
    color: #fff;
    cursor: pointer;
    box-shadow: var(--shadow);
  }
  .day-lock-btn.show { display: inline-flex; animation: btn-in 0.2s ease; }
  .day-lock-btn.close { background: var(--cancelled); }
  .day-lock-btn.close:hover { filter: brightness(0.92); }
  .day-lock-btn.reopen { background: var(--teal); }
  .day-lock-btn.reopen:hover { background: var(--teal-deep); }

  /* While a day is closed, hide the controls that would let anyone edit it */
  body.day-closed #session-badge,
  body.day-closed #session-lock-btn,
  body.day-closed #session-switch-btn,
  body.day-closed #session-cancel-btn,
  body.day-closed #undo-btn { display: none !important; }
  body.day-closed tbody tr { cursor: default; }
  #log-view { max-width: 1200px; }
  #log-view[hidden] { display: none; }
  #log-project { overflow-wrap: anywhere; color: var(--ink-soft); }
  #log-body tr { cursor: default; }
  #log-body td { white-space: pre-wrap; overflow-wrap: anywhere; min-width: 110px; max-width: 300px; }
  #log-status { color: var(--ink-soft); }
  .session-switch-btn:hover { background: var(--teal-deep); }
  body[data-session="checkout"] .session-switch-btn { background: var(--checkedout); }
  body[data-session="checkout"] .session-switch-btn:hover { background: var(--checkedout-deep); }
  .session-cancel-btn {
    display: none;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    flex-shrink: 0;
    border: none;
    background: var(--line);
    color: var(--ink-soft);
    border-radius: 50%;
    font-size: 15px;
    cursor: pointer;
  }
  .session-cancel-btn:hover { background: var(--line-strong); }
  .session-cancel-btn.show { display: inline-flex; }

  /* ---- Empty state ---- */
  .empty {
    display: none;
    flex-direction: column;
    align-items: center;
    text-align: center;
    padding: 48px 24px 20px;
    color: var(--muted);
  }
  .empty.show { display: flex; }
  .empty svg { width: 34px; height: 34px; stroke: var(--line-strong); margin-bottom: 12px; }
  .empty p { margin: 0; font-size: 14px; }
  .empty b { color: var(--ink-soft); font-weight: 500; }

  @media (max-width: 380px) {
    .head { padding-left: 14px; padding-right: 14px; }
    .table-scroll { padding-left: 14px; padding-right: 14px; }
  }
</style>
</head>
<body>

<div class="app" id="roster-view">
  <div class="head">
    <div class="account-row">
      <p class="title account-greeting">Hi <?= htmlspecialchars($authenticatedUser, ENT_QUOTES, 'UTF-8') ?>! <span class="role-badge"><?= htmlspecialchars($userRoleLabels[$authenticatedUser] ?? 'Reader', ENT_QUOTES, 'UTF-8') ?></span></p>
      <form class="logout-form" action="logout.php" method="post">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <button class="undo-btn" type="submit">Log out</button>
      </form>
    </div>
    <div class="file-tabs" id="file-tabs"></div>
    <div class="day-lock" id="day-lock">
      <span class="day-lock-badge" id="day-lock-badge">🔒 This day is closed — read-only</span>
      <?php if (($userRoleLabels[$authenticatedUser] ?? '') === 'Administrator'): ?>
      <button class="day-lock-btn close" id="close-day-btn">Close this day</button>
      <button class="day-lock-btn reopen" id="reopen-day-btn">Reopen this day</button>
      <?php endif; ?>
    </div>
    <div class="eyebrow-row">
      <p class="title" id="title">Check-in roster</p>
      <span class="count-badge"><b id="visible-count">8</b> / <span id="visible-total">8</span> shown</span>
    </div>
    <div class="stat-bar" id="stat-bar-checkin">
      <div class="stat"><span class="dot all"></span><b id="stat-all">0</b><span>all records</span></div>
      <div class="stat"><span class="dot waiting"></span><b id="stat-waiting">0</b><span>waiting</span></div>
      <div class="stat"><span class="dot arrived"></span><b id="stat-arrived">0</b><span>arrived</span></div>
      <div class="stat"><span class="dot cancelled"></span><b id="stat-cancelled">0</b><span>cancelled</span></div>
    </div>
    <div class="stat-bar" id="stat-bar-checkout">
      <div class="stat"><span class="dot all"></span><b id="stat-all-2">0</b><span>all records</span></div>
      <div class="stat"><span class="dot checkedout"></span><b id="stat-checkedout">0</b><span>checked out</span></div>
      <div class="stat"><span class="dot waiting"></span><b id="stat-waiting-2">0</b><span>waiting</span></div>
    </div>
    <div class="session-switch" id="session-switch">
      <span class="session-badge" id="session-badge">Check-in session</span>
      <button class="lock-btn" id="session-lock-btn" aria-label="Unlock to switch session">🔒</button>
      <button class="session-switch-btn" id="session-switch-btn">Start check-out session →</button>
      <button class="session-cancel-btn" id="session-cancel-btn" aria-label="Cancel, keep current session">✕</button>
      <button class="undo-btn" id="undo-btn" disabled title="Undo the last change for this day">UNDO</button>
      <button class="undo-btn" id="view-log-btn" disabled>View log</button>
      <button class="undo-btn" id="all-logs-btn">All logs</button>
    </div>
    <div class="search-wrap">
      <input type="text" id="search" placeholder="Filter by name…" autocomplete="off" />
      <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
      <button class="clear-btn" id="clear-btn" aria-label="Clear search">✕</button>
    </div>
    <div id="checkin-controls">
      <div class="action-row" id="action-row">
        <button class="action-btn arrived" id="arrived-btn">Arrived</button>
        <button class="action-btn late" id="late-btn">Late</button>
        <button class="action-btn cancelled" id="cancelled-btn">Cancelled</button>
        <button class="lock-btn" id="lock-btn" aria-label="Unlock to change status">🔒</button>
      </div>
      <div class="allergy-row" id="allergy-row">
        <span class="allergy-label">Diet</span>
        <button class="allergy-btn gluten" data-value="Gluten" data-cls="gluten">Gluten</button>
        <button class="allergy-btn vegan" data-value="Vegan" data-cls="vegan">Vegan</button>
        <button class="allergy-btn nopork" data-value="No pork" data-cls="nopork">No pork</button>
        <button class="allergy-btn diabetes" data-value="Diabetes" data-cls="diabetes">Diabetes</button>
      </div>
      <div class="allergy-row" id="flag-row">
        <span class="allergy-label">Flag</span>
        <button class="flag-btn prob" data-value="PROB">PROB</button>
        <button class="flag-btn data-error" data-value="DATA ERROR">DATA ERROR</button>
        <button class="flag-btn prio" data-value="PRIO">PRIO</button>
      </div>
      <div class="notes-row" id="notes-row">
        <textarea id="notes-input" rows="2" placeholder="Add a note…"></textarea>
        <button class="notes-save-btn" id="notes-save">Save note</button>
      </div>
      <div class="late-panel" id="late-panel">
        <input type="time" id="late-time" aria-label="Arrival time" />
        <button class="late-submit" id="late-submit" disabled>Submit</button>
        <button class="late-cancel" id="late-cancel" aria-label="Cancel">✕</button>
      </div>
      <div class="cancel-panel" id="cancel-panel">
        <input type="text" id="cancel-reason" placeholder="Reason for cancelling…" autocomplete="off" />
        <button class="cancel-submit" id="cancel-submit" disabled>Submit</button>
        <button class="cancel-close" id="cancel-close" aria-label="Cancel">✕</button>
      </div>
    </div>
    <div id="checkout-controls">
      <div class="action-row" id="checkout-action-row">
        <button class="action-btn checkout" id="checkout-btn">Check out</button>
      </div>
    </div>
  </div>


  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th class="col-counter">#</th>
          <th class="col-id">ID</th>
          <th class="col-name">Name</th>
          <th class="col-allergy">Allergy</th>
          <th class="col-flag">Flag</th>
          <th class="col-time">Time</th>
          <th class="col-reason">Reason</th>
          <th class="col-notes">Notes</th>
        </tr>
      </thead>
      <tbody id="roster-body">
        <!-- rows injected by JS -->
      </tbody>
    </table>

    <div class="empty" id="empty-state">
      <svg viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
      <p>No one named <b id="empty-term"></b> on the list.</p>
    </div>
  </div>
</div>

<section class="app" id="log-view" hidden aria-labelledby="log-title">
  <div class="head">
    <div class="account-row">
      <button class="undo-btn" id="log-back-btn">Back to roster</button>
      <form class="logout-form" action="logout.php" method="post">
        <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <button class="undo-btn" type="submit">Log out</button>
      </form>
    </div>
    <h1 class="title" id="log-title" tabindex="-1" style="margin-top:16px;">Project log</h1>
    <p id="log-project"></p>
    <p id="log-status" role="status"></p>
  </div>
  <div class="table-scroll">
    <table aria-label="Audit log">
      <thead><tr><th>When (Budapest)</th><th>User</th><th>Action</th><th>ID</th><th>Person</th><th>Field</th><th>Before</th><th>After</th><th id="log-project-column" hidden>Project / date</th></tr></thead>
      <tbody id="log-body"></tbody>
    </table>
  </div>
</section>

<script>
  // Roster data is now loaded from get_roster.php (which reads data.csv)
  // instead of being hardcoded here — see the $.getJSON call below.
  var ROSTER = [];

  var selectedId = null;
  var mode = null; // null | 'late' | 'cancelled'
  var unlocked = false;
  var session = 'checkin'; // 'checkin' | 'checkout'
  var sessionUnlocked = false;
  var FILES = [];
  var currentFile = null;
  var undoHistory = {};
  var lastSnapshots = {};
  var pendingSaves = {};
  var rosterLoading = true;
  var fileClosed = false;
  var saveQueue = $.Deferred().resolve().promise();
  var logRequestId = 0;
  var rosterScrollPosition = 0;
  var logReturnButton = '#view-log-btn';

  function showProjectLog(allProjects) {
    allProjects = allProjects === true;
    if (!allProjects && (!currentFile || rosterLoading)) return;
    var file = currentFile;
    var requestId = ++logRequestId;
    rosterScrollPosition = window.scrollY;
    $('#roster-view').hide();
    $('#log-view').prop('hidden', false);
    logReturnButton = allProjects ? '#all-logs-btn' : '#view-log-btn';
    $('#log-title').text(allProjects ? 'General log' : 'Project log');
    $('#log-project').text(allProjects ? 'All projects / all dates' : file);
    $('#log-project-column').prop('hidden', !allProjects);
    $('#log-body').empty();
    $('#log-status').text('Loading log records…');
    $('#log-title').trigger('focus');
    window.scrollTo(0, 0);
    saveQueue.then(function () { return $.getJSON('get_log.php', allProjects ? { scope: 'all' } : { file: file }); })
      .done(function (data) {
        if (requestId !== logRequestId) return;
        data.records.forEach(function (record) {
          var $row = $('<tr>').attr('title', 'Event: ' + record.event_id);
          [record.timestamp, record.user, record.action.toUpperCase(), record.person_id,
            record.person_name, record.field, record.old_value, record.new_value].forEach(function (value) {
              $row.append($('<td>').text(value === '' ? '—' : value));
            });
          if (allProjects) $row.append($('<td>').text(record.project));
          $('#log-body').append($row);
        });
        $('#log-status').text(data.records.length ? data.records.length + ' log records · newest first' : (allProjects ? 'No general log records yet.' : 'No log records yet for this project.'));
      }).fail(function () {
        if (requestId !== logRequestId) return;
        $('#log-status').text('Could not load the log. Go back and try again.');
      });
  }

  function rosterSnapshot() {
    return JSON.stringify({ rows: ROSTER, session: session });
  }

  function updateUndoUI() {
    $('#view-log-btn').prop('disabled', rosterLoading || !currentFile);
    $('#undo-btn').prop('disabled', rosterLoading || fileClosed || !!pendingSaves[currentFile] || !(undoHistory[currentFile] || []).length);
  }

  function updateDayLockUI() {
    $('body').toggleClass('day-closed', fileClosed);
    $('#day-lock-badge').toggleClass('show', fileClosed);
    $('#close-day-btn').toggleClass('show', !fileClosed);
    $('#reopen-day-btn').toggleClass('show', fileClosed);
  }

  function restoreRoster(snapshot) {
    var state = JSON.parse(snapshot);
    ROSTER = state.rows;
    session = state.session;
    selectedId = null;
    sessionUnlocked = false;
    resetPanels();
    updateSessionUI();
    updateActionUI();
    renderRows($('#search').val().trim());
  }

  function formatTime12(value) {
    var parts = value.split(':');
    var h = parseInt(parts[0], 10);
    var m = parts[1];
    var suffix = h >= 12 ? 'PM' : 'AM';
    var h12 = h % 12;
    if (h12 === 0) h12 = 12;
    return (h12 < 10 ? '0' + h12 : h12) + ':' + m + ' ' + suffix;
  }

  function parseTime12(str) {
    var m = str.match(/^(\d{1,2}):(\d{2})\s?(AM|PM)$/i);
    if (!m) return '';
    var h = parseInt(m[1], 10);
    var min = m[2];
    var ampm = m[3].toUpperCase();
    if (ampm === 'PM' && h !== 12) h += 12;
    if (ampm === 'AM' && h === 12) h = 0;
    return (h < 10 ? '0' + h : h) + ':' + min;
  }

  function personStatus(person) {
    if (!person) return null;
    if (person.cancelled) return 'cancelled';
    if (person.late) return 'late';
    if (person.arrived) return 'arrived';
    return null;
  }

  function saveRoster(isUndo, action) {
    if (!currentFile) return;
    var file = currentFile;
    var snapshot = rosterSnapshot();
    var state = JSON.parse(snapshot);
    var result = $.Deferred();
    if (!isUndo && lastSnapshots[file] && lastSnapshots[file] !== snapshot) {
      undoHistory[file].push(lastSnapshots[file]);
    }
    lastSnapshots[file] = snapshot;
    pendingSaves[file] = (pendingSaves[file] || 0) + 1;
    updateUndoUI();
    // Keep a quick sequence of edits and undos in the same order on disk.
    saveQueue = saveQueue.then(function () {
      return $.ajax({
        url: 'save_roster.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ file: file, session: state.session, rows: state.rows, action: isUndo ? 'undo' : (action || 'save') }),
        dataType: 'json'
      }).then(function () {
        var f = FILES.find(function (x) { return x.file === file; });
        if (f) f.session = state.session;
        renderFileTabs();
        result.resolve();
      }, function (xhr) {
        result.reject();
        if (xhr && xhr.status === 423) {
          fileClosed = true;
          updateDayLockUI();
          updateActionUI();
          window.alert('This day was closed and can no longer be edited. Reloading…');
          loadRoster(file);
        } else {
          window.alert('Could not save changes to ' + file + '. Please check your connection.');
        }
      }).always(function () {
        pendingSaves[file]--;
        updateUndoUI();
      });
    });
    return result.promise();
  }

  function renderFileTabs() {
    var $tabs = $('#file-tabs').empty();
    FILES.forEach(function (f) {
      var $tab = $('<button class="file-tab">')
        .attr('data-file', f.file)
        .toggleClass('active', f.file === currentFile)
        .toggleClass('session-checkout', f.session === 'checkout')
        .toggleClass('tab-closed', !!f.closed)
        .append($('<span class="tab-dot">'))
        .append($('<span>').text(f.label))
        .append($('<span class="tab-state">').text(f.session === 'checkout' ? 'CHECK-OUT' : 'CHECK-IN'));
      if (f.closed) $tab.append($('<span class="tab-lock">').text('🔒'));
      $tab.appendTo($tabs);
    });
  }

  function loadRoster(file) {
    currentFile = file;
    rosterLoading = true;
    updateUndoUI();
    selectedId = null;
    mode = null;
    unlocked = false;
    sessionUnlocked = false;
    fileClosed = false;
    updateDayLockUI();
    $('#search').val('');
    $('#clear-btn').removeClass('show');
    renderFileTabs();

    $('#roster-body').html(
      '<tr><td colspan="8" style="text-align:center;padding:24px;color:var(--muted);">Loading roster…</td></tr>'
    );

    saveQueue.then(function () { return $.getJSON('get_roster.php', { file: file }); })
      .done(function (data) {
        if (currentFile !== file) return;
        ROSTER = data.rows || [];
        session = data.session === 'checkout' ? 'checkout' : 'checkin';
        fileClosed = !!data.closed;
        var snapshot = rosterSnapshot();
        if (lastSnapshots[file] !== snapshot) undoHistory[file] = [];
        lastSnapshots[file] = snapshot;
        rosterLoading = false;
        updateUndoUI();
        updateSessionUI();
        updateDayLockUI();
        updateActionUI();
        renderRows('');
      })
      .fail(function () {
        $('#roster-body').html(
          '<tr><td colspan="8" style="text-align:center;padding:24px;color:var(--cancelled);">' +
          'Could not load ' + file + '.</td></tr>'
        );
      });
  }

  function loadFileList() {
    $.getJSON('list_files.php')
      .done(function (data) {
        FILES = data || [];
        if (!FILES.length) {
          $('#roster-body').html(
            '<tr><td colspan="8" style="text-align:center;padding:24px;color:var(--muted);">No CSV files found in /data.</td></tr>'
          );
          return;
        }
        renderFileTabs();
        loadRoster(FILES[0].file);
      })
      .fail(function () {
        $('#roster-body').html(
          '<tr><td colspan="8" style="text-align:center;padding:24px;color:var(--cancelled);">' +
          'Could not load the file list from list_files.php.</td></tr>'
        );
      });
  }

  function escapeHtml(str) {
    return $('<div>').text(str).html();
  }

  function highlight(name, term) {
    if (!term) return escapeHtml(name);
    var idx = name.toLowerCase().indexOf(term.toLowerCase());
    if (idx === -1) return escapeHtml(name);
    var before = escapeHtml(name.slice(0, idx));
    var match = escapeHtml(name.slice(idx, idx + term.length));
    var after = escapeHtml(name.slice(idx + term.length));
    return before + '<mark>' + match + '</mark>' + after;
  }

  function renderRows(term) {
    var $body = $('#roster-body');
    $body.empty();
    var visible = 0;

    ROSTER.forEach(function (person, i) {
      var inSession = session !== 'checkout' || person.arrived;
      var nameMatch = !term || person.name.toLowerCase().indexOf(term.toLowerCase()) !== -1;
      var matches = inSession && nameMatch;
      if (matches) visible++;

      var $row = $('<tr>')
        .attr('data-id', person.id)
        .toggleClass('row-hidden', !matches)
        .toggleClass('is-match', !!term && matches)
        .toggleClass('is-selected', person.id === selectedId)
        .toggleClass('is-late', person.late)
        .toggleClass('is-arrived', person.arrived)
        .toggleClass('is-cancelled', person.cancelled)
        .toggleClass('is-checked-out', person.checkedOut);

      $row.append($('<td class="cell-counter">').text(i + 1));
      $row.append($('<td class="cell-id">').text(person.id));
      $row.append($('<td class="cell-name">').html(highlight(person.name, term)));
      $row.append(
        $('<td>').append(
          person.allergy
            ? $('<span class="pill">').addClass(person.cls).text(person.allergy)
            : $('<span class="cell-empty-dash">').text('–')
        )
      );
      $row.append(
        $('<td class="cell-flag">').append(
          person.flag
            ? $('<span class="pill">').addClass({ 'PROB': 'prob', 'DATA ERROR': 'data-error', 'PRIO': 'prio' }[person.flag] || '').text(person.flag)
            : $('<span class="cell-empty-dash">').text('–')
        )
      );
      $row.append(
        $('<td class="cell-time">')
          .toggleClass('is-empty', !person.time)
          .text(person.time || '–')
      );
      $row.append(
        $('<td class="cell-reason">')
          .toggleClass('is-empty', !person.reason)
          .text(person.reason || '–')
      );
      $row.append(
        $('<td class="cell-notes">')
          .toggleClass('is-empty', !person.notes)
          .text(person.notes || '–')
      );

      $body.append($row);
    });

    $('#visible-count').text(visible);
    $('#visible-total').text(session === 'checkout' ? ROSTER.filter(function (p) { return p.arrived; }).length : ROSTER.length);
    $('#empty-state').toggleClass('show', visible === 0);
    $('#empty-term').text(term);

    $('#stat-all').text(ROSTER.length);
    var arrivedCount = ROSTER.filter(function (p) { return p.arrived; }).length;
    var cancelledCount = ROSTER.filter(function (p) { return p.cancelled; }).length;
    $('#stat-waiting').text(ROSTER.length - arrivedCount - cancelledCount);
    $('#stat-arrived').text(arrivedCount);
    $('#stat-cancelled').text(cancelledCount);

    var arrivedTotal = ROSTER.filter(function (p) { return p.arrived; }).length;
    $('#stat-all-2').text(arrivedTotal);
    var checkedOutCount = ROSTER.filter(function (p) { return p.checkedOut; }).length;
    $('#stat-checkedout').text(checkedOutCount);
    $('#stat-waiting-2').text(arrivedTotal - checkedOutCount);
  }

  function updateActionUI() {
    var hasSelection = selectedId !== null;
    var person = ROSTER.find(function (p) { return p.id === selectedId; });
    var status = personStatus(person);
    var locked = status !== null && !unlocked;

    $('#arrived-btn').toggleClass('btn-hidden', locked && status !== 'arrived');
    $('#late-btn').toggleClass('btn-hidden', locked && status !== 'late');
    $('#cancelled-btn').toggleClass('btn-hidden', locked && status !== 'cancelled');
    $('#lock-btn').toggleClass('show', hasSelection && locked && mode === null);

    $('#action-row').toggleClass('show', hasSelection && mode === null);
    $('#allergy-row').toggleClass('show', hasSelection && mode === null);
    $('#flag-row').toggleClass('show', hasSelection && mode === null);
    $('.flag-btn').each(function () {
      $(this).toggleClass('active', !!person && person.flag === $(this).data('value'));
    });
    $('#notes-row').toggleClass('show', hasSelection && mode === null);
    $('.allergy-btn').each(function () {
      $(this).toggleClass('active', !!person && person.allergy === $(this).data('value'));
    });
    $('#late-panel').toggleClass('show', hasSelection && mode === 'late');
    $('#cancel-panel').toggleClass('show', hasSelection && mode === 'cancelled');

    $('#checkout-action-row').toggleClass('show', hasSelection && session === 'checkout');
  }

  function resetPanels() {
    mode = null;
    unlocked = false;
    $('#late-time').val('');
    $('#late-submit').prop('disabled', true);
    $('#cancel-reason').val('');
    $('#cancel-submit').prop('disabled', true);
    $('#notes-input').val('');
  }

  function selectRow(id) {
    selectedId = (selectedId === id) ? null : id;
    resetPanels();
    var person = ROSTER.find(function (p) { return p.id === selectedId; });
    $('#notes-input').val(person ? person.notes : '');
    updateActionUI();
    renderRows($('#search').val().trim());
  }

  function updateSessionUI() {
    $('body').attr('data-session', session);
    $('#title').text(session === 'checkin' ? 'Check-in roster' : 'Check-out roster');
    $('#session-badge')
      .text(session === 'checkin' ? 'Check-in session' : 'Check-out session')
      .toggleClass('checkout', session === 'checkout');
    $('#session-switch-btn').text(
      session === 'checkin' ? 'Start check-out session →' : '← Back to check-in'
    );
    $('#session-badge').toggleClass('show', !sessionUnlocked);
    $('#session-lock-btn').toggleClass('show', !sessionUnlocked);
    $('#session-switch-btn').toggleClass('show', sessionUnlocked);
    $('#session-cancel-btn').toggleClass('show', sessionUnlocked);
  }

  $(function () {
    updateSessionUI();
    loadFileList();

    $('.logout-form').on('submit', function (event) {
      event.preventDefault();
      var form = this;
      $('.logout-form button').prop('disabled', true);
      saveQueue.then(function () { form.submit(); });
    });
    $(window).on('pageshow', function (event) {
      if (event.originalEvent.persisted) window.location.reload();
    });

    $('#view-log-btn').on('click', showProjectLog);
    $('#all-logs-btn').on('click', function () { showProjectLog(true); });
    $('#log-back-btn').on('click', function () {
      logRequestId++;
      $('#log-view').prop('hidden', true);
      $('#roster-view').show();
      $(logReturnButton).trigger('focus');
      window.scrollTo(0, rosterScrollPosition);
    });

    $('#undo-btn').on('click', function () {
      if (rosterLoading || pendingSaves[currentFile] || !(undoHistory[currentFile] || []).length) return;
      var file = currentFile;
      var history = undoHistory[file];
      var before = rosterSnapshot();
      var previous = history.pop();
      restoreRoster(previous);
      saveRoster(true).fail(function () {
        // Keep the undo available for retry if its save fails.
        history.push(previous);
        if (currentFile === file && rosterSnapshot() === previous) {
          restoreRoster(before);
          lastSnapshots[file] = before;
        }
        updateUndoUI();
      });
    });

    $('#file-tabs').on('click', '.file-tab', function () {
      var file = $(this).attr('data-file');
      if (file === currentFile) return;
      resetPanels();
      loadRoster(file);
    });

    $('#search').on('input', function () {
      var term = $(this).val().trim();
      $('#clear-btn').toggleClass('show', term.length > 0);
      selectedId = null;
      resetPanels();
      updateActionUI();
      renderRows(term);
    });

    $('#clear-btn').on('click', function () {
      $('#search').val('').trigger('input').focus();
    });

    $('#roster-body').on('click', 'tr', function () {
      if (fileClosed) return;
      selectRow($(this).attr('data-id'));
    });

    $('#session-lock-btn').on('click', function () {
      sessionUnlocked = true;
      updateSessionUI();
    });

    $('#session-cancel-btn').on('click', function () {
      sessionUnlocked = false;
      updateSessionUI();
    });

    function setDayClosed(closed) {
      if (!currentFile) return;
      var file = currentFile;
      $.ajax({
        url: 'set_closed.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({ file: file, closed: closed }),
        dataType: 'json'
      }).done(function (res) {
        if (!res || !res.ok || currentFile !== file) return;
        fileClosed = closed;
        var f = FILES.find(function (x) { return x.file === file; });
        if (f) f.closed = closed;
        selectedId = null;
        resetPanels();
        updateDayLockUI();
        updateActionUI();
        renderFileTabs();
        renderRows($('#search').val().trim());
      }).fail(function (xhr) {
        var msg = 'Could not update the closed state.';
        try {
          var body = JSON.parse(xhr.responseText);
          if (body && body.error) msg = body.error;
        } catch (e) {}
        window.alert(msg);
      });
    }

    $('#close-day-btn').on('click', function () {
      if (!currentFile) return;
      if (!window.confirm('Close ' + currentFile + ' for editing? Only an administrator will be able to reopen it.')) return;
      setDayClosed(true);
    });

    $('#reopen-day-btn').on('click', function () {
      setDayClosed(false);
    });

    $('#session-switch-btn').on('click', function () {
      session = (session === 'checkin') ? 'checkout' : 'checkin';
      sessionUnlocked = false;
      selectedId = null;
      resetPanels();
      updateSessionUI();
      updateActionUI();
      renderRows($('#search').val().trim());
      saveRoster(false, 'session');
    });

    $('#checkout-btn').on('click', function () {
      var person = ROSTER.find(function (p) { return p.id === selectedId; });
      if (person) person.checkedOut = true;
      selectedId = null;
      updateActionUI();
      renderRows($('#search').val().trim());
      saveRoster(false, 'checkout');
    });

    $('#arrived-btn').on('click', function () {
      var person = ROSTER.find(function (p) { return p.id === selectedId; });
      if (person) {
        person.arrived = true;
        person.late = false;
        person.cancelled = false;
        person.time = '';
        person.reason = '';
      }
      selectedId = null;
      unlocked = false;
      updateActionUI();
      renderRows($('#search').val().trim());
      saveRoster(false, 'arrived');
    });

    $('#lock-btn').on('click', function () {
      unlocked = true;
      updateActionUI();
    });

    $('.allergy-btn').on('click', function () {
      var person = ROSTER.find(function (p) { return p.id === selectedId; });
      if (person) {
        person.allergy = $(this).data('value');
        person.cls = $(this).data('cls');
      }
      updateActionUI();
      renderRows($('#search').val().trim());
      saveRoster(false, 'diet');
    });

    $('.flag-btn').on('click', function () {
      var person = ROSTER.find(function (p) { return p.id === selectedId; });
      if (person) person.flag = $(this).data('value');
      updateActionUI();
      renderRows($('#search').val().trim());
      saveRoster(false, 'flag');
    });

    $('#notes-save').on('click', function () {
      var person = ROSTER.find(function (p) { return p.id === selectedId; });
      if (person) person.notes = $('#notes-input').val();
      renderRows($('#search').val().trim());
      saveRoster(false, 'notes');
    });

    $('#late-btn').on('click', function () {
      mode = 'late';
      var person = ROSTER.find(function (p) { return p.id === selectedId; });
      var prefill = (person && person.time) ? parseTime12(person.time) : '';
      $('#late-time').val(prefill);
      $('#late-submit').prop('disabled', !prefill);
      updateActionUI();
      $('#late-time').focus();
    });

    $('#late-cancel').on('click', function () {
      mode = null;
      $('#late-time').val('');
      $('#late-submit').prop('disabled', true);
      updateActionUI();
    });

    $('#late-time').on('input', function () {
      $('#late-submit').prop('disabled', !$(this).val());
    });

    $('#late-submit').on('click', function () {
      var value = $('#late-time').val();
      if (!value) return;
      var person = ROSTER.find(function (p) { return p.id === selectedId; });
      if (person) {
        person.time = formatTime12(value);
        person.late = true;
        person.arrived = false;
        person.cancelled = false;
        person.reason = '';
      }
      selectedId = null;
      resetPanels();
      updateActionUI();
      renderRows($('#search').val().trim());
      saveRoster(false, 'late');
    });

    $('#cancelled-btn').on('click', function () {
      mode = 'cancelled';
      var person = ROSTER.find(function (p) { return p.id === selectedId; });
      var prefill = (person && person.reason) ? person.reason : '';
      $('#cancel-reason').val(prefill);
      $('#cancel-submit').prop('disabled', !prefill.trim());
      updateActionUI();
      $('#cancel-reason').focus();
    });

    $('#cancel-close').on('click', function () {
      mode = null;
      $('#cancel-reason').val('');
      $('#cancel-submit').prop('disabled', true);
      updateActionUI();
    });

    $('#cancel-reason').on('input', function () {
      $('#cancel-submit').prop('disabled', !$(this).val().trim());
    });

    $('#cancel-submit').on('click', function () {
      var value = $('#cancel-reason').val().trim();
      if (!value) return;
      var person = ROSTER.find(function (p) { return p.id === selectedId; });
      if (person) {
        person.reason = value;
        person.cancelled = true;
        person.arrived = false;
        person.late = false;
        person.time = '';
      }
      selectedId = null;
      resetPanels();
      updateActionUI();
      renderRows($('#search').val().trim());
      saveRoster(false, 'cancelled');
    });
  });
</script>
</body>
</html>
