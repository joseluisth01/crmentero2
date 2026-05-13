<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i data-feather="users" class="icon-16"></i> Clientes de Facturación</h5>
        <?php if ($login_user->is_admin): ?>
            <?php echo modal_anchor(get_uri("facturacion/cliente_modal_form"), '<i data-feather="plus-circle" class="icon-14"></i> Nuevo cliente', ['class' => 'btn btn-primary btn-sm', 'title' => 'Nuevo cliente de facturación']); ?>
        <?php endif; ?>
    </div>

    <!-- FILTROS -->
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label small mb-1">Estado</label>
                    <select id="filtro-estado-cliente" class="form-select form-select-sm">
                        <option value="">Todos</option>
                        <option value="activo">Activo</option>
                        <option value="inactivo">Inactivo</option>
                        <option value="finalizado">Finalizado</option>
                        <option value="suspendido">Suspendido</option>
                        <option value="cancelado">Cancelado</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button class="btn btn-secondary btn-sm" id="btn-filtrar-clientes">
                        <i data-feather="search" class="icon-14"></i> Filtrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table id="tabla-clientes-fac" class="table table-hover table-sm w-100">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>CIF/NIF</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Forma de Pago</th>
                        <th>Estado</th>
                        <th width="120">Acciones</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
</div>

<script>
var tablaClientes = null;
$(document).ready(function(){
    cargarTablaClientes();
    $('#btn-filtrar-clientes').on('click', function(){
        if (tablaClientes) { tablaClientes.destroy(); tablaClientes = null; }
        cargarTablaClientes();
    });
});
function cargarTablaClientes() {
    var estado = $('#filtro-estado-cliente').val();
    tablaClientes = $('#tabla-clientes-fac').DataTable({
        processing: true,
        serverSide: false,
        ajax: {
            url: '<?php echo get_uri("facturacion/clientes_list_data"); ?>',
            type: 'POST',
            data: { estado: estado },
        },
        columns: [
            {data:0},{data:1},{data:2},{data:3},{data:4},
            {data:5, orderable:false},{data:6, orderable:false}
        ],
        language: { search: 'Buscar:', lengthMenu: 'Mostrar _MENU_ registros', info: 'Mostrando _START_ a _END_ de _TOTAL_ registros', infoEmpty: 'Sin resultados', zeroRecords: 'No se encontraron resultados', paginate: { first: '«', last: '»', next: '›', previous: '‹' } },
        order: [[0, 'asc']],
        drawCallback: function(){ feather.replace(); }
    });
}
</script>
