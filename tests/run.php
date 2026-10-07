<?php
require __DIR__ . '/../src/CookielessAudiencesException.php';
require __DIR__ . '/../src/Client.php';

use CookielessAudiences\Client;
use CookielessAudiences\CookielessAudiencesException;

$fail = 0;
function check($ok, $label) { global $fail; echo ($ok ? 'ok   ' : 'FAIL ') . $label . "\n"; if (!$ok) { $fail++; } }

$seen = [];
$client = new Client('good', 5, function ($m, $u, $f) use (&$seen) {
    $seen = [$m, $u, $f];
    return $f['api_key'] === 'good' ? '{"status":200,"interests":{"tier1":["INT.a"]},"labels":{"INT.a":"A"}}' : '{"status":401}';
});
$r = $client->segment('https://example.com');
check($seen[0] === 'POST' && $seen[2]['format'] === 'structured', 'segment sends structured form');
check(Client::labelsFor($r) === ['A'], 'labels resolve');
$bad = new Client('bad', 5, function ($m, $u, $f) { return '{"status":401}'; });
try { $bad->segment('x'); check(false, 'error thrown'); } catch (CookielessAudiencesException $e) { check($e->getCode() === 401, 'status 401 raised'); }
exit($fail ? 1 : 0);
