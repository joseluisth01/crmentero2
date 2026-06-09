<?php

namespace App\Controllers;

class Offer extends Security_Controller
{

    function __construct()
    {
        parent::__construct(false);
    }

    function index()
    {
        app_redirect("forbidden");
    }

    function preview($proposal_id = 0, $public_key = "")
    {
        if (!($proposal_id && $public_key)) {
            show_404();
        }

        validate_numeric_value($proposal_id);

        if (strlen($public_key) !== 10) {
            return false;
        }

        //check public key
        $proposal_info = $this->Proposals_model->get_one($proposal_id);
        if ($proposal_info->public_key !== $public_key) {
            show_404();
        }

        $view_data = array();

        $proposal_data = get_proposal_making_data($proposal_id);
        if (!$proposal_data) {
            show_404();
        }

        if (!isset($this->login_user->user_type) || (isset($this->login_user->user_type) && $this->login_user->user_type !== "staff")) {
            if ($proposal_info->status == "sent") {
                $this->Proposals_model->update_proposal_preview_activity($proposal_id);
                log_notification("proposal_preview_opened", array("proposal_id" => $proposal_id), isset($this->login_user->id) ? $this->login_user->id : "999999996");
            }
        }

        $view_data['contract_preview']      = prepare_proposal_view($proposal_data);
        $view_data['show_close_preview']    = true;
        $view_data['proposal_id']           = $proposal_id;
        $view_data['proposal_type']         = "public";
        $view_data['public_key']            = clean_data($public_key);
        $view_data["has_pdf_access"]        = true; // Acceso público — el cliente siempre puede descargar
        $view_data['proposal_items']        = $proposal_data['proposal_items'];
        $view_data['proposal_total_summary'] = $proposal_data['proposal_total_summary'];
        $view_data['proposal_info']         = $proposal_info;

        // Leer client_info — real o desde meta_data (cliente pendiente)
        $client_info = $proposal_data['client_info'];
        if (empty($client_info->id)) {
            $meta_raw = @unserialize($proposal_info->meta_data ?? '');
            if ($meta_raw && !empty($meta_raw['pending_client'])) {
                $pc = $meta_raw['pending_client'];
                $client_info = new \stdClass();
                $client_info->id           = 0;
                $client_info->company_name = $pc['company'] ?? '';
                $client_info->address      = $pc['address'] ?? '';
                $client_info->city         = $pc['city'] ?? '';
                $client_info->zip          = $pc['zip'] ?? '';
                $client_info->vat_number   = $pc['vat'] ?? '';
                $client_info->country      = '';
            }
        }
        $view_data['client_info'] = $client_info;

        return view("proposals/proposal_public_preview", $view_data);
    }

