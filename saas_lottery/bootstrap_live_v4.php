<?php
/** Veegame WinGo integration. Adapted from shreewin 86e5048e8b07a04e5fa4e3825db77dfa917ac1bd.
 * Only WinGo enabled. No random results, issue rebinding, overrides, follow simulations,
 * VIP backfills or commission side effects. Database migration starts in preview mode.
 */
date_default_timezone_set('Asia/Kolkata');
$SL_CONFIG = require __DIR__ . '/config_live_v4.php';
require_once dirname(__DIR__) . '/evenvessis/conn.php';
require_once dirname(__DIR__) . '/evenvessis/functions2.php';
require_once dirname(__DIR__) . '/evenvessis/app_core_live_v4.php';
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_errno) throw new RuntimeException('Database unavailable');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');
$conn->query("SET time_zone='+05:30'");
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/provider.php';
require_once __DIR__ . '/legacy_archive.php';
function sl_now_ms()
{
    return (int) floor(microtime(true) * 1000);
}


function sl_input()
{
    $data = $_GET;
    $raw = file_get_contents('php://input');
    if (is_string($raw) && trim($raw) !== '') {
        $json = json_decode($raw, true);
        if (is_array($json)) {
            $data = array_merge($data, $json);
        }
    }
    return $data;
}


function sl_send($payload, $httpStatus)
{
    http_response_code((int) $httpStatus);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}


function sl_ok($data, $successCode = 0)
{
    sl_send(array(
        'data' => $data,
        'code' => 0,
        'msg' => 'Succeed',
        'msgCode' => (int) $successCode,
        'serviceTime' => sl_now_ms()
    ), 200);
}


function sl_fail($code, $message, $msgCode, $httpStatus = 200)
{
    sl_send(array(
        'data' => null,
        'code' => (int) $code,
        'msg' => (string) $message,
        'msgCode' => (int) $msgCode,
        'serviceTime' => sl_now_ms()
    ), $httpStatus);
}


function sl_bearer_token()
{
    $header = '';
    foreach (array('HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION') as $key) {
        if (!empty($_SERVER[$key])) {
            $header = (string) $_SERVER[$key];
            break;
        }
    }
    if ($header === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        if (isset($headers['Authorization'])) {
            $header = (string) $headers['Authorization'];
        } elseif (isset($headers['authorization'])) {
            $header = (string) $headers['authorization'];
        }
    }
    return preg_match('/^Bearer\s+(.+)$/i', trim($header), $match) ? trim($match[1]) : '';
}


function sl_require_user()
{
    global $conn;
    $token = sl_bearer_token();
    if ($token === '') {
        sl_fail(401, 'Login required', 401, 401);
    }

    if (strlen($token)>8192 || !preg_match('/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/',$token)) sl_fail(401, 'Session is invalid', 401, 401);
    $verified = @json_decode(is_jwt_valid($token), true);
    if (isset($verified['payload']['exp']) && (!is_numeric($verified['payload']['exp']) || (int)$verified['payload']['exp']<=time())) sl_fail(401, 'Session expired', 401, 401);
    $userId = is_array($verified) && isset($verified['payload']['id']) ? (int) $verified['payload']['id'] : 0;
    if (!is_array($verified) || ($verified['status'] ?? '') !== 'Success' || $userId < 1) {
        sl_fail(401, 'Session is invalid', 401, 401);
    }

    $stmt = $conn->prepare('SELECT id,mobile FROM shonu_subjects WHERE id=? AND akshinak=? AND status=1 LIMIT 1');
    if (!$stmt) {
        sl_fail(503, 'User service is unavailable', 503, 503);
    }
    $stmt->bind_param('is', $userId, $token);
    $stmt->execute();
    $id = $mobile = null;
    $stmt->bind_result($id, $mobile);
    $found = $stmt->fetch();
    $stmt->close();
    if (!$found) {
        sl_fail(401, 'Session is not active', 401, 401);
    }
    return array('id' => (int) $id, 'mobile' => (string) $mobile);
}


function sl_game_code($input)
{
    global $SL_CONFIG;
    $gameCode = isset($input['gameCode']) ? (string) $input['gameCode'] : 'WinGo_30S';
    if (!isset($SL_CONFIG['games'][$gameCode])) {
        sl_fail(7, 'Unsupported game code', 7, 200);
    }
    return $gameCode;
}


function sl_game_config($gameCode)
{
    global $SL_CONFIG;
    return isset($SL_CONFIG['games'][$gameCode]) ? $SL_CONFIG['games'][$gameCode] : null;
}


function sl_game_family($gameCode)
{
    $config = sl_game_config($gameCode);
    return $config ? (string) $config['lottery'] : '';
}


function sl_game_list()
{
    global $SL_CONFIG;
    $groups = array(
        'WinGo' => array('gameType' => 100, 'gameTypeName' => 'WinGo', 'sort' => 1, 'gameList' => array()),
        'TrxWinGo' => array('gameType' => 103, 'gameTypeName' => 'TrxWinGo', 'sort' => 6, 'gameList' => array()),
        'K3' => array('gameType' => 101, 'gameTypeName' => 'K3', 'sort' => 5, 'gameList' => array()),
        'D5' => array('gameType' => 102, 'gameTypeName' => '5D', 'sort' => 4, 'gameList' => array()),
        'MotoRace' => array('gameType' => 105, 'gameTypeName' => 'MotoRace', 'sort' => 2, 'gameList' => array())
    );
    foreach ($SL_CONFIG['games'] as $gameCode => $game) {
        $family = (string) $game['lottery'];
        if (!isset($groups[$family])) {
            continue;
        }
        $groups[$family]['gameList'][] = array(
            'gameCode' => $gameCode,
            'gameName' => (string) $game['name'],
            'sort' => (int) $game['sort'],
            'state' => sl_game_enabled($gameCode) ? 1 : 2,
            'intervalMinute' => (float) $game['interval']
        );
    }
    return array_values($groups);
}


