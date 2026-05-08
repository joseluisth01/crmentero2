<?php

namespace App\Controllers;

class Facturacion extends Security_Controller {

    function __construct() {
        parent::__construct();

        // Solo staff (team members), no clientes
        $this->access_only_team_members();

        $this->Facturacion_model = model('App\Models\Facturacion_model');
    }

    // ============================================================
    // INDEX — DASHBOARD PRINCIPAL
    // ============================================================

    function index() {

        $anno = intval($this->request->getGet('anno') ?: date('Y'));
        $mes  = intval($this->request->getGet('mes')  ?: date('n'));

        $view_data['anno']              = $anno;
        $view_data['mes']               = $mes;
        $view_data['resumen_mes']       = $this->Facturacion_model->get_resumen_mes($anno, $mes);
        $view_data['resumen_anno']      = $this->Facturacion_model->get_resumen_anno($anno);
        $view_data['resumen_clientes']  = $this->Facturacion_model->get_resumen_por_cliente($anno, $mes);
        $view_data['servicios_activos'] = $this->Facturacion_model->get_servicios_recurrentes_activos_total();
        $view_data['clientes_activos']  = $this->Facturacion_model->get_clientes_activos_count();
        $view_data['renovaciones_proximas'] = $this->Facturacion_model->get_renovaciones(['proximas_dias' => 30]);
        $view_data['avisos']            = $this->Facturacion_model->get_avisos(['leido' => 0]);
        $view_data['meses_nombres']     = $this->_get_meses();
        $view_data['tab']               = clean_data($this->request->getGet('tab') ?: 'dashboard');

        return $this->template->rander("facturacion/index", $view_data);
    }

    // ============================================================
    // CLIENTES DE FACTURACIÓN
    // ============================================================

    function clientes() {
        $view_data['clientes'] = $this->Facturacion_model->get_clientes()->getResult();
        return $this->template->view("facturacion/clientes/index", $view_data);
    }

    function clientes_list_data() {
        $options = [
            'estado' => $this->request->getPost('estado'),
            'search' => $this->request->getPost('search'),
        ];
        $clientes = $this->Facturacion_model->get_clientes($options)->getResult();
        $list = [];
        foreach ($clientes as $c) {
            $actions = '';
            if ($this->login_user->is_admin) {
                $actions .= '<a href="' . get_uri("facturacion/cliente_view/$c->id") . '" class="btn btn-outline-info btn-sm"><i data-feather="eye" class="icon-14"></i></a> ';
                $actions .= modal_anchor(get_uri("facturacion/cliente_modal_form"), '<i data-feather="edit-2" class="icon-14"></i>', ['class' => 'btn btn-outline-secondary btn-sm', 'title' => 'Editar cliente', 'data-post-id' => $c->id]) . ' ';
                $actions .= js_anchor('<i data-feather="trash-2" class="icon-14"></i>', ['class' => 'btn btn-outline-danger btn-sm', 'title' => 'Eliminar', 'data-id' => $c->id, 'data-action-url' => get_uri("facturacion/delete_cliente"), 'data-action' => 'delete-confirmation']);
            }
            $badge_estado = $this->_badge_estado_cliente($c->estado);
            $badge_pago   = $this->_badge_forma_pago($c->forma_pago_default);
            $list[] = [
                $c->nombre,
                $c->cif_nif ?: '-',
                $c->email_facturacion ?: '-',
                $c->telefono ?: '-',
                $badge_pago,
                $badge_estado,
                $actions
            ];
        }
        echo json_encode(['data' => $list]);
    }

    function cliente_modal_form() {
        $id = $this->request->getPost('id');
        $view_data['model_info'] = $id ? $this->Facturacion_model->get_cliente($id) : null;
        $view_data['crm_clients_dropdown'] = $this->_get_crm_clients_dropdown();
        return $this->template->view("facturacion/clientes/modal_form", $view_data);
    }

