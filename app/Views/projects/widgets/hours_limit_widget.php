<?php
if (!$project_info->max_hours_monthly && !$project_info->max_hours_total) {
    return;
}

$is_monthly = $project_info->max_hours_monthly > 0;
$max_hours = $is_monthly ? $project_info->max_hours_monthly : $project_info->max_hours_total;
$used_hours = $is_monthly ? $hours_used_this_month : $total_project_hours;
$used_hours_float = (float) $used_hours;

$percentage = $max_hours > 0 ? min(100, round(($used_hours_float / $max_hours) * 100)) : 0;
$remaining = max(0, $max_hours - $used_hours_float);

if ($percentage >= 90) {
    $bar_color = 'bg-danger';
    $text_color = 'text-danger';
    $icon = 'alert-octagon';
} elseif ($percentage >= 70) {
    $bar_color = 'bg-warning';
    $text_color = 'text-warning';
    $icon = 'alert-triangle';
} else {
    $bar_color = 'bg-success';
    $text_color = 'text-success';
    $icon = 'check-circle';
}

$label = $is_monthly ? 'Horas mensuales usadas' : 'Horas de bolsa usadas';
$type_label = $is_monthly ? '(se reinicia cada mes)' : '(bolsa total)';
?>

<div class="card widget-card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
                <i data-feather="<?php echo $icon; ?>" class="icon-16 <?php echo $text_color; ?>"></i>
                <strong class="ml5"><?php echo $label; ?></strong>
                <small class="text-off ml5"><?php echo $type_label; ?></small>
            </div>
            <div class="<?php echo $text_color; ?> strong">
                <?php echo number_format($percentage, 0); ?>%
            </div>
        </div>

        <div class="progress mb-2" style="height: 10px;">
            <div class="progress-bar <?php echo $bar_color; ?>"
                 role="progressbar"
                 style="width: <?php echo $percentage; ?>%;"
                 aria-valuenow="<?php echo $percentage; ?>"
                 aria-valuemin="0"
                 aria-valuemax="100">
            </div>
        </div>

        <div class="d-flex justify-content-between text-off" style="font-size: 0.85em;">
            <span>
                <strong class="<?php echo $text_color; ?>"><?php echo number_format($used_hours_float, 2); ?>h</strong>
                usadas de
                <strong><?php echo number_format($max_hours, 2); ?>h</strong>
            </span>
            <span>
                Quedan: <strong class="<?php echo $text_color; ?>"><?php echo number_format($remaining, 2); ?>h</strong>
            </span>
        </div>

        <?php if (!empty($hours_by_user)): ?>
            <div class="mt-2 pt-2" style="border-top: 1px solid rgba(0,0,0,0.08); font-size: 0.82em;">
                <?php foreach ($hours_by_user as $user_hours): ?>
                    <div class="d-flex justify-content-between text-off py-1">
                        <span><?php echo $user_hours->usuario; ?></span>
                        <span><strong><?php echo number_format($user_hours->horas, 2); ?>h</strong></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>