function sl_game_rates($gameCode)
{
    $family = sl_game_family($gameCode);
    if ($family === 'WinGo' || $family === 'TrxWinGo') {
        return array(
            array('playTypeId'=>54,'playType'=>'Color','playBet'=>'violet','state'=>1,'playRate'=>4.5),
            array('playTypeId'=>52,'playType'=>'Color','playBet'=>'red','state'=>1,'playRate'=>1.5),
            array('playTypeId'=>53,'playType'=>'Color','playBet'=>'violet','state'=>1,'playRate'=>4.5),
            array('playTypeId'=>50,'playType'=>'Color','playBet'=>'green','state'=>1,'playRate'=>1.5),
            array('playTypeId'=>49,'playType'=>'Color','playBet'=>'green','state'=>1,'playRate'=>2.0),
            array('playTypeId'=>51,'playType'=>'Color','playBet'=>'red','state'=>1,'playRate'=>2.0),
            array('playTypeId'=>55,'playType'=>'Num','playBet'=>'0-9','state'=>1,'playRate'=>9.0),
            array('playTypeId'=>56,'playType'=>'BigSmall','playBet'=>'big','state'=>1,'playRate'=>2.0),
            array('playTypeId'=>57,'playType'=>'BigSmall','playBet'=>'small','state'=>1,'playRate'=>2.0)
        );
    }
    if ($family === 'K3') {
        $sumRates = array(3=>207.36,4=>69.12,5=>34.56,6=>20.74,7=>13.83,8=>9.88,9=>8.30,10=>7.68,11=>7.68,12=>8.30,13=>9.88,14=>13.83,15=>20.74,16=>34.56,17=>69.12,18=>207.36);
        $rates = array();
        foreach ($sumRates as $number => $rate) {
            $rates[] = array('playTypeId'=>55+$number,'playType'=>'SumNum','playBet'=>(string)$number,'state'=>1,'playRate'=>$rate);
        }
        $rates[] = array('playTypeId'=>74,'playType'=>'SumBigSmall','playBet'=>'HL','state'=>1,'playRate'=>2.0);
        $rates[] = array('playTypeId'=>75,'playType'=>'SumOddEven','playBet'=>'OE','state'=>1,'playRate'=>2.0);
        $rates[] = array('playTypeId'=>76,'playType'=>'NumDiff2','playBet'=>'2BT','state'=>1,'playRate'=>6.91);
        $rates[] = array('playTypeId'=>77,'playType'=>'NumSame2','playBet'=>'2TD','state'=>1,'playRate'=>13.83);
        $rates[] = array('playTypeId'=>78,'playType'=>'NumSame2Mult','playBet'=>'2TF','state'=>1,'playRate'=>69.12);
        $rates[] = array('playTypeId'=>79,'playType'=>'NumSame3','playBet'=>'3TD','state'=>1,'playRate'=>207.36);
        $rates[] = array('playTypeId'=>80,'playType'=>'NumSame3All','playBet'=>'3TT','state'=>1,'playRate'=>34.56);
        $rates[] = array('playTypeId'=>81,'playType'=>'NumDiff3','playBet'=>'3BT','state'=>1,'playRate'=>34.56);
        $rates[] = array('playTypeId'=>82,'playType'=>'NumNear3All','playBet'=>'3LT','state'=>1,'playRate'=>8.64);
        return $rates;
    }
    if ($family === 'D5') {
        $positions = array('First','Second','Third','Fourth','Fifth');
        $rates = array();
        $id = 83;
        foreach ($positions as $position) {
            $rates[] = array('playTypeId'=>$id++,'playType'=>$position.'Num','playBet'=>'0-9','state'=>1,'playRate'=>9.0);
            $rates[] = array('playTypeId'=>$id++,'playType'=>$position.'BigSmall','playBet'=>'H','state'=>1,'playRate'=>2.0);
            $rates[] = array('playTypeId'=>$id++,'playType'=>$position.'BigSmall','playBet'=>'L','state'=>1,'playRate'=>2.0);
            $rates[] = array('playTypeId'=>$id++,'playType'=>$position.'OddEven','playBet'=>'O','state'=>1,'playRate'=>2.0);
            $rates[] = array('playTypeId'=>$id++,'playType'=>$position.'OddEven','playBet'=>'E','state'=>1,'playRate'=>2.0);
        }
        $rates[] = array('playTypeId'=>108,'playType'=>'SumBigSmall','playBet'=>'H','state'=>1,'playRate'=>2.0);
        $rates[] = array('playTypeId'=>109,'playType'=>'SumBigSmall','playBet'=>'L','state'=>1,'playRate'=>2.0);
        $rates[] = array('playTypeId'=>110,'playType'=>'SumOddEven','playBet'=>'O','state'=>1,'playRate'=>2.0);
        $rates[] = array('playTypeId'=>111,'playType'=>'SumOddEven','playBet'=>'E','state'=>1,'playRate'=>2.0);
        return $rates;
    }
    if ($family === 'MotoRace') {
        $rates = array();
        $id = 130;
        foreach (array('First','Second','Third') as $position) {
            $rates[] = array('playTypeId'=>$id++,'playType'=>$position.'Num','playBet'=>'1-10','state'=>1,'playRate'=>9.8);
            $rates[] = array('playTypeId'=>$id++,'playType'=>$position.'OddEven','playBet'=>'Odd','state'=>1,'playRate'=>2.0);
            $rates[] = array('playTypeId'=>$id++,'playType'=>$position.'OddEven','playBet'=>'Even','state'=>1,'playRate'=>2.0);
            $rates[] = array('playTypeId'=>$id++,'playType'=>$position.'BigSmall','playBet'=>'Big','state'=>1,'playRate'=>2.0);
            $rates[] = array('playTypeId'=>$id++,'playType'=>$position.'BigSmall','playBet'=>'Small','state'=>1,'playRate'=>2.0);
        }
        return $rates;
    }
    return array();
}


