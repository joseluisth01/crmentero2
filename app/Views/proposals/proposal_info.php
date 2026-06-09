<div class="card" id="proposal-info-card">
    <div class="card-header fw-bold">
        <span class="inline-block mt-1">
            <i data-feather="anchor" class="icon-16"></i> &nbsp;<?php echo app_lang("proposal_info"); ?>
        </span>

        <div class="float-end">
            <div class="action-option" data-bs-toggle="dropdown" aria-expanded="true">
                <i data-feather="more-horizontal" class="icon-16"></i>
            </div>
            <ul class="dropdown-menu" role="menu">
                <?php if ($is_proposal_editable) { ?>
                    <li role="presentation"><?php echo modal_anchor(get_uri("proposals/modal_form"), "<i data-feather='edit' class='icon-16'></i> " . app_lang('edit_proposal'), array("title" => app_lang('edit_proposal'), "data-post-id" => $proposal_info->id, "role" => "menuitem", "tabindex" => "-1", "class" => "dropdown-item")); ?> </li>
                <?php } ?>
                <li role="presentation"><?php echo modal_anchor(get_uri("proposals/modal_form"), "<i data-feather='copy' class='icon-16'></i> " . app_lang('clone_proposal'), array("data-post-is_clone" => true, "data-post-id" => $proposal_info->id, "title" => app_lang('clone_proposal'), "class" => "dropdown-item")); ?></li>

                <?php if ($proposal_status == "accepted") { ?>
                    <li role="presentation" class="dropdown-divider"></li>
                    <?php if ($can_create_projects && !$proposal_info->project_id) { ?>
                        <li role="presentation"><?php echo modal_anchor(get_uri("projects/modal_form"), "<i data-feather='command' class='icon-16'></i> " . app_lang('create_project'), array("data-post-context" => "proposal", "data-post-context_id" => $proposal_info->id, "title" => app_lang('create_project'), "data-post-client_id" => $proposal_info->client_id, "class" => "dropdown-item")); ?> </li>
                    <?php } ?>
                    <?php if ($show_estimate_option) { ?>
                        <li role="presentation"><?php echo modal_anchor(get_uri("estimates/modal_form/"), "<i data-feather='file' class='icon-16'></i> " . app_lang('create_estimate'), array("title" => app_lang("create_estimate"), "data-post-proposal_id" => $proposal_info->id, "class" => "dropdown-item")); ?> </li>
                    <?php } ?>
                    <?php if ($show_invoice_option) { ?>
                        <li role="presentation"><?php echo modal_anchor(get_uri("invoices/modal_form/"), "<i data-feather='file-text' class='icon-16'></i> " . app_lang('create_invoice'), array("title" => app_lang("create_invoice"), "data-post-proposal_id" => $proposal_info->id, "class" => "dropdown-item")); ?> </li>
                    <?php } ?>
                    <?php if ($show_contract_option) { ?>
                        <li role="presentation"><?php echo modal_anchor(get_uri("contracts/modal_form/"), "<i data-feather='file-plus' class='icon-16'></i> " . app_lang('create_contract'), array("title" => app_lang("create_contract"), "data-post-proposal_id" => $proposal_info->id, "class" => "dropdown-item")); ?> </li>
                    <?php } ?>
                <?php } ?>

                <li role="presentation" class="dropdown-divider"></li>

                <?php if ($proposal_status == "draft" || $proposal_status == "sent") { ?>
                    <li role="presentation"><?php echo ajax_anchor(get_uri("proposals/update_proposal_status/" . $proposal_info->id . "/accepted"), "<i data-feather='check-circle' class='icon-16'></i> " . app_lang('mark_as_accepted'), array("data-reload-on-success" => "1", "class" => "dropdown-item")); ?> </li>
                    <li role="presentation"><?php echo ajax_anchor(get_uri("proposals/update_proposal_status/" . $proposal_info->id . "/declined"), "<i data-feather='x-circle' class='icon-16'></i> " . app_lang('mark_as_rejected'), array("data-reload-on-success" => "1", "class" => "dropdown-item")); ?> </li>
                    <?php if ($proposal_status == "draft") { ?>
                        <li role="presentation"><?php echo ajax_anchor(get_uri("proposals/update_proposal_status/" . $proposal_info->id . "/sent"), "<i data-feather='send' class='icon-16'></i> " . app_lang('mark_as_sent'), array("data-reload-on-success" => "1", "class" => "dropdown-item")); ?> </li>
                    <?php } ?>
                <?php } else if ($proposal_status == "accepted") { ?>
                    <li role="presentation"><?php echo ajax_anchor(get_uri("proposals/update_proposal_status/" . $proposal_info->id . "/declined"), "<i data-feather='x-circle' class='icon-16'></i> " . app_lang('mark_as_rejected'), array("data-reload-on-success" => "1", "class" => "dropdown-item")); ?> </li>
                <?php } else if ($proposal_status == "declined") { ?>
                    <li role="presentation"><?php echo ajax_anchor(get_uri("proposals/update_proposal_status/" . $proposal_info->id . "/accepted"), "<i data-feather='check-circle' class='icon-16'></i> " . app_lang('mark_as_accepted'), array("data-reload-on-success" => "1", "class" => "dropdown-item")); ?> </li>
                <?php } ?>

            </ul>
        </div>
    </div>
    <div class="card-body">
        <ul class="list-group info-list">
            <li class="list-group-item">
                <?php
                // Si no hay cliente real, obtener nombre del pending_client en meta_data
                $display_company = $proposal_info->company_name ?? '';
                if (!$display_company && !empty($proposal_info->meta_data)) {
                    $meta_tmp = @unserialize($proposal_info->meta_data);
                    if ($meta_tmp && !empty($meta_tmp['pending_client'])) {
                        $display_company = $meta_tmp['pending_client']['company'] ?? '';
                    }
                }
                ?>
                <?php if ($proposal_info->client_id && $proposal_info->is_lead) { ?>
                    <span title="<?php echo app_lang("lead"); ?>"><i data-feather="layers" class="icon-16 mr5"></i> <?php echo anchor(get_uri("leads/view/" . $proposal_info->client_id), $display_company ?: '-'); ?></span>
                <?php } elseif ($proposal_info->client_id) { ?>
                    <span title="<?php echo app_lang("client"); ?>"><i data-feather="briefcase" class="icon-16 mr5"></i> <?php echo anchor(get_uri("clients/view/" . $proposal_info->client_id), $display_company ?: '-'); ?></span>
                <?php } else { ?>
                    <span title="Futuro cliente"><i data-feather="user" class="icon-16 mr5"></i> <em style="color:#d72173;"><?php echo htmlspecialchars($display_company ?: 'Sin cliente asignado'); ?></em> <small style="color:#aaa;">(pendiente de creación)</small></span>
                <?php } ?>
            </li>

            <li class="list-group-item">
                <strong><?php echo app_lang("proposal_date") . ": "; ?></strong><span class='ml5'><?php echo format_to_date($proposal_info->proposal_date, false); ?></span>
            </li>

            <li class="list-group-item">
                <strong><?php echo app_lang("valid_until") . ": "; ?></strong><span class='ml5'><?php echo format_to_date($proposal_info->valid_until, false); ?></span>
            </li>

            <?php if ($proposal_info->last_preview_seen) { ?>
                <li class="list-group-item">
                    <span title="<?php echo app_lang("last_preview_seen"); ?>"><i data-feather="target" class="icon-16 mr5"></i> <?php echo format_to_relative_time($proposal_info->last_preview_seen); ?></span>
                </li>
            <?php } ?>

            <?php
            $read_count = "";
            if ($total_read_count) {
                $read_count = "<span class='ml5' title='" . app_lang("email_seen_count") . "'>" . ajax_anchor(get_uri("proposals/email_view_report/" . $proposal_info->id), " (" . $total_read_count . ")", array("data-real-target" => "#proposal-email-view-report", "class" => "strong")) . "</span>";
            }
            ?>

            <?php if ($proposal_info->last_email_read_time) { ?>
                <li class="list-group-item pb0">
                    <span title="<?php echo app_lang("last_email_seen"); ?>"><i data-feather="mail" class="icon-16 mr5"></i> <?php echo format_to_relative_time($proposal_info->last_email_read_time) . $read_count; ?></span>
                </li>
            <?php } ?>

            <?php if ($total_read_count) { ?>
                <div id="proposal-email-view-report"></div>
            <?php } ?>

            <?php if ($proposal_info->project_id) { ?>
                <li class="list-group-item">
                    <span title="<?php echo app_lang("project"); ?>"><i data-feather="command" class="icon-16 mr5"></i> <?php echo anchor(get_uri("projects/view/" . $proposal_info->project_id), $proposal_info->project_title); ?></span>
                </li>
            <?php } ?>
        </ul>
    </div>

    <?php if ($is_proposal_editable): ?>
    <!-- Sección de notas editable con Quill -->
    <div class="card-body border-top pt-3">
        <?php
        // Cargar Quill si no está ya disponible en la página
        if (!defined('TICTAC_QUILL_LOADED')):
            define('TICTAC_QUILL_LOADED', true);
        ?>
        <link rel="stylesheet" href="https://cdn.quilljs.com/1.3.7/quill.snow.css">
        <script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
        <?php endif; ?>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong><i data-feather="file-text" class="icon-14 mr5"></i> Notas</strong>
            <button type="button" class="btn btn-xs btn-default" id="tt-nota-edit-btn" onclick="ttNotaToggle()">
                <i data-feather="edit-2" class="icon-12"></i> Editar
            </button>
        </div>

        <!-- Vista de la nota -->
        <div id="tt-nota-view" style="font-size:13px;color:#555;min-height:24px;">
            <?php if (!empty($proposal_info->note) && strip_tags($proposal_info->note) !== ''): ?>
                <?php echo custom_nl2br($proposal_info->note); ?>
            <?php else: ?>
                <em style="color:#bbb;">Sin notas</em>
            <?php endif; ?>
        </div>

        <!-- Editor de la nota -->
        <div id="tt-nota-edit" style="display:none;">
            <div id="tt-nota-quill" style="min-height:100px;border:1px solid #e0e0e0;border-radius:6px;font-size:13px;"></div>
            <input type="hidden" id="tt-nota-hidden" data-original="<?php echo htmlspecialchars($proposal_info->note ?? ''); ?>">
            <div style="margin-top:8px;display:flex;gap:8px;">
                <button type="button" class="btn btn-sm btn-primary" onclick="ttNotaGuardar()">
                    <i data-feather="check" class="icon-12"></i> Guardar
                </button>
                <button type="button" class="btn btn-sm btn-default" onclick="ttNotaCancelar()">
                    <i data-feather="x" class="icon-12"></i> Cancelar
                </button>
            </div>
        </div>
    </div>

    <script>
    var ttQuill = null;

    function ttNotaInit() {
        if (ttQuill) return;
        ttQuill = new Quill('#tt-nota-quill', {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['clean']
                ]
            }
        });
    }

    function ttNotaToggle() {
        ttNotaInit();
        var hidden  = document.getElementById('tt-nota-hidden');
        var original = hidden.getAttribute('data-original') || '';
        // Cargar el contenido HTML actual en Quill
        ttQuill.root.innerHTML = original;
        document.getElementById('tt-nota-view').style.display = 'none';
        document.getElementById('tt-nota-edit').style.display = 'block';
        document.getElementById('tt-nota-edit-btn').style.display = 'none';
    }

    function ttNotaCancelar() {
        document.getElementById('tt-nota-view').style.display = 'block';
        document.getElementById('tt-nota-edit').style.display = 'none';
        document.getElementById('tt-nota-edit-btn').style.display = '';
    }

    function ttNotaGuardar() {
        ttNotaInit();
        var nota   = ttQuill.root.innerHTML;
        // Si solo hay <p><br></p> vacío, guardar vacío
        if (nota === '<p><br></p>') nota = '';
        var hidden = document.getElementById('tt-nota-hidden');
        appLoader.show();
        appAjaxRequest({
            url: '<?php echo get_uri("proposals/save_note/" . $proposal_info->id); ?>',
            type: 'POST',
            data: { note: nota },
            dataType: 'json',
            success: function(resp) {
                appLoader.hide();
                if (resp.success) {
                    hidden.setAttribute('data-original', nota);
                    document.getElementById('tt-nota-view').innerHTML = resp.note_html || '<em style="color:#bbb;">Sin notas</em>';
                    ttNotaCancelar();
                    appAlert.success(resp.message || 'Nota guardada', { duration: 3000 });
                } else {
                    appAlert.error(resp.message || 'Error guardando la nota');
                }
            }
        });
    }
    </script>
    <?php endif; ?>

</div>