const $ = (s) => document.querySelector(s);
const SECONDS = 20;
let qs = [], idx = 0, left = 0, ticker = null, answers = {}, player = '', topic = 'All';

// Load + compile the stylesheet ONCE; every view (quiz, result, board) reuses it.
const processor = fetch('quiz.xsl')
  .then((r) => r.text())
  .then((t) => {
    const p = new XSLTProcessor();
    p.importStylesheet(new DOMParser().parseFromString(t, 'application/xml'));
    return p;
  });

async function render(xmlText, target) {
  const p = await processor;
  const xml = new DOMParser().parseFromString(xmlText, 'application/xml');
  target.replaceChildren(p.transformToFragment(xml, document));
}

function show(view) {
  ['setup', 'play', 'result'].forEach((id) => ($('#' + id).hidden = id !== view));
}
function oops(text) {
  const e = $('#err');
  e.textContent = text || '';
  e.hidden = !text;
}

// ---------- start ----------
$('#startForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  oops();
  player = $('#name').value.trim() || 'Anonymous';
  topic = $('#category').value;
  try {
    const res = await fetch('api.php?action=quiz&category=' + encodeURIComponent(topic));
    await render(await res.text(), $('#stage'));
    qs = [...document.querySelectorAll('#stage .q')];
    if (!qs.length) throw new Error('empty');
    idx = 0; answers = {};
    show('play');
    showQuestion();
  } catch {
    oops('Could not load the quiz. Run the project through a PHP server (see README).');
  }
});

// ---------- play ----------
function showQuestion() {
  qs.forEach((q, i) => q.classList.toggle('on', i === idx));
  $('#count').textContent = `Question ${idx + 1} / ${qs.length}`;
  $('#bar').style.width = (idx / qs.length) * 100 + '%';
  $('#next').textContent = idx === qs.length - 1 ? 'Finish' : 'Next';
  startClock();
}

function startClock() {
  clearInterval(ticker);
  left = SECONDS;
  paintClock();
  ticker = setInterval(() => {
    left--;
    paintClock();
    if (left <= 0) next();               // time up: move on automatically
  }, 1000);
}
function paintClock() {
  const c = $('#clock');
  c.textContent = `${left}s`;
  c.classList.toggle('low', left <= 5);
}

function next() {
  const picked = qs[idx].querySelector('input:checked');
  if (picked) answers[qs[idx].dataset.id] = picked.value;
  if (idx < qs.length - 1) { idx++; showQuestion(); } else finish();
}
$('#next').addEventListener('click', next);

document.addEventListener('keydown', (e) => {
  if ($('#play').hidden) return;
  if (e.key >= '1' && e.key <= '4') qs[idx].querySelectorAll('input')[e.key - 1]?.click();
  if (e.key === 'Enter') next();
});

// ---------- finish ----------
async function finish() {
  clearInterval(ticker);
  $('#bar').style.width = '100%';
  const body = new FormData();
  body.append('name', player);
  body.append('category', topic);
  body.append('ids', qs.map((q) => q.dataset.id).join(','));
  body.append('answers', JSON.stringify(answers));
  try {
    const res = await fetch('api.php?action=submit', { method: 'POST', body });
    if ((res.headers.get('content-type') || '').includes('json')) {
      throw new Error((await res.json()).error);
    }
    await render(await res.text(), $('#resultBox'));
    show('result');
    loadBoard();
  } catch (err) {
    show('setup');
    oops(err.message || 'Could not submit your answers.');
  }
}

$('#again').addEventListener('click', () => { oops(); show('setup'); });

// ---------- leaderboard ----------
async function loadBoard() {
  try {
    const res = await fetch('api.php?action=board');
    await render(await res.text(), $('#board'));
  } catch {
    $('#board').textContent = 'Leaderboard unavailable.';
  }
}
loadBoard();