function sl_game_info($gameCode)
{
    $family = sl_game_family($gameCode);
    return array(
        'state' => sl_game_enabled($gameCode) ? 1 : 2,
        'betScopes' => $family === 'MotoRace' ? array(1,10,50,100) : array(1,10,100,1000),
        'betMultiples' => array(1,5,10,20,100),
        'webSocketUrl' => '',
        'rates' => sl_game_rates($gameCode)
    );
}


function sl_bet_limits($gameCode)
{
    $seen = array();
    $limits = array();
    foreach (sl_game_rates($gameCode) as $rate) {
        $type = (string) $rate['playType'];
        $content = (string) $rate['playBet'];
        $key = $type . '|' . $content;
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $limits[] = array(
                'playType'=>$type,
                'betContent'=>$content,
                'maxPayoutAmount'=>100000,
                // Retain these aliases for older clients using the same API.
                'minimum'=>1,
                'maximum'=>100000
            );
        }
    }
    return $limits;
}


function sl_game_introduce($gameCode)
{
    $family = sl_game_family($gameCode);
    if ($family === 'K3') {
        return '<p>Select a dice total, pair, triple or combination before the countdown closes. Three dice (1-6) form each result.</p>';
    }
    if ($family === 'D5') {
        return '<p>Select a digit, Big/Small or Odd/Even for any of the five positions, or select a property of the five-digit sum.</p>';
    }
    if ($family === 'MotoRace') {
        return '<p>Select the number or Big/Small/Odd/Even property for the first, second or third finishing position.</p>';
    }
    return '<p>Choose a number, color, Big or Small before the betting countdown closes.</p>';
}


function sl_interval_seconds($gameCode)
{
    $game = sl_game_config($gameCode);
    return max(30, (int) round(((float) $game['interval']) * 60));
}


function sl_result_color($number)
{
    $number = (int) $number;
    $colors = array();
    if ($number === 0 || $number === 5) {
        $colors[] = 'violet';
    }
    if (in_array($number, array(1,3,5,7,9), true)) {
        array_unshift($colors, 'green');
    } else {
        array_unshift($colors, 'red');
    }
    return implode(',', $colors);
}


function sl_combination_count($n, $r)
{
    $n = (int) $n;
    $r = (int) $r;
    if ($r < 0 || $n < $r) {
        return 0;
    }
    if ($r === 0 || $n === $r) {
        return 1;
    }
    $r = min($r, $n - $r);
    $result = 1;
    for ($i = 1; $i <= $r; $i++) {
        $result = (int) (($result * ($n - $r + $i)) / $i);
    }
    return $result;
}


function sl_selected_numbers($value, $min, $max)
{
    $parts = explode('_', (string) $value);
    $numbers = array();
    foreach ($parts as $part) {
        if ($part === '' || !ctype_digit($part)) {
            return null;
        }
        $number = (int) $part;
        if ($number < $min || $number > $max || in_array($number, $numbers, true)) {
            return null;
        }
        $numbers[] = $number;
    }
    return $numbers;
}


function sl_bet_units($gameCode, $content)
{
    $family = sl_game_family($gameCode);
    $content = trim((string) $content);
    if ($family === 'WinGo' || $family === 'TrxWinGo') {
        return preg_match('/^(Num_[0-9]|Color_(green|red|violet)|BigSmall_(big|small))$/i', $content) ? 1 : 0;
    }
    if ($family === 'K3') {
        if (preg_match('/^SumNum_(\d{1,2})$/', $content, $m)) {
            return (int) $m[1] >= 3 && (int) $m[1] <= 18 ? 1 : 0;
        }
        if (preg_match('/^(SumBigSmall_(Big|Small)|SumOddEven_(Odd|Even)|NumSame3All_AAA|NumNear3All_ABC)$/', $content)) {
            return 1;
        }
        if (preg_match('/^NumSame2_([1-6])\1$/', $content) || preg_match('/^NumSame3_([1-6])\1\1$/', $content)) {
            return 1;
        }
        if (preg_match('/^NumSame2Mult_([1-6])\1_(.+)$/', $content, $m)) {
            $singles = sl_selected_numbers($m[2], 1, 6);
            return $singles && !in_array((int) $m[1], $singles, true) ? count($singles) : 0;
        }
        if (strpos($content, 'NumDiff3_') === 0) {
            $numbers = sl_selected_numbers(substr($content, 9), 1, 6);
            return $numbers && count($numbers) >= 3 ? sl_combination_count(count($numbers), 3) : 0;
        }
        if (strpos($content, 'NumDiff2_') === 0) {
            $numbers = sl_selected_numbers(substr($content, 9), 1, 6);
            return $numbers && count($numbers) >= 2 ? sl_combination_count(count($numbers), 2) : 0;
        }
        return 0;
    }
    if ($family === 'D5') {
        if (preg_match('/^(First|Second|Third|Fourth|Fifth)Num_[0-9]$/', $content)) {
            return 1;
        }
        if (preg_match('/^(First|Second|Third|Fourth|Fifth)BigSmall_(Big|Small)$/', $content)) {
            return 1;
        }
        if (preg_match('/^(First|Second|Third|Fourth|Fifth)OddEven_(Odd|Even)$/', $content)) {
            return 1;
        }
        return preg_match('/^Sum(BigSmall_(Big|Small)|OddEven_(Odd|Even))$/', $content) ? 1 : 0;
    }
    if ($family === 'MotoRace') {
        if (preg_match('/^(First|Second|Third)Num_(10|[1-9])$/', $content)) {
            return 1;
        }
        return preg_match('/^(First|Second|Third)(BigSmall_(Big|Small)|OddEven_(Odd|Even))$/', $content) ? 1 : 0;
    }
    return 0;
}


