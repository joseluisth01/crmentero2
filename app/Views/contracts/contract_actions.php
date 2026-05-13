<div class="card">
    <div class="card-body">
        <div class="box b-t">
            <div class="box-content pt15 b-r">
                <?php echo anchor(get_uri("contracts/download_pdf/" . $contract_info->id . "/view"), "<i data-feather='file-text' class='icon-16'></i> " . app_lang('view_pdf'), array("title" => app_lang('view_pdf'), "target" => "_blank")); ?>
            </div>
            <div class="box-content pl15 pt15">
                <?php echo anchor(get_uri("contracts/download_pdf/" . $contract_info->id), "<i data-feather='download' class='icon-16'></i> " . app_lang('download_pdf'), array("title" => app_lang('download_pdf'))); ?>
            </div>
        </div>
    </div>
</div>