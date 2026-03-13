<div id="modal-ver-prospecto" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-xl" role="document" style="max-width:90%;">
        <div class="modal-content" style="border-radius:16px;overflow:hidden;border:none;">

            <!-- HEADER -->
            <div class="modal-header" style="background:linear-gradient(135deg,#a8005a 0%,#d72173 60%,#ff4da6 100%);padding:20px 28px;border:none;">
                <div style="display:flex;align-items:center;gap:14px;flex:1;">
                    <div style="width:48px;height:48px;border-radius:50%;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;font-size:20px;color:#fff;font-weight:700;flex-shrink:0;">
                        <?php echo strtoupper(mb_substr($prospecto->nombre ?: '?', 0, 1)); ?>
                    </div>
                    <div>
                        <h5 class="modal-title" style="color:#fff;margin:0;font-size:18px;font-weight:700;">
                            <?php echo clean_data($prospecto->nombre) ?: '(Sin nombre)'; ?>
                        </h5>
                        <div style="color:rgba(255,255,255,0.75);font-size:13px;margin-top:2px;">
                            <?php
                            $estados_header = array(
                                'nuevo'       => '🔴 Nuevo',
                                'en_contacto' => '🟡 En contacto',
                                'perdido'     => '🔴 Perdido',
                                'convertido'  => '🟢 Convertido',
                            );
                            echo isset($estados_header[$prospecto->estado]) ? $estados_header[$prospecto->estado] : $prospecto->estado;
                            ?>
                            &nbsp;·&nbsp; Recibido <?php echo format_to_datetime($prospecto->fecha_recepcion); ?>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" style="margin-left:12px;"></button>
            </div>

            <div class="modal-body" style="padding:0;background:#f8f9fa;">
                <div style="display:flex;min-height:520px;">

                    <!-- COLUMNA IZQUIERDA: datos -->
                    <div style="padding:24px;border-right:1px solid #e9ecef;background:#fff;min-width:0;">

                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
                            <h6 style="margin:0;color:#555;font-size:12px;letter-spacing:1.5px;text-transform:uppercase;font-weight:700;">Datos del prospecto</h6>
                            <button id="btn-editar-toggle" class="btn btn-sm" style="background:#f0e6f0;color:#d72173;border:none;font-size:12px;font-weight:600;border-radius:8px;padding:5px 14px;">
                                <i data-feather="edit-2" class="icon-14"></i> Editar
                            </button>
                        </div>

                        <?php
                        $p = $prospecto;
                        $campos_edicion = array(
                            array('key' => 'nombre',   'label' => 'Nombre',   'icon' => '👤', 'type' => 'text'),
                            array('key' => 'email',    'label' => 'Email',    'icon' => '✉️', 'type' => 'email'),
                            array('key' => 'telefono', 'label' => 'Teléfono', 'icon' => '📞', 'type' => 'text'),
                            array('key' => 'web',      'label' => 'Web',      'icon' => '🌐', 'type' => 'text'),
                        );
                        ?>

                        <div id="campos-display">
                            <?php foreach ($campos_edicion as $c): ?>
                            <div style="display:flex;align-items:center;padding:10px 0;border-bottom:1px solid #f0f0f0;">
                                <span style="width:28px;font-size:15px;"><?php echo $c['icon']; ?></span>
                                <span style="width:80px;font-size:12px;color:#aaa;text-transform:uppercase;letter-spacing:.5px;"><?php echo $c['label']; ?></span>
                                <span style="flex:1;font-size:14px;color:#333;font-weight:500;" id="display-<?php echo $c['key']; ?>">
                                    <?php echo clean_data($p->{$c['key']}) ?: '<span style="color:#ccc;">—</span>'; ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                            <?php if ($p->mensaje): ?>
                            <div style="padding:12px 0;border-bottom:1px solid #f0f0f0;">
                                <div style="display:flex;align-items:flex-start;gap:0;">
                                    <span style="width:28px;font-size:15px;flex-shrink:0;">💬</span>
                                    <span style="width:80px;font-size:12px;color:#aaa;text-transform:uppercase;letter-spacing:.5px;flex-shrink:0;padding-top:2px;">Mensaje</span>
                                    <span style="flex:1;font-size:14px;color:#333;line-height:1.6;white-space:pre-wrap;" id="display-mensaje"><?php echo clean_data($p->mensaje); ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div id="campos-edit" style="display:none;">
                            <?php foreach ($campos_edicion as $c): ?>
                            <div style="margin-bottom:12px;">
                                <label style="font-size:12px;color:#aaa;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;display:block;">
                                    <?php echo $c['icon']; ?> <?php echo $c['label']; ?>
                                </label>
                                <input type="<?php echo $c['type']; ?>"
                                       class="form-control form-control-sm edit-campo"
                                       data-campo="<?php echo $c['key']; ?>"
                                       value="<?php echo htmlspecialchars($p->{$c['key']} ?? '', ENT_QUOTES); ?>"
                                       style="border-radius:8px;">
                            </div>
                            <?php endforeach; ?>
                            <div style="margin-bottom:12px;">
                                <label style="font-size:12px;color:#aaa;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;display:block;">🏷 Estado</label>
                                <select class="form-control form-control-sm edit-campo" data-campo="estado" style="border-radius:8px;">
                                    <option value="nuevo"       <?php echo $p->estado == 'nuevo'       ? 'selected' : ''; ?>>Nuevo</option>
                                    <option value="en_contacto" <?php echo $p->estado == 'en_contacto' ? 'selected' : ''; ?>>En contacto</option>
                                    <option value="perdido"     <?php echo $p->estado == 'perdido'     ? 'selected' : ''; ?>>Perdido</option>
                                    <option value="convertido"  <?php echo $p->estado == 'convertido'  ? 'selected' : ''; ?>>Convertido</option>
                                </select>
                            </div>
                            <div style="margin-bottom:12px;">
                                <label style="font-size:12px;color:#aaa;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;display:block;">💬 Mensaje</label>
                                <textarea class="form-control form-control-sm edit-campo" data-campo="mensaje" rows="3" style="border-radius:8px;"><?php echo htmlspecialchars($p->mensaje ?? '', ENT_QUOTES); ?></textarea>
                            </div>
                            <div style="display:flex;gap:8px;">
                                <button id="btn-guardar-edicion" class="btn btn-sm btn-primary" style="background:#d72173;border-color:#d72173;border-radius:8px;flex:1;">
                                    <i data-feather="check" class="icon-14"></i> Guardar cambios
                                </button>
                                <button id="btn-cancelar-edicion" class="btn btn-sm btn-default" style="border-radius:8px;">Cancelar</button>
                            </div>
                        </div>

                        <!-- Info técnica colapsada -->
                        <div style="margin-top:20px;">
                            <div id="toggle-info-tecnica" style="cursor:pointer;font-size:12px;color:#aaa;display:flex;align-items:center;gap:6px;padding:8px 0;border-top:1px solid #f0f0f0;user-select:none;">
                                <i data-feather="info" class="icon-14"></i> Info técnica
                                <i data-feather="chevron-down" class="icon-12" id="chevron-tecnica" style="transition:transform .2s;"></i>
                            </div>
                            <div id="info-tecnica" style="display:none;font-size:12px;color:#999;">
                                <?php if ($p->pagina_origen): ?><div style="padding:4px 0;"><strong>Origen:</strong> <?php echo clean_data($p->pagina_origen); ?></div><?php endif; ?>
                                <?php if ($p->fecha_envio):   ?><div style="padding:4px 0;"><strong>Fecha envío:</strong> <?php echo clean_data($p->fecha_envio); ?></div><?php endif; ?>
                                <?php if ($p->ip):            ?><div style="padding:4px 0;"><strong>IP:</strong> <?php echo clean_data($p->ip); ?></div><?php endif; ?>
                                <?php if ($p->user_agent):    ?><div style="padding:4px 0;word-break:break-all;"><strong>Navegador:</strong> <?php echo clean_data($p->user_agent); ?></div><?php endif; ?>
                                <?php if ($p->privacidad):    ?><div style="padding:4px 0;"><strong>Privacidad:</strong> <?php echo clean_data($p->privacidad); ?></div><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- COLUMNA DERECHA: timeline de notas -->
                    <div style="flex:1;padding:24px;background:#f8f9fa;min-width:0;display:flex;flex-direction:column;">

                        <h6 style="margin:0 0 18px;color:#555;font-size:12px;letter-spacing:1.5px;text-transform:uppercase;font-weight:700;">
                            <i data-feather="message-square" class="icon-14"></i> &nbsp;Notas &amp; seguimiento
                        </h6>

                        <!-- Timeline -->
                        <div id="notas-timeline" style="flex:1;overflow-y:auto;max-height:360px;margin-bottom:16px;padding-right:4px;">
                            <?php if (empty($notas)): ?>
                            <div id="notas-empty" style="text-align:center;padding:40px 20px;color:#ccc;">
                                <div style="font-size:36px;margin-bottom:8px;">📋</div>
                                <div style="font-size:13px;">Sin notas todavía.<br>Añade la primera nota abajo.</div>
                            </div>
                            <?php else: ?>
                            <?php foreach ($notas as $nota): ?>
                            <?php
                                $pinned     = $nota->pinned ? true : false;
                                $bg_color   = $pinned ? '#fffbea' : '#fff';
                                $border_col = $pinned ? '#f0c040' : '#d72173';
                                $pin_icon   = $pinned ? '📌' : '📍';
                                $pin_title  = $pinned ? 'Quitar pin' : 'Pinear nota';
                            ?>
                            <div class="nota-item <?php echo $pinned ? 'nota-pinned' : ''; ?>"
                                 data-nota-id="<?php echo $nota->id; ?>"
                                 data-pinned="<?php echo $pinned ? '1' : '0'; ?>"
                                 style="display:flex;gap:12px;margin-bottom:16px;">
                                <div style="display:flex;flex-direction:column;align-items:center;flex-shrink:0;">
                                    <div style="width:10px;height:10px;border-radius:50%;background:<?php echo $border_col; ?>;margin-top:4px;flex-shrink:0;"></div>
                                    <div style="width:2px;flex:1;background:#f0e6f0;margin-top:4px;"></div>
                                </div>
                                <div class="nota-card" style="flex:1;background:<?php echo $bg_color; ?>;border-radius:10px;padding:12px 14px;box-shadow:0 1px 4px rgba(0,0,0,0.06);border-left:3px solid <?php echo $border_col; ?>;">
                                    <?php if ($pinned): ?>
                                    <div style="font-size:10px;font-weight:700;color:#b8860b;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">
                                        📌 Nota destacada
                                    </div>
                                    <?php endif; ?>
                                    <!-- Texto display -->
                                    <div class="nota-texto-display" style="font-size:13px;color:#333;line-height:1.6;white-space:pre-wrap;"><?php echo clean_data($nota->texto); ?></div>
                                    <!-- Textarea edición (oculto) -->
                                    <textarea class="nota-texto-edit form-control form-control-sm" rows="3"
                                              style="display:none;border-radius:8px;font-size:13px;resize:none;margin-bottom:8px;"><?php echo htmlspecialchars($nota->texto, ENT_QUOTES); ?></textarea>
                                    <!-- Botones edición (ocultos) -->
                                    <div class="nota-edit-actions" style="display:none;gap:6px;margin-bottom:6px;">
                                        <button class="btn-guardar-nota-edit btn btn-sm btn-primary" style="background:#d72173;border-color:#d72173;border-radius:6px;font-size:12px;">
                                            <i data-feather="check" class="icon-12"></i> Guardar
                                        </button>
                                        <button class="btn-cancelar-nota-edit btn btn-sm btn-default" style="border-radius:6px;font-size:12px;">Cancelar</button>
                                    </div>
                                    <!-- Metadatos y acciones -->
                                    <div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;flex-wrap:wrap;gap:4px;">
                                        <span style="font-size:11px;color:#bbb;">
                                            <?php echo format_to_datetime($nota->created_at); ?>
                                            <?php if ($nota->updated_at): ?>
                                            · <em>editada <?php echo format_to_datetime($nota->updated_at); ?></em>
                                            <?php endif; ?>
                                        </span>
                                        <div style="display:flex;gap:10px;align-items:center;">
                                            <a href="#" class="btn-pin-nota" data-id="<?php echo $nota->id; ?>" title="<?php echo $pin_title; ?>" style="font-size:14px;text-decoration:none;"><?php echo $pin_icon; ?></a>
                                            <a href="#" class="btn-editar-nota" data-id="<?php echo $nota->id; ?>" style="font-size:11px;color:#888;text-decoration:none;">Editar</a>
                                            <a href="#" class="btn-borrar-nota" data-id="<?php echo $nota->id; ?>" style="font-size:11px;color:#e74c3c;text-decoration:none;">Borrar</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Añadir nota -->
                        <div style="background:#fff;border-radius:12px;padding:14px;box-shadow:0 1px 4px rgba(0,0,0,0.06);">
                            <textarea id="nueva-nota-texto" class="form-control" rows="3"
                                placeholder="Escribe una nota sobre este prospecto..."
                                style="border:1px solid #e9ecef;border-radius:8px;font-size:13px;resize:none;"></textarea>
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:10px;">
                                <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:#888;cursor:pointer;margin:0;">
                                    <input type="checkbox" id="nueva-nota-pinned" style="accent-color:#d72173;">
                                    📌 Pinear esta nota
                                </label>
                                <button id="btn-anadir-nota" class="btn btn-sm btn-primary"
                                        data-prospecto-id="<?php echo $prospecto->id; ?>"
                                        style="background:#d72173;border-color:#d72173;border-radius:8px;font-size:13px;padding:6px 18px;">
                                    <i data-feather="plus" class="icon-14"></i> Añadir nota
                                </button>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <div class="modal-footer" style="background:#fff;border-top:1px solid #f0f0f0;padding:12px 24px;justify-content:space-between;">
                <button type="button" id="btn-eliminar-prospecto" class="btn btn-sm" style="background:#fff;border:1px solid #e74c3c;color:#e74c3c;border-radius:8px;padding:6px 14px;">
                    <i data-feather="trash-2" class="icon-14"></i> Eliminar lead
                </button>
                <button type="button" class="btn btn-default" data-bs-dismiss="modal" style="border-radius:8px;">Cerrar</button>
            </div>

        </div>
    </div>