function sl_normalize_bets($gameCode, $rawContent)
{
    $contents = is_array($rawContent) ? array_values($rawContent) : array($rawContent);
    if (!$contents || count($contents) > 100) {
        sl_fail(342, 'Bet content is invalid', 342, 200);
    }
    $bets = array();
    foreach ($contents as $content) {
        if (!is_scalar($content)) {
            sl_fail(342, 'Bet content is invalid', 342, 200);
        }
        $content = trim((string) $content);
        $units = sl_bet_units($gameCode, $content);
        if ($units < 1) {
            sl_fail(342, 'Bet content is invalid', 342, 200);
        }
        $bets[] = array('content'=>$content,'units'=>$units);
    }
    return $bets;
}


function sl_k3_sum_rate($sum)
{
    $rates = array(3=>207.36,4=>69.12,5=>34.56,6=>20.74,7=>13.83,8=>9.88,9=>8.30,10=>7.68,11=>7.68,12=>8.30,13=>9.88,14=>13.83,15=>20.74,16=>34.56,17=>69.12,18=>207.36);
    return isset($rates[(int) $sum]) ? $rates[(int) $sum] : 0.0;
}


function sl_evaluate_bet($gameCode, $content, $result)
{
    $family = sl_game_family($gameCode);
    $premium = (string) $result['premium'];
    if ($family === 'WinGo' || $family === 'TrxWinGo') {
        $number = (int) $premium;
        $parts = explode('_', strtolower($content), 2);
        if (count($parts) !== 2) {
            return array(0, 0.0);
        }
        list($type, $pick) = $parts;
        if ($type === 'num') {
            return array((string) $number === $pick ? 1 : 0, 9.0);
        }
        if ($type === 'bigsmall') {
            $won = ($pick === 'big' && $number >= 5) || ($pick === 'small' && $number <= 4);
            return array($won ? 1 : 0, 2.0);
        }
        if ($type === 'color') {
            if ($pick === 'violet') {
                return array(in_array($number, array(0,5), true) ? 1 : 0, 4.5);
            }
            if ($pick === 'green') {
                return array(in_array($number, array(1,3,5,7,9), true) ? 1 : 0, $number === 5 ? 1.5 : 2.0);
            }
            if ($pick === 'red') {
                return array(in_array($number, array(0,2,4,6,8), true) ? 1 : 0, $number === 0 ? 1.5 : 2.0);
            }
        }
        return array(0, 0.0);
    }

    if ($family === 'K3') {
        $dice = array_map('intval', str_split($premium));
        $sum = array_sum($dice);
        $counts = array_count_values($dice);
        if (preg_match('/^SumNum_(\d{1,2})$/', $content, $m)) {
            return array($sum === (int) $m[1] ? 1 : 0, sl_k3_sum_rate((int) $m[1]));
        }
        if (preg_match('/^SumBigSmall_(Big|Small)$/', $content, $m)) {
            $won = ($m[1] === 'Big' && $sum >= 11) || ($m[1] === 'Small' && $sum <= 10);
            return array($won ? 1 : 0, 2.0);
        }
        if (preg_match('/^SumOddEven_(Odd|Even)$/', $content, $m)) {
            $won = ($m[1] === 'Odd' && $sum % 2 === 1) || ($m[1] === 'Even' && $sum % 2 === 0);
            return array($won ? 1 : 0, 2.0);
        }
        if (preg_match('/^NumSame2_([1-6])\1$/', $content, $m)) {
            return array(($counts[(int) $m[1]] ?? 0) === 2 ? 1 : 0, 13.83);
        }
        if (preg_match('/^NumSame2Mult_([1-6])\1_(.+)$/', $content, $m)) {
            $pair = (int) $m[1];
            $singles = sl_selected_numbers($m[2], 1, 6) ?: array();
            $third = null;
            if (($counts[$pair] ?? 0) === 2) {
                foreach ($dice as $die) {
                    if ($die !== $pair) {
                        $third = $die;
                        break;
                    }
                }
            }
            return array($third !== null && in_array($third, $singles, true) ? 1 : 0, 69.12);
        }
        if (preg_match('/^NumSame3_([1-6])\1\1$/', $content, $m)) {
            return array(($counts[(int) $m[1]] ?? 0) === 3 ? 1 : 0, 207.36);
        }
        if ($content === 'NumSame3All_AAA') {
            return array(count($counts) === 1 ? 1 : 0, 34.56);
        }
        if (strpos($content, 'NumDiff3_') === 0) {
            $selected = sl_selected_numbers(substr($content, 9), 1, 6) ?: array();
            $unique = array_values(array_unique($dice));
            $won = count($unique) === 3 && count(array_intersect($unique, $selected)) === 3;
            return array($won ? 1 : 0, 34.56);
        }
        if ($content === 'NumNear3All_ABC') {
            $unique = array_values(array_unique($dice));
            sort($unique);
            $won = count($unique) === 3 && $unique[1] === $unique[0] + 1 && $unique[2] === $unique[1] + 1;
            return array($won ? 1 : 0, 8.64);
        }
        if (strpos($content, 'NumDiff2_') === 0) {
            $selected = sl_selected_numbers(substr($content, 9), 1, 6) ?: array();
            $matched = array_values(array_intersect(array_values(array_unique($dice)), $selected));
            return array(sl_combination_count(count($matched), 2), 6.91);
        }
        return array(0, 0.0);
    }

    if ($family === 'D5') {
        $digits = array_map('intval', str_split($premium));
        $positions = array('First'=>0,'Second'=>1,'Third'=>2,'Fourth'=>3,'Fifth'=>4);
        if (preg_match('/^(First|Second|Third|Fourth|Fifth)Num_([0-9])$/', $content, $m)) {
            return array($digits[$positions[$m[1]]] === (int) $m[2] ? 1 : 0, 9.0);
        }
        if (preg_match('/^(First|Second|Third|Fourth|Fifth)BigSmall_(Big|Small)$/', $content, $m)) {
            $value = $digits[$positions[$m[1]]];
            $won = ($m[2] === 'Big' && $value >= 5) || ($m[2] === 'Small' && $value <= 4);
            return array($won ? 1 : 0, 2.0);
        }
        if (preg_match('/^(First|Second|Third|Fourth|Fifth)OddEven_(Odd|Even)$/', $content, $m)) {
            $value = $digits[$positions[$m[1]]];
            $won = ($m[2] === 'Odd' && $value % 2 === 1) || ($m[2] === 'Even' && $value % 2 === 0);
            return array($won ? 1 : 0, 2.0);
        }
        $sum = array_sum($digits);
        if (preg_match('/^SumBigSmall_(Big|Small)$/', $content, $m)) {
            $won = ($m[1] === 'Big' && $sum >= 23) || ($m[1] === 'Small' && $sum <= 22);
            return array($won ? 1 : 0, 2.0);
        }
        if (preg_match('/^SumOddEven_(Odd|Even)$/', $content, $m)) {
            $won = ($m[1] === 'Odd' && $sum % 2 === 1) || ($m[1] === 'Even' && $sum % 2 === 0);
            return array($won ? 1 : 0, 2.0);
        }
        return array(0, 0.0);
    }

    if ($family === 'MotoRace') {
        $rank = array_map('intval', explode(',', $premium));
        $positions = array('First'=>0,'Second'=>1,'Third'=>2);
        if (preg_match('/^(First|Second|Third)Num_(10|[1-9])$/', $content, $m)) {
            return array($rank[$positions[$m[1]]] === (int) $m[2] ? 1 : 0, 9.8);
        }
        if (preg_match('/^(First|Second|Third)BigSmall_(Big|Small)$/', $content, $m)) {
            $value = $rank[$positions[$m[1]]];
            $won = ($m[2] === 'Big' && $value >= 6) || ($m[2] === 'Small' && $value <= 5);
            return array($won ? 1 : 0, 2.0);
        }
        if (preg_match('/^(First|Second|Third)OddEven_(Odd|Even)$/', $content, $m)) {
            $value = $rank[$positions[$m[1]]];
            $won = ($m[2] === 'Odd' && $value % 2 === 1) || ($m[2] === 'Even' && $value % 2 === 0);
            return array($won ? 1 : 0, 2.0);
        }
    }
    return array(0, 0.0);
}