    function save_cliente() {
        $id = $this->request->getPost('id');
        $data = [
            'crm_client_id'       => $this->request->getPost('crm_client_id') ?: null,
            'nombre'              => $this->request->getPost('nombre'),
            'nombre_fiscal'       => $this->request->getPost('nombre_fiscal'),
            'cif_nif'             => $this->request->getPost('cif_nif'),
            'direccion'           => $this->request->getPost('direccion'),
            'ciudad'              => $this->request->getPost('ciudad'),
            'codigo_postal'       => $this->request->getPost('codigo_postal'),
            'pais'                => $this->request->getPost('pais') ?: 'España',
            'email_facturacion'   => $this->request->getPost('email_facturacion'),
            'telefono'            => $this->request->getPost('telefono'),
            'persona_contacto'    => $this->request->getPost('persona_contacto'),
            'forma_pago_default'  => $this->request->getPost('forma_pago_default'),
            'estado'              => $this->request->getPost('estado'),
            'observaciones'       => $this->request->getPost('observaciones'),
            'etiquetas'           => $this->request->getPost('etiquetas'),
            'created_by'          => $this->login_user->id,
        ];
        $new_id = $this->Facturacion_model->save_cliente($data, $id);
        echo json_encode(['success' => true, 'id' => $new_id]);
    }

    function delete_cliente() {
        $id = $this->request->getPost('id');
        $this->Facturacion_model->delete_cliente($id);
        echo json_encode(['success' => true]);
    }

    function cliente_view($id) {
        $cliente = $this->Facturacion_model->get_cliente($id);
        if (!$cliente) app_redirect("forbidden");
        $view_data['cliente']   = $cliente;
        $view_data['servicios'] = $this->Facturacion_model->get_cliente_servicios(['cliente_id' => $id])->getResult();
        $view_data['facturas']  = $this->Facturacion_model->get_facturas(['cliente_id' => $id])->getResult();
        $view_data['renovaciones'] = $this->Facturacion_model->get_renovaciones(['cliente_id' => $id]);
        $view_data['kit_digital']  = $this->Facturacion_model->get_kit_digital(['cliente_id' => $id]);
        $view_data['comisiones']   = $this->Facturacion_model->get_comisiones(['cliente_id' => $id])->getResult();
        $view_data['meses_nombres'] = $this->_get_meses();
        return $this->template->view("facturacion/clientes/view", $view_data);
    }

    // ============================================================
    // SERVICIOS CATÁLOGO
    // ============================================================

    function servicios_catalogo() {
        $view_data['servicios'] = $this->Facturacion_model->get_servicios_catalogo()->getResult();
        return $this->template->view("facturacion/servicios/catalogo", $view_data);
    }

    function servicio_catalogo_modal_form() {
        $id = $this->request->getPost('id');
        $view_data['model_info'] = null;
        if ($id) {
            $view_data['model_info'] = $this->Facturacion_model->get_servicios_catalogo(['id' => $id])->getRow(); // podríamos añadir filtro id al método
        }
        return $this->template->view("facturacion/servicios/modal_form_catalogo", $view_data);
    }

    function save_servicio_catalogo() {
        $id = $this->request->getPost('id');
        $data = [
            'nombre'         => $this->request->getPost('nombre'),
            'descripcion'    => $this->request->getPost('descripcion'),
            'categoria'      => $this->request->getPost('categoria'),
            'importe_base'   => floatval($this->request->getPost('importe_base')),
            'iva_porcentaje' => floatval($this->request->getPost('iva_porcentaje') ?: 21),
            'periodicidad'   => $this->request->getPost('periodicidad'),
            'es_recurrente'  => intval($this->request->getPost('es_recurrente')),
            'genera_comision'=> intval($this->request->getPost('genera_comision')),
            'estado'         => $this->request->getPost('estado') ?: 'activo',
        ];
        $new_id = $this->Facturacion_model->save_servicio_catalogo($data, $id);
        echo json_encode(['success' => true, 'id' => $new_id]);
    }

    // ============================================================
    // SERVICIOS DE CLIENTES (CONTRATADOS)
    // ============================================================

