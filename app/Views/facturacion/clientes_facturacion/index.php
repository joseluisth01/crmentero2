<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0"><i data-feather="users" class="icon-16"></i> Clientes</h5>
        <small class="text-muted">Sincronizados con el CRM — para añadir un cliente, créalo en <a href="<?php echo get_uri('clients'); ?>">Clientes del CRM</a></small>
    </div>

    <div class="card shadow-sm">
        <div class="card-body p-0">
            <table id="tabla-clientes-fac" class="table table-hover table-sm w-100">
                <thead class="table-light">
                    <tr>
                        <th>Nombre</th>
                        <th>CIF/NIF</th>
                        <th>Forma de Pago</th>
                        <th width="100">Acciones</th>
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
    tablaClientes = $('#tabla-clientes-fac').DataTable({
        processing: true,
        ajax: {
            url: '<?php echo get_uri("facturacion/clientes_list_data"); ?>',
            type: 'POST',
        },
        columns: [
            {data:0}, {data:1}, {data:2}, {data:3, orderable:false}
        ],
        language: { search: 'Buscar:', lengthMenu: 'Mostrar _MENU_ registros', info: 'Mostrando _START_ a _END_ de _TOTAL_ registros', infoEmpty: 'Sin resultados', zeroRecords: 'No se encontraron resultados', paginate: { first: '«', last: '»', next: '›', previous: '‹' } },
        order: [[0, 'asc']],
        drawCallback: function(){ feather.replace(); }
    });
});
</script>