/**
 * Return the net amount credited for a winning bet.
 *
 * Game odds remain gross display odds (for example 2X). Settlement always
 * deducts the mandatory 2% payout tax once and stores only the net amount, so
 * the winner popup, wallet, ledger and record APIs cannot disagree.
 */


function sl_tax_percent()
{
    $taxPercent = function_exists('app_setting') ? (float) app_setting('lottery_payout_tax_percent', '2.00') : 2.0;
    return max(0.0, min(100.0, $taxPercent));
}


function sl_stake_tax_fee($stake)
{
    return round(max(0.0, (float) $stake) * sl_tax_percent() / 100.0, 4);
}


function sl_net_payout_after_tax($grossPayout)
{
    $grossPayout = max(0.0, (float) $grossPayout);
    if ($grossPayout <= 0.0) {
        return 0.0;
    }
    return round($grossPayout * (100.0 - sl_tax_percent()) / 100.0, 2);
}


function sl_settle_issue($gameCode, $issue, $resultItem)
{
    global $conn;
    if (!app_setting_bool('auto_settlement_enabled', true)) {
        return;
    }
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("SELECT id,user_id,bet_content,amount,bet_multiple,stake FROM veegame_saas_bets WHERE game_code=? AND issue_number=? AND status='pending' FOR UPDATE");
        $stmt->bind_param('ss', $gameCode, $issue);
        $stmt->execute();
        $result = $stmt->get_result();
        $bets = array();
        while ($result && ($row = $result->fetch_assoc())) {
            $bets[] = $row;
        }
        $stmt->close();

        foreach ($bets as $bet) {
            list($winningUnits, $rate) = sl_evaluate_bet($gameCode, (string) $bet['bet_content'], $resultItem);
            $unitStake = (float) $bet['amount'] * (int) $bet['bet_multiple'];
            $grossPayout = $winningUnits > 0 ? round($unitStake * $rate * $winningUnits, 4) : 0.0;
            $payout = $winningUnits > 0 ? sl_net_payout_after_tax($grossPayout) : 0.0;
            $taxFee = sl_stake_tax_fee((float) $bet['stake']);
            $status = $winningUnits > 0 ? 'won' : 'lost';
            $userId = (int) $bet['user_id'];
            $betId = (int) $bet['id'];

            if ($payout > 0) {
                $wallet = $conn->prepare('SELECT motta FROM shonu_kaichila WHERE balakedara=? FOR UPDATE');
                $wallet->bind_param('i', $userId);
                $wallet->execute();
                $walletResult = $wallet->get_result();
                $walletRow = $walletResult ? $walletResult->fetch_assoc() : null;
                $wallet->close();
                if (!$walletRow) {
                    throw new RuntimeException('Wallet row missing during settlement');
                }
                $before = (float) $walletRow['motta'];
                $after = round($before + $payout, 4);
                $updateWallet = $conn->prepare('UPDATE shonu_kaichila SET motta=? WHERE balakedara=?');
                $updateWallet->bind_param('di', $after, $userId);
                $updateWallet->execute();
                $updateWallet->close();

                $entryKey = 'saas-settlement:' . $betId;
                $entryType = 'bet_payout';
                $ledger = $conn->prepare('INSERT IGNORE INTO veegame_saas_wallet_ledger(user_id,entry_key,entry_type,amount,balance_before,balance_after,created_at) VALUES (?,?,?,?,?,?,NOW())');
                $ledger->bind_param('issddd', $userId, $entryKey, $entryType, $payout, $before, $after);
                $ledger->execute();
                $ledger->close();
            }

            $premium = (string) $resultItem['premium'];
            $update = $conn->prepare("UPDATE veegame_saas_bets SET status=?,result_premium=?,payout=?,tax_fee=?,settled_at=NOW() WHERE id=? AND status='pending'");
            $update->bind_param('ssddi', $status, $premium, $payout, $taxFee, $betId);
            $update->execute();
            $update->close();
        }
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}


