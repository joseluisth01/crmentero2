<div class="card">
    <div class="card-body">
        <!--<div class="box">
            <div class="box-content b-r pb15">
                <?php echo anchor(get_uri("proposals/preview/" . $proposal_info->id . "/1"), "<i data-feather='search' class='icon-16'></i> " . app_lang('preview'), array("id" => "proposal-preview-btn", "title" => app_lang('proposal_preview'))); ?>
            </div>
            <div class="box-content pl15 pb15">
                <?php echo js_anchor("<i data-feather='printer' class='icon-16'></i> " . app_lang('print'), array('title' => app_lang('print_proposal'), 'id' => 'print-proposal-btn')); ?>
            </div>
        </div>-->
        
        <!-- MODIFICADO: Redirigir al Dashboard de Tictac -->
        <div class="box b-t">
            <div class="box-content pt15 b-r">
                <?php 
                // URL del dashboard de presupuestos
                $dashboard_url = "https://gestion-tictac-comunicacion.es/dashboard/presupuestos/redirect_proposal.php?proposal_id=" . $proposal_info->id;
                echo anchor($dashboard_url, "<i data-feather='edit' class='icon-16'></i> " . app_lang('edit') . " (Dashboard)", array("title" => "Editar en Dashboard Tictac", "target" => "_blank", "class" => "text-primary")); 
                ?>
            </div>
            <div class="box-content pl15 pt15">
                <?php 
                // Botón de descarga PDF - abre el dashboard para descargar
                echo anchor($dashboard_url, "<i data-feather='download' class='icon-16'></i> " . app_lang('download_pdf') . " (Tictac)", array("title" => "Descargar PDF con diseño Tictac", "target" => "_blank")); 
                ?>
            </div>
        </div>
        
        <!-- OPCIONAL: Mantener botones originales del CRM comentados por si acaso -->
        <!-- 
        <div class="box b-t">
            <div class="box-content pt15 b-r">
                <?php // echo anchor(get_uri("proposals/download_pdf/" . $proposal_info->id . "/view"), "<i data-feather='file-text' class='icon-16'></i> " . app_lang('view_pdf'), array("title" => app_lang('view_pdf'), "target" => "_blank")); ?>
            </div>
            <div class="box-content pl15 pt15">
                <?php // echo anchor(get_uri("proposals/download_pdf/" . $proposal_info->id), "<i data-feather='download' class='icon-16'></i> " . app_lang('download_pdf'), array("title" => app_lang('download_pdf'))); ?>
            </div>
        </div>
        -->
    </div>
</div>