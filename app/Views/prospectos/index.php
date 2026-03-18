<div id="page-content" class="page-wrapper clearfix">

    <!-- ── KANBAN ─────────────────────────────────────────────────────────── -->
    <div class="card" style="padding:20px 24px 24px;">
        <div class="tab-title clearfix" style="margin-bottom:20px;">
            <h4 style="margin:0;display:inline-block;"><i data-feather="inbox" class="icon-16"></i> &nbsp;Leads</h4>
            <div class="title-button-group" style="float:right;">
                <a href="#" id="btn-nuevo-prospecto" class="btn btn-primary" style="background:#d72173;border-color:#d72173;">
                    <i data-feather="plus" class="icon-16"></i> Añadir lead
                </a>
            </div>
        </div>

        <div id="prospectos-kanban" style="display:flex;gap:16px;align-items:flex-start;overflow-x:auto;padding-bottom:8px;">

            <div class="kanban-col" data-estado="nuevo" style="flex:0 0 260px;min-width:260px;background:#f7f7f9;border-radius:12px;padding:12px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;padding:0 4px;">
                    <span style="font-weight:700;font-size:13px;color:#d72173;letter-spacing:.5px;text-transform:uppercase;">
                        <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#d72173;margin-right:6px;"></span>Nuevo
                    </span>
                    <span class="kanban-count badge" style="background:#d72173;font-size:11px;">0</span>
                </div>
                <div class="kanban-cards" data-estado="nuevo" style="min-height:80px;"></div>
            </div>

            <div class="kanban-col" data-estado="en_contacto" style="flex:0 0 260px;min-width:260px;background:#f7f7f9;border-radius:12px;padding:12px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;padding:0 4px;">
                    <span style="font-weight:700;font-size:13px;color:#7b6f00;letter-spacing:.5px;text-transform:uppercase;">
                        <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#C6D617;margin-right:6px;"></span>En contacto
                    </span>
                    <span class="kanban-count badge" style="background:#C6D617;color:#333;font-size:11px;">0</span>
                </div>
                <div class="kanban-cards" data-estado="en_contacto" style="min-height:80px;"></div>
            </div>

            <div class="kanban-col" data-estado="propuesta_enviada" style="flex:0 0 260px;min-width:260px;background:#f7f7f9;border-radius:12px;padding:12px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;padding:0 4px;">
                    <span style="font-weight:700;font-size:13px;color:#5b21b6;letter-spacing:.5px;text-transform:uppercase;">
                        <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#7c3aed;margin-right:6px;"></span>Propuesta enviada
                    </span>
                    <span class="kanban-count badge" style="background:#7c3aed;font-size:11px;">0</span>
                </div>
                <div class="kanban-cards" data-estado="propuesta_enviada" style="min-height:80px;"></div>
            </div>

            <div class="kanban-col" data-estado="convertido" style="flex:0 0 260px;min-width:260px;background:#f7f7f9;border-radius:12px;padding:12px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;padding:0 4px;">
                    <span style="font-weight:700;font-size:13px;color:#1e7e44;letter-spacing:.5px;text-transform:uppercase;">
                        <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#27ae60;margin-right:6px;"></span>Convertido
                    </span>
                    <span class="kanban-count badge" style="background:#27ae60;font-size:11px;">0</span>
                </div>
                <div class="kanban-cards" data-estado="convertido" style="min-height:80px;"></div>
            </div>

            <div class="kanban-col" data-estado="perdido" style="flex:0 0 260px;min-width:260px;background:#f7f7f9;border-radius:12px;padding:12px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;padding:0 4px;">
                    <span style="font-weight:700;font-size:13px;color:#c0392b;letter-spacing:.5px;text-transform:uppercase;">
                        <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#e74c3c;margin-right:6px;"></span>Perdido
                    </span>
                    <span class="kanban-count badge" style="background:#e74c3c;font-size:11px;">0</span>
                </div>
                <div class="kanban-cards" data-estado="perdido" style="min-height:80px;"></div>
            </div>

        </div>
    </div>

    <!-- ── LISTADO COLAPSABLE ─────────────────────────────────────────────── -->
    <div class="card" style="margin-top:20px;">
        <div class="tab-title clearfix" style="cursor:pointer;" id="toggle-listado">
            <h4 style="margin:0;user-select:none;">
                <i data-feather="list" class="icon-16"></i> &nbsp;Listado completo
                <i data-feather="chevron-down" class="icon-16" id="listado-chevron" style="margin-left:8px;transition:transform .3s;"></i>
            </h4>
        </div>
        <div id="listado-contenido" style="display:none;">
            <div class="table-responsive">
                <table id="prospectos-table" class="display" width="100%"></table>
            </div>
        </div>
    </div>