function sl_wallet_balance($userId)
{
    global $conn;
    $stmt = $conn->prepare('SELECT motta FROM shonu_kaichila WHERE balakedara=? LIMIT 1');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $balance = null;
    $stmt->bind_result($balance);
    $found = $stmt->fetch();
    $stmt->close();
    return $found && is_numeric($balance) ? round((float) $balance, 4) : 0.0;
}


function sl_place_bet($user, $input)
{
    global $conn, $SL_CONFIG;
    if (!app_setting_bool('betting_enabled', true)) {
        sl_fail(405, 'Betting is temporarily disabled', 405, 200);
    }
    $gameCode = sl_game_code($input);
    if (!sl_game_enabled($gameCode)) {
        sl_fail(405, 'Game is under maintenance', 405, 200);
    }
    $issue = isset($input['issueNumber']) ? (string) $input['issueNumber'] : '';
    if (!preg_match('/^[A-Za-z0-9_-]{12,128}$/',(string)($input['random']??''))) sl_fail(7,'Request nonce is required',7,400);
    $rawContent = isset($input['betContent']) ? $input['betContent'] : null;
    $amount = isset($input['amount']) && is_numeric($input['amount']) ? (float) $input['amount'] : 0.0;
    $multipleRaw = $input['betMultiple'] ?? ($input['quantity'] ?? ($input['betCount'] ?? 0));
    $multiple = is_numeric($multipleRaw) ? (int) $multipleRaw : 0;
    if (!preg_match('/^\d{8,40}$/', $issue)) {
        sl_fail(342, 'Issue number is invalid', 342, 200);
    }
    if (!is_finite($amount) || !in_array($amount, [1.0, 10.0, 100.0, 1000.0], true) || !is_numeric($multipleRaw) || (float)$multipleRaw !== (float)$multiple) sl_fail(7, 'Invalid stake units', 7, 400);
    $bets = sl_normalize_bets($gameCode, $rawContent);
    if (count($bets) > 30) sl_fail(7, 'Too many selections', 7, 400);
    $totalUnits = 0;
    foreach ($bets as $bet) {
        $totalUnits += (int) $bet['units'];
    }
    $stake = round($amount * $multiple * $totalUnits, 4);
    if ($amount < 1 || $multiple < 1 || $multiple > 100000 || $stake <= 0 || $stake > (float) $SL_CONFIG['maximum_stake']) {
        sl_fail(401, 'Bet amount is invalid', 401, 200);
    }

    $current = sl_provider_current($gameCode);
    if (!$current) {
        sl_fail(503, 'Result feed is unavailable; betting is paused', 503, 200);
    }
    $currentIssue = (string) $current['current']['issueNumber'];
    $endTime = (int) $current['current']['endTime'];
    $control = app_game_control($gameCode);
    $lockSeconds = max((int) $SL_CONFIG['bet_lock_seconds'], (int) ($control['lock_before_close_seconds'] ?? 0));
    if ($currentIssue !== $issue || $endTime <= sl_now_ms() + ($lockSeconds * 1000)) {
        sl_fail(404, 'Betting has stopped for the current period', 404, 200);
    }

    $known = sl_provider_history($gameCode, 1, 10);
    if (!$known) sl_fail(503, 'Result feed unavailable; betting paused', 503, 503);
    foreach ($known['data']['list'] as $row) {
        if ((string)$row['issueNumber'] === $issue) sl_fail(409, 'Result already published', 409, 409);
    }
    $userId = (int) $user['id'];
    $requestSeed = $userId . '|' . $gameCode . '|' . $issue . '|' . json_encode($bets) . '|' . number_format($amount, 4, '.', '') . '|' . $multiple . '|' . (string) ($input['signature'] ?? '') . '|' . (string) ($input['random'] ?? '');
    $groupKey = hash('sha256', $requestSeed);

    $conn->begin_transaction();
    try {
        // Reserve the whole client request before touching the wallet. This
        // makes simultaneous retries idempotent even when one request has
        // several bet-content rows.
        $reserve = $conn->prepare('INSERT IGNORE INTO veegame_saas_requests(request_group_key,user_id,game_code,issue_number,created_at) VALUES (?,?,?,?,NOW())');
        $reserve->bind_param('siss', $groupKey, $userId, $gameCode, $issue);
        $reserve->execute();
        $reserved = $reserve->affected_rows === 1;
        $reserve->close();
        if (!$reserved) {
            $duplicate = $conn->prepare('SELECT id FROM veegame_saas_bets WHERE request_group_key=? ORDER BY id ASC LIMIT 1');
            $duplicate->bind_param('s', $groupKey);
            $duplicate->execute();
            $duplicateId = null;
            $duplicate->bind_result($duplicateId);
            $duplicate->fetch();
            $duplicate->close();
            $conn->commit();
            return array('betId'=>(int)$duplicateId,'accepted'=>true,'duplicate'=>true,'balance'=>sl_wallet_balance($userId));
        }

        $wallet = $conn->prepare('SELECT motta FROM shonu_kaichila WHERE balakedara=? FOR UPDATE');
        $wallet->bind_param('i', $userId);
        $wallet->execute();
        $walletResult = $wallet->get_result();
        $walletRow = $walletResult ? $walletResult->fetch_assoc() : null;
        $wallet->close();
        if (!$walletRow) {
            throw new RuntimeException('Wallet row is missing');
        }
        if (sl_now_ms() >= $endTime - $lockSeconds * 1000 || !app_setting_bool('betting_enabled', false)) {
            $conn->rollback();
            sl_fail(409, 'Betting has closed', 409, 409);
        }
        $before = (float) $walletRow['motta'];
        if ($before + 0.00001 < $stake) {
            $conn->rollback();
            sl_fail(1, 'Balance is not enough', 142, 200);
        }
        $after = round($before - $stake, 4);
        $betIds = array();
        foreach ($bets as $index => $bet) {
            $content = (string) $bet['content'];
            $units = (int) $bet['units'];
            $rowStake = round($amount * $multiple * $units, 4);
            $rowTax = sl_stake_tax_fee($rowStake);
            $requestKey = hash('sha256', $groupKey . '|' . $index . '|' . $content);
            $insert = $conn->prepare("INSERT INTO veegame_saas_bets(user_id,game_code,issue_number,bet_content,amount,bet_multiple,bet_units,stake,tax_fee,request_group_key,request_key,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,'pending',NOW())");
            $insert->bind_param('isssdiiddss', $userId, $gameCode, $issue, $content, $amount, $multiple, $units, $rowStake, $rowTax, $groupKey, $requestKey);
            $insert->execute();
            $betIds[] = (int) $insert->insert_id;
            $insert->close();
        }

        $update = $conn->prepare('UPDATE shonu_kaichila SET motta=? WHERE balakedara=?');
        $update->bind_param('di', $after, $userId);
        $update->execute();
        $update->close();



        $entryKey = 'saas-bet-group:' . $groupKey;
        $entryType = 'bet_stake';
        $negativeStake = -$stake;
        $ledger = $conn->prepare('INSERT INTO veegame_saas_wallet_ledger(user_id,entry_key,entry_type,amount,balance_before,balance_after,created_at) VALUES (?,?,?,?,?,?,NOW())');
        $ledger->bind_param('issddd', $userId, $entryKey, $entryType, $negativeStake, $before, $after);
        $ledger->execute();
        $ledger->close();

        $conn->commit();
        return array(
            'betId'=>$betIds[0] ?? 0,
            'betIds'=>$betIds,
            'accepted'=>true,
            'balance'=>$after,
            'stake'=>$stake
        );
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}


function sl_record_page($userId, $input)
{
    global $conn;
    $pageNo = max(1, isset($input['pageNo']) ? (int) $input['pageNo'] : 1);
    $pageSize = min(50, max(1, isset($input['pageSize']) ? (int) $input['pageSize'] : 10));
    $offset = ($pageNo - 1) * $pageSize;
    $gameCode = isset($input['gameCode']) && (string) $input['gameCode'] !== '' ? sl_game_code($input) : '';
    if ($gameCode !== '') {
        try {
            sl_sync_results($gameCode);
        } catch (Throwable $ignored) {
        }
    }

    if ($gameCode !== '') {
        $count = $conn->prepare('SELECT COUNT(*) FROM veegame_saas_bets WHERE user_id=? AND game_code=?');
        $count->bind_param('is', $userId, $gameCode);
    } else {
        $count = $conn->prepare('SELECT COUNT(*) FROM veegame_saas_bets WHERE user_id=?');
        $count->bind_param('i', $userId);
    }
    $count->execute();
    $total = 0;
    $count->bind_result($total);
    $count->fetch();
    $count->close();

    $fields = 'id,game_code,issue_number,bet_content,amount,bet_multiple,bet_units,stake,status,result_premium,payout,tax_fee,created_at';
    if ($gameCode !== '') {
        $stmt = $conn->prepare('SELECT '.$fields.' FROM veegame_saas_bets WHERE user_id=? AND game_code=? ORDER BY id DESC LIMIT ? OFFSET ?');
        $stmt->bind_param('isii', $userId, $gameCode, $pageSize, $offset);
    } else {
        $stmt = $conn->prepare('SELECT '.$fields.' FROM veegame_saas_bets WHERE user_id=? ORDER BY id DESC LIMIT ? OFFSET ?');
        $stmt->bind_param('iii', $userId, $pageSize, $offset);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $list = array();
    while ($result && ($row = $result->fetch_assoc())) {
        $state = $row['status'] === 'pending' ? 2 : ($row['status'] === 'won' ? 1 : 0);
        $stake = (float) $row['stake'];
        $payout = (float) $row['payout'];
        $taxFee = isset($row['tax_fee']) ? (float) $row['tax_fee'] : 0.0;
        if ($taxFee <= 0.0 && $stake > 0.0) { $taxFee = sl_stake_tax_fee($stake); }
        $winLose = $row['status'] === 'won' ? round($payout - $stake, 4) : ($row['status'] === 'lost' ? -$stake : 0.0);
        $premium = $row['result_premium'] === null ? '' : (string) $row['result_premium'];
        $recordFamily = sl_game_family((string) $row['game_code']);
        $isWingo = $recordFamily === 'WinGo' || $recordFamily === 'TrxWinGo';
        $contentParts = explode('_', (string) $row['bet_content'], 2);
        $playType = (string) ($contentParts[0] ?? '');
        $selectType = (string) ($contentParts[1] ?? '');
        // The bundled record components use `number` as a truthy result flag
        // (and expect "0" to remain a string), while K3 renders the dice from
        // `premium`. Return both the current and legacy aliases so every game
        // record view can render the same settled row.
        $recordNumber = ($premium !== '' && ($isWingo || $recordFamily === 'K3')) ? $premium : '';
        $list[] = array(
            'orderNo'=>(string)$row['id'],'issueNumber'=>(string)$row['issue_number'],'gameCode'=>(string)$row['game_code'],
            'betContent'=>(string)$row['bet_content'],'playType'=>$playType,'selectType'=>$selectType,
            'amount'=>$stake,'unitAmount'=>(float)$row['amount'],'betMultiple'=>(int)$row['bet_multiple'],'betCount'=>(int)$row['bet_multiple'],
            'betUnits'=>(int)$row['bet_units'],'realAmount'=>max(0.0, round($stake-$taxFee,4)),'fee'=>$taxFee,'serviceCharge'=>$taxFee,'tax'=>$taxFee,'taxAmount'=>$taxFee,'taxRate'=>sl_tax_percent(),'state'=>$state,'premium'=>$premium,
            'winLoseAmount'=>$winLose,'betTime'=>(string)$row['created_at'],'orderNumber'=>(string)$row['id'],
            'betAmount'=>$stake,'number'=>$recordNumber,'resultNumber'=>$isWingo && $premium !== '' ? (int)$premium : null,
            'color'=>$isWingo && $premium !== '' ? sl_result_color((int)$premium) : '',
            'winAmount'=>$payout,'profitAmount'=>$state === 2 ? 0.0 : abs($winLose),
            'createTime'=>(string)$row['created_at'],'addTime'=>(string)$row['created_at']
        );
    }
    $stmt->close();
    return array('list'=>$list,'pageNo'=>$pageNo,'totalPage'=>$total ? (int)ceil($total/$pageSize) : 0,'totalCount'=>(int)$total);
}


function sl_history_values($gameCode, $item)
{
    $family = sl_game_family($gameCode);
    if ($family === 'MotoRace') {
        return array_map('intval', explode(',', (string) $item['premium']));
    }
    return array_map('intval', str_split((string) $item['premium']));
}


function sl_trend($gameCode)
{
    sl_sync_results($gameCode);
    $history = sl_cached_history($gameCode, 100);
    $family = sl_game_family($gameCode);
    $positionCount = $family === 'D5' ? 5 : ($family === 'K3' || $family === 'MotoRace' ? 3 : 1);
    $numbers = $family === 'K3' ? range(1,6) : ($family === 'MotoRace' ? range(1,10) : range(0,9));
    $stats = array();
    for ($position = 1; $position <= $positionCount; $position++) {
        foreach ($numbers as $number) {
            $missing = 0;
            $open = 0;
            $maxContinuous = 0;
            $continuous = 0;
            $seen = false;
            foreach ($history as $item) {
                $values = sl_history_values($gameCode, $item);
                $value = isset($values[$position - 1]) ? (int) $values[$position - 1] : -1;
                if ($value === $number) {
                    $open++;
                    $continuous++;
                    $maxContinuous = max($maxContinuous, $continuous);
                    $seen = true;
                } else {
                    if (!$seen) {
                        $missing++;
                    }
                    $continuous = 0;
                }
            }
            $stats[] = array(
                'position'=>$position,'number'=>$number,'missingCount'=>$missing,
                'avgMissing'=>$open ? (int)round(count($history)/$open) : count($history),
                'openCount'=>$open,'maxContinuous'=>$maxContinuous
            );
        }
    }
    return $stats;
}