    function cliente_servicios_list_data() {
        $options = [
            'cliente_id'  => $this->request->getPost('cliente_id'),
            'estado'      => $this->request->getPost('estado'),
            'es_recurrente' => $this->request->getPost('es_recurrente'),
        ];
        $servicios = $this->Facturacion_model->get_cliente_servicios($options)->getResult();
        $list = [];
        foreach ($servicios as $s) {
            $actions = modal_anchor(get_uri("facturacion/cliente_servicio_modal_form"), '<i data-feather="edit-2" class="icon-14"></i>', ['class' => 'btn btn-outline-secondary btn-sm', 'title' => 'Editar', 'data-post-id' => $s->id]) . ' ';
            $actions .= js_anchor('<i data-feather="trash-2" class="icon-14"></i>', ['class' => 'btn btn-outline-danger btn-sm', 'data-id' => $s->id, 'data-action-url' => get_uri("facturacion/delete_cliente_servicio"), 'data-action' => 'delete-confirmation']);
            $list[] = [
                $s->cliente_nombre ?? '-',
                $s->concepto,
                number_format($s->importe, 2, ',', '.') . ' €',
                ucfirst($s->periodicidad),
                $s->es_recurrente ? '<span class="badge bg-success">Recurrente</span>' : '<span class="badge bg-info">Puntual</span>',
                $s->fecha_inicio ?: '-',
                $s->fecha_fin ?: '-',
                $this->_badge_estado_servicio($s->estado),
                $actions
            ];
        }
        echo json_encode(['data' => $list]);
    }

    function cliente_servicio_modal_form() {
        $id = $this->request->getPost('id');
        $cliente_id = $this->request->getPost('cliente_id');
        $view_data['model_info']    = $id ? $this->Facturacion_model->get_cliente_servicios(['id' => $id])->getRow() : null;
        $view_data['cliente_id']    = $cliente_id;
        $view_data['clientes']      = $this->Facturacion_model->get_clientes()->getResult();
        $view_data['catalogo']      = $this->Facturacion_model->get_servicios_catalogo(['estado' => 'activo'])->getResult();
        $view_data['team_members']  = $this->_get_team_members_dropdown();
        return $this->template->view("facturacion/servicios/modal_form_cliente_servicio", $view_data);
    }

    function save_cliente_servicio() {
        $id = $this->request->getPost('id');
        $data = [
            'cliente_id'           => $this->request->getPost('cliente_id'),
            'servicio_catalogo_id' => $this->request->getPost('servicio_catalogo_id') ?: null,
            'concepto'             => $this->request->getPost('concepto'),
            'importe'              => floatval($this->request->getPost('importe')),
            'iva_porcentaje'       => floatval($this->request->getPost('iva_porcentaje') ?: 21),
            'periodicidad'         => $this->request->getPost('periodicidad'),
            'es_recurrente'        => intval($this->request->getPost('es_recurrente')),
            'fecha_inicio'         => $this->request->getPost('fecha_inicio') ?: null,
            'fecha_fin'            => $this->request->getPost('fecha_fin') ?: null,
            'forma_pago'           => $this->request->getPost('forma_pago') ?: null,
            'estado'               => $this->request->getPost('estado'),
            'fecha_baja'           => $this->request->getPost('fecha_baja') ?: null,
            'motivo_baja'          => $this->request->getPost('motivo_baja') ?: null,
            'genera_comision'      => intval($this->request->getPost('genera_comision')),
            'comercial_id'         => $this->request->getPost('comercial_id') ?: null,
            'observaciones'        => $this->request->getPost('observaciones'),
            'created_by'           => $this->login_user->id,
        ];
        $new_id = $this->Facturacion_model->save_cliente_servicio($data, $id);
        echo json_encode(['success' => true, 'id' => $new_id]);
    }

    function delete_cliente_servicio() {
        $id = $this->request->getPost('id');
        // Marcar como cancelado en lugar de borrar
        $this->Facturacion_model->save_cliente_servicio(['estado' => 'cancelado'], $id);
        echo json_encode(['success' => true]);
    }

    // ============================================================
    // FACTURAS
    // ============================================================

    function facturas($tab = '') {
        $view_data['tab']           = clean_data($tab);
        $view_data['annos']         = range(date('Y'), date('Y') - 5);
        $view_data['meses_nombres'] = $this->_get_meses();
        $view_data['clientes']      = $this->Facturacion_model->get_clientes(['estado' => 'activo'])->getResult();
        return $this->template->view("facturacion/facturas/index", $view_data);
    }

