<?php

namespace App\Controllers;

class Contract extends Security_Controller {

    function __construct() {
        parent::__construct(false);
    }

    function index() {
        app_redirect("forbidden");
    }

    function preview($contract_id = 0, $public_key = "") {
        if (!($contract_id && $public_key)) {
            show_404();
        }

        validate_numeric_value($contract_id);

        if (strlen($public_key) !== 10) {
            show_404();
        }

        $contract_info = $this->Contracts_model->get_one($contract_id);
        if ($contract_info->public_key !== $public_key) {
            show_404();
        }

        $contract_data = get_contract_making_data($contract_id);
        if (!$contract_data) {
            show_404();
        }

        $view_data = array();
        $view_data['contract_info']          = $contract_info;
        $view_data['client_info']            = $contract_data['client_info'];
        $view_data['contract_items']         = $this->Contract_items_model->get_details(array('contract_id' => $contract_id))->getResult();
        $view_data['contract_total_summary'] = $this->Contracts_model->get_contract_total_summary($contract_id);
        $view_data['show_close_preview']     = false;
        $view_data['contract_id']            = $contract_id;
        $view_data['contract_type']          = "public";
        $view_data['public_key']             = clean_data($public_key);
        $view_data['has_pdf_access']         = true; // Acceso público — el cliente siempre puede descargar

        return view("contracts/contract_public_preview", $view_data);
    }

    //update contract status
    function update_contract_status($contract_id, $public_key, $status) {
        validate_numeric_value($contract_id);
        if (!($contract_id && $public_key && $status)) {
            show_404();
        }

        $contract_info = $this->Contracts_model->get_one($contract_id);
        if (!($contract_info->id && $contract_info->public_key === $public_key)) {
            show_404();
        }

        //client can only update the status once and the value should be either accepted or declined
        if ($status == "accepted" || $status == "declined") {
            $contract_data = array("status" => $status);
            $contract_id = $this->Contracts_model->ci_save($contract_data, $contract_id);

            //create notification
            if ($status == "accepted") {
                log_notification("contract_accepted", array("contract_id" => $contract_id), isset($this->login_user->id) ? $this->login_user->id : "999999996");
                $this->session->setFlashdata("success_message", app_lang("contract_accepted"));
            } else if ($status == "declined") {
                log_notification("contract_rejected", array("contract_id" => $contract_id), isset($this->login_user->id) ? $this->login_user->id : "999999996");
                $this->session->setFlashdata("error_message", app_lang('contract_rejected'));
            }
        }
    }

    //print contract
    function print_contract($contract_id = 0, $public_key = "") {
        validate_numeric_value($contract_id);
        if ($contract_id && $public_key) {
            $view_data = get_contract_making_data($contract_id);

            //check public key
            $contract_info = get_array_value($view_data, "contract_info");
            if ($contract_info->public_key !== $public_key) {
                show_404();
            }

            $view_data['contract_preview'] = prepare_contract_view($view_data);

            echo json_encode(array("success" => true, "print_view" => $this->template->view("contracts/print_contract", $view_data)));
        } else {
            echo json_encode(array("success" => false, app_lang('error_occurred')));
        }
    }

    function accept_contract_modal_form($contract_id = 0, $public_key = "") {
        validate_numeric_value($contract_id);
        if (!$contract_id) {
            show_404();
        }

        $contract_info = $this->Contracts_model->get_one($contract_id);
        if (!$contract_info->id) {
            show_404();
        }

        if ($public_key) {
            //public contract
            if ($contract_info->public_key !== $public_key) {
                show_404();
            }

            $view_data["show_info_fields"] = true;
        } else {
            //contract preview, should be logged in client contact or team member
            $this->init_permission_checker("contract");
            $this->can_edit_contracts($contract_id, true);
            if ($this->login_user->user_type === "client" && $this->login_user->client_id !== $contract_info->client_id) {
                show_404();
            }

            $view_data["show_info_fields"] = false;
        }

        $view_data["model_info"] = $contract_info;
        return $this->template->view('contracts/accept_contract_modal_form', $view_data);
    }