</div>

<!-- ── MODAL AÑADIR PROSPECTO ────────────────────────────────────────────── -->
<div id="modal-nuevo-prospecto" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:#d72173;">
                <h5 class="modal-title" style="color:#fff;"><i data-feather="user-plus" class="icon-16"></i> &nbsp;Añadir prospecto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label style="font-weight:600;">Nombre</label>
                            <input type="text" id="np-nombre" class="form-control" placeholder="Nombre del prospecto">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label style="font-weight:600;">Email</label>
                            <input type="email" id="np-email" class="form-control" placeholder="email@ejemplo.com">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label style="font-weight:600;">Teléfono</label>
                            <input type="text" id="np-telefono" class="form-control" placeholder="Teléfono">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group mb-3">
                            <label style="font-weight:600;">Web</label>
                            <input type="text" id="np-web" class="form-control" placeholder="https://...">
                        </div>
                    </div>
                </div>
                <div class="form-group mb-3">
                    <label style="font-weight:600;">Mensaje</label>
                    <textarea id="np-mensaje" class="form-control" rows="3" placeholder="Mensaje o descripción"></textarea>
                </div>
                <div class="form-group mb-3">
                    <label style="font-weight:600;">Página origen</label>
                    <input type="text" id="np-pagina-origen" class="form-control" placeholder="URL de origen">
                </div>
                <div class="form-group mb-3">
                    <label style="font-weight:600;">Notas internas</label>
                    <textarea id="np-notas" class="form-control" rows="2" placeholder="Notas internas"></textarea>
                </div>
                <div class="form-group mb-0">
                    <label style="font-weight:600;">Estado inicial</label>
                    <select id="np-estado" class="form-control">
                        <option value="nuevo">Nuevo</option>
                        <option value="en_contacto">En contacto</option>
                        <option value="propuesta_enviada">Propuesta enviada</option>
                        <option value="convertido">Convertido</option>
                        <option value="perdido">Perdido</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btn-guardar-prospecto" class="btn btn-primary" style="background:#d72173;border-color:#d72173;">
                    <i data-feather="save" class="icon-16"></i> Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- SortableJS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.2/Sortable.min.js"></script>

<script type="text/javascript">

var _baseUrl = AppHelper.baseUrl + 'index.php/';
var _tablaIniciada = false;

function actualizarBadgeMenu() {
    $.getJSON(_baseUrl + 'prospectos/kanban_data', function (data) {
        var nuevos = 0;
        $.each(data, function (i, p) { if (p.estado === 'nuevo') nuevos++; });
        var $menuItem = $('a[href*="prospectos"]').filter(':visible').first();
        $menuItem.find('.prospecto-badge').remove();
        if (nuevos > 0) {
            $menuItem.append('<span class="prospecto-badge" style="display:inline-block;background:#d72173;color:#fff;font-size:10px;font-weight:700;border-radius:10px;padding:1px 6px;margin-left:6px;line-height:16px;">' + nuevos + '</span>');
        }
    });
}

