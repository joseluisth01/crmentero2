<?php

namespace App\Controllers;

class Prospectos extends Security_Controller {

    function __construct() {
        parent::__construct();
    }

    function index() {
        $this->access_only_team_members();
        return $this->template->rander("prospectos/index", array());
    }

    // ── Datos para la tabla ──────────────────────────────────────────────────
    function list_data() {
        $this->access_only_team_members();

        $db    = \Config\Database::connect();
        $table = $db->prefixTable('prospectos');

        $search = $this->request->getPost('search_by');
        $estado = $this->request->getPost('estado');

        $where = "WHERE $table.deleted = 0";

        if ($estado) {
            $estado_clean = $db->escapeString($estado);
            $where .= " AND $table.estado = '$estado_clean'";
        }

        if ($search) {
            $search_clean = $db->escapeLikeString($search);
            $where .= " AND (
                $table.nombre   LIKE '%$search_clean%' ESCAPE '!'
                OR $table.email    LIKE '%$search_clean%' ESCAPE '!'
                OR $table.telefono LIKE '%$search_clean%' ESCAPE '!'
                OR $table.mensaje  LIKE '%$search_clean%' ESCAPE '!'
            )";
        }

        $sql    = "SELECT SQL_CALC_FOUND_ROWS $table.*
                   FROM $table
                   $where
                   ORDER BY $table.fecha_recepcion DESC";
        $result = $db->query($sql)->getResult();

        $data = array();
        foreach ($result as $row) {
            $data[] = $this->_make_row($row);
        }