    function facturas_list_data() {
        $options = [
            'anno'          => $this->request->getPost('anno'),
            'mes'           => $this->request->getPost('mes'),
            'cliente_id'    => $this->request->getPost('cliente_id'),
            'estado_factura'=> $this->request->getPost('estado_factura'),
            'estado_cobro'  => $this->request->getPost('estado_cobro'),
            'forma_pago'    => $this->request->getPost('forma_pago'),
            'es_kit_digital'=> $this->request->getPost('es_kit_digital'),
            'vencidas'      => $this->request->getPost('vencidas'),
        ];
        $facturas = $this->Facturacion_model->get_facturas($options)->getResult();
        $list = [];
        foreach ($facturas as $f) {
            $actions = '';
            $actions .= '<a href="' . get_uri("facturacion/factura_view/$f->id") . '" class="btn btn-outline-info btn-sm" title="Ver factura"><i data-feather="eye" class="icon-14"></i></a> ';
            if ($this->login_user->is_admin) {
                $actions .= modal_anchor(get_uri("facturacion/factura_modal_form"), '<i data-feather="edit-2" class="icon-14"></i>', ['class' => 'btn btn-outline-secondary btn-sm', 'title' => 'Editar', 'data-post-id' => $f->id]) . ' ';
                $actions .= js_anchor('<i data-feather="trash-2" class="icon-14"></i>', ['class' => 'btn btn-outline-danger btn-sm', 'data-id' => $f->id, 'data-action-url' => get_uri("facturacion/delete_factura"), 'data-action' => 'delete-confirmation']);
            }
            $vencido_class = ($f->dias_vencimiento > 0 && !in_array($f->estado_cobro, ['cobrado', 'no_procede'])) ? ' style="color:red;font-weight:bold"' : '';
            $list[] = [
                '<a href="' . get_uri("facturacion/factura_view/$f->id") . '">' . $f->numero_factura . '</a>',
                $f->cliente_nombre,
                $f->mes . '/' . $f->anno,
                $f->fecha_emision,
                '<span' . $vencido_class . '>' . ($f->fecha_vencimiento ?: '-') . '</span>',
                number_format($f->total, 2, ',', '.') . ' €',
                number_format($f->importe_cobrado, 2, ',', '.') . ' €',
                number_format($f->importe_pendiente, 2, ',', '.') . ' €',
                $this->_badge_forma_pago($f->forma_pago),
                $this->_badge_estado_factura($f->estado_factura),
                $this->_badge_estado_cobro($f->estado_cobro),
                $actions
            ];
        }
        echo json_encode(['data' => $list]);
    }

    function factura_modal_form() {
        $id = $this->request->getPost('id');
        $view_data['model_info'] = $id ? $this->Facturacion_model->get_factura($id) : null;
        $view_data['clientes']   = $this->Facturacion_model->get_clientes(['estado' => 'activo'])->getResult();
        $view_data['meses_nombres'] = $this->_get_meses();
        return $this->template->view("facturacion/facturas/modal_form", $view_data);
    }

    function save_factura() {
        $id = $this->request->getPost('id');
        $anno = intval($this->request->getPost('anno') ?: date('Y'));
        $mes  = intval($this->request->getPost('mes')  ?: date('n'));
        $data = [
            'cliente_id'             => $this->request->getPost('cliente_id'),
            'anno'                   => $anno,
            'mes'                    => $mes,
            'fecha_emision'          => $this->request->getPost('fecha_emision'),
            'fecha_vencimiento'      => $this->request->getPost('fecha_vencimiento') ?: null,
            'forma_pago'             => $this->request->getPost('forma_pago'),
            'estado_factura'         => $this->request->getPost('estado_factura') ?: 'borrador',
            'estado_cobro'           => $this->request->getPost('estado_cobro') ?: 'pendiente',
            'es_kit_digital'         => intval($this->request->getPost('es_kit_digital')),
            'observaciones_internas' => $this->request->getPost('observaciones_internas'),
            'observaciones_cliente'  => $this->request->getPost('observaciones_cliente'),
            'created_by'             => $this->login_user->id,
        ];
        $new_id = $this->Facturacion_model->save_factura($data, $id);
        echo json_encode(['success' => true, 'id' => $new_id]);
    }

    function delete_factura() {
        if (!$this->login_user->is_admin) app_redirect("forbidden");
        $id = $this->request->getPost('id');
        $this->Facturacion_model->delete_factura($id);
        echo json_encode(['success' => true]);
    }

