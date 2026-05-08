<?php

namespace App\Models;

class Facturacion_model extends Crud_model {

    protected $table = null;

    function __construct() {
        $this->table = 'fac_facturas';
        parent::__construct($this->table);
    }

    // ============================================================
    // CLIENTES DE FACTURACIÓN
    // ============================================================

    function get_clientes($options = array()) {
        $t = $this->db->prefixTable('fac_clientes');
        $tc = $this->db->prefixTable('clients');

        $where = " AND $t.deleted=0";

        $id = $this->_get_clean_value($options, 'id');
        if ($id) $where .= " AND $t.id=$id";

        $estado = $this->_get_clean_value($options, 'estado');
        if ($estado) $where .= " AND $t.estado='$estado'";

        $search = $this->_get_clean_value($options, 'search');
        if ($search) $where .= " AND ($t.nombre LIKE '%$search%' OR $t.cif_nif LIKE '%$search%' OR $t.email_facturacion LIKE '%$search%')";

        $sql = "SELECT $t.*, $tc.company_name as crm_company_name
                FROM $t
                LEFT JOIN $tc ON $tc.id = $t.crm_client_id
                WHERE 1=1 $where
                ORDER BY $t.nombre ASC";

        return $this->db->query($sql);
    }

    function save_cliente($data, $id = 0) {
        $t = $this->db->prefixTable('fac_clientes');
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

    function get_cliente($id) {
        return $this->get_clientes(['id' => $id])->getRow();
    }

    function delete_cliente($id) {
        $t = $this->db->prefixTable('fac_clientes');
        $this->db->query("UPDATE $t SET deleted=1, updated_at=NOW() WHERE id=$id");
    }

    // ============================================================
    // SERVICIOS CATÁLOGO
    // ============================================================

    function get_servicios_catalogo($options = array()) {
        $t = $this->db->prefixTable('fac_servicios_catalogo');
        $where = "";

        $estado = $this->_get_clean_value($options, 'estado');
        if ($estado) $where .= " AND $t.estado='$estado'";

        $categoria = $this->_get_clean_value($options, 'categoria');
        if ($categoria) $where .= " AND $t.categoria='$categoria'";

        return $this->db->query("SELECT * FROM $t WHERE 1=1 $where ORDER BY $t.nombre ASC");
    }

    function save_servicio_catalogo($data, $id = 0) {
        $t = $this->db->prefixTable('fac_servicios_catalogo');
        if ($id) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            $this->db->query("UPDATE $t SET " . $this->_fac_build_set($data) . " WHERE id=$id");
            return $id;
        } else {
            $this->db->query("INSERT INTO $t SET " . $this->_fac_build_set($data));
            return $this->db->insertID();
        }
    }

    // ============================================================
    // SERVICIOS DE CLIENTES (CONTRATADOS)
    // ============================================================

    function get_cliente_servicios($options = array()) {
        $t  = $this->db->prefixTable('fac_cliente_servicios');
        $tc = $this->db->prefixTable('fac_clientes');
        $ts = $this->db->prefixTable('fac_servicios_catalogo');
        $tu = $this->db->prefixTable('users');

        $where = "";

        $id = $this->_get_clean_value($options, 'id');
        if ($id) $where .= " AND $t.id=$id";

        $cliente_id = $this->_get_clean_value($options, 'cliente_id');
        if ($cliente_id) $where .= " AND $t.cliente_id=$cliente_id";

        $estado = $this->_get_clean_value($options, 'estado');
        if ($estado) $where .= " AND $t.estado='$estado'";

        $es_recurrente = $this->_get_clean_value($options, 'es_recurrente');
        if ($es_recurrente !== null && $es_recurrente !== '') {
            $where .= " AND $t.es_recurrente=" . intval($es_recurrente);
        }

        $sql = "SELECT $t.*,
                    $tc.nombre as cliente_nombre,
                    $tc.forma_pago_default as cliente_forma_pago,
                    $ts.nombre as servicio_catalogo_nombre,
                    $ts.categoria as servicio_categoria,
                    CONCAT($tu.first_name, ' ', $tu.last_name) as comercial_nombre
                FROM $t
                LEFT JOIN $tc ON $tc.id = $t.cliente_id
                LEFT JOIN $ts ON $ts.id = $t.servicio_catalogo_id
                LEFT JOIN $tu ON $tu.id = $t.comercial_id
                WHERE 1=1 $where
                ORDER BY $tc.nombre ASC, $t.concepto ASC";

        return $this->db->query($sql);
    }

