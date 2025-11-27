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

// إنشاء جدول الحجوزات تلقائياً لو كان غير موجود (PostgreSQL)
$pdo->exec("
    CREATE TABLE IF NOT EXISTS reservations (
        id SERIAL PRIMARY KEY,
        full_name    VARCHAR(100) NOT NULL,
        people_count INT          NOT NULL,
        phone        VARCHAR(20)  NOT NULL,
        status       VARCHAR(20)  DEFAULT 'waiting',
        created_at   TIMESTAMPTZ  DEFAULT CURRENT_TIMESTAMP
    );
");


// تعليم حجز كـ "تمت خدمته" حسب رقم محدد
if (isset($_GET['serve_id'])) {
    $id = (int)$_GET['serve_id'];
    $stmt = $pdo->prepare("UPDATE reservations SET status='served' WHERE id=?");
    $stmt->execute([$id]);
    header('Location: admin.php'); exit;
}

// تقديم الدور التالي (أقدم منتظر)
if (isset($_GET['serve_next'])) {
    $next = $pdo->query("SELECT id FROM reservations WHERE status='waiting' ORDER BY id ASC LIMIT 1")->fetch();
    if ($next) {
        $stmt = $pdo->prepare("UPDATE reservations SET status='served' WHERE id=?");
        $stmt->execute([$next['id']]);
    }
    header('Location: admin.php'); exit;
}

// استرجاع القوائم
$waiting = $pdo->query("SELECT id, full_name, people_count, TO_CHAR(created_at, 'HH24:MI') AS time FROM reservations WHERE status='waiting' ORDER BY id ASC LIMIT 50")->fetchAll();
$served  = $pdo->query("SELECT id, full_name, people_count, TO_CHAR(created_at, 'HH24:MI') AS time FROM reservations WHERE status='served'  ORDER BY id DESC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>WaitLess Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;600;700&display=swap" rel="stylesheet">
<style>
body{font-family:"Tajawal",sans-serif;background:#0b1220;margin:0;padding:20px;color:#e5e7eb}
.wrap{max-width:1000px;margin:0 auto}
.header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
.title{font-weight:700;font-size:22px}
.btn{display:inline-block;padding:10px 14px;border-radius:10px;border:1px solid #374151;background:#111827;color:#e5e7eb;text-decoration:none}
.btn:hover{background:#0f172a}
.btn-gold{background:linear-gradient(135deg,#C9A24B,#b28b3c);border:none;color:#111827;font-weight:700}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.card{background:#111827;border:1px solid #1f2937;border-radius:14px;padding:14px}
h2{margin:0 0 10px 0;font-size:18px}
table{width:100%;border-collapse:collapse;font-size:13px}
th,td{padding:8px 6px;border-bottom:1px solid #1f2937;text-align:center}
th{color:#cbd5e1}
tr:nth-child(even){background:#0f172a}
a.action{padding:6px 10px;border-radius:8px;background:#0b7a43;color:#eafff4;text-decoration:none;font-size:12px}
a.action:hover{background:#099454}
.note{font-size:12px;color:#9ca3af;margin-top:8px}
</style>
</head>
<body>
<div class="wrap">

    <div class="header">
        <div class="title">لوحة إدارة WaitLess</div>
        <div>
            <a class="btn" href="index.php">العودة للواجهة</a>
            <a class="btn btn-gold" href="?serve_next=1">تقديم الدور التالي</a>
        </div>
    </div>

    <div class="grid">
        <div class="card">
            <h2>المنتظرون (Waiting)</h2>
            <?php if ($waiting): ?>
            <table>
                <thead><tr>
                    <th>الرقم</th><th>الاسم</th><th>الأشخاص</th><th>الوقت</th><th>إجراء</th>
                </tr></thead>
                <tbody>
                <?php foreach ($waiting as $w): ?>
                    <tr>
                        <td><?= $w['id'] ?></td>
                        <td><?= htmlspecialchars($w['full_name']) ?></td>
                        <td><?= (int)$w['people_count'] ?></td>
                        <td><?= htmlspecialchars($w['time']) ?></td>
                        <td><a class="action" href="?serve_id=<?= $w['id'] ?>">تمت خدمته</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="note">لا يوجد أحد في الانتظار حاليًا.</div>
            <?php endif; ?>
            <div class="note">يمكنك استخدام زر "تقديم الدور التالي" في الأعلى لتقديم أقدم منتظر تلقائيًا.</div>
        </div>

        <div class="card">
            <h2>آخر المُخدّمين (Served)</h2>
            <?php if ($served): ?>
            <table>
                <thead><tr>
                    <th>الرقم</th><th>الاسم</th><th>الأشخاص</th><th>الوقت</th>
                </tr></thead>
                <tbody>
                <?php foreach ($served as $s): ?>
                    <tr>
                        <td><?= $s['id'] ?></td>
                        <td><?= htmlspecialchars($s['full_name']) ?></td>
                        <td><?= (int)$s['people_count'] ?></td>
                        <td><?= htmlspecialchars($s['time']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <div class="note">لا توجد سجلات مؤخرًا.</div>
            <?php endif; ?>
        </div>
    </div>

</div>
</body>
</html>