    function factura_view($id) {
        $factura = $this->Facturacion_model->get_factura($id);
        if (!$factura) app_redirect("facturacion/facturas");
        $view_data['factura'] = $factura;
        $view_data['lineas']  = $this->Facturacion_model->get_lineas_factura($id);
        $view_data['pagos']   = $this->Facturacion_model->get_pagos(['factura_id' => $id]);
        $view_data['catalogo_dropdown'] = $this->Facturacion_model->get_servicios_catalogo(['estado' => 'activo'])->getResult();
        $view_data['cliente_servicios'] = $this->Facturacion_model->get_cliente_servicios(['cliente_id' => $factura->cliente_id, 'estado' => 'activo'])->getResult();
        $view_data['meses_nombres'] = $this->_get_meses();
        return $this->template->view("facturacion/facturas/view", $view_data);
    }

    // ============================================================
    // LÍNEAS DE FACTURA
    // ============================================================

    function save_linea() {
        $id         = $this->request->getPost('id');
        $factura_id = $this->request->getPost('factura_id');
        $data = [
            'factura_id'          => $factura_id,
            'cliente_servicio_id' => $this->request->getPost('cliente_servicio_id') ?: null,
            'descripcion'         => $this->request->getPost('descripcion'),
            'tipo_linea'          => $this->request->getPost('tipo_linea') ?: 'recurrente',
            'cantidad'            => floatval($this->request->getPost('cantidad') ?: 1),
            'precio_unitario'     => floatval($this->request->getPost('precio_unitario')),
            'iva_porcentaje'      => floatval($this->request->getPost('iva_porcentaje') ?: 21),
            'orden'               => intval($this->request->getPost('orden') ?: 0),
            'observaciones'       => $this->request->getPost('observaciones'),
        ];
        $new_id = $this->Facturacion_model->save_linea($data, $id);
        $this->Facturacion_model->recalcular_totales_factura($factura_id);
        $factura = $this->Facturacion_model->get_factura($factura_id);
        echo json_encode(['success' => true, 'id' => $new_id, 'factura' => $factura]);
    }

    function delete_linea() {
        $id         = $this->request->getPost('id');
        $factura_id = $this->request->getPost('factura_id');
        $this->Facturacion_model->delete_linea($id);
        $this->Facturacion_model->recalcular_totales_factura($factura_id);
        $factura = $this->Facturacion_model->get_factura($factura_id);
        echo json_encode(['success' => true, 'factura' => $factura]);
    }

    // ============================================================
    // PAGOS / COBROS
    // ============================================================

    function cobros() {
        $view_data['clientes']      = $this->Facturacion_model->get_clientes()->getResult();
        $view_data['annos']         = range(date('Y'), date('Y') - 5);
        $view_data['meses_nombres'] = $this->_get_meses();
        return $this->template->view("facturacion/cobros/index", $view_data);
    }

    function save_pago() {
        $id = $this->request->getPost('id');
        $factura_id = $this->request->getPost('factura_id');
        $data = [
            'factura_id'    => $factura_id,
            'cliente_id'    => $this->request->getPost('cliente_id'),
            'importe'       => floatval($this->request->getPost('importe')),
            'fecha_pago'    => $this->request->getPost('fecha_pago'),
            'metodo_pago'   => $this->request->getPost('metodo_pago'),
            'estado'        => $this->request->getPost('estado') ?: 'confirmado',
            'referencia'    => $this->request->getPost('referencia'),
            'motivo_rechazo'=> $this->request->getPost('motivo_rechazo'),
            'observaciones' => $this->request->getPost('observaciones'),
            'created_by'    => $this->login_user->id,
        ];
        $new_id = $this->Facturacion_model->save_pago($data, $id);
        $factura = $this->Facturacion_model->get_factura($factura_id);
        echo json_encode(['success' => true, 'id' => $new_id, 'factura' => $factura]);
    }

    function marcar_cobrada() {
        $factura_id = $this->request->getPost('factura_id');
        $factura    = $this->Facturacion_model->get_factura($factura_id);
        if (!$factura) { echo json_encode(['success' => false]); return; }
        // Registrar pago completo
        $data = [
            'factura_id'  => $factura_id,
            'cliente_id'  => $factura->cliente_id,
            'importe'     => $factura->total - $factura->importe_cobrado,
            'fecha_pago'  => date('Y-m-d'),
            'metodo_pago' => $factura->forma_pago,
            'estado'      => 'confirmado',
            'created_by'  => $this->login_user->id,
        ];
        $this->Facturacion_model->save_pago($data);
        echo json_encode(['success' => true]);
    }