        echo json_encode(array("data" => $data));
    }

    private function _make_row($row) {
        $estado_labels = array(
            'nuevo'             => "<span class='badge' style='background-color:#d72173;'>Nuevo</span>",
            'en_contacto'       => "<span class='badge' style='background-color:#C6D617;color:#333;'>En contacto</span>",
            'propuesta_enviada' => "<span class='badge' style='background-color:#7c3aed;'>Propuesta enviada</span>",
            'convertido'        => "<span class='badge' style='background-color:#27ae60;'>Convertido</span>",
            'perdido'           => "<span class='badge' style='background-color:#e74c3c;'>Perdido</span>",
        );

        $estado_badge    = isset($estado_labels[$row->estado]) ? $estado_labels[$row->estado] : $row->estado;
        $estado_selector = js_anchor($estado_badge, array(
            'title'      => '',
            'data-id'    => $row->id,
            'data-value' => $row->estado,
            'data-act'   => 'update-prospecto-estado',
        ));

        $ver = modal_anchor(
            get_uri("prospectos/ver/" . $row->id),
            "<i data-feather='eye' class='icon-16'></i>",
            array("class" => "action-option", "title" => "Ver detalle")
        );

        $eliminar = js_anchor(
            "<i data-feather='x' class='icon-16'></i>",
            array(
                'title'           => 'Eliminar',
                'class'           => 'delete',
                'data-id'         => $row->id,
                'data-action-url' => get_uri("prospectos/delete"),
                'data-action'     => 'delete-confirmation',
            )
        );

        $mensaje_corto = mb_strlen($row->mensaje) > 80
            ? mb_substr($row->mensaje, 0, 80) . '...'
            : $row->mensaje;

        return array(
            format_to_datetime($row->fecha_recepcion),
            clean_data($row->nombre),
            clean_data($row->email),
            clean_data($row->telefono),
            clean_data($row->web),
            clean_data($mensaje_corto),
            $estado_selector,
            $ver . $eliminar,
        );
    }

    // ── Datos para el kanban ─────────────────────────────────────────────────
    function kanban_data() {
        $this->access_only_team_members();

        $db     = \Config\Database::connect();
        $table  = $db->prefixTable('prospectos');
        $result = $db->query("
            SELECT id, nombre, email, telefono, web, mensaje, estado, fecha_recepcion
            FROM $table
            WHERE deleted = 0
            ORDER BY fecha_recepcion DESC
        ")->getResult();

        $data = array();
        foreach ($result as $row) {
            $data[] = array(
                'id'              => $row->id,
                'nombre'          => $row->nombre,
                'email'           => $row->email,
                'telefono'        => $row->telefono,
                'web'             => $row->web,
                'mensaje'         => $row->mensaje,
                'estado'          => $row->estado,
                'fecha_recepcion' => $row->fecha_recepcion,
            );
        }

        header('Content-Type: application/json');
        echo json_encode($data);
    }

    // ── Ver detalle (modal) ──────────────────────────────────────────────────
    function ver($id = 0) {
        validate_numeric_value($id);
        $this->access_only_team_members();

        $db    = \Config\Database::connect();
        $table = $db->prefixTable('prospectos');
        $row   = $db->query("SELECT * FROM $table WHERE id = $id AND deleted = 0")->getRow();

        if (!$row) {
            show_404();
        }

        // Notas: primero las pineadas, luego por fecha desc
        $tabla_notas = $db->prefixTable('prospectos_notas');
        $notas = $db->query("
            SELECT * FROM $tabla_notas
            WHERE prospecto_id = $id
            ORDER BY pinned DESC, created_at DESC
        ")->getResult();

        return $this->template->view("prospectos/ver", array(
            "prospecto" => $row,
            "notas"     => $notas,
        ));
    }

    // ── Guardar prospecto manual ─────────────────────────────────────────────
    function save_manual() {
        $this->access_only_team_members();

        $nombre        = $this->request->getPost('nombre');
        $email         = $this->request->getPost('email');
        $telefono      = $this->request->getPost('telefono');
        $web           = $this->request->getPost('web');
        $mensaje       = $this->request->getPost('mensaje');
        $pagina_origen = $this->request->getPost('pagina_origen');
        $notas         = $this->request->getPost('notas');
        $estado        = $this->request->getPost('estado');

        if (empty($nombre) && empty($email)) {
            echo json_encode(array("success" => false, "message" => "Introduce al menos un nombre o email."));
            return;
        }

        $estados_validos = array('nuevo', 'en_contacto', 'propuesta_enviada', 'convertido', 'perdido');
        if (!in_array($estado, $estados_validos)) $estado = 'nuevo';

        $db    = \Config\Database::connect();
        $table = $db->prefixTable('prospectos');
        $now   = get_current_utc_time();

        $db->query("
            INSERT INTO $table
                (nombre, email, telefono, web, asunto, mensaje,
                 pagina_origen, notas, estado, fecha_recepcion, created_at)
            VALUES (?, ?, ?, ?, 'Manual', ?, ?, ?, ?, ?, ?)
        ", array(
            clean_data($nombre),
            clean_data($email),
            clean_data($telefono),
            clean_data($web),
            clean_data($mensaje),
            clean_data($pagina_origen),
            clean_data($notas),
            $estado,
            $now,
            $now,
        ));

        echo json_encode(array("success" => true));
    }

    // ── Guardar edición completa de un prospecto ─────────────────────────────
    function save_edicion() {
        $this->access_only_team_members();

        $id = $this->request->getPost('id');
        validate_numeric_value($id);

        $campos_permitidos = array('nombre', 'email', 'telefono', 'web', 'mensaje', 'estado');
        $estados_validos   = array('nuevo', 'en_contacto', 'propuesta_enviada', 'convertido', 'perdido');

        $db     = \Config\Database::connect();
        $table  = $db->prefixTable('prospectos');
        $sets   = array();
        $valores = array();

        foreach ($campos_permitidos as $campo) {
            $valor = $this->request->getPost($campo);
            if ($valor === null) continue;
            if ($campo === 'estado' && !in_array($valor, $estados_validos)) continue;
            $sets[]    = "$campo = ?";
            $valores[] = clean_data($valor);
        }

        if (empty($sets)) {
            echo json_encode(array("success" => false, "message" => "Sin cambios."));
            return;
        }

        $valores[] = $id;
        $db->query("UPDATE $table SET " . implode(', ', $sets) . " WHERE id = ?", $valores);

        echo json_encode(array("success" => true));
    }

    // ── Guardar estado (kanban / tabla) ──────────────────────────────────────
    function save_estado() {
        $this->access_only_team_members();

        $id     = $this->request->getPost('id');
        $estado = $this->request->getPost('value');

        validate_numeric_value($id);

        $estados_validos = array('nuevo', 'en_contacto', 'propuesta_enviada', 'convertido', 'perdido');
        if (!in_array($estado, $estados_validos)) {
            echo json_encode(array("success" => false, "message" => "Estado no válido"));
            return;
        }

        $db    = \Config\Database::connect();
        $table = $db->prefixTable('prospectos');
        $db->query("UPDATE $table SET estado = ? WHERE id = ?", array($estado, $id));

        echo json_encode(array("success" => true));
    }

    // ── Guardar nota nueva ───────────────────────────────────────────────────
    function save_nota() {
        $this->access_only_team_members();

        $prospecto_id = $this->request->getPost('prospecto_id');
        $texto        = $this->request->getPost('texto');
        $pinned       = $this->request->getPost('pinned') ? 1 : 0;

        validate_numeric_value($prospecto_id);

        if (empty(trim($texto))) {
            echo json_encode(array("success" => false, "message" => "La nota no puede estar vacía."));
            return;
        }

        $db    = \Config\Database::connect();
        $table = $db->prefixTable('prospectos_notas');
        $now   = get_current_utc_time();

        $login_user = $this->login_user;
        $user_id    = $login_user ? $login_user->id : 0;

        $db->query("
            INSERT INTO $table (prospecto_id, texto, pinned, created_by, created_at)
            VALUES (?, ?, ?, ?, ?)
        ", array($prospecto_id, clean_data($texto), $pinned, $user_id, $now));

        $nueva_id = $db->insertID();

        echo json_encode(array(
            "success" => true,
            "nota"    => array(
                "id"         => $nueva_id,
                "texto"      => clean_data($texto),
                "pinned"     => $pinned,
                "created_at" => format_to_datetime($now),
            ),
        ));
    }

    // ── Editar texto de una nota ─────────────────────────────────────────────
    function update_nota() {
        $this->access_only_team_members();

        $id    = $this->request->getPost('id');
        $texto = $this->request->getPost('texto');

        validate_numeric_value($id);

        if (empty(trim($texto))) {
            echo json_encode(array("success" => false, "message" => "La nota no puede estar vacía."));
            return;
        }

        $db    = \Config\Database::connect();
        $table = $db->prefixTable('prospectos_notas');
        $now   = get_current_utc_time();

        $db->query("UPDATE $table SET texto = ?, updated_at = ? WHERE id = ?", array(
            clean_data($texto),
            $now,
            $id,
        ));

        echo json_encode(array(
            "success"    => true,
            "updated_at" => format_to_datetime($now),
        ));
    }

    // ── Toggle pin de una nota ───────────────────────────────────────────────
    function toggle_pin_nota() {
        $this->access_only_team_members();

        $id     = $this->request->getPost('id');
        $pinned = $this->request->getPost('pinned') ? 1 : 0;

        validate_numeric_value($id);

        $db    = \Config\Database::connect();
        $table = $db->prefixTable('prospectos_notas');
        $db->query("UPDATE $table SET pinned = ? WHERE id = ?", array($pinned, $id));

        echo json_encode(array("success" => true, "pinned" => $pinned));
    }

    // ── Borrar nota ──────────────────────────────────────────────────────────
    function delete_nota() {
        $this->access_only_team_members();

        $id = $this->request->getPost('id');
        validate_numeric_value($id);

        $db    = \Config\Database::connect();
        $table = $db->prefixTable('prospectos_notas');
        $db->query("DELETE FROM $table WHERE id = ?", array($id));

        echo json_encode(array("success" => true));
    }

    // ── Eliminar prospecto (soft delete) ─────────────────────────────────────
    function delete() {
        $this->access_only_team_members();

        $id = $this->request->getPost('id');
        validate_numeric_value($id);

        $db    = \Config\Database::connect();
        $table = $db->prefixTable('prospectos');
        $db->query("UPDATE $table SET deleted = 1 WHERE id = ?", array($id));

        echo json_encode(array("success" => true, "message" => app_lang('record_deleted')));
    }
}