    function accept_contract() {
        $validation_array = array(
            "id" => "numeric|required",
            "public_key" => "required",
            "email" => "valid_email"
        );

        if (get_setting("add_signature_option_on_accepting_contract") || get_setting("add_signature_option_for_team_members")) {
            $validation_array["signature"] = "required";
        }

        $this->validate_submitted_data($validation_array);

        $contract_id = $this->request->getPost("id");
        $contract_info = $this->Contracts_model->get_one($contract_id);
        if (!$contract_info->id) {
            show_404();
        }

        $public_key = $this->request->getPost("public_key");
        if ($contract_info->public_key !== $public_key) {
            show_404();
        }

        $name = $this->request->getPost("name");
        $email = $this->request->getPost("email");
        $signature = $this->request->getPost("signature");

        $meta_data = $contract_info->meta_data ? unserialize($contract_info->meta_data) : array(); //check if ther has already some meta data
        $contract_data = array();

        if ($signature) {
            $signature = explode(",", $signature);
            $signature = get_array_value($signature, 1);
            $signature = base64_decode($signature);
            $signature = serialize(move_temp_file("signature.jpg", get_setting("timeline_file_path"), "contract", NULL, "", $signature));

            if (!$name && $this->login_user->user_type === "staff") {
                $meta_data["staff_signature"] = $signature;
                $meta_data["staff_signed_date"] = get_current_utc_time();
            } else {
                $meta_data["signature"] = $signature;
                $meta_data["signed_date"] = get_current_utc_time();
            }
        }

        if ($name) {
            //from public contract
            if (!$email) {
                show_404();
            }

            $meta_data["name"] = clean_data($name);
            $meta_data["email"] = clean_data($email);
        } else {
            //from preview, should be logged in client contact/team member
            $this->init_permission_checker("contract");
            $this->can_edit_contracts($contract_id, true);
            if ($this->login_user->user_type === "client" && $this->login_user->client_id !== $contract_info->client_id) {
                show_404();
            }

            if ($this->login_user->user_type === "staff") {
                $contract_data["staff_signed_by"] = $this->login_user->id;
            } else {
                $contract_data["accepted_by"] = $this->login_user->id;
            }
        }

        // Preservar meta_data existente (proyecto_tipo, contact_phone, etc.)
        $existing_meta = @unserialize($contract_info->meta_data ?? '') ?: array();
        $meta_data = array_merge($existing_meta, $meta_data);

        $contract_data["meta_data"] = serialize($meta_data);
        $contract_data["status"] = "accepted";

        if ($this->Contracts_model->ci_save($contract_data, $contract_id)) {
            log_notification("contract_accepted", array("contract_id" => $contract_id), ($name ? "999999996" : $this->login_user->id));

            // ── Notificación interna a Tictac ─────────────────────────
            try {
                $client_info   = $this->Clients_model->get_one($contract_info->client_id);
                $client_nombre = $client_info->company_name ?? 'Cliente #' . $contract_info->client_id;
                $contract_ref  = get_contract_id($contract_id);
                $firmante      = $name ?: ($meta_data['name'] ?? ($meta_data['email'] ?? 'Cliente'));
                $contract_url  = get_uri('contracts/view/' . $contract_id);

                $subj = 'Contrato aceptado: ' . $contract_ref . ' — ' . $client_nombre;
                $msg  = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;color:#333;background:#f5f5f5;margin:0;padding:0;">
<div style="max-width:580px;margin:30px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.1);">
  <div style="background:#d72173;padding:28px 30px;text-align:center;">
    <h1 style="color:#fff;margin:0;font-size:22px;">Contrato Aceptado</h1>
  </div>
  <div style="padding:30px;">
    <p>El cliente <strong>' . htmlspecialchars($client_nombre) . '</strong> ha aceptado el contrato <strong>' . htmlspecialchars($contract_ref) . '</strong>.</p>
    <p>Firmado por: <strong>' . htmlspecialchars($firmante) . '</strong></p>
    <p style="font-size:12px;color:#aaa;">Puedes ver el contrato en el CRM desde el siguiente enlace: <a href="' . $contract_url . '">' . $contract_url . '</a></p>
  </div>
  <div style="background:#1a1a1a;color:#aaa;text-align:center;padding:16px;font-size:12px;">
    Tictac Comunicacion Digital SL · hola@tictac-comunicacion.es
  </div>
</div></body></html>';

                send_app_mail('hola@tictac-comunicacion.es', $subj, $msg);
            } catch (\Throwable $e) {
                log_message('error', '[Tictac] accept_contract: fallo email notificacion — ' . $e->getMessage());
            }

            // ── Clonar proyectos tipo si se eligieron ──────────────────
            $proyecto_tipo_ids = $meta_data['proyecto_tipo_ids'] ?? array();
            if (empty($proyecto_tipo_ids) && !empty($meta_data['proyecto_tipo_id'])) {
                $proyecto_tipo_ids = array($meta_data['proyecto_tipo_id']);
            }
            $proyecto_nombres = $meta_data['proyecto_nombres'] ?? array(); // mapa [id => nombre]
            // Compatibilidad con formato antiguo
            if (empty($proyecto_nombres) && !empty($meta_data['proyecto_nombre'])) {
                $first_id = reset($proyecto_tipo_ids);
                if ($first_id) $proyecto_nombres[$first_id] = $meta_data['proyecto_nombre'];
            }

            if (!empty($proyecto_tipo_ids) && $contract_info->client_id) {
                foreach ($proyecto_tipo_ids as $ptid) {
                    $ptid   = intval($ptid);
                    if (!$ptid) continue;
                    $nombre = trim($proyecto_nombres[$ptid] ?? '');
                    try {
                        $this->_clonar_proyecto_tipo($ptid, $nombre, $contract_info->client_id, $contract_id);
                    } catch (\Throwable $e) {
                        log_message('error', '[Tictac] accept_contract: error clonando proyecto #' . $ptid . ' — ' . $e->getMessage());
                    }
                }
            }

            // ── Generar facturas automáticamente ──────────────────────
            try {
                $facturacion_model = model('App\Models\Facturacion_model', false);
                if ($facturacion_model) {
                    $resultado = $facturacion_model->generar_facturas_desde_contrato($contract_id);
                    log_message('info', '[Tictac] Facturas contrato #' . $contract_id . ': ' . json_encode($resultado));
                }
            } catch (\Throwable $e) {
                log_message('error', '[Tictac] Facturas contrato error: ' . $e->getMessage());
            }

            echo json_encode(array("success" => true, "message" => app_lang("contract_accepted")));
        } else {
            echo json_encode(array("success" => false, "message" => app_lang("error_occurred")));
        }
    }