    function save_cliente_servicio($data, $id = 0) {
        $t = $this->db->prefixTable('fac_cliente_servicios');
        if ($id) {
            // Si cambia el importe, guardar histórico
            $old = $this->db->query("SELECT importe FROM $t WHERE id=$id")->getRow();
            if ($old && isset($data['importe']) && $old->importe != $data['importe']) {
                $th = $this->db->prefixTable('fac_cliente_servicios_historial_precio');
                $this->db->query("INSERT INTO $th (cliente_servicio_id, importe_anterior, importe_nuevo, fecha_cambio, changed_by, created_at) VALUES ($id, {$old->importe}, {$data['importe']}, CURDATE(), " . ($data['created_by'] ?? 'NULL') . ", NOW())");
            }
            $data['updated_at'] = date('Y-m-d H:i:s');
            $this->db->query("UPDATE $t SET " . $this->_fac_build_set($data) . " WHERE id=$id");
            return $id;
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->db->query("INSERT INTO $t SET " . $this->_fac_build_set($data));
            return $this->db->insertID();
        }
    }

    // ============================================================
    // FACTURAS
    // ============================================================

    function get_facturas($options = array()) {
        $tf  = $this->db->prefixTable('fac_facturas');
        $tc  = $this->db->prefixTable('fac_clientes');
        $tu  = $this->db->prefixTable('users');

        $where = " AND $tf.deleted=0";

        $id = $this->_get_clean_value($options, 'id');
        if ($id) $where .= " AND $tf.id=$id";

        $cliente_id = $this->_get_clean_value($options, 'cliente_id');
        if ($cliente_id) $where .= " AND $tf.cliente_id=$cliente_id";

        $anno = $this->_get_clean_value($options, 'anno');
        if ($anno) $where .= " AND $tf.anno=$anno";

        $mes = $this->_get_clean_value($options, 'mes');
        if ($mes) $where .= " AND $tf.mes=$mes";

        $estado_factura = $this->_get_clean_value($options, 'estado_factura');
        if ($estado_factura) $where .= " AND $tf.estado_factura='$estado_factura'";

        $estado_cobro = $this->_get_clean_value($options, 'estado_cobro');
        if ($estado_cobro) $where .= " AND $tf.estado_cobro='$estado_cobro'";

        $forma_pago = $this->_get_clean_value($options, 'forma_pago');
        if ($forma_pago) $where .= " AND $tf.forma_pago='$forma_pago'";

        $es_kit_digital = $this->_get_clean_value($options, 'es_kit_digital');
        if ($es_kit_digital !== null && $es_kit_digital !== '') {
            $where .= " AND $tf.es_kit_digital=" . intval($es_kit_digital);
        }

        $remesa_id = $this->_get_clean_value($options, 'remesa_id');
        if ($remesa_id) $where .= " AND $tf.remesa_id=$remesa_id";

        $vencidas = $this->_get_clean_value($options, 'vencidas');
        if ($vencidas) $where .= " AND $tf.fecha_vencimiento < CURDATE() AND $tf.estado_cobro NOT IN ('cobrado','no_procede')";

        $search = $this->_get_clean_value($options, 'search');
        if ($search) $where .= " AND ($tf.numero_factura LIKE '%$search%' OR $tc.nombre LIKE '%$search%')";

        $sql = "SELECT $tf.*,
                    $tc.nombre as cliente_nombre,
                    $tc.forma_pago_default as cliente_forma_pago_default,
                    $tc.cif_nif,
                    $tc.email_facturacion,
                    CONCAT($tu.first_name, ' ', $tu.last_name) as creador_nombre,
                    ($tf.total - $tf.importe_cobrado) as importe_pendiente,
                    DATEDIFF(CURDATE(), $tf.fecha_vencimiento) as dias_vencimiento
                FROM $tf
                LEFT JOIN $tc ON $tc.id = $tf.cliente_id
                LEFT JOIN $tu ON $tu.id = $tf.created_by
                WHERE 1=1 $where
                ORDER BY $tf.anno DESC, $tf.mes DESC, $tf.fecha_emision DESC";

        return $this->db->query($sql);
    }

