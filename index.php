<?php
$host = "dpg-d4jqjoje5dus73episqg-a";
$db = "waitless_db";
$user = "waitless_db_user";
$pass = "e8qno4XAnpOnFsdi1xJXz3zzAPHIIV0F";
$dsn = "pgsql:host=$host;port=5432;dbname=$db;";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}



// ================= تصفير الأدوار يوميًا =================
// الفكرة: نخزن تاريخ آخر تصفير في ملف نصي، إذا تغيّر اليوم → نصفر الجدول
$currentDate    = date("Y-m-d");
$storedDateFile = __DIR__ . "/last_reset.txt";

// إذا الملف غير موجود ننشئه لأول مرة
if (!file_exists($storedDateFile)) {
    file_put_contents($storedDateFile, $currentDate);
}

$lastResetDate = trim(file_get_contents($storedDateFile));

if ($lastResetDate !== $currentDate) {
    // نحذف حجوزات الأيام السابقة ونصفر العداد
    // لو حاب تحتفظ بالحجوزات القديمة، احذف TRUNCATE وخله بس AUTO_INCREMENT
    $pdo->exec("TRUNCATE TABLE reservations"); // يحذف كل السجلات ويصفر الـ ID

    // نحدّث تاريخ آخر تصفير
    file_put_contents($storedDateFile, $currentDate);
}

// ================= منطق الطابور =================
// آخر من تمت خدمته
$currentNumber = 0;
$current = $pdo->query("SELECT id FROM reservations WHERE status='served' ORDER BY id DESC LIMIT 1")->fetch();
$currentNumber = $current ? (int)$current['id'] : 0;

// أول منتظر
$next = $pdo->query("SELECT id FROM reservations WHERE status='waiting' ORDER BY id ASC LIMIT 1")->fetch();
$nextNumber = $next ? (int)$next['id'] : ($currentNumber + 1);

// متوسط الوقت التقريبي (بالدقائق لكل رقم)
$avgMinutesPerTicket = 3;
$etaNextDisplay = max(0, ($nextNumber - $currentNumber) * $avgMinutesPerTicket);

// ================= استقبال حجز جديد =================
$yourNumber = null;
$etaForYou  = null;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name   = trim($_POST['full_name'] ?? '');
  $people = (int)($_POST['people_count'] ?? 0);
  $phone  = trim($_POST['phone'] ?? '');

  if ($name === '')   $errors[] = 'الاسم مطلوب.';
  if ($people <= 0)   $errors[] = 'عدد الأشخاص غير صحيح.';
  if ($phone === '')  $errors[] = 'رقم الجوال مطلوب.';

  if (!$errors) {
    $stmt = $pdo->prepare("INSERT INTO reservations (full_name,people_count,phone,status) VALUES (?,?,?,'waiting')");
    $stmt->execute([$name,$people,$phone]);
    $yourNumber = (int)$pdo->lastInsertId();

    $ahead = max(0, $yourNumber - $currentNumber - 1);
    $etaForYou = ($ahead + 1) * $avgMinutesPerTicket;
  }
}