$(document).ready(function () {

    var ESTADOS = {
        nuevo:             { color: '#d72173' },
        en_contacto:       { color: '#C6D617' },
        propuesta_enviada: { color: '#7c3aed' },
        convertido:        { color: '#27ae60' },
        perdido:           { color: '#e74c3c' },
    };

    // ── Toggle listado ───────────────────────────────────────────────────────
    $('#toggle-listado').on('click', function () {
        var $contenido = $('#listado-contenido');
        var $chevron   = $('#listado-chevron');
        var abierto    = $contenido.is(':visible');

        $contenido.slideToggle(250);
        $chevron.css('transform', abierto ? 'rotate(0deg)' : 'rotate(180deg)');

        // Iniciar tabla solo la primera vez que se abre
        if (!abierto && !_tablaIniciada) {
            _tablaIniciada = true;
            $('#prospectos-table').appTable({
                source: _baseUrl + 'prospectos/list_data',
                order: [[0, 'desc']],
                filterDropdown: [{
                    name: 'estado', class: 'w200',
                    options: [
                        {id: '',                   text: '- Estado -'},
                        {id: 'nuevo',              text: 'Nuevo'},
                        {id: 'en_contacto',        text: 'En contacto'},
                        {id: 'propuesta_enviada',  text: 'Propuesta enviada'},
                        {id: 'convertido',         text: 'Convertido'},
                        {id: 'perdido',            text: 'Perdido'},
                    ]
                }],
                columns: [
                    {title: 'Fecha',    class: 'w150'},
                    {title: 'Nombre',   class: 'all'},
                    {title: 'Email',    class: 'all'},
                    {title: 'Teléfono'},
                    {title: 'Web'},
                    {title: 'Mensaje'},
                    {title: 'Estado',   class: 'text-center w130'},
                    {title: '<i data-feather="menu" class="icon-16"></i>', class: 'text-center option w80'},
                ],
                printColumns: [0,1,2,3,4,5,6],
                xlsColumns:   [0,1,2,3,4,5,6],
            });
        }
    });

    // Cambio de estado inline desde tabla
    $('body').on('click', '[data-act=update-prospecto-estado]', function () {
        var $btn = $(this);
        $(this).appModifier({
            value:     $btn.attr('data-value'),
            actionUrl: _baseUrl + 'prospectos/save_estado',
            postData:  {id: $btn.attr('data-id')},
            select2Option: {
                data: [
                    {id: 'nuevo',             text: 'Nuevo'},
                    {id: 'en_contacto',       text: 'En contacto'},
                    {id: 'propuesta_enviada', text: 'Propuesta enviada'},
                    {id: 'convertido',        text: 'Convertido'},
                    {id: 'perdido',           text: 'Perdido'},
                ]
            },
            onSuccess: function (response) {
                if (response.success) {
                    if (_tablaIniciada) $('#prospectos-table').appTable({reload: true});
                    cargarKanban();
                }
            }
        });
        return false;
    });

    // ── Modal nuevo prospecto ────────────────────────────────────────────────
    $('#btn-nuevo-prospecto').on('click', function (e) {
        e.preventDefault();
        $('#np-nombre, #np-email, #np-telefono, #np-web, #np-mensaje, #np-pagina-origen, #np-notas').val('');
        $('#np-estado').val('nuevo');
        $('#modal-nuevo-prospecto').modal('show');
    });

    $('#btn-guardar-prospecto').on('click', function () {
        var email  = $.trim($('#np-email').val());
        var nombre = $.trim($('#np-nombre').val());
        if (!email && !nombre) {
            appAlert.error('Introduce al menos un nombre o email.', {duration: 3000});
            return;
        }
        var $btn = $(this);
        $btn.prop('disabled', true).text('Guardando...');
        $.post(_baseUrl + 'prospectos/save_manual', {
            nombre:        nombre,
            email:         email,
            telefono:      $.trim($('#np-telefono').val()),
            web:           $.trim($('#np-web').val()),
            mensaje:       $.trim($('#np-mensaje').val()),
            pagina_origen: $.trim($('#np-pagina-origen').val()),
            notas:         $.trim($('#np-notas').val()),
            estado:        $('#np-estado').val(),
        }, function (response) {
            $btn.prop('disabled', false).html('<i data-feather="save" class="icon-16"></i> Guardar');
            if (typeof feather !== 'undefined') feather.replace();
            if (response.success) {
                $('#modal-nuevo-prospecto').modal('hide');
                appAlert.success('Prospecto añadido correctamente.', {duration: 3000});
                cargarKanban();
                if (_tablaIniciada) $('#prospectos-table').appTable({reload: true});
            } else {
                appAlert.error(response.message || 'Error al guardar.', {duration: 4000});
            }
        }, 'json');
    });

    // ── Kanban ───────────────────────────────────────────────────────────────
    function renderCard(p) {
        var color  = ESTADOS[p.estado] ? ESTADOS[p.estado].color : '#999';
        var nombre = p.nombre ? p.nombre : '(Sin nombre)';
        var fecha  = p.fecha_recepcion ? p.fecha_recepcion.substring(0, 10) : '';
        var msgHtml = '';
        if (p.mensaje) {
            var msg = p.mensaje.length > 60 ? p.mensaje.substring(0, 60) + '…' : p.mensaje;
            msgHtml = '<div style="font-size:12px;color:#aaa;font-style:italic;border-top:1px solid #f0f0f0;padding-top:6px;margin-top:4px;">' + $('<div>').text(msg).html() + '</div>';
        }
        var $card = $('<div>').addClass('kanban-card').attr('data-id', p.id).css({
            background: '#fff', borderRadius: '10px', padding: '14px 14px 10px',
            marginBottom: '10px', cursor: 'grab',
            boxShadow: '0 1px 4px rgba(0,0,0,0.08)', borderLeft: '3px solid ' + color,
        }).html(
            '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px;">'
            + '<span style="font-weight:700;font-size:13px;color:#222;line-height:1.3;">' + $('<div>').text(nombre).html() + '</span>'
            + '<a href="#" class="kanban-ver" data-id="' + p.id + '" style="color:#d72173;font-size:11px;white-space:nowrap;margin-left:8px;">Ver</a>'
            + '</div>'
            + '<div style="font-size:12px;color:#666;margin-bottom:3px;">✉ ' + $('<div>').text(p.email || '').html() + '</div>'
            + '<div style="font-size:12px;color:#888;margin-bottom:4px;">📞 ' + $('<div>').text(p.telefono || '').html() + '</div>'
            + msgHtml
            + '<div style="font-size:11px;color:#bbb;margin-top:6px;text-align:right;">' + fecha + '</div>'
        );
        return $card;
    }

    function recalcularContadores() {
        $('.kanban-col').each(function () {
            $(this).find('.kanban-count').text($(this).find('.kanban-card').length);
        });
    }

    function cargarKanban() {
        $.getJSON(_baseUrl + 'prospectos/kanban_data', function (data) {
            $('.kanban-cards').empty();
            $('.kanban-count').text('0');
            if (!data || !data.length) return;
            $.each(data, function (i, p) {
                var $col = $('.kanban-cards[data-estado="' + p.estado + '"]');
                if ($col.length) $col.append(renderCard(p));
            });
            recalcularContadores();
            actualizarBadgeMenu();
            if (typeof feather !== 'undefined') feather.replace();
            activarSortable();
        });
    }

    function activarSortable() {
        document.querySelectorAll('.kanban-cards').forEach(function (el) {
            if (el._sortable) el._sortable.destroy();
            el._sortable = new Sortable(el, {
                group: 'prospectos', animation: 150,
                ghostClass: 'kanban-ghost', chosenClass: 'kanban-chosen',
                onEnd: function (evt) {
                    var id          = evt.item.dataset.id;
                    var nuevoEstado = evt.to.dataset.estado;
                    evt.item.style.borderLeftColor = ESTADOS[nuevoEstado] ? ESTADOS[nuevoEstado].color : '#999';
                    $.post(_baseUrl + 'prospectos/save_estado', {id: id, value: nuevoEstado}, function (r) {
                        if (r.success) {
                            recalcularContadores();
                            actualizarBadgeMenu();
                            if (_tablaIniciada) $('#prospectos-table').appTable({reload: true});
                        } else {
                            cargarKanban();
                        }
                    }, 'json');
                }
            });
        });
    }

    // Ver detalle desde kanban
    $('body').on('click', '.kanban-ver', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var id = $(this).data('id');
        abrirModalProspecto(id);
        return false;
    });

    $('<style>').text(
        '.kanban-ghost  { opacity:.4; background:#f0e6f0; border-radius:10px; }' +
        '.kanban-chosen { box-shadow: 0 8px 24px rgba(215,33,115,.2) !important; cursor: grabbing !important; }' +
        '.kanban-card:hover { box-shadow: 0 4px 12px rgba(215,33,115,.15) !important; }' +
        '.kanban-card { user-select: none; }' +
        '#toggle-listado:hover { background: #fafafa; }' +
        '#listado-chevron { vertical-align: middle; }'
    ).appendTo('head');

    cargarKanban();
    actualizarBadgeMenu();
});

// Abrir modal de ver/editar prospecto
function abrirModalProspecto(id) {
    $.get(_baseUrl + 'prospectos/ver/' + id, function (html) {
        $('#modal-ver-prospecto').remove();
        $('body').append(html);
        $('#modal-ver-prospecto').modal('show');
        if (typeof feather !== 'undefined') feather.replace();
    });
}
</script>