    function get_factura($id) {
        return $this->get_facturas(['id' => $id])->getRow();
    }

    function get_siguiente_numero_factura($anno) {
        $t = $this->db->prefixTable('fac_facturas');
        $result = $this->db->query("SELECT MAX(CAST(SUBSTRING_INDEX(numero_factura, '-', -1) AS UNSIGNED)) as max_num FROM $t WHERE anno=$anno AND deleted=0")->getRow();
        $siguiente = ($result && $result->max_num) ? $result->max_num + 1 : 1;
        return $anno . '-' . str_pad($siguiente, 4, '0', STR_PAD_LEFT);
    }

    function save_factura($data, $id = 0) {
        $t = $this->db->prefixTable('fac_facturas');
        if ($id) {
            $data['updated_at'] = date('Y-m-d H:i:s');
            $this->db->query("UPDATE $t SET " . $this->_fac_build_set($data) . " WHERE id=$id");
            return $id;
        } else {
            if (empty($data['numero_factura'])) {
                $data['numero_factura'] = $this->get_siguiente_numero_factura($data['anno'] ?? date('Y'));
            }
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->db->query("INSERT INTO $t SET " . $this->_fac_build_set($data));
            return $this->db->insertID();
        }
    }

    function delete_factura($id) {
        $t = $this->db->prefixTable('fac_facturas');
        $this->db->query("UPDATE $t SET deleted=1, updated_at=NOW() WHERE id=$id");
    }

    // ============================================================
    // LÍNEAS DE FACTURA
    // ============================================================

    function get_lineas_factura($factura_id) {
        $t = $this->db->prefixTable('fac_factura_lineas');
        return $this->db->query("SELECT * FROM $t WHERE factura_id=$factura_id ORDER BY orden ASC, id ASC")->getResult();
    }

    function save_linea($data, $id = 0) {
        // Calcular subtotal, iva e importe
        $qty     = floatval($data['cantidad'] ?? 1);
        $precio  = floatval($data['precio_unitario'] ?? 0);
        $iva_pct = floatval($data['iva_porcentaje'] ?? 21);
        $subtotal = $qty * $precio;
        $iva_imp  = $subtotal * ($iva_pct / 100);
        $data['subtotal']    = round($subtotal, 2);
        $data['iva_importe'] = round($iva_imp, 2);
        $data['total']       = round($subtotal + $iva_imp, 2);

        $t = $this->db->prefixTable('fac_factura_lineas');
        if ($id) {
            $this->db->query("UPDATE $t SET " . $this->_fac_build_set($data) . " WHERE id=$id");
            return $id;
        } else {
            $this->db->query("INSERT INTO $t SET " . $this->_fac_build_set($data));
            return $this->db->insertID();
        }
    }

    function delete_linea($id) {
        $t = $this->db->prefixTable('fac_factura_lineas');
        $this->db->query("DELETE FROM $t WHERE id=$id");
    }

    function delete_lineas_factura($factura_id) {
        $t = $this->db->prefixTable('fac_factura_lineas');
        $this->db->query("DELETE FROM $t WHERE factura_id=$factura_id");
    }

    function recalcular_totales_factura($factura_id) {
        $tl = $this->db->prefixTable('fac_factura_lineas');
        $tf = $this->db->prefixTable('fac_facturas');
        $totales = $this->db->query("SELECT SUM(subtotal) as subtotal, SUM(iva_importe) as iva_total, SUM(total) as total FROM $tl WHERE factura_id=$factura_id")->getRow();
        $this->db->query("UPDATE $tf SET subtotal={$totales->subtotal}, iva_total={$totales->iva_total}, total={$totales->total}, updated_at=NOW() WHERE id=$factura_id");
    }

