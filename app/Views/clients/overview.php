<div class="clearfix mt15">
    <div class="details-view-wrapper d-flex">

        <!-- ═══ COLUMNA PRINCIPAL — solo onboarding ═══ -->
        <div class="w-100">
            <div class="w-100 details-view-left-section">
        <!-- ═══ LÍNEA DE TIEMPO DE NOTAS (sin scroll propio, altura libre) ═══ -->
        <?php echo view("clients/client_notes_timeline", array("client_info" => $client_info)); ?>
            </div>
        </div>

        <!-- ═══ SIDEBAR DERECHO ═══ -->
        <div class="flex-shrink-0 details-view-right-section">

            <!-- Info del cliente (nativo) -->
            <div id="client-details-client-info">
                <?php echo view("clients/client_info"); ?>
            </div>
            <div id="client-details-client-custom-fields-info">
                <?php echo view("clients/client_custom_fields_info"); ?>
            </div>

            <!-- Contactos -->
            <div class="card" id="tt-sidebar-contacts" style="margin-bottom:16px;">
                <div class="card-header fw-bold d-flex align-items-center justify-content-between" style="padding:12px 16px;">
                    <span><i data-feather="users" class="icon-14 mr5"></i> Contactos</span>
                    <?php if ($can_edit_clients): ?>
                    <span><?php echo modal_anchor(get_uri("clients/add_new_contact_modal_form"), "<i data-feather='plus' class='icon-12'></i>", array("class" => "text-muted", "title" => "Añadir contacto", "data-post-client_id" => $client_info->id)); ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-body" style="padding:8px 0;" id="tt-contacts-body">
                    <div style="padding:12px 16px;color:#ccc;font-size:12px;">Cargando...</div>
                </div>
            </div>

            <!-- Proyectos -->
            <?php if ($show_project_info): ?>
            <div class="card" id="tt-sidebar-projects" style="margin-bottom:16px;">
                <div class="card-header fw-bold d-flex align-items-center justify-content-between" style="padding:12px 16px;">
                    <span><i data-feather="command" class="icon-14 mr5"></i> Proyectos</span>
                    <a href="<?php echo get_uri('clients/view/' . $client_info->id . '/projects'); ?>" class="text-muted" style="font-size:11px;">Ver todos</a>
                </div>
                <div class="card-body" style="padding:8px 0;" id="tt-projects-body">
                    <div style="padding:12px 16px;color:#ccc;font-size:12px;">Cargando...</div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Propuestas -->
            <?php if ($show_proposal_info): ?>
            <div class="card" id="tt-sidebar-proposals" style="margin-bottom:16px;">
                <div class="card-header fw-bold d-flex align-items-center justify-content-between" style="padding:12px 16px;">
                    <span><i data-feather="file-text" class="icon-14 mr5"></i> Propuestas</span>
                    <a href="<?php echo get_uri('clients/view/' . $client_info->id . '/proposals'); ?>" class="text-muted" style="font-size:11px;">Ver todas</a>
                </div>
                <div class="card-body" style="padding:8px 0;" id="tt-proposals-body">
                    <div style="padding:12px 16px;color:#ccc;font-size:12px;">Cargando...</div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Contratos -->
            <?php if ($show_contract_info): ?>
            <div class="card" id="tt-sidebar-contracts" style="margin-bottom:16px;">
                <div class="card-header fw-bold d-flex align-items-center justify-content-between" style="padding:12px 16px;">
                    <span><i data-feather="file-plus" class="icon-14 mr5"></i> Contratos</span>
                    <a href="<?php echo get_uri('clients/view/' . $client_info->id . '/contracts'); ?>" class="text-muted" style="font-size:11px;">Ver todos</a>
                </div>
                <div class="card-body" style="padding:8px 0;" id="tt-contracts-body">
                    <div style="padding:12px 16px;color:#ccc;font-size:12px;">Cargando...</div>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /sidebar -->
    </div>
</div>