    function marcar_rechazada() {
        $factura_id     = $this->request->getPost('factura_id');
        $motivo         = $this->request->getPost('motivo');
        $this->Facturacion_model->save_factura(['estado_cobro' => 'rechazado', 'observaciones_internas' => $motivo], $factura_id);
        echo json_encode(['success' => true]);
    }

    // ============================================================
    // REMESAS
    // ============================================================

    function remesas() {
        $anno = intval($this->request->getGet('anno') ?: date('Y'));
        $view_data['remesas'] = $this->Facturacion_model->get_remesas(['anno' => $anno]);
        $view_data['anno']    = $anno;
        $view_data['annos']   = range(date('Y'), date('Y') - 5);
        // Facturas de este mes en forma de pago remesa sin remesa asignada
        $view_data['facturas_sin_remesa'] = $this->Facturacion_model->get_facturas([
            'forma_pago' => 'remesa',
            'anno'       => $anno,
            'mes'        => date('n'),
        ])->getResult();
        return $this->template->view("facturacion/remesas/index", $view_data);
    }

    function remesa_modal_form() {
        $id = $this->request->getPost('id');
        $view_data['model_info'] = null;
        if ($id) {
            $remesas = $this->Facturacion_model->get_remesas();
            foreach ($remesas as $r) { if ($r->id == $id) { $view_data['model_info'] = $r; break; } }
        }
        $view_data['meses_nombres'] = $this->_get_meses();
        return $this->template->view("facturacion/remesas/modal_form", $view_data);
    }

    function save_remesa() {
        $id = $this->request->getPost('id');
        $data = [
            'nombre'          => $this->request->getPost('nombre'),
            'anno'            => intval($this->request->getPost('anno')),
            'mes'             => intval($this->request->getPost('mes')),
            'fecha_generacion'=> $this->request->getPost('fecha_generacion') ?: null,
            'fecha_envio'     => $this->request->getPost('fecha_envio') ?: null,
            'fecha_cobro'     => $this->request->getPost('fecha_cobro') ?: null,
            'estado'          => $this->request->getPost('estado') ?: 'pendiente',
            'observaciones'   => $this->request->getPost('observaciones'),
            'created_by'      => $this->login_user->id,
        ];
        $new_id = $this->Facturacion_model->save_remesa($data, $id);
        echo json_encode(['success' => true, 'id' => $new_id]);
    }

    function asignar_facturas_remesa() {
        $remesa_id   = $this->request->getPost('remesa_id');
        $factura_ids = $this->request->getPost('factura_ids');
        if (!is_array($factura_ids)) $factura_ids = [];
        $tf = \Config\Database::connect()->prefixTable('fac_facturas');
        foreach ($factura_ids as $fid) {
            \Config\Database::connect()->query("UPDATE $tf SET remesa_id=$remesa_id WHERE id=$fid");
        }
        // Recalcular total remesa
        $facturas = $this->Facturacion_model->get_facturas_remesa($remesa_id);
        $total = array_sum(array_column($facturas, 'total'));
        $this->Facturacion_model->save_remesa(['total_importe' => $total], $remesa_id);
        echo json_encode(['success' => true]);
    }

    // ============================================================
    // RENOVACIONES
    // ============================================================

    function renovaciones() {
        $view_data['renovaciones'] = $this->Facturacion_model->get_renovaciones();
        $view_data['clientes']     = $this->Facturacion_model->get_clientes()->getResult();
        return $this->template->view("facturacion/renovaciones/index", $view_data);
    }

    function renovacion_modal_form() {
        $id = $this->request->getPost('id');
        $cliente_id = $this->request->getPost('cliente_id');
        $view_data['model_info'] = $id ? (object)[] : null;
        if ($id) {
            $renovaciones = $this->Facturacion_model->get_renovaciones(['cliente_id' => null]);
            // filtro por id manual
        }
        $view_data['clientes']   = $this->Facturacion_model->get_clientes()->getResult();
        $view_data['cliente_id'] = $cliente_id;
        return $this->template->view("facturacion/renovaciones/modal_form", $view_data);
    }