    // ============================================================
    // PAGOS
    // ============================================================

    function get_pagos($options = array()) {
        $tp = $this->db->prefixTable('fac_pagos');
        $tf = $this->db->prefixTable('fac_facturas');
        $tc = $this->db->prefixTable('fac_clientes');

        $where = "";
        $factura_id = $this->_get_clean_value($options, 'factura_id');
        if ($factura_id) $where .= " AND $tp.factura_id=$factura_id";

        $cliente_id = $this->_get_clean_value($options, 'cliente_id');
        if ($cliente_id) $where .= " AND $tp.cliente_id=$cliente_id";

        return $this->db->query("SELECT $tp.*, $tf.numero_factura, $tc.nombre as cliente_nombre
                FROM $tp
                LEFT JOIN $tf ON $tf.id = $tp.factura_id
                LEFT JOIN $tc ON $tc.id = $tp.cliente_id
                WHERE 1=1 $where
                ORDER BY $tp.fecha_pago DESC")->getResult();
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
        // Recalcular importe cobrado en la factura
        $this->_actualizar_importe_cobrado($data['factura_id']);
        return $id;
    }

    private function _actualizar_importe_cobrado($factura_id) {
        $tp = $this->db->prefixTable('fac_pagos');
        $tf = $this->db->prefixTable('fac_facturas');
        $total_pagado = $this->db->query("SELECT SUM(importe) as total FROM $tp WHERE factura_id=$factura_id AND estado='confirmado'")->getRow()->total ?? 0;
        $factura = $this->db->query("SELECT total FROM $tf WHERE id=$factura_id")->getRow();
        $estado_cobro = 'pendiente';
        if ($total_pagado >= $factura->total) $estado_cobro = 'cobrado';
        elseif ($total_pagado > 0) $estado_cobro = 'parcialmente_cobrado';
        $this->db->query("UPDATE $tf SET importe_cobrado=$total_pagado, estado_cobro='$estado_cobro', updated_at=NOW() WHERE id=$factura_id");
    }

    // ============================================================
    // REMESAS
    // ============================================================

    function get_remesas($options = array()) {
        $t = $this->db->prefixTable('fac_remesas');
        $where = "";
        $anno = $this->_get_clean_value($options, 'anno');
        if ($anno) $where .= " AND anno=$anno";
        return $this->db->query("SELECT * FROM $t WHERE 1=1 $where ORDER BY anno DESC, mes DESC")->getResult();
    }

    function save_remesa($data, $id = 0) {
        $t = $this->db->prefixTable('fac_remesas');
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

    function get_facturas_remesa($remesa_id) {
        $tf = $this->db->prefixTable('fac_facturas');
        $tc = $this->db->prefixTable('fac_clientes');
        return $this->db->query("SELECT $tf.*, $tc.nombre as cliente_nombre, $tc.cif_nif
                FROM $tf LEFT JOIN $tc ON $tc.id=$tf.cliente_id
                WHERE $tf.remesa_id=$remesa_id AND $tf.deleted=0
                ORDER BY $tc.nombre ASC")->getResult();
    }

    // ============================================================
    // RENOVACIONES
    // ============================================================

    function get_renovaciones($options = array()) {
        $t  = $this->db->prefixTable('fac_renovaciones');
        $tc = $this->db->prefixTable('fac_clientes');
        $tf = $this->db->prefixTable('fac_facturas');

        $where = "";
        $cliente_id = $this->_get_clean_value($options, 'cliente_id');
        if ($cliente_id) $where .= " AND $t.cliente_id=$cliente_id";

        $estado = $this->_get_clean_value($options, 'estado');
        if ($estado) $where .= " AND $t.estado='$estado'";

        $proximas_dias = $this->_get_clean_value($options, 'proximas_dias');
        if ($proximas_dias) $where .= " AND $t.fecha_renovacion <= DATE_ADD(CURDATE(), INTERVAL $proximas_dias DAY) AND $t.fecha_renovacion >= CURDATE()";

        return $this->db->query("SELECT $t.*, $tc.nombre as cliente_nombre, $tf.numero_factura
                FROM $t
                LEFT JOIN $tc ON $tc.id = $t.cliente_id
                LEFT JOIN $tf ON $tf.id = $t.factura_id
                WHERE 1=1 $where
                ORDER BY $t.fecha_renovacion ASC")->getResult();
    }

    function save_renovacion($data, $id = 0) {
        $t = $this->db->prefixTable('fac_renovaciones');
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

    // ============================================================
    // KIT DIGITAL
    // ============================================================

    function get_kit_digital($options = array()) {
        $t  = $this->db->prefixTable('fac_kit_digital');
        $tc = $this->db->prefixTable('fac_clientes');
        $tu = $this->db->prefixTable('users');

        $where = "";
        $cliente_id = $this->_get_clean_value($options, 'cliente_id');
        if ($cliente_id) $where .= " AND $t.cliente_id=$cliente_id";
        $estado_proyecto = $this->_get_clean_value($options, 'estado_proyecto');
        if ($estado_proyecto) $where .= " AND $t.estado_proyecto='$estado_proyecto'";

        return $this->db->query("SELECT $t.*, $tc.nombre as cliente_nombre,
                    CONCAT($tu.first_name, ' ', $tu.last_name) as comercial_nombre
                FROM $t
                LEFT JOIN $tc ON $tc.id = $t.cliente_id
                LEFT JOIN $tu ON $tu.id = $t.comercial_id
                WHERE 1=1 $where
                ORDER BY $t.created_at DESC")->getResult();
    }

    function save_kit_digital($data, $id = 0) {
        $t = $this->db->prefixTable('fac_kit_digital');
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

    // ============================================================
    // COMISIONES
    // ============================================================

    function get_comisiones($options = array()) {
        $t  = $this->db->prefixTable('fac_comisiones');
        $tc = $this->db->prefixTable('fac_clientes');
        $tf = $this->db->prefixTable('fac_facturas');
        $tu = $this->db->prefixTable('users');

        $where = "";
        $persona_id = $this->_get_clean_value($options, 'persona_id');
        if ($persona_id) $where .= " AND $t.persona_id=$persona_id";
        $cliente_id = $this->_get_clean_value($options, 'cliente_id');
        if ($cliente_id) $where .= " AND $t.cliente_id=$cliente_id";
        $estado_pago = $this->_get_clean_value($options, 'estado_pago');
        if ($estado_pago) $where .= " AND $t.estado_pago='$estado_pago'";

        return $this->db->query("SELECT $t.*,
                    $tc.nombre as cliente_nombre,
                    $tf.numero_factura,
                    CONCAT($tu.first_name, ' ', $tu.last_name) as persona_nombre
                FROM $t
                LEFT JOIN $tc ON $tc.id = $t.cliente_id
                LEFT JOIN $tf ON $tf.id = $t.factura_id
                LEFT JOIN $tu ON $tu.id = $t.persona_id
                WHERE 1=1 $where
                ORDER BY $t.created_at DESC")->getResult();
    }

    function save_comision($data, $id = 0) {
        // Calcular comisión automáticamente
        if (isset($data['importe_recibido']) && isset($data['porcentaje'])) {
            $data['comision_calculada'] = round(floatval($data['importe_recibido']) * floatval($data['porcentaje']) / 100, 2);
        }
        $t = $this->db->prefixTable('fac_comisiones');
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

    // ============================================================
    // DASHBOARD
    // ============================================================

    function get_resumen_mes($anno, $mes) {
        $tf = $this->db->prefixTable('fac_facturas');
        return $this->db->query("SELECT
                COUNT(*) as total_facturas,
                SUM(total) as total_facturado,
                SUM(importe_cobrado) as total_cobrado,
                SUM(total - importe_cobrado) as total_pendiente,
                SUM(CASE WHEN estado_cobro='rechazado' THEN total ELSE 0 END) as total_rechazado,
                SUM(CASE WHEN forma_pago='remesa' THEN total ELSE 0 END) as total_remesa,
                SUM(CASE WHEN forma_pago='transferencia' THEN total ELSE 0 END) as total_transferencia,
                COUNT(CASE WHEN estado_cobro='pendiente' THEN 1 END) as facturas_pendientes,
                COUNT(CASE WHEN estado_cobro='vencido' OR (fecha_vencimiento < CURDATE() AND estado_cobro NOT IN ('cobrado','no_procede')) THEN 1 END) as facturas_vencidas
                FROM $tf
                WHERE anno=$anno AND mes=$mes AND deleted=0")->getRow();
    }

    function get_resumen_anno($anno) {
        $tf = $this->db->prefixTable('fac_facturas');
        return $this->db->query("SELECT
                mes,
                SUM(total) as total_facturado,
                SUM(importe_cobrado) as total_cobrado,
                COUNT(*) as num_facturas
                FROM $tf
                WHERE anno=$anno AND deleted=0
                GROUP BY mes
                ORDER BY mes ASC")->getResult();
    }

    function get_resumen_por_cliente($anno, $mes = null) {
        $tf = $this->db->prefixTable('fac_facturas');
        $tc = $this->db->prefixTable('fac_clientes');
        $where_mes = $mes ? " AND $tf.mes=$mes" : "";
        return $this->db->query("SELECT $tc.nombre, $tc.id as cliente_id,
                SUM($tf.total) as total_facturado,
                SUM($tf.importe_cobrado) as total_cobrado
                FROM $tf
                LEFT JOIN $tc ON $tc.id = $tf.cliente_id
                WHERE $tf.anno=$anno $where_mes AND $tf.deleted=0
                GROUP BY $tf.cliente_id
                ORDER BY total_facturado DESC")->getResult();
    }

    function get_servicios_recurrentes_activos_total() {
        $t = $this->db->prefixTable('fac_cliente_servicios');
        return $this->db->query("SELECT SUM(importe) as total_recurrente, COUNT(*) as num_servicios
                FROM $t WHERE estado='activo' AND es_recurrente=1")->getRow();
    }

    function get_clientes_activos_count() {
        $t = $this->db->prefixTable('fac_clientes');
        return $this->db->query("SELECT COUNT(*) as total FROM $t WHERE estado='activo' AND deleted=0")->getRow()->total;
    }

    // ============================================================
    // GENERACIÓN AUTOMÁTICA DE FACTURAS RECURRENTES
    // ============================================================

    function generar_facturas_mes($anno, $mes, $created_by, $estado_inicial = 'borrador') {
        $servicios = $this->get_cliente_servicios(['estado' => 'activo', 'es_recurrente' => 1])->getResult();
        $generadas = 0;
        $errores = [];

        foreach ($servicios as $servicio) {
            // Verificar que no existe ya una factura para ese cliente/mes/año con ese servicio
            $existe = $this->_existe_linea_mes($servicio->cliente_id, $anno, $mes, $servicio->id);
            if ($existe) continue;

            // Verificar periodicidad
            if (!$this->_corresponde_generar($servicio->periodicidad, $anno, $mes, $servicio->fecha_inicio)) continue;

            // Buscar si ya hay factura en borrador para ese cliente/mes
            $factura_id = $this->_get_or_create_factura_mes($servicio->cliente_id, $anno, $mes, $created_by, $estado_inicial);

            if (!$factura_id) {
                $errores[] = "Error creando factura para cliente {$servicio->cliente_id}";
                continue;
            }

            // Añadir línea
            $forma_pago = $servicio->forma_pago ?: $servicio->cliente_forma_pago;
            $linea = [
                'factura_id'          => $factura_id,
                'cliente_servicio_id' => $servicio->id,
                'descripcion'         => $servicio->concepto,
                'tipo_linea'          => 'recurrente',
                'cantidad'            => 1,
                'precio_unitario'     => $servicio->importe,
                'iva_porcentaje'      => $servicio->iva_porcentaje,
            ];
            $this->save_linea($linea);
            $this->recalcular_totales_factura($factura_id);
            $generadas++;
        }

        return ['generadas' => $generadas, 'errores' => $errores];
    }

    private function _existe_linea_mes($cliente_id, $anno, $mes, $cliente_servicio_id) {
        $tf = $this->db->prefixTable('fac_facturas');
        $tl = $this->db->prefixTable('fac_factura_lineas');
        $r = $this->db->query("SELECT COUNT(*) as n FROM $tl
                INNER JOIN $tf ON $tf.id=$tl.factura_id
                WHERE $tf.cliente_id=$cliente_id AND $tf.anno=$anno AND $tf.mes=$mes
                AND $tl.cliente_servicio_id=$cliente_servicio_id AND $tf.deleted=0")->getRow();
        return $r->n > 0;
    }

    private function _corresponde_generar($periodicidad, $anno, $mes, $fecha_inicio) {
        if ($periodicidad === 'mensual') return true;
        if (!$fecha_inicio) return false;
        $fi = new \DateTime($fecha_inicio);
        $actual = new \DateTime("$anno-$mes-01");
        $diff = ($actual->format('Y') - $fi->format('Y')) * 12 + ($actual->format('m') - $fi->format('m'));
        if ($diff < 0) return false;
        if ($periodicidad === 'trimestral') return $diff % 3 === 0;
        if ($periodicidad === 'semestral') return $diff % 6 === 0;
        if ($periodicidad === 'anual') return $diff % 12 === 0;
        return false;
    }

    private function _get_or_create_factura_mes($cliente_id, $anno, $mes, $created_by, $estado_inicial) {
        $tf = $this->db->prefixTable('fac_facturas');
        $existing = $this->db->query("SELECT id FROM $tf WHERE cliente_id=$cliente_id AND anno=$anno AND mes=$mes AND estado_factura='borrador' AND deleted=0 LIMIT 1")->getRow();
        if ($existing) return $existing->id;

        // Obtener forma de pago del cliente
        $tc = $this->db->prefixTable('fac_clientes');
        $cliente = $this->db->query("SELECT forma_pago_default FROM $tc WHERE id=$cliente_id")->getRow();
        $forma_pago = $cliente ? $cliente->forma_pago_default : 'transferencia';

        $fecha_emision = date("$anno-" . str_pad($mes, 2, '0', STR_PAD_LEFT) . "-01");
        $data = [
            'cliente_id'     => $cliente_id,
            'anno'           => $anno,
            'mes'            => $mes,
            'fecha_emision'  => $fecha_emision,
            'fecha_vencimiento' => date('Y-m-d', strtotime($fecha_emision . ' +30 days')),
            'forma_pago'     => $forma_pago,
            'estado_factura' => $estado_inicial,
            'estado_cobro'   => 'pendiente',
            'subtotal'       => 0,
            'iva_total'      => 0,
            'total'          => 0,
            'generada_automaticamente' => 1,
            'created_by'     => $created_by,
        ];
        return $this->save_factura($data);
    }

    // ============================================================
    // AVISOS
    // ============================================================

    function get_avisos($options = array()) {
        $t = $this->db->prefixTable('fac_avisos');
        $where = "";
        $leido = $this->_get_clean_value($options, 'leido');
        if ($leido !== null && $leido !== '') $where .= " AND leido=$leido";
        return $this->db->query("SELECT * FROM $t WHERE 1=1 $where ORDER BY fecha_aviso DESC, id DESC")->getResult();
    }

    function marcar_aviso_leido($id) {
        $t = $this->db->prefixTable('fac_avisos');
        $this->db->query("UPDATE $t SET leido=1 WHERE id=$id");
    }

    // ============================================================
    // HELPER PRIVADO
    // ============================================================

    protected function _fac_build_set($data) {
        $parts = [];
        foreach ($data as $k => $v) {
            if ($v === null || $v === 'NULL') {
                $parts[] = "`$k`=NULL";
            } else {
                $escaped = $this->db->escapeString($v);
                $parts[] = "`$k`='$escaped'";
            }
        }
        return implode(', ', $parts);
    }


}