<?php

namespace App\Models;

class Facturacion_model extends Crud_model {

    protected $table = null;

    function __construct() {
        $this->table = 'fac_facturas';
        parent::__construct($this->table);
    }

    // ============================================================
    // CLIENTES — ahora usa crm_clients directamente
    // ============================================================

    function get_clientes($options = []) {
        $tc = $this->db->prefixTable('clients');
        $where = " AND $tc.deleted=0 AND $tc.is_lead=0";

        $id = $this->_get_clean_value($options, 'id');
        if ($id) $where .= " AND $tc.id=$id";

        $search = $this->_get_clean_value($options, 'search');
        if ($search) $where .= " AND ($tc.company_name LIKE '%$search%')";

        $sql = "SELECT $tc.id, $tc.company_name as nombre, $tc.forma_pago,
                       $tc.address, $tc.city, $tc.zip, $tc.country,
                       $tc.phone, $tc.vat_number
                FROM $tc
                WHERE 1=1 $where
                ORDER BY $tc.company_name ASC";

        return $this->db->query($sql);
    }

    function get_cliente($id) {
        return $this->get_clientes(['id' => $id])->getRow();
    }

    function save_forma_pago_cliente($crm_client_id, $forma_pago) {
        $tc = $this->db->prefixTable('clients');
        $fp = $this->db->escapeString($forma_pago);
        $this->db->query("UPDATE $tc SET forma_pago='$fp' WHERE id=$crm_client_id");
    }

    // ============================================================
    // FACTURAS
    // ============================================================

    function get_facturas($options = []) {
        $tf  = $this->db->prefixTable('fac_facturas');
        $tc  = $this->db->prefixTable('clients');
        $where = " AND $tf.deleted=0";

        $id = $this->_get_clean_value($options, 'id');
        if ($id) $where .= " AND $tf.id=$id";

        $cliente_id = $this->_get_clean_value($options, 'cliente_id');
        if ($cliente_id) $where .= " AND $tf.cliente_id=$cliente_id";

        $anno = $this->_get_clean_value($options, 'anno');
        if ($anno) $where .= " AND $tf.anno=$anno";

        $mes = $this->_get_clean_value($options, 'mes');
        if ($mes) $where .= " AND $tf.mes=$mes";

        $estado_cobro = $this->_get_clean_value($options, 'estado_cobro');
        if ($estado_cobro) $where .= " AND $tf.estado_cobro='$estado_cobro'";

        $estado_cobro_not_in = get_array_value($options, 'estado_cobro_not_in');
        if (!empty($estado_cobro_not_in)) {
            $vals = "'" . implode("','", $estado_cobro_not_in) . "'";
            $where .= " AND $tf.estado_cobro NOT IN ($vals)";
        }

        $estado_factura = $this->_get_clean_value($options, 'estado_factura');
        if ($estado_factura) $where .= " AND $tf.estado_factura='$estado_factura'";

        $forma_pago = $this->_get_clean_value($options, 'forma_pago');
        if ($forma_pago) $where .= " AND $tf.forma_pago='$forma_pago'";

        $contract_id = $this->_get_clean_value($options, 'contract_id');
        if ($contract_id) $where .= " AND $tf.contract_id=$contract_id";

        $quinquena = $this->_get_clean_value($options, 'quinquena');
        if ($quinquena) $where .= " AND $tf.quinquena=$quinquena";

        // Filtro vencidas
        if (!empty($options['vencidas'])) {
            $where .= " AND $tf.fecha_vencimiento < CURDATE() AND $tf.estado_cobro NOT IN ('cobrado','no_procede')";
        }

        $sql = "SELECT $tf.*, $tc.company_name as cliente_nombre, $tc.forma_pago as cliente_forma_pago,
                       $tc.vat_number
                FROM $tf
                LEFT JOIN $tc ON $tc.id = $tf.cliente_id
                WHERE 1=1 $where
                ORDER BY $tf.anno DESC, $tf.mes DESC, $tf.id DESC";

        return $this->db->query($sql);
    }