    function save_renovacion() {
        $id = $this->request->getPost('id');
        $data = [
            'cliente_id'         => $this->request->getPost('cliente_id'),
            'tipo'               => $this->request->getPost('tipo'),
            'descripcion'        => $this->request->getPost('descripcion'),
            'fecha_renovacion'   => $this->request->getPost('fecha_renovacion'),
            'fecha_vencimiento'  => $this->request->getPost('fecha_vencimiento') ?: null,
            'proveedor'          => $this->request->getPost('proveedor'),
            'coste'              => floatval($this->request->getPost('coste') ?: 0),
            'precio_venta'       => floatval($this->request->getPost('precio_venta') ?: 0),
            'estado'             => $this->request->getPost('estado') ?: 'pendiente',
            'dias_aviso_previo'  => intval($this->request->getPost('dias_aviso_previo') ?: 30),
            'observaciones'      => $this->request->getPost('observaciones'),
            'created_by'         => $this->login_user->id,
        ];
        $new_id = $this->Facturacion_model->save_renovacion($data, $id);
        echo json_encode(['success' => true, 'id' => $new_id]);
    }

    // ============================================================
    // KIT DIGITAL
    // ============================================================

    function kit_digital() {
        $view_data['proyectos'] = $this->Facturacion_model->get_kit_digital();
        $view_data['clientes']  = $this->Facturacion_model->get_clientes()->getResult();
        $view_data['team_members'] = $this->_get_team_members_dropdown();
        return $this->template->view("facturacion/kit_digital/index", $view_data);
    }

    function kit_digital_modal_form() {
        $id = $this->request->getPost('id');
        $view_data['model_info']   = null;
        $view_data['clientes']     = $this->Facturacion_model->get_clientes()->getResult();
        $view_data['team_members'] = $this->_get_team_members_dropdown();
        return $this->template->view("facturacion/kit_digital/modal_form", $view_data);
    }

    function save_kit_digital() {
        $id = $this->request->getPost('id');
        $data = [
            'cliente_id'        => $this->request->getPost('cliente_id'),
            'solucion'          => $this->request->getPost('solucion'),
            'importe_bono'      => floatval($this->request->getPost('importe_bono') ?: 0),
            'importe_facturado' => floatval($this->request->getPost('importe_facturado') ?: 0),
            'importe_recibido'  => floatval($this->request->getPost('importe_recibido') ?: 0),
            'fecha_inicio'      => $this->request->getPost('fecha_inicio') ?: null,
            'fecha_fin'         => $this->request->getPost('fecha_fin') ?: null,
            'estado_proyecto'   => $this->request->getPost('estado_proyecto') ?: 'pendiente_inicio',
            'estado_cobro'      => $this->request->getPost('estado_cobro') ?: 'pendiente',
            'genera_comision'   => intval($this->request->getPost('genera_comision')),
            'comercial_id'      => $this->request->getPost('comercial_id') ?: null,
            'observaciones'     => $this->request->getPost('observaciones'),
            'created_by'        => $this->login_user->id,
        ];
        $new_id = $this->Facturacion_model->save_kit_digital($data, $id);
        echo json_encode(['success' => true, 'id' => $new_id]);
    }

    // ============================================================
    // COMISIONES
    // ============================================================

    function comisiones() {
        $view_data['comisiones']   = $this->Facturacion_model->get_comisiones()->getResult();
        $view_data['team_members'] = $this->_get_team_members_dropdown();
        $view_data['clientes']     = $this->Facturacion_model->get_clientes()->getResult();
        return $this->template->view("facturacion/comisiones/index", $view_data);
    }

    function comision_modal_form() {
        $id = $this->request->getPost('id');
        $view_data['model_info']   = null;
        $view_data['clientes']     = $this->Facturacion_model->get_clientes()->getResult();
        $view_data['team_members'] = $this->_get_team_members_dropdown();
        return $this->template->view("facturacion/comisiones/modal_form", $view_data);
    }

    function save_comision() {
        $id = $this->request->getPost('id');
        $data = [
            'persona_id'       => $this->request->getPost('persona_id'),
            'cliente_id'       => $this->request->getPost('cliente_id'),
            'factura_id'       => $this->request->getPost('factura_id') ?: null,
            'tipo_comision'    => $this->request->getPost('tipo_comision'),
            'porcentaje'       => floatval($this->request->getPost('porcentaje') ?: 8),
            'importe_base'     => floatval($this->request->getPost('importe_base') ?: 0),
            'importe_recibido' => floatval($this->request->getPost('importe_recibido') ?: 0),
            'estado_pago'      => $this->request->getPost('estado_pago') ?: 'pendiente',
            'fecha_pago'       => $this->request->getPost('fecha_pago') ?: null,
            'observaciones'    => $this->request->getPost('observaciones'),
            'created_by'       => $this->login_user->id,
        ];
        $new_id = $this->Facturacion_model->save_comision($data, $id);
        echo json_encode(['success' => true, 'id' => $new_id]);
    }

