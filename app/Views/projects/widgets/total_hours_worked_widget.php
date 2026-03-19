<div class="card widget-card">
    <div class="card-body">
        <div class="d-flex">
            <div class="flex-shrink-0 text-off align-self-center">
                <i data-feather="clock" width="5rem" height="5rem"></i>
            </div>
            <div class="w-100 text-end">
                <h1><?php echo $total_project_hours; ?></h1>
                <?php echo app_lang("total_hours_worked"); ?>
                <?php if (isset($my_project_hours) && $my_project_hours != $total_project_hours): ?>
                    <div class="text-off" style="font-size: 0.85em; margin-top: 4px;">
                        Tus horas: <strong><?php echo $my_project_hours; ?>h</strong>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>