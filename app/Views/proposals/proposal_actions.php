<div class="card">
    <div class="card-body">
        <div class="box b-t">
            <div class="box-content pt15 b-r">
                <?php echo anchor(get_uri("proposals/download_pdf/" . $proposal_info->id . "/view"), "<i data-feather='file-text' class='icon-16'></i> " . app_lang('view_pdf'), array("title" => app_lang('view_pdf'), "target" => "_blank")); ?>
            </div>
            <div class="box-content pl15 pt15">
                <?php echo anchor(get_uri("proposals/download_pdf/" . $proposal_info->id), "<i data-feather='download' class='icon-16'></i> " . app_lang('download_pdf'), array("title" => app_lang('download_pdf'))); ?>
            </div>
        </div>

        <?php
        $meta_raw  = $proposal_info->meta_data ?? '';
        $meta_arr  = @unserialize($meta_raw);
        $con_firma = (
            $proposal_info->status === 'accepted' &&
            $meta_arr && is_array($meta_arr) &&
            !empty($meta_arr['signature'])
        );
        if ($con_firma):
        ?>
        <div class="box b-t">
            <div class="box-content pt15">
                <?php echo anchor(
                    get_uri("offer/download_signed_pdf/" . $proposal_info->id . "/" . $proposal_info->public_key),
                    "<i data-feather='pen-tool' class='icon-16'></i> PDF Firmado",
                    array("title" => "Descargar PDF con firma del cliente", "style" => "color:#1a7f4b;font-weight:600;")
                ); ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>