    function get_factura($id) {
        return $this->get_facturas(['id' => $id])->getRow();
    }

    function save_factura($data, $id = 0) {
        $t = $this->db->prefixTable('fac_facturas');
        if ($id) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            $this->db->query("UPDATE $t SET " . $this->_fac_build_set($data) . " WHERE id=$id");
            return $id;
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->db->query("INSERT INTO $t SET " . $this->_fac_build_set($data));
            return $this->db->insertID();
        }
    }

    function delete_factura($id) {
        $t = $this->db->prefixTable('fac_facturas');
        $this->db->query("UPDATE $t SET deleted=1 WHERE id=$id");
    }

    // ============================================================
    // LÍNEAS DE FACTURA
    // ============================================================

    function get_lineas_factura($factura_id) {
        $t = $this->db->prefixTable('fac_factura_lineas');
        return $this->db->query("SELECT * FROM $t WHERE factura_id=$factura_id ORDER BY orden ASC")->getResult();
    }

    function save_linea($data, $id = 0) {
        $t = $this->db->prefixTable('fac_factura_lineas');
        if ($id) {
            $this->db->query("UPDATE $t SET " . $this->_fac_build_set($data) . " WHERE id=$id");
            return $id;
        } else {
            $this->db->query("INSERT INTO $t SET " . $this->_fac_build_set($data));
            $lid = $this->db->insertID();
            $this->_recalcular_totales($data['factura_id']);
            return $lid;
        }
    }

    function delete_linea($id) {
        $t = $this->db->prefixTable('fac_factura_lineas');
        $linea = $this->db->query("SELECT factura_id FROM $t WHERE id=$id")->getRow();
        $this->db->query("DELETE FROM $t WHERE id=$id");
        if ($linea) $this->_recalcular_totales($linea->factura_id);
    }

    private function _recalcular_totales($factura_id) {
        $tl = $this->db->prefixTable('fac_factura_lineas');
        $tf = $this->db->prefixTable('fac_facturas');
        $row = $this->db->query("SELECT SUM(subtotal) as sub, SUM(iva_importe) as iva, SUM(total) as tot FROM $tl WHERE factura_id=$factura_id")->getRow();
        if ($row) {
            $this->db->query("UPDATE $tf SET subtotal={$row->sub}, iva_total={$row->iva}, total={$row->tot} WHERE id=$factura_id");
        }
    }

    // ============================================================
    // PAGOS
    // ============================================================

    function get_pagos($options = []) {
        $t  = $this->db->prefixTable('fac_pagos');
        $tc = $this->db->prefixTable('clients');
        $where = '';

        $factura_id = $this->_get_clean_value($options, 'factura_id');
        if ($factura_id) $where .= " AND $t.factura_id=$factura_id";

        $cliente_id = $this->_get_clean_value($options, 'cliente_id');
        if ($cliente_id) $where .= " AND $t.cliente_id=$cliente_id";

        $sql = "SELECT $t.*, $tc.company_name as cliente_nombre
                FROM $t
                LEFT JOIN $tc ON $tc.id = $t.cliente_id
                WHERE 1=1 $where
                ORDER BY $t.fecha_pago DESC";

        return $this->db->query($sql);
    }

    function save_pago($data, $id = 0) {
        $t = $this->db->prefixTable('fac_pagos');
        if ($id) {
            $this->db->query("UPDATE $t SET " . $this->_fac_build_set($data) . " WHERE id=$id");
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->db->query("INSERT INTO $t SET " . $this->_fac_build_set($data));
            $id = $this->db->insertID();
        }
        $this->_actualizar_importe_cobrado($data['factura_id']);
        return $id;
    }

    private function _actualizar_importe_cobrado($factura_id) {
        $tp = $this->db->prefixTable('fac_pagos');
        $tf = $this->db->prefixTable('fac_facturas');
        $row = $this->db->query("SELECT SUM(importe) as total FROM $tp WHERE factura_id=$factura_id AND estado='confirmado'")->getRow();
        $cobrado = $row ? floatval($row->total) : 0;
        $factura = $this->get_factura($factura_id);
        if ($factura) {
            $estado = $cobrado <= 0 ? 'pendiente' : ($cobrado >= $factura->total ? 'cobrado' : 'parcialmente_cobrado');
            $this->db->query("UPDATE $tf SET importe_cobrado=$cobrado, estado_cobro='$estado' WHERE id=$factura_id");
        }
    }

    // ============================================================
    // GENERACIÓN AUTOMÁTICA DESDE CONTRATO
    // ============================================================

    function generar_facturas_desde_contrato($contract_id) {
        $tc  = $this->db->prefixTable('contracts');
        $tci = $this->db->prefixTable('contract_items');
        $tcl = $this->db->prefixTable('clients');

        // Datos del contrato
        $contrato = $this->db->query("
            SELECT c.*, cl.company_name, cl.forma_pago
            FROM $tc c
            LEFT JOIN $tcl cl ON cl.id = c.client_id
            WHERE c.id=$contract_id AND c.deleted=0
        ")->getRow();

        if (!$contrato) return ['success' => false, 'message' => 'Contrato no encontrado'];

        // Líneas del contrato
        $lineas = $this->db->query("
            SELECT * FROM $tci
            WHERE contract_id=$contract_id AND deleted=0
            ORDER BY sort ASC
        ")->getResult();

        if (empty($lineas)) return ['success' => false, 'message' => 'El contrato no tiene líneas'];

        $cliente_id  = $contrato->client_id;
        $forma_pago  = $contrato->forma_pago ?: 'transferencia';
        $fecha_inicio = $contrato->contract_date ?: date('Y-m-d');
        $facturas_creadas = 0;

        // Agrupar líneas por tipo de pago y periodo
        // Las líneas mensuales se agrupan en facturas mensuales
        // Las de pago único se generan como factura independiente

        $lineas_unico   = [];
        $lineas_mensual = [];

        foreach ($lineas as $linea) {
            $tipo = $linea->tipo_pago ?? 'mensual';
            if ($tipo === 'unico') {
                $lineas_unico[] = $linea;
            } else {
                // mensual y cualquier otro tipo → mensual recurrente indefinido
                $lineas_mensual[] = $linea;
            }
        }

        $dt_inicio = new \DateTime($fecha_inicio);

        // 1. Pago único — una sola factura al aceptar el contrato
        if (!empty($lineas_unico)) {
            $factura_id = $this->_crear_factura_con_lineas(
                $cliente_id, $contract_id, $forma_pago,
                intval($dt_inicio->format('Y')), intval($dt_inicio->format('n')),
                $dt_inicio->format('Y-m-d'), $lineas_unico, 'unico'
            );
            if ($factura_id) $facturas_creadas++;
        }

        // 2. Mensual — solo se crea la del mes actual con recurrente=1
        // Los meses siguientes se generan con el botón "Generar mes"
        if (!empty($lineas_mensual)) {
            $factura_id = $this->_crear_factura_con_lineas(
                $cliente_id, $contract_id, $forma_pago,
                intval($dt_inicio->format('Y')), intval($dt_inicio->format('n')),
                $dt_inicio->format('Y-m-d'), $lineas_mensual, 'mensual'
            );
            if ($factura_id) $facturas_creadas++;
        }

        return ['success' => true, 'facturas_creadas' => $facturas_creadas];
    }

    private function _crear_factura_con_lineas($cliente_id, $contract_id, $forma_pago, $anno, $mes, $fecha, $lineas, $tipo_pago) {
        // Calcular totales
        $subtotal = 0;
        foreach ($lineas as $l) {
            $importe = $tipo_pago === 'mensual' ? floatval($l->rate) : floatval($l->total);
            $subtotal += $importe;
        }
        if ($subtotal <= 0) return null;

        $iva = 0; // Sin IVA — se gestiona externamente
        $total = $subtotal;

        // Número de factura
        $tf = $this->db->prefixTable('fac_facturas');
        $count = $this->db->query("SELECT COUNT(*)+1 as num FROM $tf WHERE anno=$anno")->getRow();
        $num_factura = $anno . '-' . str_pad($count->num, 4, '0', STR_PAD_LEFT);

        // Fecha de vencimiento: 30 días
        $dt = new \DateTime($fecha);
        $dt->modify('+30 days');
        $vencimiento = $dt->format('Y-m-d');

        $factura_data = [
            'numero_factura'          => $num_factura,
            'cliente_id'              => $cliente_id,
            'contract_id'             => $contract_id,
            'anno'                    => $anno,
            'mes'                     => $mes,
            'fecha_emision'           => $fecha,
            'fecha_vencimiento'       => $vencimiento,
            'forma_pago'              => $forma_pago,
            'estado_factura'          => 'emitida',
            'estado_cobro'            => 'pendiente',
            'subtotal'                => $subtotal,
            'iva_total'               => 0,
            'total'                   => $total,
            'importe_cobrado'         => 0,
            'recurrente'              => ($tipo_pago === 'mensual') ? 1 : 0,
            'quinquena'               => 1,
            'generada_automaticamente'=> 1,
            'created_by'              => get_current_user_id(),
        ];

        $factura_id = $this->save_factura($factura_data);

        // Insertar líneas
        $tl = $this->db->prefixTable('fac_factura_lineas');
        foreach ($lineas as $orden => $l) {
            $importe = $tipo_pago === 'mensual' ? floatval($l->rate) : floatval($l->total);
            if ($importe <= 0) continue;
            $iva_l = 0;
            $tot_l = $importe;
            $desc  = $this->db->escapeString($l->title);
            $this->db->query("INSERT INTO $tl SET
                factura_id=$factura_id,
                descripcion='$desc',
                tipo_linea='$tipo_pago',
                cantidad=1,
                precio_unitario=$importe,
                iva_porcentaje=0,
                subtotal=$importe,
                iva_importe=0,
                total=$tot_l,
                orden=" . ($orden+1)
            );
        }

        return $factura_id;
    }

    // ============================================================
    // DASHBOARD / TOTALES
    // ============================================================

    function get_totales_mes($anno, $mes) {
        $tf = $this->db->prefixTable('fac_facturas');
        return $this->db->query("
            SELECT
                SUM(total) as total_facturado,
                SUM(importe_cobrado) as total_cobrado,
                SUM(total - importe_cobrado) as total_pendiente,
                COUNT(*) as num_facturas,
                COUNT(CASE WHEN estado_cobro='cobrado' THEN 1 END) as num_cobradas,
                COUNT(CASE WHEN estado_cobro='pendiente' THEN 1 END) as num_pendientes,
                COUNT(CASE WHEN estado_cobro='rechazado' THEN 1 END) as num_rechazadas
            FROM $tf
            WHERE anno=$anno AND mes=$mes AND deleted=0
        ")->getRow();
    }

    function get_totales_anuales($anno) {
        $tf = $this->db->prefixTable('fac_facturas');
        return $this->db->query("
            SELECT mes,
                SUM(total) as total_facturado,
                SUM(importe_cobrado) as total_cobrado
            FROM $tf
            WHERE anno=$anno AND deleted=0
            GROUP BY mes ORDER BY mes ASC
        ")->getResult();
    }

    // ============================================================
    // HELPER: _fac_build_set
    // ============================================================

    protected function _fac_build_set($data) {
        $parts = [];
        foreach ($data as $k => $v) {
            if ($v === null || $v === 'NULL') {
                $parts[] = "`$k`=NULL";
            } else {
                $escaped = $this->db->escapeString((string) $v);
                $parts[] = "`$k`='$escaped'";
            }
        }
        return implode(', ', $parts);
    }
}