    // ─────────────────────────────────────────────────────────────────
    // HELPER PRIVADO: acciones post-aceptación
    // 1) Email de notificación a hola@tictac-comunicacion.es
    // 2) Creación automática de contrato en el cliente
    // ─────────────────────────────────────────────────────────────────
    private function _on_proposal_accepted($proposal_id)
    {
        $proposal_info = $this->Proposals_model->get_one($proposal_id);
        if (!$proposal_info->id) return;

        // ── Crear cliente pendiente si no hay client_id real ──────────────
        if (!$proposal_info->client_id || $proposal_info->client_id == 0) {
            $meta_raw = @unserialize($proposal_info->meta_data ?? '');
            if ($meta_raw && !empty($meta_raw['pending_client'])) {
                $pc = $meta_raw['pending_client'];
                try {
                    $client_data = array(
                        "company_name" => $pc['company'] ?? '',
                        "vat_number"   => $pc['vat'] ?? '',
                        "address"      => $pc['address'] ?? '',
                        "city"         => $pc['city'] ?? '',
                        "zip"          => $pc['zip'] ?? '',
                        "phone"        => $pc['phone'] ?? '',
                        "currency"     => get_setting("default_currency"),
                        "deleted"      => 0,
                        "is_lead"      => 0,
                    );
                    $client_data = clean_data($client_data);
                    $new_client_id = $this->Clients_model->ci_save($client_data);

                    if ($new_client_id) {
                        // Crear contacto principal
                        $name_parts = explode(' ', $pc['name'] ?? '', 2);
                        $contact_data = array(
                            "first_name"         => $name_parts[0] ?? ($pc['company'] ?? ''),
                            "last_name"          => $name_parts[1] ?? '',
                            "email"              => $pc['email'] ?? '',
                            "phone"              => $pc['phone'] ?? '',
                            "client_id"          => $new_client_id,
                            "is_primary_contact" => 1,
                            "user_type"          => "client",
                            "deleted"            => 0,
                        );
                        $contact_data = clean_data($contact_data);
                        $this->Users_model->ci_save($contact_data);

                        // Actualizar client_id en la propuesta via modelo
                        $db = \Config\Database::connect();
                        $proposals_table = $db->prefixTable('proposals');
                        $db->query("UPDATE {$proposals_table} SET client_id = {$new_client_id} WHERE id = {$proposal_id}");
                        $proposal_info->client_id = $new_client_id;

                        log_message('info', '[Tictac] _on_proposal_accepted: cliente ID=' . $new_client_id . ' creado y asignado a propuesta #' . $proposal_id);
                    }
                } catch (\Throwable $e) {
                    log_message('error', '[Tictac] _on_proposal_accepted: error creando cliente — ' . $e->getMessage());
                }
            }
        }

        $client_info   = $this->Clients_model->get_one($proposal_info->client_id);
        $proposal_ref  = get_proposal_id($proposal_id);
        $client_nombre = $client_info->company_name ?? 'Cliente #' . $proposal_info->client_id;
        $proposal_url  = get_uri('proposals/view/' . $proposal_id);

        // ── 1. Email de notificación interno ─────────────────────────────
        try {
            $subject = 'Presupuesto aceptado: ' . $proposal_ref . ' — ' . $client_nombre;
            $message = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;color:#333;background:#f5f5f5;margin:0;padding:0;">
<div style="max-width:580px;margin:30px auto;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.1);">
  <div style="background:#d72173;padding:28px 30px;text-align:center;">
    <h1 style="color:#fff;margin:0;font-size:22px;">Presupuesto Aceptado</h1>
  </div>
  <div style="padding:30px;">
    <p>El cliente <strong>' . htmlspecialchars($client_nombre) . '</strong> ha aceptado el presupuesto <strong>' . htmlspecialchars($proposal_ref) . '</strong>.</p>
    <p style="font-size:12px;color:#aaa;">Se ha creado automaticamente un contrato en borrador para este cliente.</p>
  </div>
  <div style="background:#1a1a1a;color:#aaa;text-align:center;padding:16px;font-size:12px;">
    Tictac Comunicacion Digital SL · hola@tictac-comunicacion.es
  </div>
</div></body></html>';

            send_app_mail('hola@tictac-comunicacion.es', $subject, $message);
            log_message('info', '[Tictac] _on_proposal_accepted: email enviado para propuesta #' . $proposal_id);
        } catch (\Throwable $e) {
            log_message('error', '[Tictac] _on_proposal_accepted: fallo email — ' . $e->getMessage());
            // Continuamos aunque el email falle
        }

        // ── 2. Crear contrato automático en borrador ──────────────────────
        try {
            $proposal_items = $this->Proposal_items_model->get_details(
                array("proposal_id" => $proposal_id)
            )->getResult();

            $contract_content = '';
            $default_template = get_setting("default_contract_template");
            if ($default_template) {
                $Contract_templates_model = model("App\\Models\\Contract_templates_model");
                $tpl = $Contract_templates_model->get_one($default_template);
                if ($tpl && $tpl->id) {
                    $contract_content = $tpl->template ?? '';
                }
            }

            $today       = get_my_local_time("Y-m-d");
            $valid_until = $proposal_info->valid_until
                           ? $proposal_info->valid_until
                           : date('Y-m-d', strtotime('+30 days'));

            $proposal_ref  = get_proposal_id($proposal_id);

            $contract_data = array(
                "title"                => 'Contrato - ' . $proposal_ref . ' - ' . $client_nombre,
                "client_id"            => $proposal_info->client_id,
                "project_id"           => 0,
                "contract_date"        => $today,
                "valid_until"          => $valid_until,
                "status"               => "draft",
                "note"                 => $proposal_info->note ?? '',
                "tax_id"               => $proposal_info->tax_id ?? 0,
                "tax_id2"              => $proposal_info->tax_id2 ?? 0,
                "discount_type"        => $proposal_info->discount_type ?: 'before_tax',
                "discount_amount"      => $proposal_info->discount_amount ?? 0,
                "discount_amount_type" => $proposal_info->discount_amount_type ?: 'percentage',
                "content"              => $contract_content,
                "public_key"           => make_random_string(),
                "accepted_by"          => 0,
                "staff_signed_by"      => 0,
                "meta_data"            => serialize(array()),
                "files"                => serialize(array()),
                "company_id"           => $proposal_info->company_id ?? get_default_company_id(),
                "deleted"              => 0,
            );

            log_message('info', '[Tictac] _on_proposal_accepted: intentando crear contrato con datos: ' . json_encode($contract_data));

            $contract_id = $this->Contracts_model->ci_save($contract_data);

            log_message('info', '[Tictac] ci_save contrato devolvió: ' . var_export($contract_id, true));

            if ($contract_id && $proposal_items) {
                foreach ($proposal_items as $item) {
                    $item_data = array(
                        "contract_id" => $contract_id,
                        "title"       => $item->title,
                        "description" => $item->description ?? '',
                        "quantity"    => $item->quantity,
                        "unit_type"   => $item->unit_type ?? '',
                        "rate"        => $item->rate,
                        "total"       => $item->total,
                        "item_id"     => $item->item_id ?? 0,
                    );
                    $this->Contract_items_model->ci_save($item_data);
                }

                if (method_exists($this->Contracts_model, 'update_contract_total_meta')) {
                    $this->Contracts_model->update_contract_total_meta($contract_id);
                }
            }

            log_message('info', '[Tictac] _on_proposal_accepted: contrato #' . $contract_id . ' creado para propuesta #' . $proposal_id);
        } catch (\Throwable $e) {
            log_message('error', '[Tictac] _on_proposal_accepted: fallo contrato — ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
        }
    }

    //update proposal status
    function update_proposal_status($proposal_id, $public_key, $status)
    {
        validate_numeric_value($proposal_id);
        if (!($proposal_id && $public_key && $status)) {
            show_404();
        }

        $proposal_info = $this->Proposals_model->get_one($proposal_id);
        if (!($proposal_info->id && $proposal_info->public_key === $public_key)) {
            show_404();
        }

        //client can only update the status once and the value should be either accepted or declined
        if ($status == "accepted" || $status == "declined") {
            $proposal_data = array("status" => $status);
            $proposal_id = $this->Proposals_model->ci_save($proposal_data, $proposal_id);

            //create notification
            if ($status == "accepted") {
                log_notification("proposal_accepted", array("proposal_id" => $proposal_id), isset($this->login_user->id) ? $this->login_user->id : "999999996");
                $this->_on_proposal_accepted($proposal_id);
                $this->session->setFlashdata("success_message", app_lang("proposal_accepted"));
            } else if ($status == "declined") {
                log_notification("proposal_rejected", array("proposal_id" => $proposal_id), isset($this->login_user->id) ? $this->login_user->id : "999999996");
                $this->session->setFlashdata("error_message", app_lang('proposal_rejected'));
            }
        }
    }

    //print proposal
    function print_proposal($proposal_id = 0, $public_key = "")
    {
        validate_numeric_value($proposal_id);
        if ($proposal_id && $public_key) {
            $view_data = get_proposal_making_data($proposal_id);

            //check public key
            $proposal_info = get_array_value($view_data, "proposal_info");
            if ($proposal_info->public_key !== $public_key) {
                show_404();
            }

            $view_data['proposal_preview'] = prepare_proposal_view($view_data);

            echo json_encode(array("success" => true, "print_view" => $this->template->view("proposals/print_proposal", $view_data)));
        } else {
            echo json_encode(array("success" => false, app_lang('error_occurred')));
        }
    }

    function accept_proposal_modal_form($proposal_id = 0, $public_key = "")
    {
        validate_numeric_value($proposal_id);
        if (!$proposal_id) {
            show_404();
        }

        $proposal_info = $this->Proposals_model->get_one($proposal_id);
        if (!$proposal_info->id) {
            show_404();
        }

        if ($public_key) {
            //public proposal
            if ($proposal_info->public_key !== $public_key) {
                show_404();
            }

            $view_data["show_info_fields"] = true;
        } else {
            //proposal preview, should be logged in client contact
            $this->init_permission_checker("proposal");
            $this->access_only_allowed_members_or_client_contact($proposal_info->client_id);
            if ($this->login_user->user_type === "client" && $this->login_user->client_id !== $proposal_info->client_id) {
                show_404();
            }

            $view_data["show_info_fields"] = false;
        }

        $view_data["model_info"] = $proposal_info;
        return $this->template->view('proposals/accept_proposal_modal_form', $view_data);
    }

    function accept_proposal()
    {
        try {
        $validation_array = array(
            "id" => "numeric|required",
            "public_key" => "required",
            "email" => "valid_email"
        );

        if (get_setting("add_signature_option_on_accepting_proposal")) {
            $validation_array["signature"] = "required";
        }

        $this->validate_submitted_data($validation_array);

        $proposal_id = $this->request->getPost("id");
        $proposal_info = $this->Proposals_model->get_one($proposal_id);
        if (!$proposal_info->id) {
            show_404();
        }

        $public_key = $this->request->getPost("public_key");
        if ($proposal_info->public_key !== $public_key) {
            show_404();
        }

        $name      = $this->request->getPost("name");
        $email     = $this->request->getPost("email");
        $signature = $this->request->getPost("signature");

        $meta_data     = array();
        $proposal_data = array();

        if ($signature) {
            $signature = explode(",", $signature);
            $signature = get_array_value($signature, 1);
            $signature = base64_decode($signature);
            $signature = serialize(move_temp_file("signature.jpg", get_setting("timeline_file_path"), "proposal", NULL, "", $signature));

            $meta_data["signature"]    = $signature;
            $meta_data["signed_date"]  = get_current_utc_time();
        }

        if ($name) {
            //from public proposal
            if (!$email) {
                show_404();
            }

            $meta_data["name"]  = clean_data($name);
            $meta_data["email"] = clean_data($email);
        } else {
            //from preview, should be logged in client contact
            $this->init_permission_checker("proposal");
            $this->access_only_allowed_members_or_client_contact($proposal_info->client_id);
            if ($this->login_user->user_type === "client" && $this->login_user->client_id !== $proposal_info->client_id) {
                show_404();
            }

            $proposal_data["accepted_by"] = $this->login_user->id;
        }

        // Preservar meta_data existente (pending_client, etc.) y añadir datos de firma encima
        $existing_meta = @unserialize($proposal_info->meta_data ?? '') ?: array();
        $meta_data = array_merge($existing_meta, $meta_data);

        $proposal_data["meta_data"] = serialize($meta_data);
        $proposal_data["status"]    = "accepted";

        if ($this->Proposals_model->ci_save($proposal_data, $proposal_id)) {
            log_notification("proposal_accepted", array("proposal_id" => $proposal_id), ($name ? "999999996" : $this->login_user->id));
            $this->_on_proposal_accepted($proposal_id);
            echo json_encode(array("success" => true, "message" => app_lang("proposal_accepted")));
        } else {
            echo json_encode(array("success" => false, "message" => app_lang("error_occurred")));
        }
        } catch (\Throwable $e) {
            log_message('error', '[Tictac] accept_proposal EXCEPCION: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
            echo json_encode(array("success" => false, "message" => "Error interno: " . $e->getMessage()));
        }
    }

    /**
     * Descarga el PDF desde la URL pública de la propuesta.
     * Redirige a proposals/download_pdf que usa el diseño Tictac.
     * El usuario ya tiene la clave pública validada arriba; proposals/download_pdf
     * aplica sus propios controles de acceso (check_proposal_pdf_access_for_clients).
     */
    function download_pdf($proposal_id = 0, $public_key = "")
    {
        validate_numeric_value($proposal_id);
        if (!$proposal_id) show_404();

        $proposal_info = $this->Proposals_model->get_one($proposal_id);
        if (!$proposal_info->id || $proposal_info->public_key !== $public_key) show_404();

        // Generar PDF sin instanciar Proposals (que requeriría login)
        \App\Controllers\Proposals::generate_pdf_static($proposal_id, 'download');
    }

    function download_signed_pdf($proposal_id = 0, $public_key = "")
    {
        validate_numeric_value($proposal_id);
        if (!$proposal_id) show_404();

        $proposal_info = $this->Proposals_model->get_one($proposal_id);
        if (!$proposal_info->id || $proposal_info->public_key !== $public_key) show_404();
        if ($proposal_info->status !== 'accepted') show_404();

        // Generar PDF firmado usando método estático (sin login)
        // El modo 'signed' lee la firma directamente de meta_data
        \App\Controllers\Proposals::generate_pdf_static($proposal_id, 'signed');
    }
}

/* End of file Offer.php */
/* Location: ./app/Controllers/Offer.php */