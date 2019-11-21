<?php
declare(strict_types = 1);
require_once (__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "config.inc.php");
/** @var array $conf */
// md5 is ok because we're just using it as a compressor...
$password = hash('md5', (string) ($_POST['password'] ?? ''), true);
if (! hash_equals($password, $conf['password'])) {
    http_response_code(403);
    echo "wrong password!";
    return;
}
if (! session_id()) {
    session_start();
}
if (empty($_SESSION['evaldir']) || empty($_SESSION['evalid'])) {
    $attempts = 0;
    do {
        $evalid = generate_eval_id();
        $evaldir = __DIR__ . DIRECTORY_SEPARATOR . "evals" . DIRECTORY_SEPARATOR . $evalid;
        ++ $attempts;
        if ($attempts > 10) {
            // the chance of this happening should be astronomically low
            // (unless the random generator is broken)
            $msg = "mkdir failed over 10 times, something is wrong! error_get_last():" . print_r(error_get_last(), true) . " last generated evaldir: " . $evaldir;
            echo $msg, "\n";
            throw new \LogicException($msg);
        }
    } while (! mkdir($evaldir, 0755));
    $evaldir .= DIRECTORY_SEPARATOR;
    $_SESSION['evaldir'] = $evaldir;
    $_SESSION['evalid'] = $evalid;
} else {
    $evaldir = $_SESSION['evaldir'];
    $evalid = $_SESSION['evalid'];
}
assert(file_exists($evaldir), 'should be created above');
$code = (string) ($_POST['code'] ?? '');
if (empty($code)) {
    // ... srsly?
    // HTTP 204 No Content
    http_response_code(204);
    return;
}
$old_files = glob($evaldir . "*", GLOB_NOSORT);
$i = 1;
$file = null;
$fp = null;
$duplicate = false;
$duplicate_file = null;
// ... i think i made a mess of this loop =/
while (true) {
    while (in_array(($file = $evaldir . $i . ".php"), $old_files)) {
        // var_dump($file,$old_files) & die();
        ++ $i;
    }
    $duplicate_file = $evaldir . ($i - 1) . ".php";
    if (file_exists($duplicate_file)) {
        $duplicate = file_get_contents($duplicate_file) === $code;
        if ($duplicate) {
            $file = $duplicate_file;
            break;
        }
    }
    $fp = @fopen($file, 'xb');
    if ($fp) {
        break;
    }
}
if (! $duplicate) {
    fwrite($fp, $code);
    fclose($fp);
}
$redirect = "evals/{$_SESSION['evalid']}/" . basename($file);
if (! isset($_GET['disable_redirect']) && ! isset($_POST['disable_redirect'])) {
    http_response_code(303);
    header("Location: {$redirect}");
}
header("Content-Type: text/plain;charset=utf-8");
echo $redirect;
return;

// end of code, functions below.
function generate_eval_id(): string
{
    return generate_password(10);
}

function generate_password(int $len): string
{
    $len = filter_var($len, FILTER_VALIDATE_INT, [
        'options' => [
            'min_range' => 0
        ]
    ]);
    if (false === $len) {
        throw new \InvalidArgumentException('invalid length given, must be an integer >=0');
    }
    $dict = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_';
    $problematic = "1IloO0-_"; // 5S?
    $dict = preg_replace('/[' . preg_quote($problematic, '/') . ']/', "", $dict);
    $randmax = strlen($dict) - 1;
    $ret = '';
    for ($i = 0; $i < $len; ++ $i) {
        $ret .= $dict[random_int(0, $randmax)];
    }
    return $ret;
    // return substr ( strtr ( base64_encode ( random_bytes ( $len ) ), '+/', '-_' ), 0, $len );
}