    // ── Clona un proyecto tipo y lo asigna al cliente del contrato ────
    private function _clonar_proyecto_tipo($proyecto_tipo_id, $nombre_nuevo, $client_id, $contract_id) {
        $db  = \Config\Database::connect();
        $pt  = $db->prefixTable('projects');
        $pm  = $db->prefixTable('milestones');
        $pk  = $db->prefixTable('tasks');

        $orig = $db->query("SELECT * FROM {$pt} WHERE id={$proyecto_tipo_id} AND deleted=0 LIMIT 1")->getRow();
        if (!$orig) {
            log_message('error', '[Tictac] _clonar_proyecto_tipo: proyecto #' . $proyecto_tipo_id . ' no encontrado');
            return;
        }

        $client_info = $this->Clients_model->get_one($client_id);
        $title       = $nombre_nuevo ?: ($orig->title . ' — ' . ($client_info->company_name ?? 'Cliente'));
        $today       = date('Y-m-d');
        $now         = get_current_utc_time();

        // Deadline proporcional
        $deadline = null;
        if ($orig->deadline && $orig->start_date) {
            $diff     = max(0, floor((strtotime($orig->deadline) - strtotime($orig->start_date)) / 86400));
            $deadline = date('Y-m-d', strtotime($today . ' +' . $diff . ' days'));
        }

        // Insertar proyecto con los mismos campos que usa el CRM nativo
        $project_data = array(
            'title'        => $title,
            'description'  => $orig->description ?? '',
            'client_id'    => $client_id,
            'start_date'   => $today,
            'deadline'     => $deadline,
            'project_type' => $orig->project_type ?? 'client_project',
            'price'        => $orig->price ?? 0,
            'labels'       => $orig->labels ?? '',
            'status_id'    => 1,
            'created_date' => $now,
            'created_by'   => 1,
            'deleted'      => 0,
        );

        $db->table($db->prefixTable('projects'))->insert($project_data);
        $new_id = $db->insertID();

        if (!$new_id) {
            log_message('error', '[Tictac] _clonar_proyecto_tipo: INSERT proyecto falló');
            return;
        }

        // Clonar milestones
        $milestones = $db->query("SELECT * FROM {$pm} WHERE project_id={$proyecto_tipo_id} AND deleted=0")->getResult();
        $ms_map     = array();
        foreach ($milestones as $ms) {
            $mdd = null;
            if ($ms->due_date && $orig->start_date) {
                $diff = max(0, floor((strtotime($ms->due_date) - strtotime($orig->start_date)) / 86400));
                $mdd  = date('Y-m-d', strtotime($today . ' +' . $diff . ' days'));
            }
            $db->table($pm)->insert(array(
                'project_id'   => $new_id,
                'title'        => $ms->title ?? '',
                'color'        => $ms->color ?? '',
                'due_date'     => $mdd,
                'created_date' => $now,
                'deleted'      => 0,
            ));
            $ms_map[$ms->id] = $db->insertID();
        }

        // Clonar tareas (primero las principales, luego subtareas)
        $task_map = array();
        foreach (array(0, 1) as $subtask_pass) {
            $where    = $subtask_pass ? "parent_task_id!=0" : "parent_task_id=0";
            $tasks    = $db->query("SELECT * FROM {$pk} WHERE project_id={$proyecto_tipo_id} AND deleted=0 AND {$where}")->getResult();
            foreach ($tasks as $task) {
                $start_date_t = null;
                if ($task->start_date && $orig->start_date) {
                    $diff         = max(0, floor((strtotime($task->start_date) - strtotime($orig->start_date)) / 86400));
                    $start_date_t = date('Y-m-d', strtotime($today . ' +' . $diff . ' days'));
                }
                $deadline_t = null;
                if ($task->deadline && $orig->start_date) {
                    $diff       = max(0, floor((strtotime($task->deadline) - strtotime($orig->start_date)) / 86400));
                    $deadline_t = date('Y-m-d', strtotime($today . ' +' . $diff . ' days'));
                }
                $db->table($pk)->insert(array(
                    'project_id'     => $new_id,
                    'milestone_id'   => $ms_map[$task->milestone_id] ?? 0,
                    'parent_task_id' => $subtask_pass ? ($task_map[$task->parent_task_id] ?? 0) : 0,
                    'context'        => 'project',
                    'title'          => $task->title ?? '',
                    'description'    => $task->description ?? '',
                    'assigned_to'    => 0,
                    'status'         => 'to_do',
                    'status_id'      => 1,
                    'priority_id'    => 1,
                    'points'         => $task->points ?? 1,
                    'collaborators'  => '',
                    'blocking'       => '',
                    'blocked_by'     => '',
                    'ticket_id'      => 0,
                    'start_date'     => $start_date_t,
                    'deadline'       => $deadline_t,
                    'created_date'   => $now,
                    'created_by'     => 1,
                    'deleted'        => 0,
                ));
                $task_map[$task->id] = $db->insertID();
            }
        }

        // Guardar ID del nuevo proyecto en meta_data del contrato
        $ct     = $db->prefixTable('contracts');
        $cr     = $this->Contracts_model->get_one($contract_id);
        $mr     = @unserialize($cr->meta_data ?? '') ?: array();
        $mr['proyecto_clonado_id'] = $new_id;
        $ms2    = $db->escapeString(serialize($mr));
        $db->query("UPDATE {$ct} SET meta_data='{$ms2}' WHERE id={$contract_id}");

        log_message('info', "[Tictac] Proyecto tipo #{$proyecto_tipo_id} → nuevo #{$new_id} '{$title}' con " . count($task_map) . " tareas");
    }

