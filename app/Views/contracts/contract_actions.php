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

        <?php
        $meta_actions = @unserialize($contract_info->meta_data ?? '') ?: array();

        // PDF firmado por Lleida — guardado localmente
        if (!empty($meta_actions['lleida_signed_pdf_local'])): ?>
        <div class="box b-t" style="margin-top:10px;">
            <div class="box-content pt15" style="width:100%;">
                <a href="<?php echo get_uri($meta_actions['lleida_signed_pdf_local']); ?>"
                   target="_blank"
                   class="btn btn-sm btn-default"
                   style="width:100%;text-align:center;color:#1a7f4b;border-color:#1a7f4b;font-weight:600;">
                    <i data-feather="check-circle" class="icon-16"></i> Descargar PDF Firmado
                </a>
                <small style="display:block;text-align:center;color:#aaa;margin-top:4px;font-size:10px;">
                    Firmado el: <?php echo $meta_actions['lleida_signed_pdf_at'] ?? ''; ?>
                </small>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($contract_info->status !== 'accepted'): ?>
        <div class="box b-t" style="margin-top:10px;">
            <div class="box-content pt15" style="width:100%;">
                <?php echo modal_anchor(
                    get_uri("contracts/lleida_modal_form/" . $contract_info->id),
                    "<i data-feather='smartphone' class='icon-16'></i> &nbsp;Enviar a firmar por SMS",
                    array(
                        "class" => "btn btn-sm btn-default",
                        "style" => "width:100%;text-align:center;color:#d72173;border-color:#d72173;font-weight:600;",
                        "title" => "Enviar a firmar por SMS (Click&Sign)"
                    )
                ); ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($meta_actions['lleida_sent_at'])): ?>
        <div style="margin-top:8px;padding:8px 10px;background:#f8f9fa;border-radius:6px;font-size:11px;color:#888;">
            <i data-feather="send" class="icon-12"></i>
            Enviado a Click&Sign: <?php echo $meta_actions['lleida_sent_at']; ?><br>
            Teléfono: <?php echo $meta_actions['lleida_phone'] ?? '-'; ?>
            <?php if (!empty($meta_actions['lleida_signed_at'])): ?>
            <br><span style="color:#28a745;font-weight:600;">✅ Firmado: <?php echo $meta_actions['lleida_signed_at']; ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>