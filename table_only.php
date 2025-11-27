<?php if (!empty($todays)): ?>
<table border="0" cellspacing="0" cellpadding="0" style="width:100%; border-collapse:collapse;">
    <thead>
        <tr style="background:#f3f3f3;">
            <th style="padding:8px; border:1px solid #ddd;">الرقم</th>
            <th style="padding:8px; border:1px solid #ddd;">الاسم</th>
            <th style="padding:8px; border:1px solid #ddd;">الأشخاص</th>
            <th style="padding:8px; border:1px solid #ddd;">الحالة</th>
            <th style="padding:8px; border:1px solid #ddd;">الوقت</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach($todays as $r): 
            // لون الصف حسب الحالة
            $rowColor = ($r['status'] === 'served') ? '#e8faee' : '#fffbea';
        ?>
        <tr style="background: <?= $rowColor ?>;">
            <td style="padding:8px; border-bottom:1px solid #ddd;"><?= $r['id'] ?></td>
            <td style="padding:8px; border-bottom:1px solid #ddd;"><?= htmlspecialchars($r['full_name']) ?></td>
            <td style="padding:8px; border-bottom:1px solid #ddd;"><?= (int)$r['people_count'] ?></td>
            <td style="padding:8px; border-bottom:1px solid #ddd;">
                <?= $r['status']==='served'?'تمت خدمته':'منتظر' ?>
            </td>
            <td style="padding:8px; border-bottom:1px solid #ddd;"><?= htmlspecialchars($r['time']) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<p style="color:#555; font-size:14px;">لا توجد حجوزات حتى الآن.</p>
<?php endif; ?>