    function file_preview($id = "", $key = "", $public_key = "") {
        validate_numeric_value($id);

        if (!$id) {
            show_404();
        }

        $contract_info = $this->Contracts_model->get_one($id);
        if ($contract_info->public_key !== $public_key) {
            show_404();
        }

        $files = unserialize($contract_info->files);
        $file = get_array_value($files, $key);

        $file_name = get_array_value($file, "file_name");
        $file_id = get_array_value($file, "file_id");
        $service_type = get_array_value($file, "service_type");

        $view_data["file_url"] = get_source_url_of_file($file, get_setting("timeline_file_path"));
        $view_data["is_image_file"] = is_image_file($file_name);
        $view_data["is_iframe_preview_available"] = is_iframe_preview_available($file_name);
        $view_data["is_google_preview_available"] = is_google_preview_available($file_name);
        $view_data["is_viewable_video_file"] = is_viewable_video_file($file_name);
        $view_data["is_google_drive_file"] = ($file_id && $service_type == "google") ? true : false;
        $view_data["is_iframe_preview_available"] = is_iframe_preview_available($file_name);

        return $this->template->view("contracts/file_preview", $view_data);
    }

    function download_pdf($contract_id = 0, $public_key = "") {
        validate_numeric_value($contract_id);
        if (!$contract_id) show_404();

        $contract_info = $this->Contracts_model->get_one($contract_id);
        if (!$contract_info->id || $contract_info->public_key !== $public_key) show_404();

        // Generar PDF sin instanciar Contracts (que requeriría login)
        \App\Controllers\Contracts::generate_contract_pdf_static($contract_id, 'download');
    }

