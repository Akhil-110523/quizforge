<?php
// QuizForge server script.
// Correct answers NEVER leave the server: the quiz is sent without them, and scoring happens here.
const Q_FILE   = __DIR__ . '/questions.xml';
const S_FILE   = __DIR__ . '/scores.xml';
const PER_QUIZ = 5;
const MAX_KEEP = 100;                       // keep the scores file small
const CATS     = ['HTML', 'CSS', 'JavaScript', 'PHP', 'XML'];

function load(string $file): DOMDocument {
    $d = new DOMDocument('1.0', 'UTF-8');
    $d->preserveWhiteSpace = false;
    $d->formatOutput = true;
    $d->load($file, LIBXML_DTDLOAD);
    return $d;
}
function el(DOMDocument $d, string $name, string $text = '', array $attrs = []): DOMElement {
    $e = $d->createElement($name);
    if ($text !== '') $e->appendChild($d->createTextNode($text));
    foreach ($attrs as $k => $v) $e->setAttribute($k, (string)$v);
    return $e;
}
function sendXml(DOMDocument $d): never {
    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: no-store');
    echo $d->saveXML();
    exit;
}
function fail(string $msg, int $code = 400): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $msg]);
    exit;
}

$action = $_GET['action'] ?? '';

// ---------- 1. Build a random quiz (answers stripped, options shuffled) ----------
if ($action === 'quiz') {
    $cat  = in_array($_GET['category'] ?? '', CATS, true) ? $_GET['category'] : 'All';
    $src  = load(Q_FILE);
    $xp   = new DOMXPath($src);
    $path = $cat === 'All' ? '/questions/question' : "/questions/question[@category='$cat']";
    $pool = iterator_to_array($xp->query($path));
    shuffle($pool);

    $out  = new DOMDocument('1.0', 'UTF-8');
    $root = el($out, 'quiz', '', ['category' => $cat]);
    $out->appendChild($root);

    foreach (array_slice($pool, 0, PER_QUIZ) as $n) {
        $q = el($out, 'q', '', ['id' => $n->getAttribute('id')]);
        $q->appendChild(el($out, 'text', $xp->evaluate('string(text)', $n)));
        $opts = [];
        foreach ($xp->query('option', $n) as $i => $o) $opts[] = [$i, $o->textContent];
        shuffle($opts);                      // i keeps the ORIGINAL index so scoring still works
        foreach ($opts as [$i, $label]) $q->appendChild(el($out, 'option', $label, ['i' => $i]));
        $root->appendChild($q);
    }
    sendXml($out);
}

// ---------- 2. Public leaderboard ----------
if ($action === 'board') {
    sendXml(load(S_FILE));
}

// ---------- 3. Score a quiz, save the result ----------
if ($action === 'submit') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('POST required.', 405);

    $name = mb_substr(trim($_POST['name'] ?? ''), 0, 20);
    if ($name === '') $name = 'Anonymous';
    $cat  = in_array($_POST['category'] ?? '', CATS, true) ? $_POST['category'] : 'All';
    $ids  = array_values(array_unique(array_filter(array_map('intval', explode(',', $_POST['ids'] ?? '')))));
    $ans  = json_decode($_POST['answers'] ?? '{}', true);
    if (!is_array($ans)) $ans = [];
    if (!$ids || count($ids) > PER_QUIZ) fail('Invalid quiz.');

    $src = load(Q_FILE);
    $xp  = new DOMXPath($src);
    $res = new DOMDocument('1.0', 'UTF-8');
    $root = el($res, 'result');
    $res->appendChild($root);

    $score = 0; $total = 0;
    foreach ($ids as $id) {
        $n = $xp->query("/questions/question[@id='$id']")->item(0);
        if (!$n) continue;
        $total++;
        $right  = (int)$xp->evaluate('string(answer)', $n);
        $chosen = isset($ans[$id]) ? (int)$ans[$id] : -1;
        $ok     = $chosen === $right;
        if ($ok) $score++;

        $opts = $xp->query('option', $n);
        $item = el($res, 'item', '', ['id' => $id, 'correct' => $ok ? 'yes' : 'no']);
        $item->appendChild(el($res, 'text',    $xp->evaluate('string(text)', $n)));
        $item->appendChild(el($res, 'chosen',  $opts->item($chosen)?->textContent ?? ''));
        $item->appendChild(el($res, 'right',   $opts->item($right)?->textContent ?? ''));
        $item->appendChild(el($res, 'explain', $xp->evaluate('string(explain)', $n)));
        $root->appendChild($item);
    }
    if ($total === 0) fail('No valid questions.');

    $root->setAttribute('name', $name);
    $root->setAttribute('score', $score);
    $root->setAttribute('total', $total);
    $root->setAttribute('percent', (int)round($score / $total * 100));

    // Save to scores.xml (DTD-validated, locked, pruned)
    $sd = load(S_FILE);
    $sd->documentElement->appendChild(el($sd, 'entry', '', [
        'name' => $name, 'category' => $cat, 'score' => $score, 'total' => $total, 'date' => date('Y-m-d'),
    ]));
    $list = $sd->getElementsByTagName('entry');
    while ($list->length > MAX_KEEP) $sd->documentElement->removeChild($list->item(0));
    if (!$sd->validate()) fail('Score could not be saved.', 500);
    file_put_contents(S_FILE, $sd->saveXML(), LOCK_EX);

    sendXml($res);
}

fail('Unknown action.');