// ================= حجوزات اليوم =================
$todays = $pdo->query("
  SELECT id, full_name, people_count, status, TIME(created_at) AS time
  FROM reservations
  WHERE DATE(created_at) = CURDATE()
  ORDER BY id ASC
  LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>WaitLess - تنظيم طابور المطعم (محلي)</title>

<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">

<style>
:root{
  --gold:#C9A24B;
  --gold-d:#b28b3c;
  --light:#f5f5f5;
  --card:#ffffff;
  --border:#e5e7eb;
  --text:#333;
  --muted:#6b7280;
  --danger:#e63946;
  --success:#22a55e;
  --row-served:#e8faee;   /* أخضر فاتح */
  --row-waiting:#fffbea;  /* أصفر فاتح */
}

body{
  font-family:"Tajawal",sans-serif;
  background:#f5f5f5;
  margin:0; padding:0;
  color:#333;
}

.container{
  max-width:800px;
  margin:20px auto;
  background:#ffffff;
  padding:22px 20px;
  border-radius:16px;
  box-shadow:0 6px 20px rgba(0,0,0,.08);
}

.header{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:16px;
}

.logo-box{
  width:200px;
  max-height:100px;
  padding:8px 12px;
  background:#fff;
  border-radius:12px;
  border:1px solid var(--border);
}
.logo-box img{
  max-width:100%;
  max-height:90px;
  object-fit:contain;
}

.logo-text-main{font-size:22px;font-weight:800;color:#222}
.logo-text-sub{font-size:13px;color:#777}

.tag{
  display:inline-block;
  margin-top:10px;
  padding:6px 14px;
  background:#faf7ef;
  border:1px solid #e5d7b0;
  color:#b28b3c;
  border-radius:999px;
  font-size:13px;
}

.btn-refresh-page{
  padding:7px 14px;
  background:#eee;
  border:1px solid #ccc;
  border-radius:8px;
  cursor:pointer;
  font-size:13px;
  margin-top:10px;
}

.cards-row{
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(230px,1fr));
  gap:14px;
  margin-top:20px;
}

.card{
  background:#fafafa;
  border:1px solid var(--border);
  border-radius:12px;
  padding:16px;
}

.queue-number{
  text-align:center;
  font-size:40px;
  font-weight:800;
}

.queue-label{
  text-align:center;
  color:#666;
  font-size:14px;
}

.queue-eta{
  text-align:center;
  margin-top:4px;
  font-size:13px;
  color:#444;
}

label{
  display:block;
  margin-top:16px;
  font-weight:600;
}
input{
  width:100%; padding:11px;
  border:1px solid #ccc;
  border-radius:8px;
  margin-top:6px;
  font-size:14px;
}

.btn-primary{
  margin-top:18px;
  width:100%;
  padding:14px;
  background:linear-gradient(135deg,var(--gold),var(--gold-d));
  color:white;
  border:none;
  border-radius:10px;
  font-size:16px;
  font-weight:700;
  cursor:pointer;
}

.success-card{
  background:#e8faee;
  border:1px solid #b4e7c7;
  padding:14px;
  border-radius:10px;
  text-align:center;
  margin-bottom:16px;
}

@keyframes popIn{
  0%{transform:scale(.4);opacity:0}
  70%{transform:scale(1.12);opacity:1}
  100%{transform:scale(1)}
}
.animate-number{animation:popIn .38s ease-out}

table{
  width:100%;
  border-collapse:collapse;
  margin-top:10px;
  font-size:14px;
}
th,td{
  padding:10px;
  border-bottom:1px solid #ddd;
  text-align:center;
}
th{background:#f3f3f3}
</style>
</head>
<body>

<div class="container">

  <!-- الهيدر + اللوقو -->
  <div class="header">
    <div>
      <div class="logo-text-main">WaitLess (محلي)</div>
      <div class="logo-text-sub">نظام ذكي لتنظيم طابور المطعم على XAMPP</div>
      <span class="tag">يتم تصفير الأدوار تلقائياً كل يوم</span>
    </div>

    <div class="logo-box">
      <!-- لو عندك اللوقو في نفس المجلد -->
      <img src="waitless-logo.jpeg" alt="WaitLess Logo"
           onerror="this.style.display='none';">
    </div>
  </div>

  <!-- زر تحديث الصفحة -->
  <button class="btn-refresh-page" onclick="location.reload()">🔄 تحديث الصفحة</button>

  <!-- كروت الدور الحالي / التالي -->
  <div class="cards-row">
    <div class="card">
      <div class="queue-number" style="color:var(--danger);">
        <?= $currentNumber > 0 ? $currentNumber : '—' ?>
      </div>
      <div class="queue-label">🔴 الدور الحالي</div>
    </div>

    <div class="card">
      <div class="queue-number" style="color:var(--success);">
        <?= $nextNumber ?>
      </div>
      <div class="queue-label">🟢 الدور التالي</div>
      <div class="queue-eta">
        ⏳ تقريباً: <?= $etaNextDisplay ?> دقيقة
      </div>
    </div>
  </div>

  <!-- نموذج الحجز -->
  <h2 style="margin-top:25px;">📱 احجز دورك الآن</h2>

  <div class="card">
    <?php if ($yourNumber): ?>
      <div class="success-card animate-number">
        <strong>تم حجز دورك 🎉</strong><br>
        رقمك هو: <strong style="color:#22a55e; font-size:18px;"><?= $yourNumber ?></strong><br>
        <span style="font-size:13px;color:#333">
          ⏳ الوقت المتوقع حتى دورك: <?= $etaForYou ?> دقيقة
        </span>
      </div>
    <?php endif; ?>

    <?php if ($errors): ?>
      <div style="background:#ffeaea;border:1px solid #f2b1b1;padding:10px;border-radius:8px;margin-bottom:10px;">
        <?php foreach($errors as $e) echo "<div>• $e</div>"; ?>
      </div>
    <?php endif; ?>

    <form method="post">
      <label>الاسم الثلاثي</label>
      <input type="text" name="full_name" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">

      <label>عدد الأشخاص</label>
      <input type="number" name="people_count" value="<?= htmlspecialchars($_POST['people_count'] ?? '') ?>">

      <label>رقم الجوال</label>
      <input type="tel" name="phone" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">

      <button class="btn-primary">🎫 احصل على رقم الانتظار</button>
    </form>
  </div>

  <!-- جدول حجوزات اليوم -->
  <h3 style="margin-top:20px;">📋 أول 10 حجوزات اليوم</h3>

  <?php if (!empty($todays)): ?>
  <table>
    <thead>
      <tr>
        <th>الرقم</th>
        <th>الاسم</th>
        <th>الأشخاص</th>
        <th>الحالة</th>
        <th>الوقت</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach($todays as $r):
        $rowColor = ($r['status'] === 'served') ? '#e8faee' : '#fffbea';
      ?>
      <tr style="background: <?= $rowColor ?>;">
        <td><?= $r['id'] ?></td>
        <td><?= htmlspecialchars($r['full_name']) ?></td>
        <td><?= (int)$r['people_count'] ?></td>
        <td><?= $r['status']==='served'?'تمت خدمته':'منتظر' ?></td>
        <td><?= htmlspecialchars($r['time']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php else: ?>
    <p style="color:#555; font-size:14px;">لا توجد حجوزات حتى الآن.</p>
  <?php endif; ?>

</div>

<!-- صوت تنبيه بسيط بدون ملفات صوتية -->
<script>
function playBeep() {
  try {
    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    const ctx = new AudioCtx();

    const osc = ctx.createOscillator();
    const gain = ctx.createGain();

    osc.type = 'sine';
    osc.frequency.value = 880;

    osc.connect(gain);
    gain.connect(ctx.destination);

    const now = ctx.currentTime;
    gain.gain.setValueAtTime(0.0001, now);
    gain.gain.exponentialRampToValueAtTime(0.4, now + 0.01);
    gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.25);

    osc.start(now);
    osc.stop(now + 0.3);
  } catch (e) {
    console.log('Audio not supported:', e);
  }
}
</script>

<?php if ($yourNumber): ?>
<script>
// تشغيل صوت التنبيه بعد الحجز
window.addEventListener('load', function () {
  playBeep();
});
</script>
<?php endif; ?>

</body>
</html>