    // ── Clonación de proyecto tipo — sin login, por API key ──────────
    // URL: /index.php/contract/clonar_proyecto_interno
    function clonar_proyecto_interno() {
        if (ob_get_level()) ob_clean();
        header('Content-Type: application/json; charset=utf-8');

        $key = $this->request->getPost('key') ?? $this->request->getVar('key');
        if ($key !== 'ea088539d42bf7e87dc7d4b171dfdcf7be3416322cb88eec6a504f701c4bd7dc') {
            echo json_encode(array('success' => false, 'message' => 'No autorizado'));
            return;
        }

        $proyecto_tipo_id = intval($this->request->getPost('proyecto_tipo_id'));
        $nombre_nuevo     = trim($this->request->getPost('nombre_nuevo') ?? '');
        $client_id        = intval($this->request->getPost('client_id'));
        $contract_id      = intval($this->request->getPost('contract_id'));

        if (!$proyecto_tipo_id || !$client_id) {
            echo json_encode(array('success' => false, 'message' => 'Parámetros incompletos'));
            return;
        }

        $orig = $this->Projects_model->get_one($proyecto_tipo_id);
        if (!$orig->id) {
            echo json_encode(array('success' => false, 'message' => 'Proyecto tipo no encontrado'));
            return;
        }

        $client_info = $this->Clients_model->get_one($client_id);
        $title       = $nombre_nuevo ?: ($orig->title . ' — ' . ($client_info->company_name ?? 'Cliente'));
        $today       = date('Y-m-d');
        $now         = get_current_utc_time();

        // Fechas del contrato para el proyecto
        $contract_raw  = $this->Contracts_model->get_one($contract_id);
        $project_start = $contract_raw->contract_date ?: $today;
        $project_end   = $contract_raw->valid_until   ?: null;

        $deadline = $project_end ?: null;

        $project_data = clean_data(array(
            'title'        => $title,
            'description'  => $orig->description ?? '',
            'client_id'    => $client_id,
            'start_date'   => $project_start,
            'deadline'     => $deadline,
            'project_type' => $orig->project_type ?? 'client_project',
            'price'        => $orig->price ?? 0,
            'labels'       => $orig->labels ?? '',
            'status_id'    => 1,
            'created_date' => $now,
            'created_by'   => 1,
        ));
        $new_id = $this->Projects_model->ci_save($project_data);

        if (!$new_id) {
            echo json_encode(array('success' => false, 'message' => 'Error creando proyecto'));
            return;
        }

        // Milestones
        $milestones = $this->Milestones_model->get_all_where(array('project_id' => $proyecto_tipo_id, 'deleted' => 0))->getResult();
        $ms_map = array();
        foreach ($milestones as $ms) {
            $d = (array) $ms; unset($d['id']);
            $d['project_id'] = $new_id;
            if ($ms->due_date && $orig->start_date) {
                $diff = max(0, floor((strtotime($ms->due_date) - strtotime($orig->start_date)) / 86400));
                $d['due_date'] = date('Y-m-d', strtotime($today . ' +' . $diff . ' days'));
            }
            $ms_map[$ms->id] = $this->Milestones_model->ci_save($d);
        }

        // Tareas (primero raíz, luego subtareas)
        $task_map = array();
        foreach (array(0, 1) as $pass) {
            $where = $pass
                ? array('project_id' => $proyecto_tipo_id, 'deleted' => 0, 'parent_task_id !=' => 0)
                : array('project_id' => $proyecto_tipo_id, 'deleted' => 0, 'parent_task_id'    => 0);
            foreach ($this->Tasks_model->get_all_where($where)->getResult() as $task) {
                $d = (array) $task; unset($d['id']);
                $d['project_id']     = $new_id;
                $d['milestone_id']   = $ms_map[$task->milestone_id] ?? 0;
                $d['parent_task_id'] = $pass ? ($task_map[$task->parent_task_id] ?? 0) : 0;
                $d['status']         = $task->status;
                $d['status_id']      = $task->status_id;
                $d['created_by']     = 1;
                $d['created_date']   = $now;
                $d['blocked_by']     = '';
                $d['blocking']       = '';
                $d['start_date']     = $project_start;
                $d['deadline']       = null;
                $d_clean = clean_data($d);
                $new_task_id = $this->Tasks_model->ci_save($d_clean);
                if ($new_task_id) $task_map[$task->id] = $new_task_id;
            }
        }

        // Dependencias
        foreach ($task_map as $old_id => $new_task_id) {
            $old_task = $this->Tasks_model->get_one($old_id);
            $upd = array();
            if ($old_task->blocked_by) {
                $bb_ids = explode(',', $old_task->blocked_by);
                $new_bb = array();
                foreach ($bb_ids as $bb_id) {
                    $bb_id = trim($bb_id);
                    if (isset($task_map[$bb_id])) $new_bb[] = $task_map[$bb_id];
                }
                if ($new_bb) $upd['blocked_by'] = implode(',', $new_bb);
            }
            if ($old_task->blocking) {
                $bl_ids = explode(',', $old_task->blocking);
                $new_bl = array();
                foreach ($bl_ids as $bl_id) {
                    $bl_id = trim($bl_id);
                    if (isset($task_map[$bl_id])) $new_bl[] = $task_map[$bl_id];
                }
                if ($new_bl) $upd['blocking'] = implode(',', $new_bl);
            }
            if ($upd) $this->Tasks_model->ci_save($upd, $new_task_id);
        }

        // Miembros
        $members = $this->Project_members_model->get_all_where(array('project_id' => $proyecto_tipo_id, 'deleted' => 0))->getResult();
        foreach ($members as $m) {
            $this->Project_members_model->save_member(array('project_id' => $new_id, 'user_id' => $m->user_id, 'is_leader' => $m->is_leader));
        }

        // Clonar custom fields del proyecto tipo
        $db_cf  = \Config\Database::connect();
        $cfv_t  = $db_cf->prefixTable('custom_field_values');
        $cf_rows = $db_cf->query("SELECT * FROM {$cfv_t} WHERE related_to_id={$proyecto_tipo_id} AND related_to_type='projects' AND deleted=0")->getResult();
        foreach ($cf_rows as $cfv) {
            // Campo ¿Proyecto Tipo? (id=12) → siempre 'No'
            $value = ($cfv->custom_field_id == 12) ? 'No' : $cfv->value;
            $v_e   = $db_cf->escapeString($value);
            // Verificar si ya existe para este nuevo proyecto
            $exists = $db_cf->query("SELECT id FROM {$cfv_t} WHERE related_to_id={$new_id} AND related_to_type='projects' AND custom_field_id={$cfv->custom_field_id} AND deleted=0 LIMIT 1")->getRow();
            if ($exists) {
                $db_cf->query("UPDATE {$cfv_t} SET value='{$v_e}' WHERE id={$exists->id}");
            } else {
                $db_cf->query("INSERT INTO {$cfv_t} (related_to_type, related_to_id, custom_field_id, value, deleted)
                    VALUES ('projects', {$new_id}, {$cfv->custom_field_id}, '{$v_e}', 0)");
            }
        }

        // Guardar en meta_data del contrato
        if ($contract_id) {
            $db  = \Config\Database::connect();
            $cr  = $this->Contracts_model->get_one($contract_id);
            $meta = @unserialize($cr->meta_data ?? '') ?: array();
            $meta['proyecto_clonado_id'] = $new_id;
            $ms2 = $db->escapeString(serialize($meta));
            $db->query("UPDATE {$db->prefixTable('contracts')} SET meta_data='{$ms2}' WHERE id={$contract_id}");
        }

        log_message('info', "[Tictac] clonar_proyecto_interno OK #{$new_id} tareas=" . count($task_map) . " miembros=" . count($members));
        echo json_encode(array('success' => true, 'id' => $new_id, 'tareas' => count($task_map), 'miembros' => count($members)));
    }

    // ── Firma SMS desde el preview público ───────────────────────────
    // URL: /index.php/contract/send_to_lleida_from_preview/ID/PUBLIC_KEY
    function send_to_lleida_from_preview($contract_id = 0, $public_key = "") {
        if (ob_get_level()) ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        validate_numeric_value($contract_id);

        $contract_info = $this->Contracts_model->get_one($contract_id);

        if (!$contract_info->id || $contract_info->public_key !== $public_key) {
            echo json_encode(array('success' => false, 'message' => 'No autorizado'));
            return;
        }
        if ($contract_info->status === 'accepted') {
            echo json_encode(array('success' => false, 'message' => 'Este contrato ya está firmado'));
            return;
        }

        $phone = trim($this->request->getPost('phone') ?? '');
        $name  = trim($this->request->getPost('name') ?? '');

        // Validar móvil español
        $phone_clean = preg_replace('/[\s\-\.]/', '', $phone);
        $phone_clean = preg_replace('/^\+34/', '', $phone_clean);
        $phone_clean = preg_replace('/^0034/', '', $phone_clean);
        if (!preg_match('/^[67]\d{8}$/', $phone_clean)) {
            echo json_encode(array('success' => false, 'message' => 'Introduce un número de móvil válido (debe empezar por 6 o 7)'));
            return;
        }
        $phone_e164 = '+34' . $phone_clean;

        // Generar PDF usando método estático (sin login)
        try {
            $pdf_path = \App\Controllers\Contracts::generate_contract_pdf_static($contract_id, 'save');
        } catch (\Throwable $e) {
            log_message('error', '[Lleida] from_preview PDF error: ' . $e->getMessage());
            echo json_encode(array('success' => false, 'message' => 'Error generando PDF: ' . $e->getMessage()));
            return;
        }

        $contract_ref = get_contract_id($contract_id);
        $creds        = array(
            'user'     => 'ticta-comunicacion',
            'apikey'   => 'nfb70Z9BhqgqpMnJAJRX0ga80tuTqQvW',
            'endpoint' => 'https://api.lleida.net/cs/v1/',
        );
        // Webhook URL GLOBAL — recibe todos los contratos, parsea contract_id del payload
        $webhook_url = get_uri('contract/lleida_webhook_global');

        // Obtener o crear config
        $config_id = $this->_lleida_get_config_id($creds, $webhook_url);
        if (!$config_id) {
            @unlink($pdf_path);
            echo json_encode(array('success' => false, 'message' => 'Error conectando con Click&Sign. Revisa el log.'));
            return;
        }

        $name_parts = explode(' ', $name, 2);
        $payload = array(
            "request"    => "START_SIGNATURE",
            "request_id" => 'preview-' . $contract_id . '-' . time(),
            "user"       => $creds['user'],
            "signature"  => array(
                "config_id"   => $config_id,
                "contract_id" => 'TICTAC-' . $contract_ref,
                "level"       => array(array(
                    "level_order"                            => 0,
                    "required_signatories_to_complete_level" => 1,
                    "signatories" => array(array(
                        "phone"       => $phone_e164,
                        "name"        => $name_parts[0] ?? 'Cliente',
                        "surname"     => $name_parts[1] ?? '',
                        "external_id" => "1",
                    )),
                )),
                "file" => array(array(
                    "filename"        => 'Contrato_' . $contract_ref . '.pdf',
                    "content"         => base64_encode(file_get_contents($pdf_path)),
                    "file_group"      => "contract_files",
                    "sign_on_landing" => "Y",
                )),
            ),
        );

        @unlink($pdf_path);

        $ch = curl_init($creds['endpoint'] . 'start_signature');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: x-api-key ' . $creds['apikey'],
        ));
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err  = curl_error($ch);
        curl_close($ch);

