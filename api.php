<?php
// Maralandia: guardado de partidas en la nube (un archivo por código familiar)
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === 'https://oscov11.github.io') {
  header('Access-Control-Allow-Origin: ' . $origin);
  header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
  header('Access-Control-Allow-Headers: Content-Type');
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') exit;

function fin($code, $arr) { http_response_code($code); echo json_encode($arr); exit; }

$dir = __DIR__ . '/partidas';
if (!is_dir($dir)) { @mkdir($dir, 0750, true); }
if (!is_file($dir . '/.htaccess')) {
  @file_put_contents($dir . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$in = null;
if ($method === 'POST') {
  $raw = file_get_contents('php://input', false, null, 0, 3000000);
  $in = json_decode($raw, true);
  if (!is_array($in)) fin(400, ['ok' => false, 'error' => 'json']);
  $code = strtoupper(trim((string)($in['code'] ?? '')));
} else {
  $code = strtoupper(trim((string)($_GET['code'] ?? '')));
}
if (!preg_match('/^[A-Z0-9]{3,12}-[A-Z0-9]{4,10}$/', $code)) fin(400, ['ok' => false, 'error' => 'codigo']);

$file = $dir . '/' . hash('sha256', $code) . '.json';

if ($method === 'GET') {
  if (!is_file($file)) fin(200, ['ok' => true, 'data' => null]);
  $txt = file_get_contents($file);
  echo '{"ok":true,"data":' . $txt . '}';
  exit;
}

if ($method === 'POST') {
  $data = $in['data'] ?? null;
  if (!is_array($data) || !isset($data['sc'])) fin(400, ['ok' => false, 'error' => 'datos']);
  $ts = (int)($data['ts'] ?? 0);
  if (is_file($file)) {
    $old = json_decode(file_get_contents($file), true);
    $oldTs = (int)($old['ts'] ?? 0);
    if ($oldTs > $ts) fin(200, ['ok' => true, 'stale' => true, 'ts' => $oldTs]);
    @copy($file, $file . '.bak');
  }
  $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
  if (file_put_contents($tmp, json_encode($data), LOCK_EX) === false) fin(500, ['ok' => false, 'error' => 'escritura']);
  rename($tmp, $file);
  fin(200, ['ok' => true, 'ts' => $ts]);
}
fin(405, ['ok' => false, 'error' => 'metodo']);
