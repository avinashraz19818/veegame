<?php
define('VA_ADMIN',true);require __DIR__.'/lib/bootstrap.php';
$error='';
if(!va_ready()) va_redirect('setup.php');
if(($_SERVER['REQUEST_METHOD']??'')==='POST') {
    va_post();$name=trim(va_input('username'));$password=va_input('password');
    if(strlen($name)>64 || strlen($password)>72) va_fail(400,'Invalid input length.');
    if(!va_rate($name)) { header('Retry-After: 900'); va_fail(429,'Too many login attempts. Try again in 15 minutes.'); }
    $a=va_one('SELECT id,username,password_hash,active,session_version FROM veegame_admin_accounts WHERE username=? LIMIT 1',[$name]);
    $hash=$a['password_hash']??'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    $valid=password_verify($password,$hash);
    if($a && (int)$a['active']===1 && $valid) {
        va_audit('login.success','admin:'.$a['id'],[],(int)$a['id']);
        session_regenerate_id(true);$_SESSION=['admin_id'=>(int)$a['id'],'version'=>(int)$a['session_version'],'started'=>time(),'last_seen'=>time(),'csrf'=>bin2hex(random_bytes(32))];va_redirect('index.php');
    }
    va_audit('login.failed','authentication');$error='Invalid admin credentials.';
}
va_login_head('Admin login');
if($error) echo '<div class="clean-alert alert-err">'.va_e($error).'</div>';
if(($_GET['setup']??'')==='done') echo '<div class="clean-alert alert-out">Admin created. Sign in below.</div>';
if(isset($_GET['expired'])) echo '<div class="clean-alert alert-warn">Session expired. Please sign in again.</div>';
?>
<form method="post" action="login.php"><?=va_csrf()?>
<?php va_login_field('username','Admin username','text','username');va_login_field('password','Password','password','current-password'); ?>
<button class="btn-premium-auth" type="submit">Sign in securely</button></form>
<p class="setup-note">Separate admin session · 30-minute inactivity timeout</p>
<?php va_login_end(); ?>
