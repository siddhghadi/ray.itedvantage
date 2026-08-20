<?php
$monthInput = (string) ($_GET['month'] ?? date('Y-m'));
$monthDate = DateTimeImmutable::createFromFormat('!Y-m', $monthInput) ?: new DateTimeImmutable('first day of this month');
$monthKey = $monthDate->format('Y-m');
$firstWeekday = (int) $monthDate->format('N');
$daysInMonth = (int) $monthDate->format('t');
$previousMonth = $monthDate->modify('-1 month')->format('Y-m');
$nextMonth = $monthDate->modify('+1 month')->format('Y-m');
$defaultDate = $monthKey === date('Y-m') ? date('Y-m-d') : $monthDate->format('Y-m-d');
$businessItems = array_values(array_filter($calendarItems, static fn(array $item): bool => ($item['business'] ?? '') === $calendarBusiness && str_starts_with((string) ($item['date'] ?? ''), $monthKey)));
$itemsByDate = [];
foreach ($businessItems as $item) $itemsByDate[$item['date']][] = $item;
?>
<section class="calendar-layout">
    <article class="panel calendar-main">
        <div class="calendar-toolbar"><a class="small-button button-link" href="?business=<?= $calendarBusiness ?>&page=calendar&month=<?= $previousMonth ?>">←</a><div><span class="eyebrow"><?= htmlspecialchars($calendarLabel) ?></span><h2><?= $monthDate->format('F Y') ?></h2></div><a class="small-button button-link" href="?business=<?= $calendarBusiness ?>&page=calendar&month=<?= $nextMonth ?>">→</a></div>
        <div class="calendar-scroll"><div class="calendar-grid"><div class="calendar-weekday">Mon</div><div class="calendar-weekday">Tue</div><div class="calendar-weekday">Wed</div><div class="calendar-weekday">Thu</div><div class="calendar-weekday">Fri</div><div class="calendar-weekday">Sat</div><div class="calendar-weekday">Sun</div><?php for ($blank=1;$blank<$firstWeekday;$blank++): ?><div class="calendar-day empty"></div><?php endfor; ?><?php for ($day=1;$day<=$daysInMonth;$day++): $date=$monthKey.'-'.str_pad((string)$day,2,'0',STR_PAD_LEFT); ?><div class="calendar-day <?= $date === date('Y-m-d') ? 'today' : '' ?>"><strong><?= $day ?></strong><?php foreach ($itemsByDate[$date] ?? [] as $item): ?><div class="calendar-item"><span><?= htmlspecialchars($item['channel'] ?: $calendarDefaultChannel) ?></span><b><?= htmlspecialchars($item['title']) ?></b></div><?php endforeach; ?></div><?php endfor; ?></div></div>
    </article>
    <article class="panel calendar-add"><span class="eyebrow">PLAN CONTENT</span><h2>Add to calendar</h2><form class="stack-form" method="post"><input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>"><label>Date</label><input type="date" name="calendar_date" value="<?= $defaultDate ?>" required><label><?= htmlspecialchars($calendarTitleLabel) ?></label><input type="text" name="calendar_title" maxlength="180" placeholder="<?= htmlspecialchars($calendarTitlePlaceholder) ?>" required><label><?= htmlspecialchars($calendarChannelLabel) ?></label><select name="calendar_channel"><?php foreach ($calendarChannels as $channel): ?><option value="<?= htmlspecialchars($channel) ?>"><?= htmlspecialchars($channel) ?></option><?php endforeach; ?></select><button class="primary-button" type="submit" name="add_calendar_item" value="1">Add to calendar</button></form><p class="panel-note">The calendar automatically opens the current month. Use the arrows to plan future months.</p></article>
</section>