    // ============================================================
    // GENERACIÓN AUTOMÁTICA
    // ============================================================

    function generar_mes_modal() {
        $view_data['meses_nombres'] = $this->_get_meses();
        $view_data['anno_actual']   = date('Y');
        $view_data['mes_actual']    = date('n');
        return $this->template->view("facturacion/facturas/modal_generar_mes", $view_data);
    }

    function generar_mes() {
        if (!$this->login_user->is_admin) app_redirect("forbidden");
        $anno          = intval($this->request->getPost('anno'));
        $mes           = intval($this->request->getPost('mes'));
        $estado_inicial = $this->request->getPost('estado_inicial') ?: 'borrador';
        $resultado = $this->Facturacion_model->generar_facturas_mes($anno, $mes, $this->login_user->id, $estado_inicial);
        echo json_encode(['success' => true, 'resultado' => $resultado]);
    }

    // ============================================================
    // AVISOS
    // ============================================================

    function marcar_aviso_leido() {
        $id = $this->request->getPost('id');
        $this->Facturacion_model->marcar_aviso_leido($id);
        echo json_encode(['success' => true]);
    }

    // ============================================================
    // HELPERS PRIVADOS
    // ============================================================

    private function _badge_estado_cliente($estado) {
        $map = [
            'activo'     => 'success',
            'inactivo'   => 'secondary',
            'finalizado' => 'dark',
            'suspendido' => 'warning',
            'cancelado'  => 'danger',
        ];
        $color = $map[$estado] ?? 'secondary';
        return '<span class="badge bg-' . $color . '">' . ucfirst($estado) . '</span>';
    }

    private function _badge_estado_servicio($estado) {
        $map = [
            'activo'            => 'success',
            'pausado'           => 'warning',
            'finalizado'        => 'dark',
            'cancelado'         => 'danger',
            'pendiente_revision'=> 'info',
        ];
        $color = $map[$estado] ?? 'secondary';
        return '<span class="badge bg-' . $color . '">' . ucfirst(str_replace('_', ' ', $estado)) . '</span>';
    }

    private function _badge_estado_factura($estado) {
        $map = [
            'borrador'   => 'secondary',
            'emitida'    => 'primary',
            'enviada'    => 'info',
            'pagada'     => 'success',
            'cancelada'  => 'danger',
            'rectificada'=> 'warning',
        ];
        $color = $map[$estado] ?? 'secondary';
        return '<span class="badge bg-' . $color . '">' . ucfirst($estado) . '</span>';
    }

    private function _badge_estado_cobro($estado) {
        $map = [
            'pendiente'              => 'warning',
            'cobrado'                => 'success',
            'rechazado'              => 'danger',
            'parcialmente_cobrado'   => 'info',
            'vencido'                => 'danger',
            'no_procede'             => 'secondary',
        ];
        $color = $map[$estado] ?? 'secondary';
        return '<span class="badge bg-' . $color . '">' . ucfirst(str_replace('_', ' ', $estado)) . '</span>';
    }

    private function _badge_forma_pago($forma_pago) {
        $icons = [
            'remesa'       => '🏦',
            'transferencia'=> '↗️',
            'efectivo'     => '💵',
            'tarjeta'      => '💳',
            'otro'         => '❓',
        ];
        $icon = $icons[$forma_pago] ?? '❓';
        return $icon . ' ' . ucfirst($forma_pago ?: '-');
    }

    private function _get_meses() {
        return [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
        ];
    }

    private function _get_crm_clients_dropdown() {
        $db = \Config\Database::connect();
        $t  = $db->prefixTable('clients');
        return $db->query("SELECT id, company_name FROM $t WHERE deleted=0 AND is_lead=0 ORDER BY company_name ASC")->getResult();
    }

    private function _get_team_members_dropdown() {
        $db = \Config\Database::connect();
        $t  = $db->prefixTable('users');
        return $db->query("SELECT id, CONCAT(first_name,' ',last_name) as name FROM $t WHERE deleted=0 AND status='active' AND user_type='staff' ORDER BY first_name ASC")->getResult();
    }
}