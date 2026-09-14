<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');
try {
    require_once dirname(__DIR__).'/saas_lottery/bootstrap_live_v4.php';
    if (($_SERVER['REQUEST_METHOD']??'')!=='GET') sl_fail(405,'Use GET',405,405);
    $game=(string)($_GET['gameCode']??'');
    if (!sl_game_config($game) || ($_GET['lottery']??'')!=='WinGo') sl_fail(404,'Unsupported game',404,404);
    if (!empty($_GET['history'])) {
        sl_install_schema();
        sl_send(['code'=>0,'msg'=>'success','data'=>sl_history_page($game,$_GET)],200);
    }
    $payload=sl_provider_current($game);
    if (!$payload) sl_fail(503,'Provider current period unavailable; betting paused',503,503);
    sl_send($payload,200);
} catch (Throwable $e) {
    error_log('[veegame-draw] '.get_class($e));
    http_response_code(503);echo json_encode(['code'=>503,'msg'=>'Draw service temporarily unavailable','data'=>null]);
}