        log_message('info', '[Lleida] from_preview HTTP=' . $http_code . ' resp=' . $response);

        if ($curl_err) {
            echo json_encode(array('success' => false, 'message' => 'Error de red con Click&Sign: ' . $curl_err));
            return;
        }

        $result = @json_decode($response, true);

        if (($result['code'] ?? 0) == 200) {
            $signature_id = $result['signature']['signature_id'] ?? ('lleida_' . time());
            $db = \Config\Database::connect();
            $contracts_table = $db->prefixTable('contracts');
            $meta_raw = @unserialize($contract_info->meta_data ?? '') ?: array();
            $meta_raw['lleida_transaction_id'] = $signature_id;
            $meta_raw['lleida_phone']          = $phone_e164;
            $meta_raw['lleida_sent_at']        = date('Y-m-d H:i:s');
            $meta_serialized = $db->escapeString(serialize($meta_raw));
            $db->query("UPDATE {$contracts_table} SET meta_data='{$meta_serialized}' WHERE id={$contract_id}");

            echo json_encode(array('success' => true, 'message' => '¡Perfecto! Recibirás un SMS con el enlace para firmar.'));
        } else {
            $err = $result['status'] ?? $result['message'] ?? ('Error HTTP ' . $http_code . ': ' . $response);
            log_message('error', '[Lleida] from_preview error: ' . $err);
            echo json_encode(array('success' => false, 'message' => 'Error Click&Sign: ' . $err));
        }
    }

    // Helper: obtener o crear config_id en Lleida
    private function _lleida_get_config_id($creds, $webhook_url) {
        // Buscar config existente — verificar que tiene la URL de webhook correcta
        $ch = curl_init($creds['endpoint'] . 'get_config_list');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
            "request" => "GET_CONFIG_LIST",
            "user"    => $creds['user'],
            "status"  => "enabled",
        )));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: x-api-key ' . $creds['apikey'],
        ));
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $resp = @json_decode(curl_exec($ch), true);
        curl_close($ch);

        if (!empty($resp['config'])) {
            foreach ($resp['config'] as $cfg) {
                if (($cfg['name'] ?? '') === 'TictacContrato') {
                    // Verificar que el webhook es el global correcto
                    // Si no, marcar como disabled para forzar recreación
                    // Por simplicidad, siempre reutilizamos la config existente
                    // (el webhook se actualiza creando nueva config si cambia)
                    return $cfg['config_id'];
                }
            }
        }

        // Crear config
        $ch = curl_init($creds['endpoint'] . 'set_config');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array(
            "request" => "SET_CONFIG",
            "user"    => $creds['user'],
            "config"  => array(
                "name"                          => "TictacContrato",
                "expire_lapse"                  => 168,
                "default_sms_sender"            => "Tictac",
                "signatory_cb_url"              => $webhook_url,
                "registered_company_name"       => "Tictac Comunicacion Digital SL",
                "registered_company_vat_number" => "B09912478",
                "registered_langs"              => "ES",
                "lang"                          => "ES",
                "sms" => array(
                    array("registered" => "Y", "type" => "start", "sender" => "Tictac",
                          "text" => "Hola #name#, tiene un contrato pendiente de firma. Acceda aqui: #url#"),
                    array("registered" => "Y", "type" => "otp", "sender" => "Tictac",
                          "text" => "Su codigo de firma Tictac es: #otp#"),
                ),
                "landing" => array(
                    "signature_type" => "on_sign",
                    "signature_on_sign_required_elements" => array("otp" => "Y", "otp_length" => 6),
                    "enable_button"  => "on_open",
                    "landing_access_max_retries" => 5,
                    "declinable_signature" => "N",
                ),
            ),
        )));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: x-api-key ' . $creds['apikey'],
        ));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $resp2 = @json_decode(curl_exec($ch), true);
        curl_close($ch);

        log_message('info', '[Lleida] SET_CONFIG: ' . json_encode($resp2));
        return $resp2['config']['config_id'] ?? null;
    }
    // URL: /index.php/contract/lleida_webhook/ID/PUBLIC_KEY
    // Lleida envía POST application/x-www-form-urlencoded con:
    // signature_id, signatory_id, contract_id, status, status_date
    function lleida_webhook($contract_id = 0, $public_key = "") {
        validate_numeric_value($contract_id);

        // Loguear todo lo que llega para depuración
        $body      = file_get_contents('php://input');
        $post_data = $_POST;
        log_message('info', '[Lleida] Webhook recibido contrato #' . $contract_id
            . ' body=' . $body
            . ' POST=' . json_encode($post_data)
            . ' headers=' . json_encode(getallheaders()));

        $contract_info = $this->Contracts_model->get_one($contract_id);

        // Verificar que el contrato existe y la clave pública es correcta
        if (!$contract_info->id || $contract_info->public_key !== $public_key) {
            log_message('error', '[Lleida] Webhook: contrato no encontrado o clave incorrecta');
            http_response_code(200); // Responder 200 siempre para que Lleida no reintente
            echo 'OK';
            return;
        }

        // Leer status — Lleida puede enviar JSON o form-encoded
        if (!empty($post_data)) {
            $status       = strtolower($post_data['status'] ?? '');
            $signature_id = $post_data['signature_id'] ?? '';
            $signatory_id = $post_data['signatory_id'] ?? '';
        } else {
            $json_data    = @json_decode($body, true) ?: array();
            $status       = strtolower($json_data['status'] ?? '');
            $signature_id = $json_data['signature_id'] ?? '';
            $signatory_id = $json_data['signatory_id'] ?? '';
        }

        log_message('info', '[Lleida] Webhook status=' . $status . ' signature_id=' . $signature_id);

        // Solo actuar cuando firma completada
        // Lleida envía "signed" cuando el firmante ha firmado
        if ($status !== 'signed') {
            http_response_code(200);
            echo 'OK';
            return;
        }

        // Si ya está aceptado, no hacer nada
        if ($contract_info->status === 'accepted') {
            http_response_code(200);
            echo 'OK';
            return;
        }

        // Marcar contrato como aceptado
        $meta_raw = @unserialize($contract_info->meta_data ?? '') ?: array();
        $meta_raw['lleida_signed_at']    = date('Y-m-d H:i:s');
        $meta_raw['lleida_signature_id'] = $signature_id;
        $meta_raw['lleida_signatory_id'] = $signatory_id;

        $db = \Config\Database::connect();
        $contracts_table = $db->prefixTable('contracts');
        $meta_serialized = $db->escapeString(serialize($meta_raw));
        $db->query("UPDATE {$contracts_table} SET status='accepted', meta_data='{$meta_serialized}' WHERE id={$contract_id}");

        log_message('info', '[Lleida] Contrato #' . $contract_id . ' marcado como aceptado');

        // Notificación interna CRM
        try {
            log_notification("contract_accepted", array("contract_id" => $contract_id), "999999996");
        } catch (\Throwable $e) {
            log_message('error', '[Lleida] Webhook: error log_notification — ' . $e->getMessage());
        }

        // Email a hola@tictac-comunicacion.es
        try {
            $client_info   = $this->Clients_model->get_one($contract_info->client_id);
            $client_nombre = $client_info->company_name ?? 'Cliente #' . $contract_info->client_id;
            $contract_ref  = get_contract_id($contract_id);
            $firmante      = $meta_raw['lleida_phone'] ?? 'vía SMS';
            $contract_url  = get_uri('contracts/view/' . $contract_id);

            $subj = 'Contrato firmado por SMS: ' . $contract_ref . ' — ' . $client_nombre;
            $msg  = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;color:#333;background:#f5f5f5;margin:0;padding:0;">
<div style="max-width:580px;margin:30px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.1);">
  <div style="background:#d72173;padding:28px 30px;text-align:center;">
    <h1 style="color:#fff;margin:0;font-size:22px;">✅ Contrato Firmado por SMS</h1>
  </div>
  <div style="padding:30px;">
    <p>El cliente <strong>' . htmlspecialchars($client_nombre) . '</strong> ha firmado el contrato <strong>' . htmlspecialchars($contract_ref) . '</strong> a través de Click&Sign.</p>
    <p>Teléfono firmante: <strong>' . htmlspecialchars($firmante) . '</strong></p>
    <p style="font-size:12px;color:#aaa;">Ver contrato: <a href="' . $contract_url . '">' . $contract_url . '</a></p>
  </div>
  <div style="background:#1a1a1a;color:#aaa;text-align:center;padding:16px;font-size:12px;">
    Tictac Comunicacion Digital SL
  </div>
</div></body></html>';

            send_app_mail('hola@tictac-comunicacion.es', $subj, $msg);
            log_message('info', '[Lleida] Email de notificacion enviado para contrato #' . $contract_id);
        } catch (\Throwable $e) {
            log_message('error', '[Lleida] Webhook: error email — ' . $e->getMessage());
        }

        http_response_code(200);
        echo 'OK';
    }
    // ── Webhook GLOBAL Click&Sign — recibe todos los contratos ────────
    // URL: /index.php/contract/lleida_webhook_global
    function lleida_webhook_global() {
        // Limpiar cualquier output previo del framework
        if (ob_get_level()) ob_clean();
        header('Content-Type: text/plain');

        $body      = file_get_contents('php://input');
        $post_data = $_POST;
        log_message('info', '[Lleida] Webhook GLOBAL body=' . $body . ' POST=' . json_encode($post_data));

        // Lleida envía form-encoded: signature_id, signatory_id, contract_id, status, status_date
        $data = !empty($post_data) ? $post_data : (@json_decode($body, true) ?: array());

        $status       = strtolower($data['status'] ?? '');
        $lleida_contract_id = $data['contract_id'] ?? ''; // Ej: "TICTAC-CONTRATO #301"
        $signature_id = $data['signature_id'] ?? '';

        log_message('info', '[Lleida] Webhook GLOBAL status=' . $status . ' lleida_contract_id=' . $lleida_contract_id);

        // Solo actuar cuando firma completada
        if ($status !== 'signed') {
            http_response_code(200);
            echo 'OK';
            return;
        }

        // Extraer el ID numérico del contrato del campo contract_id de Lleida
        // Formato que enviamos: "TICTAC-CONTRATO #301"
        if (!preg_match('/#(\d+)/', $lleida_contract_id, $matches)) {
            log_message('error', '[Lleida] Webhook GLOBAL: no se pudo extraer contract_id de "' . $lleida_contract_id . '"');
            http_response_code(200);
            echo 'OK';
            return;
        }
        $contract_id = intval($matches[1]);

        $contract_info = $this->Contracts_model->get_one($contract_id);
        if (!$contract_info->id) {
            log_message('error', '[Lleida] Webhook GLOBAL: contrato #' . $contract_id . ' no encontrado');
            http_response_code(200);
            echo 'OK';
            return;
        }

        if ($contract_info->status === 'accepted') {
            log_message('info', '[Lleida] Webhook GLOBAL: contrato #' . $contract_id . ' ya aceptado');
            http_response_code(200);
            echo 'OK';
            return;
        }

        // Marcar como aceptado
        $meta_raw = @unserialize($contract_info->meta_data ?? '') ?: array();
        $meta_raw['lleida_signed_at']    = date('Y-m-d H:i:s');
        $meta_raw['lleida_signature_id'] = $signature_id;

        $db = \Config\Database::connect();
        $contracts_table = $db->prefixTable('contracts');
        $meta_serialized = $db->escapeString(serialize($meta_raw));
        $db->query("UPDATE {$contracts_table} SET status='accepted', meta_data='{$meta_serialized}' WHERE id={$contract_id}");

        log_message('info', '[Lleida] Contrato #' . $contract_id . ' marcado como ACEPTADO via webhook global');

        // ── Generar facturas automáticamente ──────────────────────────
        try {
            $facturacion_model = model('App\Models\Facturacion_model', false);
            if ($facturacion_model) {
                $resultado = $facturacion_model->generar_facturas_desde_contrato($contract_id);
                log_message('info', '[Tictac] Facturas SMS contrato #' . $contract_id . ': ' . json_encode($resultado));
            }
        } catch (\Throwable $e) {
            log_message('error', '[Tictac] Facturas SMS error: ' . $e->getMessage());
        }

        try {
            log_notification("contract_accepted", array("contract_id" => $contract_id), "999999996");
        } catch (\Throwable $e) {
            log_message('error', '[Lleida] log_notification error: ' . $e->getMessage());
        }

        // Email a hola@
        try {
            $client_info   = $this->Clients_model->get_one($contract_info->client_id);
            $client_nombre = $client_info->company_name ?? 'Cliente #' . $contract_info->client_id;
            $contract_ref  = get_contract_id($contract_id);
            $contract_url  = get_uri('contracts/view/' . $contract_id);
            $firmante      = $meta_raw['lleida_phone'] ?? 'vía SMS';

            $subj = 'Contrato firmado por SMS: ' . $contract_ref . ' — ' . $client_nombre;
            $msg  = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;background:#f5f5f5;">
<div style="max-width:580px;margin:30px auto;background:#fff;border-radius:10px;overflow:hidden;">
  <div style="background:#d72173;padding:28px;text-align:center;"><h1 style="color:#fff;margin:0;">✅ Contrato Firmado por SMS</h1></div>
  <div style="padding:30px;">
    <p>El cliente <strong>' . htmlspecialchars($client_nombre) . '</strong> ha firmado el contrato <strong>' . htmlspecialchars($contract_ref) . '</strong>.</p>
    <p>Teléfono: <strong>' . htmlspecialchars($firmante) . '</strong></p>
    <p><a href="' . $contract_url . '">' . $contract_url . '</a></p>
  </div>
</div></body></html>';

            send_app_mail('hola@tictac-comunicacion.es', $subj, $msg);
        } catch (\Throwable $e) {
            log_message('error', '[Lleida] email error: ' . $e->getMessage());
        }

        http_response_code(200);
        echo 'OK';
    }
}
/* Location: ./app/controllers/Contract.php */