<style>
.tt-sidebar-item {
    padding:10px 16px;
    border-bottom:1px solid #f5f5f5;
    display:flex;
    align-items:center;
    gap:10px;
    font-size:13px;
    transition:background 0.15s;
}
.tt-sidebar-item:last-child { border-bottom:none; }
.tt-sidebar-item:hover { background:#fafafa; }
.tt-sidebar-item a { color:#1a1a1a; text-decoration:none; font-weight:500; }
.tt-sidebar-item a:hover { color:#d72173; }
.tt-sidebar-badge {
    font-size:10px; padding:2px 7px; border-radius:20px; font-weight:600;
    white-space:nowrap;
}
.tt-sidebar-empty { padding:14px 16px; color:#bbb; font-size:12px; text-align:center; }
</style>

<script type="text/javascript">
$(document).ready(function() {

    var CLIENT_ID = <?php echo intval($client_info->id); ?>;
    var BASE_URI  = '<?php echo get_uri(""); ?>';

    // ── Reload de client_info (nativo) ───────────────
    appContentBuilder.init(BASE_URI + 'clients/overview/' + CLIENT_ID, {
        id: "client-details-page-builder",
        data: { view_type: "client_meta" },
        reloadHooks: [
            { type: "app_form", id: "client-form" },
            { type: "app_modifier", group: "client_info" }
        ],
        reload: function(bind, result) {
            bind("#client-details-client-info", result.client_info);
            bind("#client-details-client-custom-fields-info", result.client_custom_fields_info);
        }
    });

    <?php if ($can_edit_clients): ?>
    $('body').on('click', '[data-act=client-modifier]', function(e) {
        $(this).appModifier({
            dropdownData: {
                labels:   <?php echo json_encode($label_suggestions); ?>,
                group_ids:<?php echo json_encode($groups_dropdown); ?>,
                owner_id: <?php echo json_encode($team_members_dropdown); ?>,
                managers: <?php echo json_encode($team_members_dropdown); ?>
            }
        });
        return false;
    });
    <?php endif; ?>

    // ── Cargar contactos ─────────────────────────────
    $.ajax({
        url: BASE_URI + 'clients/contacts_list_data/' + CLIENT_ID,
        data: {},
        dataType: 'json',
        success: function(resp) {
            var rows = resp.data || [];
            if (!rows.length) {
                $('#tt-contacts-body').html('<div class="tt-sidebar-empty">Sin contactos</div>');
                return;
            }
            var html = '';
            rows.forEach(function(r) {
                // r[1] contiene el anchor con el link al perfil del contacto
                var linkEl  = $('<div>').html(r[1]);
                var anchor  = linkEl.find('a').first();
                var href    = anchor.attr('href') || '#';
                var nombre  = anchor.text().trim() || linkEl.text().trim();
                var email   = r[5] || '';
                var tel     = (r[6] && r[6] !== '-') ? r[6] : '';
                var inicial = (nombre.trim().charAt(0) || '?').toUpperCase();
                html += '<a href="' + href + '" class="tt-sidebar-item" style="text-decoration:none;">' +
                    '<div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#d72173,#ff6b9d);display:flex;align-items:center;justify-content:center;flex-shrink:0;">' +
                    '<span style="color:#fff;font-size:11px;font-weight:700;">' + inicial + '</span></div>' +
                    '<div style="flex:1;min-width:0;">' +
                    '<div style="font-weight:600;font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#1a1a1a;">' + nombre + '</div>' +
                    (email ? '<div style="font-size:11px;color:#888;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + email + '</div>' : '') +
                    '</div>' +
                    (tel ? '<span style="font-size:11px;color:#aaa;">' + tel + '</span>' : '') +
                    '</a>';
            });
            $('#tt-contacts-body').html(html);
        }
    });

    // ── Cargar proyectos ─────────────────────────────
    <?php if ($show_project_info): ?>
    $.ajax({
        url: BASE_URI + 'clients/get_sidebar_projects/' + CLIENT_ID,
        dataType: 'json',
        success: function(resp) {
            var rows = resp.data || [];
            if (!rows.length) {
                $('#tt-projects-body').html('<div class="tt-sidebar-empty">Sin proyectos</div>');
                return;
            }
            var html = '';
            rows.slice(0, 8).forEach(function(r) {
                var sc = r.status === 'completed' ? '#4caf50' : (r.status === 'canceled' ? '#e53935' : (r.status === 'hold' ? '#ff9800' : '#d72173'));
                html += '<div class="tt-sidebar-item">' +
                    '<i data-feather="command" style="width:14px;height:14px;color:#d72173;flex-shrink:0;"></i>' +
                    '<div style="flex:1;min-width:0;">' +
                    '<a href="' + BASE_URI + 'projects/view/' + r.id + '" style="font-size:12px;font-weight:600;color:#1a1a1a;">' + r.title + '</a>' +
                    '</div>' +
                    '<span class="tt-sidebar-badge" style="background:' + sc + '22;color:' + sc + ';">' + r.status_label + '</span>' +
                    '</div>';
            });
            if (rows.length > 8) html += '<div class="tt-sidebar-empty">+' + (rows.length - 8) + ' más</div>';
            $('#tt-projects-body').html(html);
            if (window.feather) feather.replace();
        },
        error: function() {
            $('#tt-projects-body').html('<div class="tt-sidebar-empty">Error cargando proyectos</div>');
        }
    });
    <?php endif; ?>

    // ── Cargar propuestas ────────────────────────────
    <?php if ($show_proposal_info): ?>
    $.ajax({
        url: BASE_URI + 'proposals/proposal_list_data_of_client/' + CLIENT_ID,
        dataType: 'json',
        success: function(resp) {
            var rows = resp.data || [];
            if (!rows.length) {
                $('#tt-proposals-body').html('<div class="tt-sidebar-empty">Sin propuestas</div>');
                return;
            }
            var statusColors = { 'draft':'#888', 'sent':'#2196f3', 'accepted':'#4caf50', 'declined':'#e53935' };
            var html = '';
            rows.slice(0, 6).forEach(function(r) {
                var link = $('<div>').html(r[0]);
                var href = link.find('a').attr('href') || '#';
                var text = link.text().trim();
                var client = $('<div>').html(r[1]).text().trim();
                var status = $('<div>').html(r[3]).text().trim().toLowerCase();
                var sc = statusColors[status] || '#888';
                html += '<div class="tt-sidebar-item">' +
                    '<i data-feather="file-text" style="width:14px;height:14px;color:#888;flex-shrink:0;"></i>' +
                    '<div style="flex:1;min-width:0;">' +
                    '<a href="' + href + '" style="font-size:12px;font-weight:600;">' + text + '</a>' +
                    (client ? '<div style="font-size:11px;color:#aaa;">' + client + '</div>' : '') +
                    '</div>' +
                    '<span class="tt-sidebar-badge" style="background:' + sc + '22;color:' + sc + ';">' + status + '</span>' +
                    '</div>';
            });
            if (rows.length > 6) html += '<div class="tt-sidebar-empty">+' + (rows.length-6) + ' más</div>';
            $('#tt-proposals-body').html(html);
            if (window.feather) feather.replace();
        }
    });
    <?php endif; ?>

    // ── Cargar contratos ─────────────────────────────
    <?php if ($show_contract_info): ?>
    $.ajax({
        url: BASE_URI + 'contracts/contract_list_data_of_client/' + CLIENT_ID,
        dataType: 'json',
        success: function(resp) {
            var rows = resp.data || [];
            if (!rows.length) {
                $('#tt-contracts-body').html('<div class="tt-sidebar-empty">Sin contratos</div>');
                return;
            }
            var statusColors = { 'draft':'#888', 'sent':'#2196f3', 'accepted':'#4caf50', 'declined':'#e53935' };
            var html = '';
            rows.slice(0, 6).forEach(function(r) {
                var link = $('<div>').html(r[0]);
                var href = link.find('a').attr('href') || '#';
                var text = link.text().trim();
                var status = $('<div>').html(r[4] || r[3] || '').text().trim().toLowerCase();
                var sc = statusColors[status] || '#888';
                html += '<div class="tt-sidebar-item">' +
                    '<i data-feather="file-plus" style="width:14px;height:14px;color:#888;flex-shrink:0;"></i>' +
                    '<div style="flex:1;min-width:0;">' +
                    '<a href="' + href + '" style="font-size:12px;font-weight:600;">' + text + '</a>' +
                    '</div>' +
                    '<span class="tt-sidebar-badge" style="background:' + sc + '22;color:' + sc + ';">' + status + '</span>' +
                    '</div>';
            });
            if (rows.length > 6) html += '<div class="tt-sidebar-empty">+' + (rows.length-6) + ' más</div>';
            $('#tt-contracts-body').html(html);
            if (window.feather) feather.replace();
        }
    });
    <?php endif; ?>

});
</script>