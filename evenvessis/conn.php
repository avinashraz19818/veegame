<?php

date_default_timezone_set('Asia/Kolkata');

define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'club532583_veergame');
define('DB_PASSWORD', 'club532583_veergame');
define('DB_NAME', 'club532583_veergame');

$conn = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if($conn == false){
    http_response_code(500);
    die(json_encode(['code' => 500, 'msg' => 'DB connection failed: ' . mysqli_connect_error()]));
}

?>