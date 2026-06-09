<?php

namespace App\Controllers;

class Facturacion extends Security_Controller {

    function __construct() {
        parent::__construct();
        $this->access_only_team_members();
        $this->Facturacion_model = model('App\Models\Facturacion_model');
    }

    private function _get_meses() {
        return [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',
                7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
    }

    // ============================================================
    // INDEX — DASHBOARD PRINCIPAL
    // ============================================================

    function index() {
        $anno = intval($this->request->getGet('anno') ?: date('Y'));
        $mes  = intval($this->request->getGet('mes')  ?: date('n'));

        $view_data['anno']         = $anno;
        $view_data['mes']          = $mes;
        $view_data['totales_mes']  = $this->Facturacion_model->get_totales_mes($anno, $mes);
        $view_data['totales_anno'] = $this->Facturacion_model->get_totales_anuales($anno);
        $view_data['meses_nombres']= $this->_get_meses();
        $view_data['login_user']   = $this->login_user;

        return $this->template->rander("facturacion/index", $view_data);
    }

    // ============================================================
    // CLIENTES — sincronizados con crm_clients
    // ============================================================

    function clientes() {
        $view_data['login_user'] = $this->login_user;
        return view("facturacion/clientes_facturacion/index", $view_data);
    }

    function clientes_list_data() {
        $clientes = $this->Facturacion_model->get_clientes([
            'search' => $this->request->getPost('search'),
        ])->getResult();

        $list = [];
        foreach ($clientes as $c) {
            $badge_pago = $this->_badge_forma_pago($c->forma_pago);
            $actions  = '<a href="' . get_uri("facturacion/cliente_view/$c->id") . '" class="btn btn-outline-info btn-sm"><i data-feather="eye" class="icon-14"></i></a> ';
            $actions .= modal_anchor(get_uri("facturacion/cliente_forma_pago_modal"), '<i data-feather="credit-card" class="icon-14"></i>', [
                'class' => 'btn btn-outline-secondary btn-sm',
                'title' => 'Forma de pago',
                'data-post-id' => $c->id
            ]);
            $list[] = [
                '<a href="' . get_uri("facturacion/cliente_view/$c->id") . '">' . $c->nombre . '</a>',
                $c->vat_number ?: '-',
                $badge_pago,
                $actions
            ];
        }
        echo json_encode(['data' => $list]);
    }

    // Modal solo para editar forma de pago del cliente
    function cliente_forma_pago_modal() {
        $id = $this->request->getPost('id');
        $view_data['cliente'] = $this->Facturacion_model->get_cliente($id);
        return view("facturacion/clientes_facturacion/modal_forma_pago", $view_data);
    }

    function save_forma_pago_cliente() {
        $id         = $this->request->getPost('id');
        $forma_pago = $this->request->getPost('forma_pago');
        $this->Facturacion_model->save_forma_pago_cliente($id, $forma_pago);
        echo json_encode(['success' => true]);
    }

    function cliente_view($id) {
        $cliente = $this->Facturacion_model->get_cliente($id);
        if (!$cliente) app_redirect("forbidden");
        $view_data['cliente']        = $cliente;
        $view_data['facturas']       = $this->Facturacion_model->get_facturas(['cliente_id' => $id])->getResult();
        $view_data['meses_nombres']  = $this->_get_meses();
        $view_data['login_user']     = $this->login_user;
        return $this->template->rander("facturacion/clientes_facturacion/view", $view_data);
    }

    // ============================================================
    // FACTURAS
    // ============================================================

    function facturas() {
        $view_data['annos']         = range(date('Y'), date('Y') - 3);
        $view_data['meses_nombres'] = $this->_get_meses();
        $view_data['clientes']      = $this->Facturacion_model->get_clientes()->getResult();
        $view_data['login_user']    = $this->login_user;
        return view("facturacion/facturas/index", $view_data);
    }

    function facturas_list_data() {
        $estado_cobro_raw = $this->request->getPost('estado_cobro');
        $options = [
            'anno'                => $this->request->getPost('anno') ?: date('Y'),
            'mes'                 => $this->request->getPost('mes'),
            'cliente_id'          => $this->request->getPost('cliente_id'),
            'estado_cobro'        => ($estado_cobro_raw && $estado_cobro_raw !== 'no_cobradas') ? $estado_cobro_raw : null,
            'estado_cobro_not_in' => ($estado_cobro_raw === 'no_cobradas') ? ['cobrado','no_procede'] : null,
            'estado_factura'      => $this->request->getPost('estado_factura'),
            'forma_pago'          => $this->request->getPost('forma_pago'),
            'vencidas'            => $this->request->getPost('vencidas'),
        ];
        $facturas = $this->Facturacion_model->get_facturas($options)->getResult();
        $db  = \Config\Database::connect();
        $tl_q = $db->prefixTable('fac_factura_lineas');
        $list = [];
        foreach ($facturas as $f) {
            $pendiente = $f->total - $f->importe_cobrado;
            // Concepto: líneas de la factura
            $linea_desc = $db->query("SELECT GROUP_CONCAT(descripcion ORDER BY orden SEPARATOR ' + ') as concepto FROM $tl_q WHERE factura_id={$f->id}")->getRow();
            $concepto = $linea_desc->concepto ?? '—';

            $actions  = '<a href="' . get_uri("facturacion/factura_view/$f->id") . '" class="btn btn-outline-info btn-sm" data-fid="' . $f->id . '"><i data-feather="eye" class="icon-14"></i></a> ';
            $actions .= '<button class="btn btn-outline-danger btn-sm" '
                . 'onclick="borrarFactura(' . $f->id . ')" '
                . 'title="Borrar factura">'
                . '<i data-feather="trash-2" class="icon-14"></i></button>';
            $list[] = [
                '<a href="' . get_uri("facturacion/factura_view/$f->id") . '" data-fid="' . $f->id . '">' . $f->numero_factura . '</a>',
                $f->cliente_nombre ?? '-',
                '<small class="text-muted">' . htmlspecialchars($concepto) . '</small>',
                $f->mes . '/' . $f->anno,
                $f->fecha_vencimiento ?: '-',
                $this->_badge_estado_factura($f->estado_factura),
                number_format($f->total, 2, ',', '.') . ' €',
                number_format($f->importe_cobrado, 2, ',', '.') . ' €',
                number_format($pendiente, 2, ',', '.') . ' €',
                $this->_badge_forma_pago($f->forma_pago),
                $f->contract_id ? '<span class="badge bg-secondary">Contrato #' . $f->contract_id . '</span>' : '-',
                $this->_badge_estado_cobro($f->estado_cobro),
                $actions
            ];
        }
        echo json_encode(['data' => $list]);
    }

    function factura_modal_form() {
        $id = $this->request->getPost('id');
        $view_data['model_info'] = $id ? $this->Facturacion_model->get_factura($id) : null;
        $view_data['clientes']   = $this->Facturacion_model->get_clientes()->getResult();
        $view_data['meses_nombres'] = $this->_get_meses();
        return view("facturacion/facturas/modal_form", $view_data);
    }

    function save_factura() {
        $id         = intval($this->request->getPost('id'));
        $cliente_id = intval($this->request->getPost('cliente_id'));
        $anno       = intval($this->request->getPost('anno') ?: date('Y'));
        $mes        = intval($this->request->getPost('mes')  ?: date('n'));
        $cliente    = $this->Facturacion_model->get_cliente($cliente_id);

        // Generar número de factura automáticamente si es nueva
        $numero_factura = $this->request->getPost('numero_factura');
        if (!$id && !$numero_factura) {
            $db = \Config\Database::connect();
            $tf = $db->prefixTable('fac_facturas');
            $count = $db->query("SELECT COUNT(*)+1 as num FROM $tf WHERE anno=$anno")->getRow();
            $numero_factura = $anno . '-' . str_pad($count->num, 4, '0', STR_PAD_LEFT);
        }

        $rec_mes   = intval($this->request->getPost('recurrente_mes_limite')) ?: null;
        $rec_anno  = intval($this->request->getPost('recurrente_anno_limite')) ?: null;
        $data = [
            'numero_factura'         => $numero_factura,
            'cliente_id'             => $cliente_id,
            'anno'                   => $anno,
            'mes'                    => $mes,
            'fecha_emision'          => $this->request->getPost('fecha_emision') ?: date('Y-m-d'),
            'fecha_vencimiento'      => $this->request->getPost('fecha_vencimiento') ?: date('Y-m-d', strtotime('+30 days')),
            'forma_pago'             => $this->request->getPost('forma_pago') ?: ($cliente->forma_pago ?? 'transferencia'),
            'estado_factura'         => $this->request->getPost('estado_factura') ?: 'emitida',
            'estado_cobro'           => $this->request->getPost('estado_cobro') ?: 'pendiente',
            'subtotal'               => 0,
            'iva_total'              => 0,
            'total'                  => 0,
            'importe_cobrado'        => 0,
            'recurrente'             => $this->request->getPost('recurrente') ? 1 : 0,
            'recurrente_mes_limite'  => $rec_mes,
            'recurrente_anno_limite' => $rec_anno,
            'quinquena'              => intval($this->request->getPost('quinquena') ?: 1),
            'created_by'             => $this->login_user->id,
        ];
        $new_id = $this->Facturacion_model->save_factura($data, $id);
        echo json_encode(['success' => true, 'id' => $new_id]);
    }

    function toggle_recurrente() {
        $factura_id = intval($this->request->getPost('factura_id'));
        $recurrente = intval($this->request->getPost('recurrente'));
        if (!$factura_id) { echo json_encode(['success' => false]); return; }
        $this->Facturacion_model->save_factura(['recurrente' => $recurrente], $factura_id);
        echo json_encode(['success' => true]);
    }

    function delete_factura() {
        $id = intval($this->request->getPost('id'));
        if (!$id) { echo json_encode(['success' => false]); return; }
        $this->Facturacion_model->delete_factura($id);
        echo json_encode(['success' => true]);
    }

    function factura_view($id) {
        $factura = $this->Facturacion_model->get_factura($id);
        if (!$factura) app_redirect("forbidden");
        $view_data['factura']       = $factura;
        $view_data['lineas']        = $this->Facturacion_model->get_lineas_factura($id);
        $view_data['pagos']         = $this->Facturacion_model->get_pagos(['factura_id' => $id])->getResult();
        $view_data['meses_nombres'] = $this->_get_meses();
        $view_data['login_user']    = $this->login_user;
        return $this->template->rander("facturacion/facturas/view", $view_data);
    }

    function save_linea() {
        $factura_id = intval($this->request->getPost('factura_id'));
        $id         = intval($this->request->getPost('id'));
        $precio     = floatval($this->request->getPost('precio_unitario'));
        $cantidad   = floatval($this->request->getPost('cantidad') ?: 1);
        $iva_pct    = 0; // Sin IVA — se gestiona externamente
        $subtotal   = round($precio * $cantidad, 2);
        $iva_imp    = 0;
        $total      = $subtotal;
        $data = [
            'factura_id'      => $factura_id,
            'descripcion'     => $this->request->getPost('descripcion'),
            'tipo_linea'      => $this->request->getPost('tipo_linea') ?: 'recurrente',
            'cantidad'        => $cantidad,
            'precio_unitario' => $precio,
            'iva_porcentaje'  => $iva_pct,
            'subtotal'        => $subtotal,
            'iva_importe'     => $iva_imp,
            'total'           => $total,
            'orden'           => intval($this->request->getPost('orden') ?: 1),
        ];
        $new_id = $this->Facturacion_model->save_linea($data, $id);
        // Devolver totales actualizados de la factura
        $factura = $this->Facturacion_model->get_factura($factura_id);
        echo json_encode([
            'success' => true,
            'id'      => $new_id,
            'factura' => [
                'subtotal'  => $factura->subtotal ?? 0,
                'iva_total' => $factura->iva_total ?? 0,
                'total'     => $factura->total ?? 0,
            ]
        ]);
    }

    function delete_linea() {
        $id = $this->request->getPost('id');
        $this->Facturacion_model->delete_linea($id);
        echo json_encode(['success' => true]);
    }

    // ============================================================
    // COBROS — incluye filtro de remesa
    // ============================================================

    function cobros() {
        $view_data['annos']         = range(date('Y'), date('Y') - 3);
        $view_data['meses_nombres'] = $this->_get_meses();
        $view_data['clientes']      = $this->Facturacion_model->get_clientes()->getResult();
        $view_data['login_user']    = $this->login_user;
        return view("facturacion/cobros/index", $view_data);
    }

    function cobros_list_data() {
        $estado_cobro_raw = $this->request->getPost('estado_cobro');
        $options = [
            'anno'                => $this->request->getPost('anno') ?: date('Y'),
            'mes'                 => $this->request->getPost('mes'),
            'cliente_id'          => $this->request->getPost('cliente_id'),
            'estado_cobro'        => ($estado_cobro_raw && $estado_cobro_raw !== 'no_cobradas') ? $estado_cobro_raw : null,
            'estado_cobro_not_in' => ($estado_cobro_raw === 'no_cobradas') ? ['cobrado','no_procede'] : null,
            'forma_pago'          => $this->request->getPost('forma_pago'),
            'quinquena'           => $this->request->getPost('quinquena'),
            'vencidas'            => $this->request->getPost('vencidas'),
        ];
        $facturas = $this->Facturacion_model->get_facturas($options)->getResult();
        $db  = \Config\Database::connect();
        $tl_q = $db->prefixTable('fac_factura_lineas');
        $list = [];
        foreach ($facturas as $f) {
            $pendiente = $f->total - $f->importe_cobrado;
            $linea_desc = $db->query("SELECT GROUP_CONCAT(descripcion ORDER BY orden SEPARATOR ' + ') as concepto FROM $tl_q WHERE factura_id={$f->id}")->getRow();
            $concepto = $linea_desc->concepto ?? '—';
            $recurrente_badge = $f->recurrente ? '<span class="badge bg-info">🔄 Recurrente</span>' : '<span class="badge bg-secondary">Puntual</span>';
            $quinquena_badge = ($f->quinquena == 2) ? '<span class="badge bg-warning text-dark">2ª</span>' : '<span class="badge bg-primary">1ª</span>';
            $list[] = [
                '<a href="' . get_uri("facturacion/factura_view/$f->id") . '" data-fid="' . $f->id . '">' . $f->numero_factura . '</a>',
                $f->cliente_nombre ?? '-',
                '<small class="text-muted">' . htmlspecialchars($concepto) . '</small>',
                $recurrente_badge,
                $quinquena_badge,
                $f->fecha_vencimiento ?: '-',
                number_format($f->total, 2, ',', '.') . ' €',
                number_format($f->importe_cobrado, 2, ',', '.') . ' €',
                number_format($pendiente, 2, ',', '.') . ' €',
                $this->_badge_forma_pago($f->forma_pago),
                $this->_badge_estado_cobro($f->estado_cobro),
            ];
        }
        echo json_encode(['data' => $list]);
    }

    function save_pago() {
        $factura_id = intval($this->request->getPost('factura_id'));
        $factura    = $this->Facturacion_model->get_factura($factura_id);
        if (!$factura) { echo json_encode(['success' => false]); return; }
        $data = [
            'factura_id'  => $factura_id,
            'cliente_id'  => $factura->cliente_id,
            'importe'     => floatval($this->request->getPost('importe') ?: ($factura->total - $factura->importe_cobrado)),
            'fecha_pago'  => $this->request->getPost('fecha_pago') ?: date('Y-m-d'),
            'metodo_pago' => $this->request->getPost('metodo_pago') ?: $factura->forma_pago,
            'estado'      => 'confirmado',
            'referencia'  => $this->request->getPost('referencia'),
            'created_by'  => $this->login_user->id,
        ];
        $this->Facturacion_model->save_pago($data);
        echo json_encode(['success' => true]);
    }

    function marcar_cobrada() {
        $factura_id = intval($this->request->getPost('factura_id'));
        $factura    = $this->Facturacion_model->get_factura($factura_id);
        if (!$factura) { echo json_encode(['success' => false]); return; }
        $pendiente = $factura->total - $factura->importe_cobrado;
        if ($pendiente > 0) {
            $data = [
                'factura_id'  => $factura_id,
                'cliente_id'  => $factura->cliente_id,
                'importe'     => $pendiente,
                'fecha_pago'  => date('Y-m-d'),
                'metodo_pago' => $factura->forma_pago,
                'estado'      => 'confirmado',
                'created_by'  => $this->login_user->id,
            ];
            $this->Facturacion_model->save_pago($data);
        } else {
            $tf = \Config\Database::connect()->prefixTable('fac_facturas');
            \Config\Database::connect()->query("UPDATE $tf SET estado_cobro='cobrado', importe_cobrado=total WHERE id=$factura_id");
        }
        echo json_encode(['success' => true]);
    }

    function marcar_rechazada() {
        $factura_id = intval($this->request->getPost('factura_id'));
        $motivo     = $this->request->getPost('motivo');
        $this->Facturacion_model->save_factura(['estado_cobro' => 'rechazado', 'observaciones_internas' => $motivo], $factura_id);
        $factura = $this->Facturacion_model->get_factura($factura_id);
        if ($factura) $this->_enviar_alerta_pago($factura, 'rechazado', $motivo);
        echo json_encode(['success' => true]);
    }

    function cambiar_estado_cobro() {
        $factura_id   = intval($this->request->getPost('factura_id'));
        $nuevo_estado = $this->request->getPost('estado_cobro');
        $estados = ['pendiente','cobrado','rechazado','parcialmente_cobrado','vencido','no_procede'];
        if (!in_array($nuevo_estado, $estados)) { echo json_encode(['success' => false]); return; }
        $this->Facturacion_model->save_factura(['estado_cobro' => $nuevo_estado], $factura_id);
        $factura = $this->Facturacion_model->get_factura($factura_id);
        if (in_array($nuevo_estado, ['pendiente','rechazado','vencido']) && $factura) {
            $this->_enviar_alerta_pago($factura, $nuevo_estado);
        }
        echo json_encode(['success' => true]);
    }

    private function _enviar_alerta_pago($factura, $estado, $motivo = '') {
        $estados_texto = ['pendiente' => '⚠️ PENDIENTE DE COBRO', 'rechazado' => '🔴 RECIBO RECHAZADO', 'vencido' => '🔴 FACTURA VENCIDA'];
        $estado_label  = $estados_texto[$estado] ?? strtoupper($estado);
        $subject = "[FACTURACIÓN TICTAC] $estado_label — {$factura->cliente_nombre} — {$factura->numero_factura}";
        $pendiente = $factura->total - $factura->importe_cobrado;
        $msg  = "<h3 style='color:#d72173'>⚠️ Alerta de pago — {$factura->cliente_nombre}</h3>";
        $msg .= "<p><strong>Estado:</strong> $estado_label</p>";
        $msg .= "<p><strong>Factura:</strong> {$factura->numero_factura} — {$factura->mes}/{$factura->anno}</p>";
        $msg .= "<p><strong>Importe pendiente:</strong> <span style='color:red;font-weight:bold'>" . number_format($pendiente, 2, ',', '.') . " €</span></p>";
        $msg .= "<p><strong>Forma de pago:</strong> " . ucfirst($factura->forma_pago) . "</p>";
        if ($motivo) $msg .= "<p><strong>Motivo:</strong> $motivo</p>";
        $msg .= "<hr><p style='color:#d72173;font-weight:bold'>⚠️ DEJAR DE TRABAJAR PARA ESTE CLIENTE HASTA QUE REGULARICE EL PAGO.</p>";
        $msg .= "<p><a href='" . base_url('index.php/facturacion/factura_view/' . $factura->id) . "' style='background:#d72173;color:white;padding:10px 20px;text-decoration:none;border-radius:5px'>Ver factura</a></p>";
        send_app_mail('hola@tictac-comunicacion.es', $subject, $msg);
    }

    // ============================================================
    // GENERACIÓN AUTOMÁTICA DESDE CONTRATO
    // ============================================================

    // Este método lo llama el hook cuando un contrato pasa a 'accepted'
    function generar_facturas_contrato() {
        $contract_id = intval($this->request->getPost('contract_id'));
        if (!$contract_id) { echo json_encode(['success' => false, 'message' => 'ID de contrato requerido']); return; }
        $resultado = $this->Facturacion_model->generar_facturas_desde_contrato($contract_id);
        echo json_encode($resultado);
    }

    // Vista para revisar/confirmar antes de generar (llamada desde el contrato)
    function preview_facturas_contrato($contract_id) {
        $db  = \Config\Database::connect();
        $tc  = $db->prefixTable('contracts');
        $tci = $db->prefixTable('contract_items');
        $tcl = $db->prefixTable('clients');

        $contrato = $db->query("
            SELECT c.*, cl.company_name, cl.forma_pago
            FROM $tc c LEFT JOIN $tcl cl ON cl.id=c.client_id
            WHERE c.id=$contract_id AND c.deleted=0
        ")->getRow();

        if (!$contrato) app_redirect("forbidden");

        $lineas = $db->query("SELECT * FROM $tci WHERE contract_id=$contract_id AND deleted=0 ORDER BY sort")->getResult();

        // Verificar si ya hay facturas generadas
        $tf = $db->prefixTable('fac_facturas');
        $ya_generadas = $db->query("SELECT COUNT(*) as n FROM $tf WHERE contract_id=$contract_id AND deleted=0")->getRow();

        $view_data['contrato']      = $contrato;
        $view_data['lineas']        = $lineas;
        $view_data['ya_generadas']  = $ya_generadas->n;
        $view_data['login_user']    = $this->login_user;
        return $this->template->rander("facturacion/contratos/preview_facturas", $view_data);
    }

    // ============================================================
    // GENERAR MES (facturas recurrentes manuales)
    // ============================================================

    function generar_mes() {
        $mes  = intval($this->request->getPost('mes') ?: date('n'));
        $anno = intval($this->request->getPost('anno') ?: date('Y'));

        // Buscar facturas del mes anterior para copiar los recurrentes
        $db = \Config\Database::connect();
        $tf = $db->prefixTable('fac_facturas');
        $tl = $db->prefixTable('fac_factura_lineas');

        $quinquena_filtro = intval($this->request->getPost('quinquena') ?: 0);
        $quinquena_where  = $quinquena_filtro ? " AND quinquena=$quinquena_filtro" : "";

        // Siempre el mes inmediatamente anterior — si quitaste recurrencia en julio,
        // agosto no debe generarse aunque en junio hubiera recurrentes
        $mes_ant  = $mes == 1 ? 12 : $mes - 1;
        $anno_ant = $mes == 1 ? $anno - 1 : $anno;

        $facturas_ant = $db->query("
            SELECT * FROM $tf
            WHERE anno=$anno_ant AND mes=$mes_ant AND deleted=0
            AND recurrente=1
            AND (
                recurrente_anno_limite IS NULL
                OR recurrente_anno_limite > $anno
                OR (recurrente_anno_limite = $anno AND recurrente_mes_limite IS NULL)
                OR (recurrente_anno_limite = $anno AND recurrente_mes_limite >= $mes)
            )
            $quinquena_where
        ")->getResult();

        if (empty($facturas_ant)) {
            echo json_encode(['success' => true, 'generadas' => 0, 'message' => 'No hay facturas recurrentes en ' . $mes_ant . '/' . $anno_ant . '. Si quitaste alguna recurrencia el mes pasado, es correcto.']);
            return;
        }

        $generadas = 0;
        foreach ($facturas_ant as $f) {
            // Solo si no existe ya una factura con el mismo número de origen para este mes
            // Usamos el numero_factura origen como referencia para evitar duplicados exactos
            $num_origen_escaped = $db->escapeString($f->numero_factura);
            $existe = $db->query("SELECT id FROM $tf WHERE cliente_id={$f->cliente_id} AND anno=$anno AND mes=$mes AND deleted=0 AND observaciones_internas LIKE '%origen:{$f->numero_factura}%' LIMIT 1")->getRow();
            if ($existe) continue;

            $count = $db->query("SELECT COUNT(*)+1 as num FROM $tf WHERE anno=$anno")->getRow();
            $num_f = $anno . '-' . str_pad($count->num, 4, '0', STR_PAD_LEFT);

            $db->query("INSERT INTO $tf SET
                numero_factura='$num_f',
                cliente_id={$f->cliente_id},
                anno=$anno, mes=$mes,
                fecha_emision='$anno-" . str_pad($mes,2,'0',STR_PAD_LEFT) . "-01',
                fecha_vencimiento='$anno-" . str_pad($mes,2,'0',STR_PAD_LEFT) . "-28',
                forma_pago='{$f->forma_pago}',
                estado_factura='emitida',
                estado_cobro='pendiente',
                subtotal={$f->subtotal},
                iva_total=0,
                total={$f->subtotal},
                importe_cobrado=0,
                recurrente={$f->recurrente},
                recurrente_mes_limite=" . ($f->recurrente_mes_limite ? $f->recurrente_mes_limite : 'NULL') . ",
                recurrente_anno_limite=" . ($f->recurrente_anno_limite ? $f->recurrente_anno_limite : 'NULL') . ",
                quinquena={$f->quinquena},
                generada_automaticamente=1,
                observaciones_internas='origen:{$f->numero_factura}',
                created_by={$this->login_user->id},
                created_at=NOW()
            ");
            $nueva_id = $db->insertID();

            // Copiar líneas
            $lineas = $db->query("SELECT * FROM $tl WHERE factura_id={$f->id}")->getResult();
            foreach ($lineas as $l) {
                $desc = $db->escapeString($l->descripcion);
                $db->query("INSERT INTO $tl SET
                    factura_id=$nueva_id,
                    descripcion='$desc',
                    tipo_linea='{$l->tipo_linea}',
                    cantidad={$l->cantidad},
                    precio_unitario={$l->precio_unitario},
                    iva_porcentaje={$l->iva_porcentaje},
                    subtotal={$l->subtotal},
                    iva_importe={$l->iva_importe},
                    total={$l->total},
                    orden={$l->orden}
                ");
            }
            $generadas++;
        }
        echo json_encode(['success' => true, 'generadas' => $generadas]);
    }

    // ============================================================
    // RESUMEN GENERAL (vista tipo Excel)
    // ============================================================

    function resumen($anno = null) {
        $anno     = intval($anno ?: date('Y'));
        $quinquena = intval($this->request->getGet('quinquena') ?: 0);
        $db = \Config\Database::connect();
        $tf = $db->prefixTable('fac_facturas');
        $tl = $db->prefixTable('fac_factura_lineas');
        $tc = $db->prefixTable('clients');

        $q_where = $quinquena ? " AND f.quinquena=$quinquena" : "";

        $clientes_raw = $db->query("
            SELECT DISTINCT c.id, c.company_name as nombre, c.forma_pago
            FROM $tc c
            WHERE c.deleted=0 AND c.is_lead=0
            AND EXISTS (SELECT 1 FROM $tf f WHERE f.cliente_id=c.id AND f.anno=$anno AND f.deleted=0 $q_where)
            ORDER BY c.company_name ASC
        ")->getResult();

        $clientes    = [];
        $totales_mes = [];
        for ($m = 1; $m <= 12; $m++) {
            $totales_mes[$m] = ['facturado' => 0, 'cobrado' => 0, 'pendiente' => 0];
        }

        foreach ($clientes_raw as $cli) {
            $facturas = $db->query("
                SELECT f.mes, f.estado_cobro, f.forma_pago, f.total, f.quinquena,
                       GROUP_CONCAT(fl.descripcion ORDER BY fl.orden SEPARATOR ' + ') as concepto
                FROM $tf f
                LEFT JOIN $tl fl ON fl.factura_id=f.id
                WHERE f.cliente_id={$cli->id} AND f.anno=$anno AND f.deleted=0 $q_where
                GROUP BY f.id, f.mes, f.estado_cobro, f.forma_pago, f.total, f.quinquena
            ")->getResult();

            $meses_data = [];
            $cliente_quinquena = 1;
            foreach ($facturas as $fac) {
                $mes = intval($fac->mes);
                $totales_mes[$mes]['facturado'] += floatval($fac->total);
                if ($fac->estado_cobro === 'cobrado') $totales_mes[$mes]['cobrado'] += floatval($fac->total);
                else $totales_mes[$mes]['pendiente'] += floatval($fac->total);
                $meses_data[$mes] = [
                    'concepto'     => $fac->concepto ?: 'Servicios',
                    'importe'      => floatval($fac->total),
                    'estado_cobro' => $fac->estado_cobro,
                ];
                $cliente_quinquena = intval($fac->quinquena ?: 1);
            }

            $clientes[] = [
                'nombre'     => $cli->nombre,
                'forma_pago' => $cli->forma_pago,
                'quinquena'  => $cliente_quinquena,
                'servicios'  => [['nombre' => 'Servicios varios', 'forma_pago' => $cli->forma_pago, 'meses' => $meses_data]]
            ];
        }

        $view_data['anno']        = $anno;
        $view_data['quinquena']   = $quinquena;
        $view_data['clientes']    = $clientes;
        $view_data['totales_mes'] = $totales_mes;
        $view_data['login_user']  = $this->login_user;
        return $this->template->rander("facturacion/resumen/index", $view_data);
    }

    // ============================================================
    // BADGES PRIVADOS
    // ============================================================

    private function _badge_estado_factura($estado) {
        $map = ['borrador'=>'secondary','emitida'=>'primary','enviada'=>'info','pagada'=>'success','cancelada'=>'danger','rectificada'=>'warning'];
        $color = $map[$estado] ?? 'secondary';
        return '<span class="badge bg-' . $color . '">' . ucfirst($estado) . '</span>';
    }

    private function _badge_estado_cobro($estado) {
        $map = [
            'pendiente'            => ['warning',  '⏳', 'Pendiente'],
            'cobrado'              => ['success',  '✅', 'Pagado OK'],
            'rechazado'            => ['danger',   '🔴', 'Rechazado'],
            'parcialmente_cobrado' => ['info',     '🔶', 'Pago parcial'],
            'vencido'              => ['danger',   '🔴', 'Retrasado'],
            'no_procede'           => ['secondary','—',  'No procede'],
        ];
        $d = $map[$estado] ?? ['secondary','?', ucfirst($estado)];
        return '<span class="badge bg-' . $d[0] . '">' . $d[1] . ' ' . $d[2] . '</span>';
    }

    private function _badge_forma_pago($forma) {
        $map = ['remesa'=>['info','🏦 Remesa'],'transferencia'=>['primary','↗️ Transferencia'],'efectivo'=>['success','💵 Efectivo']];
        $d = $map[$forma] ?? ['secondary', ucfirst($forma ?: 'N/D')];
        return '<span class="badge bg-' . $d[0] . '">' . $d[1] . '</span>';
    }

    // ============================================================
    // DASHBOARD TAB Y MODALES AUXILIARES
    // ============================================================

    function dashboard_tab() {
        $anno = intval($this->request->getGet('anno') ?: date('Y'));
        $mes  = intval($this->request->getGet('mes')  ?: date('n'));
        $view_data['anno']         = $anno;
        $view_data['mes']          = $mes;
        $view_data['totales_mes']  = $this->Facturacion_model->get_totales_mes($anno, $mes);
        $view_data['totales_anno'] = $this->Facturacion_model->get_totales_anuales($anno);
        $view_data['meses_nombres']= $this->_get_meses();
        $view_data['login_user']   = $this->login_user;
        return view("facturacion/dashboard/index", $view_data);
    }

    function generar_mes_modal() {
        return view("facturacion/facturas/modal_generar_mes", []);
    }

    function get_crm_client_data() {
        $id = $this->request->getPost('id');
        if (!$id) { echo json_encode([]); return; }
        $cliente = $this->Facturacion_model->get_cliente($id);
        if (!$cliente) { echo json_encode([]); return; }
        echo json_encode([
            'nombre'            => $cliente->nombre,
            'forma_pago'        => $cliente->forma_pago,
            'vat_number'        => $cliente->vat_number ?? '',
            'address'           => $cliente->address ?? '',
            'city'              => $cliente->city ?? '',
            'zip'               => $cliente->zip ?? '',
        ]);
    }


}