</div>

<style>
.nota-pinned .nota-card { animation: pinPulse .4s ease; }
@keyframes pinPulse { 0%{transform:scale(1)} 50%{transform:scale(1.02)} 100%{transform:scale(1)} }
</style>

<script>
(function(){
    var _baseUrl    = AppHelper.baseUrl + 'index.php/';
    var prospectoId = <?php echo (int)$prospecto->id; ?>;

    // ── Toggle info técnica ──────────────────────────────────────────────────
    $('#toggle-info-tecnica').on('click', function(){
        $('#info-tecnica').slideToggle(200);
        $('#chevron-tecnica').css('transform', $('#info-tecnica').is(':visible') ? 'rotate(180deg)' : 'rotate(0deg)');
    });

    // ── Edición de datos del prospecto ───────────────────────────────────────
    $('#btn-editar-toggle').on('click', function(){
        $('#campos-display').hide();
        $('#campos-edit').show();
        $(this).hide();
    });
    $('#btn-cancelar-edicion').on('click', function(){
        $('#campos-edit').hide();
        $('#campos-display').show();
        $('#btn-editar-toggle').show();
    });
    $('#btn-guardar-edicion').on('click', function(){
        var datos = {id: prospectoId};
        $('.edit-campo').each(function(){ datos[$(this).data('campo')] = $(this).val(); });
        var $btn = $(this).prop('disabled', true).text('Guardando...');
        $.post(_baseUrl + 'prospectos/save_edicion', datos, function(r){
            $btn.prop('disabled', false).html('<i data-feather="check" class="icon-14"></i> Guardar cambios');
            if (typeof feather !== 'undefined') feather.replace();
            if (r.success) {
                $('.edit-campo').each(function(){
                    var campo = $(this).data('campo');
                    var valor = $(this).val();
                    var $d = $('#display-' + campo);
                    if ($d.length) $d.html(valor ? $('<div>').text(valor).html() : '<span style="color:#ccc;">—</span>');
                });
                $('#campos-edit').hide();
                $('#campos-display').show();
                $('#btn-editar-toggle').show();
                appAlert.success('Cambios guardados.', {duration: 2500});
                if (typeof cargarKanban === 'function') cargarKanban();
            } else {
                appAlert.error(r.message || 'Error al guardar.', {duration: 3000});
            }
        }, 'json');
    });

    // ── Añadir nota ──────────────────────────────────────────────────────────
    $('#btn-anadir-nota').on('click', function(){
        var texto  = $.trim($('#nueva-nota-texto').val());
        var pinned = $('#nueva-nota-pinned').is(':checked') ? 1 : 0;
        if (!texto) return;
        var $btn = $(this).prop('disabled', true);
        $.post(_baseUrl + 'prospectos/save_nota', {
            prospecto_id: prospectoId,
            texto:        texto,
            pinned:       pinned,
        }, function(r){
            $btn.prop('disabled', false);
            if (r.success) {
                $('#nueva-nota-texto').val('');
                $('#nueva-nota-pinned').prop('checked', false);
                $('#notas-empty').remove();
                var $nueva = $(construirHtmlNota(r.nota));
                if (r.nota.pinned) {
                    // Pinned va antes de las no pinned
                    var $primera_no_pinned = $('#notas-timeline .nota-item:not(.nota-pinned)').first();
                    if ($primera_no_pinned.length) {
                        $primera_no_pinned.before($nueva);
                    } else {
                        $('#notas-timeline').prepend($nueva);
                    }
                } else {
                    // No pinned va después de las pinned
                    var $ultima_pinned = $('#notas-timeline .nota-pinned').last();
                    if ($ultima_pinned.length) {
                        $ultima_pinned.after($nueva);
                    } else {
                        $('#notas-timeline').prepend($nueva);
                    }
                }
                if (typeof feather !== 'undefined') feather.replace();
            } else {
                appAlert.error('Error al añadir nota.', {duration: 3000});
            }
        }, 'json');
    });

    // ── Editar nota ──────────────────────────────────────────────────────────
    $('#notas-timeline').on('click', '.btn-editar-nota', function(e){
        e.preventDefault();
        var $item = $(this).closest('.nota-item');
        $item.find('.nota-texto-display').hide();
        $item.find('.nota-texto-edit').show().focus();
        $item.find('.nota-edit-actions').css('display', 'flex');
        $(this).hide();
    });

    // Cancelar edición nota
    $('#notas-timeline').on('click', '.btn-cancelar-nota-edit', function(e){
        e.preventDefault();
        var $item = $(this).closest('.nota-item');
        $item.find('.nota-texto-display').show();
        $item.find('.nota-texto-edit').hide();
        $item.find('.nota-edit-actions').hide();
        $item.find('.btn-editar-nota').show();
    });

    // Guardar edición nota
    $('#notas-timeline').on('click', '.btn-guardar-nota-edit', function(e){
        e.preventDefault();
        var $item  = $(this).closest('.nota-item');
        var notaId = $item.data('nota-id');
        var texto  = $item.find('.nota-texto-edit').val();
        if (!$.trim(texto)) return;

        var $btn = $(this).prop('disabled', true).text('...');
        $.post(_baseUrl + 'prospectos/update_nota', {id: notaId, texto: texto}, function(r){
            $btn.prop('disabled', false).html('<i data-feather="check" class="icon-12"></i> Guardar');
            if (typeof feather !== 'undefined') feather.replace();
            if (r.success) {
                $item.find('.nota-texto-display').html($('<div>').text(texto).html()).show();
                $item.find('.nota-texto-edit').hide();
                $item.find('.nota-edit-actions').hide();
                $item.find('.btn-editar-nota').show();
                // Actualizar fecha edición si existe
                if (r.updated_at) {
                    var $fecha = $item.find('.nota-fecha');
                    $fecha.find('.nota-editada').remove();
                    $fecha.append(' · <em class="nota-editada">editada ' + r.updated_at + '</em>');
                }
            } else {
                appAlert.error('Error al guardar.', {duration: 3000});
            }
        }, 'json');
    });

    // ── Pinear / despinear nota ──────────────────────────────────────────────
    $('#notas-timeline').on('click', '.btn-pin-nota', function(e){
        e.preventDefault();
        var $btn    = $(this);
        var notaId  = $btn.data('id');
        var $item   = $btn.closest('.nota-item');
        var pinned  = $item.data('pinned') == 1 ? 0 : 1;

        $.post(_baseUrl + 'prospectos/toggle_pin_nota', {id: notaId, pinned: pinned}, function(r){
            if (r.success) {
                $item.data('pinned', pinned);

                if (pinned) {
                    // Estilo pinned
                    $item.addClass('nota-pinned');
                    $item.find('.nota-card')
                        .css({'background': '#fffbea', 'border-left-color': '#f0c040'});
                    $item.find('.dot-estado').css('background', '#f0c040');
                    $btn.text('📌').attr('title', 'Quitar pin');
                    // Añadir badge si no existe
                    if (!$item.find('.nota-pin-badge').length) {
                        $item.find('.nota-card').prepend('<div class="nota-pin-badge" style="font-size:10px;font-weight:700;color:#b8860b;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">📌 Nota destacada</div>');
                    }
                    // Mover al principio (antes de las no pinned)
                    var $primera_no_pinned = $('#notas-timeline .nota-item:not(.nota-pinned)').first();
                    if ($primera_no_pinned.length) {
                        $item.insertBefore($primera_no_pinned);
                    } else {
                        $('#notas-timeline').prepend($item);
                    }
                } else {
                    // Estilo normal
                    $item.removeClass('nota-pinned');
                    $item.find('.nota-card')
                        .css({'background': '#fff', 'border-left-color': '#d72173'});
                    $item.find('.dot-estado').css('background', '#d72173');
                    $btn.text('📍').attr('title', 'Pinear nota');
                    $item.find('.nota-pin-badge').remove();
                    // Mover después de las pinned
                    var $ultima_pinned = $('#notas-timeline .nota-pinned').last();
                    if ($ultima_pinned.length) {
                        $item.insertAfter($ultima_pinned);
                    } else {
                        $('#notas-timeline').prepend($item);
                    }
                }
            }
        }, 'json');
    });

    // ── Borrar nota ──────────────────────────────────────────────────────────
    $('#notas-timeline').on('click', '.btn-borrar-nota', function(e){
        e.preventDefault();
        var notaId = $(this).data('id');
        var $item  = $(this).closest('.nota-item');
        if (!confirm('¿Borrar esta nota?')) return;
        $.post(_baseUrl + 'prospectos/delete_nota', {id: notaId}, function(r){
            if (r.success) $item.fadeOut(300, function(){ $(this).remove(); });
        }, 'json');
    });

    // ── Borrar prospecto ─────────────────────────────────────────────────────
    $('#btn-eliminar-prospecto').on('click', function(){
        if (!confirm('¿Eliminar este lead? Esta acción no se puede deshacer.')) return;
        $.post(_baseUrl + 'prospectos/delete', {id: prospectoId}, function(r){
            if (r.success) {
                $('#modal-ver-prospecto').modal('hide');
                appAlert.success('Lead eliminado.', {duration: 3000});
                if (typeof cargarKanban === 'function') cargarKanban();
                if (typeof _tablaIniciada !== 'undefined' && _tablaIniciada) {
                    $('#prospectos-table').appTable({reload: true});
                }
            }
        }, 'json');
    });

    // ── Al mostrar modal ─────────────────────────────────────────────────────
    $('#modal-ver-prospecto').on('shown.bs.modal', function(){
        if (typeof feather !== 'undefined') feather.replace();
    });

    // ── Helper: construir HTML de nota nueva ─────────────────────────────────
    function construirHtmlNota(nota) {
        var pinned     = nota.pinned ? true : false;
        var bg         = pinned ? '#fffbea' : '#fff';
        var border     = pinned ? '#f0c040' : '#d72173';
        var pinIcon    = pinned ? '📌' : '📍';
        var pinTitle   = pinned ? 'Quitar pin' : 'Pinear nota';
        var pinBadge   = pinned ? '<div class="nota-pin-badge" style="font-size:10px;font-weight:700;color:#b8860b;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">📌 Nota destacada</div>' : '';
        var claseItem  = pinned ? 'nota-item nota-pinned' : 'nota-item';

        return '<div class="' + claseItem + '" data-nota-id="' + nota.id + '" data-pinned="' + (pinned ? 1 : 0) + '" style="display:flex;gap:12px;margin-bottom:16px;">'
            + '<div style="display:flex;flex-direction:column;align-items:center;flex-shrink:0;">'
            + '<div class="dot-estado" style="width:10px;height:10px;border-radius:50%;background:' + border + ';margin-top:4px;flex-shrink:0;"></div>'
            + '<div style="width:2px;flex:1;background:#f0e6f0;margin-top:4px;"></div>'
            + '</div>'
            + '<div class="nota-card" style="flex:1;background:' + bg + ';border-radius:10px;padding:12px 14px;box-shadow:0 1px 4px rgba(0,0,0,0.06);border-left:3px solid ' + border + ';">'
            + pinBadge
            + '<div class="nota-texto-display" style="font-size:13px;color:#333;line-height:1.6;white-space:pre-wrap;">' + $('<div>').text(nota.texto).html() + '</div>'
            + '<textarea class="nota-texto-edit form-control form-control-sm" rows="3" style="display:none;border-radius:8px;font-size:13px;resize:none;margin-bottom:8px;">' + $('<div>').text(nota.texto).html() + '</textarea>'
            + '<div class="nota-edit-actions" style="display:none;gap:6px;margin-bottom:6px;">'
            + '<button class="btn-guardar-nota-edit btn btn-sm btn-primary" style="background:#d72173;border-color:#d72173;border-radius:6px;font-size:12px;"><i data-feather="check" class="icon-12"></i> Guardar</button>'
            + '<button class="btn-cancelar-nota-edit btn btn-sm btn-default" style="border-radius:6px;font-size:12px;">Cancelar</button>'
            + '</div>'
            + '<div style="display:flex;justify-content:space-between;align-items:center;margin-top:8px;flex-wrap:wrap;gap:4px;">'
            + '<span class="nota-fecha" style="font-size:11px;color:#bbb;">' + nota.created_at + '</span>'
            + '<div style="display:flex;gap:10px;align-items:center;">'
            + '<a href="#" class="btn-pin-nota" data-id="' + nota.id + '" title="' + pinTitle + '" style="font-size:14px;text-decoration:none;">' + pinIcon + '</a>'
            + '<a href="#" class="btn-editar-nota" data-id="' + nota.id + '" style="font-size:11px;color:#888;text-decoration:none;">Editar</a>'
            + '<a href="#" class="btn-borrar-nota" data-id="' + nota.id + '" style="font-size:11px;color:#e74c3c;text-decoration:none;">Borrar</a>'
            + '</div></div></div></div>';
    }

})();
</script>