<?php

namespace App\Controllers;

use App\Libraries\Dropdown_list;

class Contracts extends Security_Controller {

    function __construct() {
        parent::__construct();
        $this->init_permission_checker("contract");
    }

    /* load contract list view */

    function index($contract_id = 0) {
        validate_numeric_value($contract_id);
        $this->check_module_availability("module_contract");
        $view_data["custom_field_headers"] = $this->Custom_fields_model->get_custom_field_headers_for_table("contracts", $this->login_user->is_admin, $this->login_user->user_type);
        $view_data["custom_field_filters"] = $this->Custom_fields_model->get_custom_field_filters("contracts", $this->login_user->is_admin, $this->login_user->user_type);

        $view_data['contract_id'] = $contract_id;

        if ($this->login_user->user_type === "staff") {
            if (!$this->can_view_contracts()) {
                app_redirect("forbidden");
            }

            $view_data["can_edit_contracts"] = $this->can_edit_contracts();

            return $this->template->rander("contracts/index", $view_data);
        } else {
            //client view
            if (!$this->can_client_access("contract")) {
                app_redirect("forbidden");
            }

            $view_data["client_info"] = $this->Clients_model->get_one($this->login_user->client_id);
            $view_data['client_id'] = $this->login_user->client_id;
            $view_data['page_type'] = "full";

            return $this->template->rander("clients/contracts/client_portal", $view_data);
        }
    }

    /* load new contract modal */

    function modal_form() {
        $contract_id = $this->request->getPost('id');

        if (!$this->can_edit_contracts($contract_id)) {
            app_redirect("forbidden");
        }

        $this->validate_submitted_data(array(
            "id" => "numeric",
            "client_id" => "numeric",
            "project_id" => "numeric"
        ));

        $client_id = $this->request->getPost('client_id');
        $is_clone = $this->request->getPost('is_clone');

        $model_info = $this->Contracts_model->get_one($contract_id);

        if (!$this->_is_contract_editable($model_info, $is_clone)) {
            app_redirect("forbidden");
        }

        //here has a project id. now set the client from the project
        $project_id = $this->request->getPost('project_id');
        $proposal_id = $this->request->getPost('proposal_id');
        $view_data['proposal_id'] = $proposal_id;

        if ($project_id) {
            $client_id = $this->Projects_model->get_one($project_id)->client_id;
            $model_info->client_id = $client_id;
        } else if ($proposal_id) {
            $info = $this->Proposals_model->get_one($proposal_id);

            if ($info) {
                $model_info->contract_date = $info->proposal_date;
                $model_info->valid_until = $info->valid_until;
                $model_info->client_id = $info->client_id;
                $model_info->tax_id = $info->tax_id;
                $model_info->tax_id2 = $info->tax_id2;
                $model_info->discount_amount = $info->discount_amount;
                $model_info->discount_amount_type = $info->discount_amount_type;
                $model_info->discount_type = $info->discount_type;
                $model_info->content = $info->content;
            }
        }

        $view_data['model_info'] = $model_info;

        $project_client_id = $client_id;
        if ($model_info->client_id) {
            $project_client_id = $model_info->client_id;
        }

        //make the drodown lists
        $view_data['taxes_dropdown'] = array("" => "-") + $this->Taxes_model->get_dropdown_list(array("title"));

        $dropdown_list = new Dropdown_list($this);
        $view_data['clients_dropdown'] = $dropdown_list->get_clients_and_leads_id_and_text_dropdown();

        //don't show clients dropdown for lead's contract editing
        $client_info = $this->Clients_model->get_one($view_data['model_info']->client_id);
        if ($client_info->is_lead) {
            $client_id = $client_info->id;
        }

        $projects = $this->Projects_model->get_dropdown_list(array("title"), "id", array("client_id" => $project_client_id, "project_type" => "client_project"));
        $suggestion = array(array("id" => "", "text" => "-"));
        foreach ($projects as $key => $value) {
            $suggestion[] = array("id" => $key, "text" => $value);
        }
        $view_data['projects_suggestion'] = $suggestion;

        $view_data['client_id'] = $client_id;
        $view_data['project_id'] = $project_id;

        //clone contract data
        $view_data['is_clone'] = $is_clone;

        $view_data["custom_fields"] = $this->Custom_fields_model->get_combined_details("contracts", $view_data['model_info']->id, $this->login_user->is_admin, $this->login_user->user_type)->getResult();

        $view_data['companies_dropdown'] = $this->_get_companies_dropdown();
        if (!$model_info->company_id) {
            $view_data['model_info']->company_id = get_default_company_id();
        }

        return $this->template->view('contracts/modal_form', $view_data);
    }

    function save_view() {
        $id = $this->request->getPost("id");

        if (!$this->can_edit_contracts($id)) {
            app_redirect("forbidden");
        }

        $this->validate_submitted_data(array(
            "id" => "required|numeric"
        ));

        $contract_data = array(
            "content" => decode_ajax_post_data($this->request->getPost('view'))
        );

        $this->Contracts_model->ci_save($contract_data, $id);

        echo json_encode(array("success" => true, 'message' => app_lang('record_saved')));
    }

    /* add, edit or clone an contract */

function save() {
    $id = $this->request->getPost('id');

    if (!$this->can_edit_contracts($id)) {
        app_redirect("forbidden");
    }

    $this->validate_submitted_data(array(
        "id" => "numeric",
        "contract_project_id" => "numeric",
        "title" => "required",
        "contract_client_id" => "required|numeric",
        "contract_date" => "required",
        "valid_until" => "required"
    ));

    $client_id = $this->request->getPost('contract_client_id');
    $is_clone = $this->request->getPost('is_clone');

    if (!$this->_is_contract_editable($id, $is_clone)) {
        app_redirect("forbidden");
    }

    $target_path = get_setting("timeline_file_path");
    $files_data = move_files_from_temp_dir_to_permanent_dir($target_path, "contract");
    $new_files = unserialize($files_data);

    $contract_data = array(
        "client_id" => $client_id,
        "title" => $this->request->getPost('title'),
        "project_id" => $this->request->getPost('contract_project_id') ? $this->request->getPost('contract_project_id') : 0,
        "contract_date" => $this->request->getPost('contract_date'),
        "valid_until" => $this->request->getPost('valid_until'),
        "tax_id" => $this->request->getPost('tax_id') ? $this->request->getPost('tax_id') : 0,
        "tax_id2" => $this->request->getPost('tax_id2') ? $this->request->getPost('tax_id2') : 0,
        "company_id" => $this->request->getPost('company_id') ? $this->request->getPost('company_id') : get_default_company_id(),
        "note" => $this->request->getPost('contract_note')
    );

    if ($id) {
        $contract_info = $this->Contracts_model->get_one($id);
        $timeline_file_path = get_setting("timeline_file_path");
        $new_files = update_saved_files($timeline_file_path, $contract_info->files, $new_files);
    }

    $contract_data["files"] = serialize($new_files);

    if (!$id) {
        $contract_data["public_key"] = make_random_string();

        if (get_setting("default_contract_template")) {
            $Contract_templates_model = model("App\Models\Contract_templates_model");
            $contract_data["content"] = $Contract_templates_model->get_one(get_setting("default_contract_template"))->template;
        }
    }

    $proposal_id = $this->request->getPost('proposal_id');

    $main_contract_id = "";
    if (($is_clone && $id) || $proposal_id) {
        if ($is_clone && $id) {
            $main_contract_id = $id;
            $id = "";
        }

        $contract_data["discount_amount"] = $this->request->getPost('discount_amount') ? $this->request->getPost('discount_amount') : 0;
        $contract_data["discount_amount_type"] = $this->request->getPost('discount_amount_type') ? $this->request->getPost('discount_amount_type') : "percentage";
        $contract_data["discount_type"] = $this->request->getPost('discount_type') ? $this->request->getPost('discount_type') : "before_tax";
        $contract_data["content"] = $this->request->getPost('content') ? $this->request->getPost('content') : "";
        $contract_data["public_key"] = make_random_string();
    }

    $contract_id = $this->Contracts_model->ci_save($contract_data, $id);
    if ($contract_id) {

        if ($is_clone && $main_contract_id) {
            save_custom_fields("contracts", $contract_id, 1, "staff");

            $contract_items = $this->Contract_items_model->get_all_where(array("contract_id" => $main_contract_id, "deleted" => 0))->getResult();

            foreach ($contract_items as $contract_item) {
                $contract_item_data = (array) $contract_item;
                unset($contract_item_data["id"]);
                $contract_item_data['contract_id'] = $contract_id;
                $contract_item = $this->Contract_items_model->ci_save($contract_item_data);
            }
        } else {
            save_custom_fields("contracts", $contract_id, $this->login_user->is_admin, $this->login_user->user_type);
        }

        $copy_items_from_proposal = $this->request->getPost("copy_items_from_proposal");
        $this->_copy_related_items_to_contract($copy_items_from_proposal, $contract_id);

        echo json_encode(array("success" => true, "data" => $this->_row_data($contract_id), 'id' => $contract_id, 'message' => app_lang('record_saved')));
    } else {
        echo json_encode(array("success" => false, 'message' => app_lang('error_occurred')));
    }
}

    //update contract status
    function update_contract_status($contract_id, $status) {
        validate_numeric_value($contract_id);

        if ($contract_id && $status) {
            $contract_info = $this->Contracts_model->get_one($contract_id);

            if (!$this->can_edit_contracts($contract_id, true)) {
                app_redirect("forbidden");
            }

            if ($this->login_user->user_type == "client") {
                //updating by client
                //client can only update the status once and the value should be either accepted or declined
                if ($contract_info->status == "sent" && ($status == "accepted" || $status == "declined")) {

                    $contract_data = array("status" => $status);
                    if ($status == "accepted") {
                        $contract_data["accepted_by"] = $this->login_user->id;
                    }

                    $contract_id = $this->Contracts_model->ci_save($contract_data, $contract_id);

                    //create notification
                    if ($status == "accepted") {
                        log_notification("contract_accepted", array("contract_id" => $contract_id));
                    } else if ($status == "declined") {
                        log_notification("contract_rejected", array("contract_id" => $contract_id));
                    }
                }
            } else {
                //updating by team members
                if ($status == "accepted" || $status == "declined" || $status == "sent") {
                    $contract_data = array("status" => $status);
                    $contract_id = $this->Contracts_model->ci_save($contract_data, $contract_id);
                }
            }
        }
    }

    /* delete or undo an contract */

    function delete() {
        $id = $this->request->getPost('id');

        if (!$this->can_edit_contracts($id)) {
            app_redirect("forbidden");
        }

        $this->validate_submitted_data(array(
            "id" => "required|numeric"
        ));

        $contract_info = $this->Contracts_model->get_one($id);

        if ($this->Contracts_model->delete($id)) {
            //delete signature file
            $signer_info = @unserialize($contract_info->meta_data);
            if ($signer_info && is_array($signer_info)) {
                if (get_array_value($signer_info, "signature")) {
                    $signature_file = unserialize(get_array_value($signer_info, "signature"));
                    delete_app_files(get_setting("timeline_file_path"), $signature_file);
                }
                if (get_array_value($signer_info, "staff_signature")) {
                    $signature_file = unserialize(get_array_value($signer_info, "staff_signature"));
                    delete_app_files(get_setting("timeline_file_path"), $signature_file);
                }
            }

            //delete the files
            $file_path = get_setting("timeline_file_path");
            if ($contract_info->files) {
                $files = unserialize($contract_info->files);

                foreach ($files as $file) {
                    delete_app_files($file_path, array($file));
                }
            }

            echo json_encode(array("success" => true, 'message' => app_lang('record_deleted')));
        } else {
            echo json_encode(array("success" => false, 'message' => app_lang('record_cannot_be_deleted')));
        }
    }

    /* list of contracts, prepared for datatable  */

    function list_data($is_mobile = 0) {
        validate_numeric_value($is_mobile);

        if (!$this->can_view_contracts()) {
            app_redirect("forbidden");
        }

        $custom_fields = $this->Custom_fields_model->get_available_fields_for_table("contracts", $this->login_user->is_admin, $this->login_user->user_type);

        $options = array(
            "status" => $this->request->getPost("status"),
            "start_date" => $this->request->getPost("start_date"),
            "end_date" => $this->request->getPost("end_date"),
            "show_own_client_contract_user_id" => $this->show_own_client_contract_user_id(),
            "custom_fields" => $custom_fields,
            "custom_field_filter" => $this->prepare_custom_field_filter_values("contracts", $this->login_user->is_admin, $this->login_user->user_type)
        );

        $all_options = append_server_side_filtering_commmon_params($options);

        $result = $this->Contracts_model->get_details($all_options);


        //by this, we can handel the server side or client side from the app table prams.
        if (get_array_value($all_options, "server_side")) {
            $list_data = get_array_value($result, "data");
        } else {
            $list_data = $result->getResult();
            $result = array();
        }

        $result_data = array();
        foreach ($list_data as $data) {
            $result_data[] = $this->_make_row($data, $custom_fields, $is_mobile);
        }

        $result["data"] = $result_data;
        echo json_encode($result);
    }

    /* list of contract of a specific client, prepared for datatable  */

    function contract_list_data_of_client($client_id) {
        validate_numeric_value($client_id);

        if (!$this->can_view_contracts(0, $client_id)) {
            app_redirect("forbidden");
        }

        $custom_fields = $this->Custom_fields_model->get_available_fields_for_table("contracts", $this->login_user->is_admin, $this->login_user->user_type);

        $options = array("client_id" => $client_id, "status" => $this->request->getPost("status"), "custom_fields" => $custom_fields, "custom_field_filter" => $this->prepare_custom_field_filter_values("contracts", $this->login_user->is_admin, $this->login_user->user_type));

        if ($this->login_user->user_type == "client") {
            //don't show draft contracts to clients.
            $options["exclude_draft"] = true;
        }

        $list_data = $this->Contracts_model->get_details($options)->getResult();
        $getResult = array();
        foreach ($list_data as $data) {
            $getResult[] = $this->_make_row($data, $custom_fields);
        }
        echo json_encode(array("data" => $getResult));
    }

    /* return a row of contract list table */

    private function _row_data($id) {
        $custom_fields = $this->Custom_fields_model->get_available_fields_for_table("contracts", $this->login_user->is_admin, $this->login_user->user_type);

        $options = array("id" => $id, "custom_fields" => $custom_fields);
        $data = $this->Contracts_model->get_details($options)->getRow();
        return $this->_make_row($data, $custom_fields);
    }

    /* prepare a row of contract list table */

    private function _make_row($data, $custom_fields, $is_mobile = 0) {
        $contract_id = "";
        if ($this->can_edit_contracts()) {
            $contract_id = anchor(get_uri("contracts/view/" . $data->id), get_contract_id($data->id));
        } else {
            //for client
            $contract_id = anchor(get_uri("contracts/preview/" . $data->id), get_contract_id($data->id));
        }

        $contract_url = "";
        if ($this->can_edit_contracts()) {
            $contract_url = anchor(get_uri("contracts/view/" . $data->id), $data->title ?? get_contract_id($data->id));
        } else {
            $contract_url = anchor(get_uri("contracts/preview/" . $data->id), $data->title ?? get_contract_id($data->id));
        }

        $company_name = $data->company_name ?? '—';
        $client = anchor(get_uri("clients/view/" . $data->client_id), $company_name);
        if ($data->is_lead) {
            $client = anchor(get_uri("leads/view/" . $data->client_id), $company_name);
        }

        $contract_status = $this->_get_contract_status_label($data);

        if ($is_mobile) {
            $title_content = "
                            <div class='text-default'>
                                <div class='clearfix'>
                                    <span class='truncate-ellipsis w60p float-start'>
                                        <span class='fw-bold'>" . get_contract_id($data->id) . "</span>
                                    </span>
                                    <small class='text-off float-end'>" . to_currency($data->contract_value, $data->currency_symbol) . "</small>
                                </div>
                                <div class='clearfix'>
                                    <div class='float-start'>" . ($data->company_name ? $data->company_name : "-") . "</div>
                                    <div class='float-end'>" . format_to_date($data->contract_date, false) . "</div>
                                </div>
                                <div class='clearfix'>
                                    $contract_status
                                    <div class='float-end spinning-btn'></div>
                                </div>
                            </div>";

            $link = js_anchor($title_content, array(
                "class" => "box-label",
                "data-action-url" => get_uri("contracts/view/" . $data->id),
                "data-action" => "load_compact_view",
                "data-compact_view_id" => $data->id
            ));

            $contract_url = "<div class='box-wrapper mini-list-item'>" . $link . "</div>";
        }

        $row_data = array(
            $contract_id,
            $contract_url,
            $client,
            $data->project_title ? anchor(get_uri("projects/view/" . $data->project_id), $data->project_title) : "-",
            $data->contract_date,
            format_to_date($data->contract_date, false),
            $data->valid_until,
            format_to_date($data->valid_until, false),
            to_currency($data->contract_value, $data->currency_symbol),
            $contract_status,
        );

        foreach ($custom_fields as $field) {
            $cf_id = "cfv_" . $field->id;
            $row_data[] = $this->template->view("custom_fields/output_" . $field->field_type, array("value" => $data->$cf_id));
        }

        $edit = "";
        $contract_public_url = anchor(get_uri("contract/preview/" . $data->id . "/" . $data->public_key), "<i data-feather='external-link' class='icon-16'></i>", array("class" => "action-option", "title" => app_lang('contract') . " " . app_lang("url"), "target" => "_blank"));
        $delete = '<li role="presentation">' . js_anchor("<i data-feather='x' class='icon-16 mr5'></i>" . app_lang('delete'), array('title' => app_lang('delete_contract'), "class" => "delete dropdown-item", "data-id" => $data->id, "data-action-url" => get_uri("contracts/delete"), "data-action" => "delete-confirmation")) . '</li>';

        $action_options = "";
        if ($this->can_edit_contracts()) {
            if ($this->_is_contract_editable($data)) {
                $edit = '<li role="presentation">' . modal_anchor(get_uri("contracts/modal_form"), "<i data-feather='edit' class='icon-16 mr5'></i>" . app_lang('edit'), array("class" => "edit dropdown-item", "title" => app_lang('edit_contract'), "data-post-id" => $data->id)) . '</li>';
            }

            $actions = '<span class="dropdown inline-block">
                            <button class="action-option dropdown-toggle mt0 mb0" type="button" data-bs-toggle="dropdown" aria-expanded="true" data-bs-display="static">
                                <i data-feather="more-horizontal" class="icon-16"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" role="menu">' . $edit . $delete . '</ul>
                        </span>';

            $action_options = $contract_public_url . $actions;
        }

        $compact_view_btn = "";
        if (!$is_mobile) {
            $compact_view_btn = anchor(get_uri("contracts/compact_view/" . $data->id), "<i data-feather='sidebar' class='icon-16'></i>", array("title" => "", "class" => "action-option"));
        }

        $row_data[] = $compact_view_btn . $action_options;

        return $row_data;
    }

    //prepare contract status label 
    private function _get_contract_status_label($contract_info, $return_html = true, $extra_classes = "") {
        $contract_status_class = "bg-secondary";

        //don't show sent status to client, change the status to 'new' from 'sent'

        if ($this->login_user->user_type == "client") {
            if ($contract_info->status == "sent") {
                $contract_info->status = "new";
            } else if ($contract_info->status == "declined") {
                $contract_info->status = "rejected";
            }
        }

        if ($contract_info->status == "draft") {
            $contract_status_class = "bg-secondary";
        } else if ($contract_info->status == "declined" || $contract_info->status == "rejected") {
            $contract_status_class = "bg-danger";
        } else if ($contract_info->status == "accepted") {
            $contract_status_class = "bg-success";
        } else if ($contract_info->status == "sent") {
            $contract_status_class = "bg-primary";
        } else if ($contract_info->status == "new") {
            $contract_status_class = "bg-warning";
        }

        $contract_status = "<span class='mt0 badge $contract_status_class $extra_classes'>" . app_lang($contract_info->status) . "</span>";
        if ($return_html) {
            return $contract_status;
        } else {
            return $contract_info->status;
        }
    }

    /* load contract details view */

    function view($contract_id = 0) {
        validate_numeric_value($contract_id);

        if (!$this->can_view_contracts($contract_id)) {
            app_redirect("forbidden");
        }

        if ($contract_id) {

            $view_data = get_contract_making_data($contract_id);

            if ($view_data) {
                $view_data['contract_status_label'] = $this->_get_contract_status_label($view_data["contract_info"], true, "rounded-pill large");
                $view_data['contract_status'] = $this->_get_contract_status_label($view_data["contract_info"], false);

                $access_info = $this->get_access_info("invoice");
                $view_data["show_invoice_option"] = (get_setting("module_invoice") && $access_info->access_type == "all") ? true : false;

                $access_info = $this->get_access_info("estimate");
                $view_data["show_estimate_option"] = (get_setting("module_estimate") && $access_info->access_type == "all") ? true : false;

                $view_data["contract_id"] = $contract_id;
                $view_data["is_contract_editable"] = $this->_is_contract_editable($contract_id);

                $view_data["custom_field_headers_of_task"] = $this->Custom_fields_model->get_custom_field_headers_for_table("tasks", $this->login_user->is_admin, $this->login_user->user_type);

                $view_type = $this->request->getPost('view_type');
                if ($view_type == "contract_meta") {
                    echo json_encode(array(
                        "success" => true,
                        "top_bar" => $this->template->view("contracts/contract_top_bar",  $view_data),
                    ));
                } else if ($view_type == "compact_view") {
                    echo json_encode(array(
                        "success" => true,
                        "content" => $this->template->view("contracts/view",  $view_data),
                    ));
                } else {
                    return $this->template->rander("contracts/view", $view_data);
                }
            } else {
                show_404();
            }
        }
    }

    /* contract total section */

    private function _get_contract_total_view($contract_id = 0) {
        $view_data["contract_total_summary"] = $this->Contracts_model->get_contract_total_summary($contract_id);
        $view_data["contract_id"] = $contract_id;
        $view_data["is_contract_editable"] = $this->_is_contract_editable($contract_id);
        return $this->template->view('contracts/contract_total_section', $view_data);
    }

    /* load discount modal */

    function discount_modal_form() {
        $contract_id = $this->request->getPost('contract_id');

        if (!$this->can_edit_contracts($contract_id)) {
            app_redirect("forbidden");
        }

        $this->validate_submitted_data(array(
            "contract_id" => "required|numeric"
        ));

        if (!$this->_is_contract_editable($contract_id)) {
            app_redirect("forbidden");
        }

        $view_data['model_info'] = $this->Contracts_model->get_one($contract_id);

        return $this->template->view('contracts/discount_modal_form', $view_data);
    }

    /* save discount */

    function save_discount() {
        $contract_id = $this->request->getPost('contract_id');

        if (!$this->can_edit_contracts($contract_id)) {
            app_redirect("forbidden");
        }

        $this->validate_submitted_data(array(
            "contract_id" => "required|numeric",
            "discount_type" => "required",
            "discount_amount" => "numeric",
            "discount_amount_type" => "required"
        ));

        if (!$this->_is_contract_editable($contract_id)) {
            app_redirect("forbidden");
        }

        $data = array(
            "discount_type" => $this->request->getPost('discount_type'),
            "discount_amount" => $this->request->getPost('discount_amount'),
            "discount_amount_type" => $this->request->getPost('discount_amount_type')
        );

        $data = clean_data($data);

        $save_data = $this->Contracts_model->ci_save($data, $contract_id);
        if ($save_data) {
            echo json_encode(array("success" => true, "contract_total_view" => $this->_get_contract_total_view($contract_id), 'message' => app_lang('record_saved'), "contract_id" => $contract_id));
        } else {
            echo json_encode(array("success" => false, 'message' => app_lang('error_occurred')));
        }
    }

    /* load item modal */

    function item_modal_form() {
        $contract_id = $this->request->getPost('contract_id');

        if (!$this->can_edit_contracts($contract_id)) {
            app_redirect("forbidden");
        }

        $this->validate_submitted_data(array(
            "id" => "numeric"
        ));

        if (!$this->_is_contract_editable($contract_id)) {
            app_redirect("forbidden");
        }

        $view_data['model_info'] = $this->Contract_items_model->get_one($this->request->getPost('id'));
        if (!$contract_id) {
            $contract_id = $view_data['model_info']->contract_id;
        }
        $view_data['contract_id'] = $contract_id;
        return $this->template->view('contracts/item_modal_form', $view_data);
    }

    /* add or edit an contract item */

    function save_item() {
        $contract_id = $this->request->getPost('contract_id');

        if (!$this->can_edit_contracts($contract_id)) {
            app_redirect("forbidden");
        }

        $this->validate_submitted_data(array(
            "id" => "numeric",
            "contract_id" => "required|numeric"
        ));

        if (!$this->_is_contract_editable($contract_id)) {
            app_redirect("forbidden");
        }

        $id = $this->request->getPost('id');
        $rate = unformat_currency($this->request->getPost('contract_item_rate'));
        $quantity = unformat_currency($this->request->getPost('contract_item_quantity'));
        $contract_item_title = $this->request->getPost('contract_item_title');
        $item_id = $this->request->getPost('item_id');

        //check if the add_new_item flag is on, if so, add the item to libary. 
        $add_new_item_to_library = $this->request->getPost('add_new_item_to_library');
        if ($add_new_item_to_library) {
            $library_item_data = array(
                "title" => $contract_item_title,
                "description" => $this->request->getPost('contract_item_description'),
                "unit_type" => $this->request->getPost('contract_unit_type'),
                "rate" => unformat_currency($this->request->getPost('contract_item_rate'))
            );
            $item_id = $this->Items_model->ci_save($library_item_data);
        }

        $tipo_pago    = $this->request->getPost('tipo_pago') ?: 'mensual';
        $num_periodos = intval($this->request->getPost('num_periodos') ?: ($tipo_pago === 'unico' ? 1 : 12));
        if ($tipo_pago === 'unico') $num_periodos = 1;
 
        $contract_item_data = array(
            "contract_id" => $contract_id,
            "title" => $contract_item_title,
            "description" => $this->request->getPost('contract_item_description'),
            "quantity" => $quantity,
            "unit_type" => $this->request->getPost('contract_unit_type'),
            "rate" => unformat_currency($this->request->getPost('contract_item_rate')),
            "total" => $rate * $quantity,
            "item_id" => $item_id,
            "tipo_pago" => $tipo_pago,
            "num_periodos" => $num_periodos,
        );

        $contract_item_id = $this->Contract_items_model->ci_save($contract_item_data, $id);
        if ($contract_item_id) {
            $options = array("id" => $contract_item_id);
            $item_info = $this->Contract_items_model->get_details($options)->getRow();
            echo json_encode(array("success" => true, "contract_id" => $item_info->contract_id, "data" => $this->_make_item_row($item_info), "contract_total_view" => $this->_get_contract_total_view($item_info->contract_id), 'id' => $contract_item_id, 'message' => app_lang('record_saved')));
        } else {
            echo json_encode(array("success" => false, 'message' => app_lang('error_occurred')));
        }
    }

    /* delete or undo an contract item */

    function delete_item() {
        $id = $this->request->getPost('id');

        $this->validate_submitted_data(array(
            "id" => "required|numeric"
        ));

        $item_info = $this->Contract_items_model->get_one($id);

        if (!$this->can_edit_contracts($item_info->contract_id)) {
            app_redirect("forbidden");
        }
        if (!$this->_is_contract_editable($item_info->contract_id)) {
            app_redirect("forbidden");
        }

        if ($this->request->getPost('undo')) {
            if ($this->Contract_items_model->delete($id, true)) {
                $options = array("id" => $id);
                $item_info = $this->Contract_items_model->get_details($options)->getRow();
                echo json_encode(array("success" => true, "contract_id" => $item_info->contract_id, "data" => $this->_make_item_row($item_info), "contract_total_view" => $this->_get_contract_total_view($item_info->contract_id), "message" => app_lang('record_undone')));
            } else {
                echo json_encode(array("success" => false, app_lang('error_occurred')));
            }
        } else {
            if ($this->Contract_items_model->delete($id)) {
                $item_info = $this->Contract_items_model->get_one($id);
                echo json_encode(array("success" => true, "contract_id" => $item_info->contract_id, "contract_total_view" => $this->_get_contract_total_view($item_info->contract_id), 'message' => app_lang('record_deleted')));
            } else {
                echo json_encode(array("success" => false, 'message' => app_lang('record_cannot_be_deleted')));
            }
        }
    }

    /* list of contract items, prepared for datatable  */

    function item_list_data($contract_id = 0) {
        validate_numeric_value($contract_id);

        if (!$this->can_view_contracts($contract_id)) {
            app_redirect("forbidden");
        }

        $list_data = $this->Contract_items_model->get_details(array("contract_id" => $contract_id))->getResult();
        $getResult = array();
        foreach ($list_data as $data) {
            $getResult[] = $this->_make_item_row($data);
        }
        echo json_encode(array("data" => $getResult));
    }

    /* prepare a row of contract item list table */

    private function _make_item_row($data) {
        $move_icon = "";
        $desc_style = "";

        if ($this->_is_contract_editable($data->contract_id)) {
            $move_icon = "<div class='float-start move-icon'><i data-feather='menu' class='icon-16'></i></div>";
            $desc_style = "style='margin-left:30px'";
        }

        $item = "<div class='item-row strong mb5' data-id='$data->id'>$move_icon $data->title</div>";
        if ($data->description) {
            $item .= "<div $desc_style>" . custom_nl2br($data->description) . "</div>";
        }
        $type = $data->unit_type ? $data->unit_type : "";

        return array(
            $data->sort,
            $item,
            to_decimal_format($data->quantity) . " " . $type,
            to_currency($data->rate, $data->currency_symbol),
            to_currency($data->total, $data->currency_symbol),
            modal_anchor(get_uri("contracts/item_modal_form"), "<i data-feather='edit' class='icon-16'></i>", array("class" => "edit", "title" => app_lang('edit_contract'), "data-post-id" => $data->id, "data-post-contract_id" => $data->contract_id))
                . js_anchor("<i data-feather='x' class='icon-16'></i>", array('title' => app_lang('delete'), "class" => "delete", "data-id" => $data->id, "data-action-url" => get_uri("contracts/delete_item"), "data-action" => "delete"))
        );
    }

    /* prepare suggestion of contract item */

    function get_contract_item_suggestion() {
        $key = $this->request->getPost("q");
        $suggestion = array();

        $items = $this->Invoice_items_model->get_item_suggestion($key);

        foreach ($items as $item) {
            $suggestion[] = array("id" => $item->id, "text" => $item->title);
        }

        $suggestion[] = array("id" => "+", "text" => "+ " . app_lang("create_new_item"));

        echo json_encode($suggestion);
    }

    function get_contract_item_info_suggestion() {
        $item_id = $this->request->getPost("item_id");
        validate_numeric_value($item_id);

        $item = $this->Invoice_items_model->get_item_info_suggestion(array("item_id" => $item_id));
        if ($item) {
            $item->rate = $item->rate ? to_decimal_format($item->rate) : "";
            echo json_encode(array("success" => true, "item_info" => $item));
        } else {
            echo json_encode(array("success" => false));
        }
    }

    //view html is accessable to client only.
    function preview($contract_id = 0, $show_close_preview = false, $is_editor_preview = false) {
    validate_numeric_value($contract_id);

    $view_data = array();

    if ($contract_id) {

        $contract_data = get_contract_making_data($contract_id);
        $this->_check_contract_access_permission($contract_data);

        $contract_info = get_array_value($contract_data, "contract_info");
        $contract_data['contract_status_label'] = $this->_get_contract_status_label($contract_info);

        $view_data['contract_preview'] = prepare_contract_view($contract_data);
        $view_data['has_pdf_access'] = $this->check_contract_pdf_access_for_clients($this->login_user->user_type);
        $view_data['show_close_preview'] = $show_close_preview && $this->login_user->user_type === "staff" ? true : false;
        $view_data['contract_id'] = $contract_id;
        $view_data['can_edit_contracts'] = $this->can_edit_contracts($contract_id, true);

        if ($is_editor_preview) {
            // Redirigir al preview público Tictac en vez del nativo
            app_redirect('contract/preview/' . $contract_id . '/' . $contract_info->public_key);
            return;
        } else {
            return $this->template->rander("contracts/contract_preview", $view_data);
        }

    } else {
        show_404();
    }
}

    private function _check_contract_access_permission($contract_data) {
        //check for valid contract
        if (!$contract_data) {
            show_404();
        }

        //check for security
        $contract_info = get_array_value($contract_data, "contract_info");
        if ($this->login_user->user_type == "client") {
            if ($this->login_user->client_id != $contract_info->client_id || $contract_info->status === "draft" || !$this->can_client_access("contract")) {
                app_redirect("forbidden");
            }
        } else {
            if (!$this->can_view_contracts($contract_info->id)) {
                app_redirect("forbidden");
            }
        }
    }

    function send_contract_modal_form($contract_id) {
        validate_numeric_value($contract_id);

        if (!$this->can_edit_contracts($contract_id)) {
            app_redirect("forbidden");
        }

        if ($contract_id) {
            $options = array("id" => $contract_id);
            $contract_info = $this->Contracts_model->get_details($options)->getRow();
            $view_data['contract_info'] = $contract_info;

            $is_lead = $this->request->getPost('is_lead');
            if ($is_lead) {
                $contacts_options = array("user_type" => "lead", "client_id" => $contract_info->client_id);
            } else {
                $contacts_options = array("user_type" => "client", "client_id" => $contract_info->client_id);
            }

            $contacts = $this->Users_model->get_details($contacts_options)->getResult();

            $primary_contact_info = "";
            $contacts_dropdown = array();
            foreach ($contacts as $contact) {
                if ($contact->is_primary_contact) {
                    $primary_contact_info = $contact;
                    $contacts_dropdown[$contact->id] = $contact->first_name . " " . $contact->last_name . " (" . app_lang("primary_contact") . ")";
                }
            }

            foreach ($contacts as $contact) {
                if (!$contact->is_primary_contact) {
                    $contacts_dropdown[$contact->id] = $contact->first_name . " " . $contact->last_name;
                }
            }

            $view_data['contacts_dropdown'] = $contacts_dropdown;

            // Teléfonos de cada contacto para prellenar el campo SMS
            $contact_phones = array();
            foreach ($contacts as $contact) {
                $contact_phones[$contact->id] = $contact->phone ?? '';
            }
            $view_data['contact_phones'] = $contact_phones;

            // Proyectos tipo (custom field id=12, value='Si')
            $db = \Config\Database::connect();
            $view_data['proyectos_tipo'] = $db->query("
                SELECT p.id, p.title
                FROM {$db->prefixTable('projects')} p
                JOIN {$db->prefixTable('custom_field_values')} cfv ON cfv.related_to_id = p.id
                WHERE cfv.custom_field_id = 12
                AND cfv.value = 'Si'
                AND p.deleted = 0
                ORDER BY p.title ASC
            ")->getResult();

            $template_data = $this->get_send_contract_template($contract_id, 0, "", $contract_info, $primary_contact_info);
            $view_data['message'] = get_array_value($template_data, "message");
            $view_data['subject'] = get_array_value($template_data, "subject");
            $view_data['has_pdf_access'] = $this->check_contract_pdf_access_for_clients();

            return $this->template->view('contracts/send_contract_modal_form', $view_data);
        } else {
            show_404();
        }
    }

    function get_send_contract_template($contract_id = 0, $contact_id = 0, $return_type = "", $contract_info = "", $contact_info = "") {
        validate_numeric_value($contract_id);
        validate_numeric_value($contact_id);

        if (!$this->can_edit_contracts($contract_id)) {
            app_redirect("forbidden");
        }

        if (!$contract_info) {
            $options = array("id" => $contract_id);
            $contract_info = $this->Contracts_model->get_details($options)->getRow();
        }

        if (!$contact_info) {
            $contact_info = $this->Users_model->get_one($contact_id);
        }

        $contact_language = $contact_info->language;

        $email_template = $this->Email_templates_model->get_final_template("contract_sent", true);

        $parser_data["CONTRACT_ID"] = $contract_info->id;
        $parser_data["PROJECT_TITLE"] = $contract_info->project_title;
        $parser_data["CONTACT_FIRST_NAME"] = $contact_info->first_name;
        $parser_data["CONTACT_LAST_NAME"] = $contact_info->last_name;
        $parser_data["CONTRACT_URL"] = get_uri("contracts/preview/" . $contract_info->id);
        $parser_data["PUBLIC_CONTRACT_URL"] = get_uri("contract/preview/" . $contract_info->id . "/" . $contract_info->public_key);
        $parser_data['SIGNATURE'] = get_array_value($email_template, "signature_$contact_language") ? get_array_value($email_template, "signature_$contact_language") : get_array_value($email_template, "signature_default");
        $parser_data["LOGO_URL"] = get_logo_url();
        $parser_data["RECIPIENTS_EMAIL_ADDRESS"] = $contact_info->email;

        $parser = \Config\Services::parser();

        $message = get_array_value($email_template, "message_$contact_language") ? get_array_value($email_template, "message_$contact_language") : get_array_value($email_template, "message_default");
        $subject = get_array_value($email_template, "subject_$contact_language") ? get_array_value($email_template, "subject_$contact_language") : get_array_value($email_template, "subject_default");

        $message = $parser->setData($parser_data)->renderString($message);
        $subject = $parser->setData($parser_data)->renderString($subject);
        $message = htmlspecialchars_decode($message);
        $subject = htmlspecialchars_decode($subject);

        if ($return_type == "json") {
            echo json_encode(array("success" => true, "message_view" => $message));
        } else {
            return array(
                "message" => $message,
                "subject" => $subject
            );
        }
    }

    function send_contract() {
        try {
        $contract_id = $this->request->getPost('id');

        if (!$this->can_edit_contracts($contract_id)) {
            app_redirect("forbidden");
        }

        if (!$contract_id || !is_numeric($contract_id)) {
            echo json_encode(array('success' => false, 'message' => 'ID de contrato no válido'));
            return;
        }

        $contact_id = $this->request->getPost('contact_id');
        $cc         = $this->request->getPost('contract_cc');
        $custom_bcc = $this->request->getPost('contract_bcc');
        $attach_pdf = $this->request->getPost('attach_pdf');

        // ── Datos del contrato ────────────────────────────────────────
        $contract_data = get_contract_making_data($contract_id);
        if (!$contract_data) {
            echo json_encode(array('success' => false, 'message' => 'No se encontró el contrato'));
            return;
        }

        $contract_info  = $contract_data['contract_info'];
        $total_summary  = $this->Contracts_model->get_contract_total_summary($contract_id);
        $contact        = $this->Users_model->get_one($contact_id);
        $client_info    = $contract_data['client_info'];

        if (!$contact->email) {
            echo json_encode(array('success' => false, 'message' => 'El contacto no tiene email'));
            return;
        }

        $to = $contact->email;

        // ── Email HTML corporativo Tictac ─────────────────────────────
        $subject = 'Contrato para ' . ($client_info->company_name ?? 'su proyecto') . ' - Tictac Comunicación';

        $fecha_contrato  = format_to_date($contract_info->contract_date, false);
        $valido_hasta    = format_to_date($contract_info->valid_until, false);
        $total_str       = to_currency($total_summary->contract_total, $total_summary->currency_symbol);
        $nombre_contacto = htmlspecialchars($contact->first_name . ' ' . $contact->last_name);
        $contract_url    = get_uri('contract/preview/' . $contract_info->id . '/' . $contract_info->public_key);
        $contract_ref    = get_contract_id($contract_info->id);

        $message = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body{font-family:Arial,sans-serif;line-height:1.6;color:#333;margin:0;padding:0;background-color:#f5f5f5;}
        .email-container{max-width:600px;margin:20px auto;background:white;border-radius:10px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.1);}
        .header{background:#d72173;color:white;padding:40px 30px;text-align:center;}
        .header img{max-width:180px;height:auto;margin-bottom:15px;}
        .header h1{margin:0;font-size:24px;font-weight:600;}
        .content{padding:40px 30px;}
        .content p{margin:0 0 15px 0;}
        .resumen-box{background:#fff5f9;border-left:4px solid #d72173;padding:20px;margin:25px 0;border-radius:5px;}
        .resumen-box strong{color:#d72173;}
        .total-destacado{font-size:24px;color:#d72173;font-weight:bold;margin-top:10px;}
        .btn-cta{display:block;width:fit-content;margin:25px auto 10px;background-color:#d72173 !important;color:#ffffff !important;padding:14px 36px;border-radius:50px;text-decoration:none !important;font-weight:700;font-size:16px;text-align:center;border:2px solid #d72173 !important;}
        .btn-note{text-align:center;font-size:11px;color:#aaa;margin-bottom:10px;}
        .footer{background:#1a1a1a;color:white;padding:30px;text-align:center;font-size:13px;}
        .footer a{color:#d72173;text-decoration:none;}
        .contacto-info{margin-top:15px;line-height:1.8;}
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <img src="https://tictac-comunicacion.es/wp-content/uploads/2026/02/LOGO-1-2.png" alt="Tictac Comunicación">
            <h1>Tu Contrato Está Listo</h1>
        </div>
        <div class="content">
            <p>Estimado/a <strong>' . $nombre_contacto . '</strong>,</p>
            <p>Adjunto encontrarás el contrato de servicios con Tictac Comunicación Digital. Por favor, revísalo y acéptalo o recházalo desde el enlace que encontrarás a continuación.</p>
            <div class="resumen-box">
                <strong>📋 Resumen del Contrato</strong><br><br>
                <strong>Referencia:</strong> ' . $contract_ref . '<br>
                <strong>Fecha de emisión:</strong> ' . $fecha_contrato . '<br>
                <strong>Válido hasta:</strong> ' . $valido_hasta . '<br>
                <div class="total-destacado">Total: ' . $total_str . '</div>
            </div>
            <a href="' . $contract_url . '" class="btn-cta" target="_blank">Ver y Aceptar Contrato →</a>
            <p class="btn-note">Puedes aceptar o rechazar el contrato desde el enlace anterior</p>
            <p>Si tienes alguna duda, no dudes en contactarnos. Estamos encantados de atenderte.</p>
        </div>
        <div class="footer">
            <strong>Tictac Comunicación Digital SL</strong>
            <div class="contacto-info">
                📍 C. Cruz Conde, 19, 6º 5 · 14001 Córdoba<br>
                📞 <a href="tel:+34633335390">633 33 53 90</a><br>
                ✉ <a href="mailto:hola@tictac-comunicacion.es">hola@tictac-comunicacion.es</a><br>
                🌐 <a href="https://www.tictac-comunicacion.es" target="_blank">www.tictac-comunicacion.es</a>
            </div>
        </div>
    </div>
</body>
</html>';

        // ── PDF Tictac adjunto ────────────────────────────────────────
        $attachments  = array();
        $tmp_pdf_path = null;

        if ($attach_pdf) {
            try {
                $tmp_pdf_path = $this->_generate_tictac_contract_pdf($contract_id, 'save');
            } catch (\Throwable $e) {
                log_message('error', '[Tictac] send_contract: error PDF — ' . $e->getMessage());
            }

            if ($tmp_pdf_path && file_exists($tmp_pdf_path)) {
                if (filesize($tmp_pdf_path) / 1000000 > 10) {
                    @unlink($tmp_pdf_path);
                    echo json_encode(array("success" => false, 'message' => app_lang("attachment_size_is_too_large")));
                    return;
                }
                // Nombre limpio para el adjunto del email
                $client_info_mail = $this->Clients_model->get_one($contract_info->client_id);
                $client_name_mail = '';
                if (!empty($client_info_mail->company_name)) {
                    $client_name_mail = '_' . preg_replace('/[^a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\-]/u', '', $client_info_mail->company_name);
                    $client_name_mail = trim(str_replace(' ', '_', $client_name_mail));
                }
                $attachments[] = array(
                    "file_path" => $tmp_pdf_path,
                    "file_name" => 'Contrato' . $client_name_mail . '.pdf',
                );
            }
        }

        // ── BCC ───────────────────────────────────────────────────────
        $default_bcc = get_setting('send_contract_bcc_to');
        $bcc_emails  = "";
        if ($default_bcc && $custom_bcc) {
            $bcc_emails = $default_bcc . "," . $custom_bcc;
        } else if ($default_bcc) {
            $bcc_emails = $default_bcc;
        } else if ($custom_bcc) {
            $bcc_emails = $custom_bcc;
        }

        // ── Enviar ────────────────────────────────────────────────────
        if (send_app_mail($to, $subject, $message, array("attachments" => $attachments, "cc" => $cc, "bcc" => $bcc_emails))) {
            if ($tmp_pdf_path && file_exists($tmp_pdf_path)) {
                @unlink($tmp_pdf_path);
            }

            $contact_phone     = trim($this->request->getPost('contact_phone') ?? '');
            $proyecto_tipo_ids = $this->request->getPost('proyecto_tipo_ids') ?? array();
            $proyecto_nombres  = $this->request->getPost('proyecto_nombres') ?? array(); // mapa [id => nombre]

            if (!is_array($proyecto_tipo_ids)) {
                $proyecto_tipo_ids = $proyecto_tipo_ids ? array($proyecto_tipo_ids) : array();
            }
            $proyecto_tipo_ids = array_filter(array_map('intval', $proyecto_tipo_ids));

            log_message('info', '[Tictac] send_contract: contact_phone=' . $contact_phone . ' proyecto_tipo_ids=' . json_encode($proyecto_tipo_ids) . ' proyecto_nombres=' . json_encode($proyecto_nombres));

            if ($contact_phone || !empty($proyecto_tipo_ids)) {
                $db = \Config\Database::connect();
                $contracts_table = $db->prefixTable('contracts');
                $contract_raw    = $this->Contracts_model->get_one($contract_id);
                $meta_raw        = @unserialize($contract_raw->meta_data ?? '') ?: array();
                if ($contact_phone)             $meta_raw['contact_phone']     = $contact_phone;
                if (!empty($proyecto_tipo_ids)) $meta_raw['proyecto_tipo_ids'] = $proyecto_tipo_ids;
                if (!empty($proyecto_nombres))  $meta_raw['proyecto_nombres']  = $proyecto_nombres;
                $meta_serialized = $db->escapeString(serialize($meta_raw));
                $db->query("UPDATE {$contracts_table} SET meta_data='{$meta_serialized}' WHERE id={$contract_id}");
                log_message('info', '[Tictac] send_contract: meta_data guardado OK para contrato #' . $contract_id);
            }

            $status_data = array("status" => "sent", "last_email_sent_date" => get_my_local_time());
            if ($this->Contracts_model->ci_save($status_data, $contract_id)) {
                echo json_encode(array('success' => true, 'message' => app_lang("contract_sent_message"), "contract_id" => $contract_id));
            }
        } else {
            if ($tmp_pdf_path && file_exists($tmp_pdf_path)) {
                @unlink($tmp_pdf_path);
            }
            echo json_encode(array('success' => false, 'message' => app_lang('error_occurred')));
        }
        } catch (\Throwable $e) {
            log_message('error', '[Tictac] send_contract EXCEPCION: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
            echo json_encode(array('success' => false, 'message' => 'Error interno: ' . $e->getMessage()));
        }
    }

    //update the sort value for contract item
    function update_item_sort_values($id = 0) {
        validate_numeric_value($id);

        if (!$this->can_edit_contracts($id)) {
            app_redirect("forbidden");
        }

        $sort_values = $this->request->getPost("sort_values");
        if ($sort_values) {

            //extract the values from the comma separated string
            $sort_array = explode(",", $sort_values);

            //update the value in db
            foreach ($sort_array as $value) {
                $sort_item = explode("-", $value); //extract id and sort value

                $id = get_array_value($sort_item, 0);
                validate_numeric_value($id);

                $sort = get_array_value($sort_item, 1);
                validate_numeric_value($sort);

                $data = array("sort" => $sort);
                $this->Contract_items_model->ci_save($data, $id);
            }
        }
    }

    function editor($contract_id = 0) {
        validate_numeric_value($contract_id);

        if (!$this->can_edit_contracts($contract_id)) {
            app_redirect("forbidden");
        }

        $view_data['contract_info'] = $this->Contracts_model->get_details(array("id" => $contract_id))->getRow();
        return $this->template->view("contracts/contract_editor", $view_data);
    }

    /* prepare project dropdown based on this suggestion */

    function get_project_suggestion($client_id = 0) {
        validate_numeric_value($client_id);

        if (!$this->can_edit_contracts($client_id)) {
            app_redirect("forbidden");
        }

        $projects = $this->Projects_model->get_dropdown_list(array("title"), "id", array("client_id" => $client_id, "project_type" => "client_project"));
        $suggestion = array(array("id" => "", "text" => "-"));
        foreach ($projects as $key => $value) {
            $suggestion[] = array("id" => $key, "text" => $value);
        }
        echo json_encode($suggestion);
    }

    /* list of contract of a specific project, prepared for datatable  */

    function contract_list_data_of_project($project_id) {
        validate_numeric_value($project_id);

        if (!$this->can_view_contracts()) {
            app_redirect("forbidden");
        }

        $custom_fields = $this->Custom_fields_model->get_available_fields_for_table("contracts", $this->login_user->is_admin, $this->login_user->user_type);

        $options = array(
            "project_id" => $project_id,
            "status" => $this->request->getPost("status"),
            "custom_fields" => $custom_fields,
            "custom_field_filter" => $this->prepare_custom_field_filter_values("contracts", $this->login_user->is_admin, $this->login_user->user_type)
        );
        $list_data = $this->Contracts_model->get_details($options)->getResult();
        $result = array();
        foreach ($list_data as $data) {
            $result[] = $this->_make_row($data, $custom_fields);
        }
        echo json_encode(array("data" => $result));
    }

    //prevent editing of contract after certain state
    private function _is_contract_editable($_contract, $is_clone = 0) {
        if (get_setting("enable_contract_lock_state")) {
            $contract_info = is_object($_contract) ? $_contract : $this->Contracts_model->get_one($_contract);
            if (!$contract_info->id || $is_clone) {
                return true;
            }

            if ($contract_info->status != "accepted") {
                return true;
            }
        } else {
            return true;
        }
    }

    function download_pdf($contract_id = 0, $mode = "download", $user_language = "") {
        if (!$contract_id) {
            show_404();
        }

        if (!$this->check_contract_pdf_access_for_clients($this->login_user->user_type)) {
            show_404();
        }

        validate_numeric_value($contract_id);
        $contract_data = get_contract_making_data($contract_id);
        $this->_check_contract_access_permission($contract_data);

        $this->_generate_tictac_contract_pdf($contract_id, 'download');
    }

    public function _generate_tictac_contract_pdf($contract_id, $mode = 'download') {
        // ── Cargar TCPDF ─────────────────────────────────────────────────
        $tcpdf_path = APPPATH . '../app/ThirdParty/tcpdf/tcpdf.php';
        if (!file_exists($tcpdf_path)) {
            $tcpdf_path = APPPATH . '../dashboard/tcpdf/tcpdf.php';
        }
        if (!file_exists($tcpdf_path)) return null;
        require_once($tcpdf_path);
        require_once(APPPATH . '../app/Libraries/TictacContractPDF.php');

        // ── Obtener datos ────────────────────────────────────────────────
        $contract_data  = get_contract_making_data($contract_id);
        if (!$contract_data) return null;

        $contract_info  = $contract_data['contract_info'];
        $client_info    = $contract_data['client_info'];
        $items          = $this->Contract_items_model->get_details(array('contract_id' => $contract_id))->getResult();
        $total_summary  = $this->Contracts_model->get_contract_total_summary($contract_id);

        $brand_r = 215; $brand_g = 33; $brand_b = 115;

        // ── Helpers ──────────────────────────────────────────────────────
        $pdf_text_plain = function ($html) {
            if ($html === null) return '';
            $txt = strip_tags((string)$html);
            $txt = html_entity_decode($txt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            return trim(preg_replace("/[ \t]+/", " ", $txt));
        };

        $pdf_html_clean = function ($html) {
            if ($html === null || trim($html) === '' || trim($html) === '<p><br></p>') return '';
            $html = preg_replace('~<style[^>]*>.*?</style>~is', '', $html);
            $html = preg_replace('~<script[^>]*>.*?</script>~is', '', $html);
            $html = preg_replace('~<span[^>]*>~i', '', $html);
            $html = str_ireplace('</span>', '', $html);
            $html = strip_tags($html, '<p><strong><b><em><i><u><ul><ol><li>');
            return trim($html);
        };

        // ── Instanciar PDF ───────────────────────────────────────────────
        $pdf = new \TictacContractPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->brand_r = $brand_r;
        $pdf->brand_g = $brand_g;
        $pdf->brand_b = $brand_b;

        foreach ([
            APPPATH . '../assets/images/logoblanco.png',
            APPPATH . '../uploads/logoblanco.png',
            APPPATH . '../dashboard/assets/img/logoblanco.png',
        ] as $c) {
            if (file_exists($c)) { $pdf->logo_path = $c; break; }
        }

        $pdf->SetAutoPageBreak(true, 20);
        $pdf->SetCreator('Tictac Comunicación');
        $pdf->SetAuthor('Tictac Comunicación Digital SL');
        $pdf->SetTitle('Contrato ' . get_contract_id($contract_info->id));
        $pdf->AddPage();
        $pdf->SetMargins(15, 34, 15);
        $pdf->SetY(34);

        $pageW    = $pdf->getPageWidth();
        $contentW = $pageW - 30;
        $colW     = 85;
        $startX   = 15;
        $startY   = $pdf->GetY();

        // ── Cajas info ───────────────────────────────────────────────────
        $cajaH = 42;
        $pdf->SetFillColor(250, 250, 250);
        $pdf->SetDrawColor(230, 230, 230);
        $pdf->Rect($startX, $startY, $colW, $cajaH, 'DF');
        $pdf->SetFillColor($brand_r, $brand_g, $brand_b);
        $pdf->Rect($startX, $startY, 3, $cajaH, 'F');
        $pdf->SetXY($startX + 7, $startY + 5);
        $pdf->SetTextColor($brand_r, $brand_g, $brand_b);
        $pdf->SetFont('Helvetica', 'B', 7.5);
        $pdf->Cell($colW - 10, 4, strtoupper('Datos del Contrato'), 0, 1, 'L');
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->SetLineWidth(0.2);
        $pdf->Line($startX + 7, $pdf->GetY() + 1, $startX + $colW - 3, $pdf->GetY() + 1);
        $pdf->Ln(4);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetX($startX + 7);
        $pdf->SetFont('Helvetica', 'B', 8); $pdf->Cell(28, 5, 'Referencia:', 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 8);  $pdf->Cell(0, 5, get_contract_id($contract_info->id), 0, 1, 'L');
        $pdf->SetX($startX + 7);
        $pdf->SetFont('Helvetica', 'B', 8); $pdf->Cell(28, 5, 'Fecha:', 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 8);  $pdf->Cell(0, 5, !empty($contract_info->contract_date) ? date('d/m/Y', strtotime($contract_info->contract_date)) : '', 0, 1, 'L');
        $pdf->SetX($startX + 7);
        $pdf->SetFont('Helvetica', 'B', 8); $pdf->Cell(28, 5, 'Válido hasta:', 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 8);  $pdf->Cell(0, 5, !empty($contract_info->valid_until) ? date('d/m/Y', strtotime($contract_info->valid_until)) : '', 0, 1, 'L');

        $rightX    = $startX + $colW + 5;
        $rightColW = $colW;
        $labelW    = 22;
        $valueW    = $rightColW - $labelW - 10;

        $campos = [];
        $campos[] = ['Empresa:', $client_info->company_name ?? ''];
        if (!empty($client_info->address))  $campos[] = ['Dirección:', $client_info->address];
        $ciudad = trim(($client_info->city ?? '') . (!empty($client_info->zip) ? ', ' . $client_info->zip : ''));
        if ($ciudad && $ciudad !== ',') $campos[] = ['Ciudad:', $ciudad];
        if (!empty($client_info->vat_number)) $campos[] = ['CIF/NIF:', $client_info->vat_number];

        $cajaClienteH = max($cajaH, 16 + count($campos) * 5 + 4);
        $pdf->SetFillColor(250, 250, 250);
        $pdf->SetDrawColor(230, 230, 230);
        $pdf->Rect($rightX, $startY, $rightColW, $cajaClienteH, 'DF');
        $pdf->SetFillColor($brand_r, $brand_g, $brand_b);
        $pdf->Rect($rightX, $startY, 3, $cajaClienteH, 'F');
        $pdf->SetXY($rightX + 7, $startY + 5);
        $pdf->SetTextColor($brand_r, $brand_g, $brand_b);
        $pdf->SetFont('Helvetica', 'B', 7.5);
        $pdf->Cell($rightColW - 10, 4, strtoupper('Información del Cliente'), 0, 1, 'L');
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->SetLineWidth(0.2);
        $pdf->Line($rightX + 7, $pdf->GetY() + 1, $rightX + $rightColW - 3, $pdf->GetY() + 1);
        $pdf->Ln(4);
        foreach ($campos as $campo) {
            $curY = $pdf->GetY();
            $pdf->SetXY($rightX + 7, $curY);
            $pdf->SetTextColor(80, 80, 80);
            $pdf->SetFont('Helvetica', 'B', 8);
            $pdf->Cell($labelW, 5, $campo[0], 0, 0, 'L');
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetXY($rightX + 7 + $labelW, $curY);
            $pdf->MultiCell($valueW, 5, $campo[1] ?? '', 0, 'L', false, 1);
        }

        $pdf->SetY(max($pdf->GetY(), $startY + $cajaH));
        $pdf->SetY($pdf->GetY() + 8);

        // ── Título sección ───────────────────────────────────────────────
        $lineY = $pdf->GetY();
        $pdf->SetDrawColor(230, 230, 230);
        $pdf->SetLineWidth(0.3);
        $pdf->Line(15, $lineY, $pageW - 15, $lineY);
        $pdf->SetFillColor($brand_r, $brand_g, $brand_b);
        $pdf->Circle(15, $lineY, 0.8, 0, 360, 'F');
        $pdf->SetY($lineY + 5);
        $pdf->SetX(15);
        $pdf->SetFont('Helvetica', 'B', 13);
        $pdf->SetTextColor(30, 30, 30);
        $pdf->Cell($contentW, 7, 'Servicios Contratados', 0, 1, 'L');
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->SetX(15);
        $pdf->Cell($contentW, 5, 'Servicios incluidos en este contrato  ·  Precios sin IVA (' . number_format($total_summary->tax_percentage ?? 21, 0) . '%)', 0, 1, 'L');
        $pdf->SetY($pdf->GetY() + 5);

        // ── Tabla artículos ──────────────────────────────────────────────
        $cArticulo = 96; $cCantidad = 28; $cTarifa = 28; $cTotal = 28;
        $tableW = $contentW; // 180mm — ocupa todo el ancho

        $printHeader = function () use ($pdf, $cArticulo, $cCantidad, $cTarifa, $cTotal, $tableW, $brand_r, $brand_g, $brand_b) {
            $hy = $pdf->GetY();
            $pdf->SetFillColor($brand_r, $brand_g, $brand_b);
            $pdf->Rect(15, $hy, $tableW, 7, 'F');
            $pdf->SetXY(18, $hy + 0.8);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('Helvetica', 'B', 7.5);
            $pdf->Cell($cArticulo - 3, 6, 'SERVICIO / DESCRIPCIÓN', 0, 0, 'L');
            $pdf->Cell($cCantidad, 6, 'CANT.', 0, 0, 'C');
            $pdf->Cell($cTarifa, 6, 'PRECIO', 0, 0, 'R');
            $pdf->Cell($cTotal - 3, 6, 'TOTAL', 0, 1, 'R');
            $pdf->SetTextColor(51, 51, 51);
        };
        $printHeader();

        $rowAlt = false;
        foreach ($items as $item) {
            $cantidad = floatval($item->quantity ?? 0);
            $precio   = floatval($item->rate ?? 0);
            $total    = $cantidad * $precio;
            $nombre   = $pdf_text_plain($item->title ?? '');
            $descClean = $pdf_html_clean($item->description ?? '');
            $descPlain = $pdf_text_plain($item->description ?? '');
            $hayDesc  = trim(strip_tags($descClean)) !== '' || $descPlain !== '';
            $cabH     = 7;

            if (($pdf->GetY() + $cabH) > $pdf->getPageHeight() - 25) {
                $pdf->AddPage(); $printHeader(); $rowAlt = false;
            }

            $rowY = $pdf->GetY();
            if ($rowAlt) {
                $pdf->SetFillColor(250, 250, 252);
                $pdf->Rect(15, $rowY, $tableW, $cabH + ($hayDesc ? 8 : 0), 'F');
            }
            $pdf->SetFillColor($brand_r, $brand_g, $brand_b);
            $pdf->Rect(15, $rowY, 1.5, $cabH, 'F');
            $pdf->SetXY(19, $rowY + 1.5);
            $pdf->SetFont('Helvetica', 'B', 8.5);
            $pdf->SetTextColor(40, 40, 40);
            $pdf->Cell($cArticulo - 4, 5, $nombre, 0, 0, 'L');
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetTextColor(90, 90, 90);
            $pdf->Cell($cCantidad, 5, number_format($cantidad, 2, ',', '.') . ($item->unit_type ? ' ' . $item->unit_type : ''), 0, 0, 'C');
            $pdf->Cell($cTarifa, 5, number_format($precio, 2, ',', '.') . ' €', 0, 0, 'R');
            $pdf->SetXY(15 + $cArticulo + $cCantidad + $cTarifa, $rowY + 1.5);
            $pdf->SetFont('Helvetica', 'B', 8.5);
            $pdf->SetTextColor($brand_r, $brand_g, $brand_b);
            $pdf->Cell($cTotal - 3, 5, number_format($total, 2, ',', '.') . ' €', 0, 1, 'R');

            if ($hayDesc) {
                $pdf->SetFont('Helvetica', '', 7.5);
                $pdf->SetTextColor(110, 110, 110);
                $pdf->SetXY(19, $rowY + $cabH);
                $pdf->SetLeftMargin(19); $pdf->SetRightMargin(15);
                if ($descClean !== '') {
                    $pdf->SetX(19);
                    $pdf->writeHTML($descClean, true, false, true, false, 'L');
                } else {
                    $pdf->SetX(19);
                    $pdf->MultiCell($tableW - 4, 3.8, $descPlain, 0, 'L');
                }
                $pdf->SetLeftMargin(15); $pdf->SetRightMargin(15);
                $pdf->SetTextColor(51, 51, 51);
                $pdf->Ln(1.5);
            } else {
                $pdf->SetY($rowY + $cabH);
            }

            $rowEndY = $pdf->GetY();
            $pdf->SetDrawColor(235, 235, 235);
            $pdf->SetLineWidth(0.15);
            $pdf->Line(15, $rowEndY, 15 + $tableW, $rowEndY);
            $pdf->Ln(0.5);
            $rowAlt = !$rowAlt;
        }

        // ── Totales ──────────────────────────────────────────────────────
        $pdf->Ln(3);
        $subtotal    = floatval($total_summary->contract_subtotal ?? 0);
        $tax_amount  = floatval($total_summary->tax ?? 0);
        $tax2_amount = floatval($total_summary->tax2 ?? 0);
        $descuento   = floatval($total_summary->discount_total ?? 0);
        $totalFinal  = floatval($total_summary->contract_total ?? 0);
        $tax_name    = $total_summary->tax_name ?? ('IVA ' . number_format($total_summary->tax_percentage ?? 21, 0) . '%');
        $rightM      = 15 + $tableW;
        $labelW_t    = 38; $valW_t = 27;

        $pdf->SetDrawColor($brand_r, $brand_g, $brand_b);
        $pdf->SetLineWidth(0.4);
        $pdf->Line($rightM - $labelW_t - $valW_t, $pdf->GetY(), $rightM, $pdf->GetY());
        $pdf->Ln(3);

        $pdf->SetX($rightM - $labelW_t - $valW_t);
        $pdf->SetFont('Helvetica', '', 8); $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell($labelW_t, 5, 'Subtotal (sin IVA)', 0, 0, 'R');
        $pdf->SetTextColor(50, 50, 50);
        $pdf->Cell($valW_t, 5, number_format($subtotal, 2, ',', '.') . ' €', 0, 1, 'R');

        if ($tax_amount > 0) {
            $pdf->SetX($rightM - $labelW_t - $valW_t);
            $pdf->SetFont('Helvetica', '', 8); $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell($labelW_t, 5, $tax_name, 0, 0, 'R');
            $pdf->SetTextColor(50, 50, 50);
            $pdf->Cell($valW_t, 5, number_format($tax_amount, 2, ',', '.') . ' €', 0, 1, 'R');
        }

        if ($descuento > 0) {
            $pdf->SetX($rightM - $labelW_t - $valW_t);
            $pdf->SetFont('Helvetica', 'B', 8); $pdf->SetTextColor(34, 120, 34);
            $pdf->Cell($labelW_t, 5, 'Descuento aplicado', 0, 0, 'R');
            $pdf->Cell($valW_t, 5, '- ' . number_format($descuento, 2, ',', '.') . ' €', 0, 1, 'R');
        }

        $pdf->Ln(2);
        $totalY = $pdf->GetY();
        $totalBlockW = $labelW_t + $valW_t;
        $pdf->SetFillColor($brand_r, $brand_g, $brand_b);
        $pdf->Rect($rightM - $totalBlockW, $totalY, $totalBlockW, 10, 'F');
        $pdf->SetFillColor(180, 20, 90);
        $pdf->Rect($rightM - $totalBlockW, $totalY, 3, 10, 'F');
        $pdf->SetXY($rightM - $totalBlockW + 3, $totalY + 1.5);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Cell($labelW_t - 3, 7, 'TOTAL (IVA incluido)', 0, 0, 'R');
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Cell($valW_t, 7, number_format($totalFinal, 2, ',', '.') . ' €', 0, 1, 'R');
        $pdf->SetTextColor(51, 51, 51);
        $pdf->SetDrawColor(230, 230, 230);

        // ── Determinar compañía ───────────────────────────────────────────
        $company_id = intval($contract_info->company_id ?? 2);
        $es_tictac  = ($company_id === 2 || $company_id === 0);
        $es_tress   = ($company_id === 1);
        $empresa_resp = $es_tress ? 'PROYECTO TRESS AZAFATAS, S.L.' : 'TIC TAC COMUNICACION DIGITAL SL';

        // ── Sección "Sobre Nosotros" ──────────────────────────────────────
        $pdf->Ln(8);
        if (($pdf->GetY() + 30) > $pdf->getPageHeight() - 45) $pdf->AddPage();
        $sobreStartY = $pdf->GetY();
        $sobre_texto = $es_tress
            ? 'En Proyecto Tress Azafatas S.L. ofrecemos soluciones digitales adaptadas a las necesidades de cada cliente, con un equipo especializado en marketing digital, diseño web y comunicación estratégica.'
            : 'En Tictac Comunicación Digital SL desarrollamos estrategias digitales orientadas a conversión, visibilidad y crecimiento real. Cada contrato se diseña a medida, alineado con los objetivos del cliente y basado en criterios técnicos, creativos y estratégicos.';
        $pdf->SetXY(20, $sobreStartY + 10);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(51, 51, 51);
        $pdf->MultiCell($contentW - 10, 4, $sobre_texto, 0, 'J');
        $sobreH = $pdf->GetY() - $sobreStartY + 6;
        $pdf->SetFillColor(255, 240, 247);
        $pdf->SetDrawColor(255, 240, 247);
        $pdf->Rect(15, $sobreStartY, $contentW, $sobreH, 'F');
        $pdf->SetFillColor($brand_r, $brand_g, $brand_b);
        $pdf->Rect(15, $sobreStartY, 3, $sobreH, 'F');
        $pdf->SetXY(20, $sobreStartY + 4);
        $pdf->SetTextColor($brand_r, $brand_g, $brand_b);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell($contentW - 10, 5, 'Sobre Nosotros', 0, 1, 'L');
        $pdf->SetXY(20, $sobreStartY + 10);
        $pdf->SetTextColor(51, 51, 51);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->MultiCell($contentW - 10, 4, $sobre_texto, 0, 'J');
        $pdf->Ln(4);

        // ── Notas ────────────────────────────────────────────────────────
        $notasHtml = $contract_info->note ?? '';
        if (trim(strip_tags($notasHtml)) !== '') {
            $pdf->Ln(6);
            $notasStartY = $pdf->GetY();
            $pdf->SetXY(20, $notasStartY + 10);
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetTextColor(51, 51, 51);
            $notasText = strip_tags(html_entity_decode($notasHtml, ENT_QUOTES, 'UTF-8'));
            $pdf->MultiCell($contentW - 10, 3.5, $notasText, 0, 'L');
            $notasH = $pdf->GetY() - $notasStartY + 6;
            $pdf->SetFillColor(255, 240, 247);
            $pdf->SetDrawColor(255, 240, 247);
            $pdf->Rect(15, $notasStartY, $contentW, $notasH, 'F');
            $pdf->SetXY(20, $notasStartY + 4);
            $pdf->SetTextColor($brand_r, $brand_g, $brand_b);
            $pdf->SetFont('Helvetica', 'B', 11);
            $pdf->Cell($contentW - 10, 5, 'Notas Adicionales', 0, 1, 'L');
            $pdf->SetXY(20, $notasStartY + 10);
            $pdf->SetTextColor(51, 51, 51);
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->MultiCell($contentW - 10, 3.5, $notasText, 0, 'L');
            $pdf->Ln(4);
        }

        // ── Cláusulas del contrato ────────────────────────────────────────
        $nombre_proveedor = $es_tress ? 'PROYECTO TRESS AZAFATAS, S.L.' : 'TIC TAC COMUNICACION DIGITAL, S.L.';
        $email_proveedor  = $es_tress ? 'info@proymer.com' : 'hola@tictac-comunicacion.es';
        $web_proveedor    = $es_tress ? 'Proyecto Tress Azafatas' : 'Tic Tac Comunicacion (www.tictac-comunicacion.es)';

        $clausulas_html = '
<p><b>CLAUSULAS</b></p>
<p><b>1. OBJETO</b></p>
<p>El objeto del Contrato consiste en la prestacion de servicios por parte del Proveedor a cambio del pago de un precio por parte del Cliente, en los terminos establecidos en el mismo.</p>
<p>Las solicitudes de modificacion del contrato se haran siempre por escrito, remitido por correo ordinario o electronico ' . $email_proveedor . '. Se ejecutaran siempre que sea posible y el cliente debera asumir los costes en los que el Proveedor haya incurrido, tras dicha modificacion del contrato.</p>
<p>El Cliente acepta que el Proveedor pueda publicar su imagen corporativa, nombre comercial y sitio web dentro de "casos de exito" o "seccion clientes" de la web de ' . $web_proveedor . ', asi como la firma de la Empresa en Footer (Pie de Pagina de la web del Cliente).</p>
<p><b>2. SERVICIOS DEL PROVEEDOR</b></p>
<p>2.1. Los Servicios del proyecto vendran descritos en la hoja de encargo adjunta que debera ser firmada por el cliente y por la empresa que provee el servicio.</p>
<p>En relacion al diseno web, si el cliente ha contratado este servicio y procede, el Proveedor presentara al cliente hasta 3 bocetos en soporte fisico o digital. El Cliente ha de firmar el Boceto escogido, todos los cambios a partir del momento de la firma, conllevaran costos adicionales.</p>
<p>2.1.1. Realizacion de material especial tal como: tipografia no convencional, caligrafia, mapas, diagramas, graficos, vectores o fotomontajes.<br/>2.1.2. Preparacion de material existente para su reproduccion tales como: redibujo parcial o total, conversion a lineas, escaneado y retoque de imagenes, tipeados, etc.<br/>2.1.3. Seguimiento de la produccion.<br/>2.1.4. Recuperacion de informacion, siempre que tecnicamente sea posible. El horario de trabajo de los tecnicos del Proveedor sera de lunes a viernes de 9:00 a 17:00, salvo en los meses de Julio y agosto que sera de 9:00 a 15:00.<br/>2.1.5. La correccion de errores imputables a la manipulacion a traves de los Programas de gestion de contenidos por personal no autorizado expresamente por el Proveedor.<br/>2.1.6. Las tareas necesarias para restablecer la situacion anterior derivada de operaciones incorrectas por parte del Cliente que ocasionen perdidas de informacion, destruccion o desorganizacion de ficheros, y situaciones analogas.<br/>2.1.7. La reparacion de danos causados por virus o defectos de otros programas no relacionados en el Contrato, o en anexo posterior.<br/>2.1.8. La reparacion de danos y malfuncionamientos causados por accidentes, uso indebido, catastrofes, abusos, alteraciones, sustitucion de elementos o software no suministrado y/o recomendado por el Proveedor.</p>
<p><b>3. VALORACION DE LOS SERVICIOS, FACTURACION, FORMA DE PAGO, IMPUESTOS Y GASTOS</b></p>
<p>3.1. La valoracion economica sera actualizada anualmente por el Proveedor, en funcion de las nuevas tarifas que el Proveedor establezca.<br/>3.2. El precio de los Servicios sera abonado por el Cliente al Proveedor en el momento de la formalizacion del Contrato, con caracter previo al inicio de la prestacion de los Servicios, mediante transferencia a la cuenta numero que el proveedor designe para tal efecto.<br/>3.3. El precio expresado en la tabla de arriba contiene los impuestos indirectos desglosados a la fecha de la firma actual.<br/>3.4. Se emitira un cobro mensual en el siguiente numero de cuenta bancaria facilitado por el Cliente.<br/>3.5. Cualquier revision o adiciones a los servicios descritos en el contrato seran facturados como Servicios Adicionales no incluidos en el presupuesto estimado arriba especificado.</p>
<p><b>4. RESPONSABILIDAD DEL CLIENTE</b></p>
<p>4.1. El Cliente proveera informacion fehaciente y completa y materiales al Proveedor, y sera responsable de la exactitud y completitud de toda la informacion y los materiales provistos. El Cliente garantiza que todo material provisto al Proveedor no afecta los derechos de autor de terceros.<br/>4.2. El Cliente en caso haber realizado alguna modificacion por su cuenta y por ello, haber desconfigurado la web, sera el mismo Cliente quien responda por el costo del arreglo.<br/>4.3. Todo texto e informacion aportado por el Cliente se entregara al Proveedor en formato digital. Este proceso sera presupuestado como un servicio suplementario.</p>
<p><b>5. DERECHOS Y PROPIEDAD</b></p>
<p>5.1. Todos los servicios provistos por el Proveedor y aprobados bajo este contrato seran para uso exclusivo del Cliente mas alla de su uso promocional propio del Proveedor.<br/>5.2. El Proveedor se compromete a almacenar los originales durante 6 meses a partir de la finalizacion del Proyecto.<br/>5.3. El Dominio (direccion web) pertenecera al Cliente, siendo este su propietario en todo momento.<br/>5.4. Una vez finalizado el pago total del monto acordado, el Cliente, pasara a ser propietario de la Web.</p>
<p><b>6. DURACION DEL CONTRATO</b></p>
<p>6.1. El Contrato tendra una vigencia minima de un (1) ano, contada a partir de la fecha de la firma del presente Contrato.<br/>6.2. El Cliente podra rescindir el presente Contrato, notificandoselo por escrito al Proveedor con al menos treinta (30) dias de antelacion a la fecha de vencimiento inicial, o, en su caso, de cualquiera de sus prorrogas.</p>
<p><b>7. EXTINCION DEL CONTRATO</b></p>
<p>7.1. El Contrato se extinguira por las causas generales establecidas en la legislacion vigente.<br/>7.2. En todo caso, la extincion del Contrato antes de la finalizacion del periodo inicial no dara lugar a devolucion alguna del precio abonado al Proveedor.<br/>7.3. La no acreditacion del pago del precio sera causa automatica de resolucion del Contrato.</p>
<p><b>8. NATURALEZA DE LA RELACION</b></p>
<p>8.1. El presente Contrato tiene caracter mercantil y se regira por sus propias clausulas, y en lo que en ellas no estuviere previsto, por las disposiciones del Codigo de Comercio, leyes especiales y usos mercantiles, y en su defecto, por el Codigo Civil.</p>
<p><b>9. PROTECCION DE DATOS DE CARACTER PERSONAL</b></p>
<p>9.1. Debido a la naturaleza de los Servicios, el Proveedor puede tener que realizar tratamientos automatizados de ficheros del Cliente que contengan datos de caracter personal. El Proveedor utilizara dichos datos unica y exclusivamente para los fines que figuran en el Contrato y siempre por cuenta del Cliente.<br/>9.2. El Cliente unicamente permitira el acceso a datos de caracter personal al Proveedor cuando sea necesario para la ejecucion del objeto del Contrato.<br/>9.3. El Cliente afirma y garantiza que los datos han sido recogidos de acuerdo a lo establecido en la LOPD.</p>
<p><b>10. CONFIDENCIALIDAD</b></p>
<p>10.1. El Proveedor considerara confidencial toda la informacion relacionada con los Servicios, y que obtenga durante la prestacion de los mismos.</p>
<p><b>11. RESPONSABILIDAD DEL PROVEEDOR</b></p>
<p>11.1. Salvo en los casos de culpa grave o dolo, la responsabilidad total del Proveedor no excedara, en su conjunto, de la cantidad correspondiente al precio abonado por los Servicios durante la ultima anualidad. El Proveedor no sera responsable, en ningun caso, de los danos que puedan ser calificados como danos indirectos, consecuenciales, perdida de beneficio, negocio, ingresos, clientes, datos, imagen o reputacion comercial.</p>
<p><b>12. ACTUALIZACION</b></p>
<p>12.1. En el caso de que alguna o algunas de las clausulas del Contrato pasen a ser invalidas, ilegales o inejecutables, se consideraran ineficaces en la medida que corresponda, pero en lo demas, este Contrato conservara su validez. CONTRATO UNICO</p>
<p><b>13. NOTIFICACIONES Y REQUERIMIENTOS</b></p>
<p>13.1. Toda notificacion o requerimiento que traiga su causa del Contrato se debera remitir por escrito a la otra Parte, bien por E-mail, bien personalmente, o por mensajero o correo certificado con acuse de recibo.</p>
<p><b>14. JURISDICCION Y COMPETENCIA</b></p>
<p>14.1. Las Partes, con renuncia expresa a cualquier otro fuero que pudiera corresponderles, se someten a la jurisdiccion y competencia de los Juzgados y Tribunales de Cordoba.</p>
<p>14.2. Y para que asi conste, y en prueba de conformidad y aceptacion de todo cuanto antecede, las Partes firman el presente Contrato por duplicado ejemplar y a un solo efecto en la fecha y lugar indicados en el encabezamiento.</p>';

        if (($pdf->GetY() + 20) > $pdf->getPageHeight() - 45) $pdf->AddPage();
        $pdf->Ln(6);
        $pdf->SetX(15);
        $pdf->SetTextColor($brand_r, $brand_g, $brand_b);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->Cell($contentW, 5, 'CLAUSULAS DEL CONTRATO', 0, 1, 'L');
        $pdf->SetDrawColor($brand_r, $brand_g, $brand_b);
        $pdf->SetLineWidth(0.4);
        $pdf->Line(15, $pdf->GetY(), 15 + $contentW, $pdf->GetY());
        $pdf->Ln(4);
        $pdf->SetFont('Helvetica', '', 6);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->writeHTML($clausulas_html, true, false, true, false, 'J');
        $pdf->Ln(3);

        // Cláusula Kit Digital (solo Tress)
        if ($es_tress) {
            if (($pdf->GetY() + 20) > $pdf->getPageHeight() - 45) $pdf->AddPage();
            $pdf->SetX(15);
            $pdf->SetTextColor($brand_r, $brand_g, $brand_b);
            $pdf->SetFont('Helvetica', 'B', 7.5);
            $pdf->Cell($contentW, 5, 'KIT DIGITAL', 0, 1, 'L');
            $pdf->Ln(1);
            $kit_digital = "El cliente es beneficiario de subvencion de Kit Digital de 3000 euros que se decrementara del precio de las correspondientes partidas o soluciones digitalizadoras. El cliente esta obligado a cumplir con las premisas tributarias que genera la subvencion durante el ano siguiente para mantener la subvencion y siempre segun la Orden TDF/435/2024, de 9 de mayo, por la que se modifica la Orden ETD/1498/2021, de 29 de diciembre, por la que se aprueban las bases reguladoras de la concesion de ayudas para la digitalizacion de pequenas empresas, microempresas y personas en situacion de autoempleo, en el marco de la Agenda Espana Digital 2025, el Plan de Digitalizacion PYMEs 2021-2025 y el Plan de Recuperacion, Transformacion y Resiliencia de Espana -Financiado por la Union Europea- Next Generation EU (Programa Kit Digital), publicada en Boletin Oficial del Estado a fecha 11 de Mayo de 2024.\n\nNo seran subvencionables el Impuesto sobre el Valor Anadido que tendra que ser abonado por el beneficiario y cuya remesa se enviara durante los tres meses siguientes a la validacion del Acuerdo de Prestacion de Soluciones.\n\nEn caso de ser desestimada la ayuda (Kit Digital) por cualquier motivo ajeno a Proyecto Tress Azafatas sera el cliente el que asuma el obligado cumplimiento del pago del servicio prestado (2000 euros, IVA no incluido). La forma de pago se establece con emision de remesa bancaria previamente autorizada mediante firma de documento SEPA por parte del cliente.\n\nEn el caso de que el cliente decida desistir de la ayuda dentro del plazo de los 12 meses establecidos por Kit Digital como prestacion del servicio, sera el cliente el que tenga el obligado cumplimiento de hacerse cargo de la cuantia de los trabajos realizados hasta dicho momento por el agente digitalizador, PROYECTO TRESS AZAFATAS en este caso. Se calculara la parte proporcional del total del servicio que ira desde el inicio del acuerdo de prestacion hasta la fecha de comunicacion de renuncia de la ayuda. Una vez el cliente haya abonado la citada cuantia de los trabajos realizados se procedera a la aceptacion de la renuncia por parte de PROYECTO TRESS AZAFATAS como agente digitalizador.";
            $pdf->SetX(15);
            $pdf->SetFont('Helvetica', '', 6);
            $pdf->SetTextColor(60, 60, 60);
            $pdf->MultiCell($contentW, 3, $kit_digital, 0, 'J');
            $pdf->Ln(3);
        }

        // ── RGPD (condicional por compañía) ───────────────────────────────
        $pdf->Ln(4);
        if (($pdf->GetY() + 60) > $pdf->getPageHeight() - 45) $pdf->AddPage();
        $clY = $pdf->GetY();
        $pdf->SetDrawColor($brand_r, $brand_g, $brand_b);
        $pdf->SetLineWidth(0.4);
        $pdf->Line(15, $clY, 15 + $contentW, $clY);
        $pdf->Ln(4);
        $pdf->SetX(15);
        $pdf->SetTextColor($brand_r, $brand_g, $brand_b);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->Cell($contentW, 5, 'PROTECCION DE DATOS Y CLAUSULAS LEGALES', 0, 1, 'L');
        $pdf->Ln(2);

        if ($es_tress) {
            $pdf->SetX(15);
            $pdf->SetFont('Helvetica', 'B', 6.5);
            $pdf->SetTextColor(60, 60, 60);
            $pdf->MultiCell($contentW, 3.2, 'Responsable: PROYECTO TRESS AZAFATAS SL - CIF: B56028293  Dir. postal: C/ Cruz Conde, 19, Planta 6a, 14001 de Cordoba.  Telefono: 957963074  E-mail: info@proymer.com', 0, 'L');
            $pdf->Ln(2);
            $pdf->SetX(15);
            $pdf->SetFont('Helvetica', '', 6.5);
            $pdf->SetTextColor(80, 80, 80);
            $pdf->MultiCell($contentW, 3.2, 'Tratamos la informacion que nos facilita con el fin de prestarles el servicio solicitado. Los datos proporcionados se conservaran durante el tiempo necesario para cumplir con las finalidades previstas. Los datos no se cederan a terceros salvo en los casos en que exista una obligacion legal. Usted tiene derecho de acceso, rectificacion, supresion y portabilidad de sus datos y oposicion y limitacion a su tratamiento en la direccion postal o correo electronico facilitados, adjuntando copia de su DNI o documento equivalente. Asimismo, y especialmente si considera que no ha obtenido satisfaccion plena en el ejercicio de sus derechos, podra presentar una reclamacion ante la autoridad nacional de control dirigiendose a estos efectos a la Agencia Espanola de Proteccion de Datos, C/ Jorge Juan, 6 - 28001 Madrid.', 0, 'J');
            $pdf->Ln(2);
            $pdf->SetX(15);
            $pdf->MultiCell($contentW, 3.2, 'Asimismo, solicitamos su autorizacion para enviarle publicidad relacionada con nuestros productos y servicios por cualquier medio (postal, email o telefono) e invitarle a eventos organizados por la empresa.', 0, 'J');
            $pdf->Ln(3);
            $checkY = $pdf->GetY();
            $pdf->SetFont('Helvetica', 'B', 7.5); $pdf->SetTextColor(51, 51, 51); $pdf->SetDrawColor(100, 100, 100); $pdf->SetLineWidth(0.4);
            $pdf->SetXY(15, $checkY); $pdf->Cell(22, 5, 'SI Autorizo', 0, 0, 'L'); $pdf->Rect(38, $checkY + 0.8, 3.5, 3.5);
            $pdf->SetXY(47, $checkY); $pdf->Cell(22, 5, 'NO Autorizo', 0, 1, 'L'); $pdf->Rect(70, $checkY + 0.8, 3.5, 3.5);
            $pdf->SetDrawColor(230, 230, 230); $pdf->Ln(3);
        } else {
            $pdf->SetX(15);
            $pdf->SetFont('Helvetica', 'B', 6.5);
            $pdf->SetTextColor(60, 60, 60);
            $pdf->MultiCell($contentW, 3.2, 'Responsable: TIC TAC COMUNICACION DIGITAL SL - CIF: B09912478  Dir. postal: C/ Cruz Conde, 19, Planta 6a, 14001 de Cordoba.  Telefono: 957786914  E-mail: hola@tictac-comunicacion.es', 0, 'L');
            $pdf->Ln(2);
            $pdf->SetX(15);
            $pdf->SetFont('Helvetica', '', 6.5);
            $pdf->SetTextColor(80, 80, 80);
            $pdf->MultiCell($contentW, 3.2, 'Tratamos la informacion que nos facilita con el fin de prestarles el servicio solicitado. Los datos proporcionados se conservaran durante el tiempo necesario para cumplir con las finalidades previstas. Los datos no se cederan a terceros salvo en los casos en que exista una obligacion legal. Usted tiene derecho de acceso, rectificacion, supresion y portabilidad de sus datos y oposicion y limitacion a su tratamiento en la direccion postal o correo electronico facilitados, adjuntando copia de su DNI o documento equivalente. Asimismo, y especialmente si considera que no ha obtenido satisfaccion plena en el ejercicio de sus derechos, podra presentar una reclamacion ante la autoridad nacional de control dirigiendose a estos efectos a la Agencia Espanola de Proteccion de Datos, C/ Jorge Juan, 6 - 28001 Madrid.', 0, 'J');
            $pdf->Ln(2);
            $pdf->SetX(15);
            $pdf->MultiCell($contentW, 3.2, 'Asimismo, solicitamos su autorizacion para enviarle publicidad relacionada con nuestros productos y servicios por cualquier medio (postal, email o telefono) e invitarle a eventos organizados por la empresa.', 0, 'J');
            $pdf->Ln(3);
            $checkY = $pdf->GetY();
            $pdf->SetFont('Helvetica', 'B', 7.5); $pdf->SetTextColor(51, 51, 51); $pdf->SetDrawColor(100, 100, 100); $pdf->SetLineWidth(0.4);
            $pdf->SetXY(15, $checkY); $pdf->Cell(22, 5, 'SI Autorizo', 0, 0, 'L'); $pdf->Rect(38, $checkY + 0.8, 3.5, 3.5);
            $pdf->SetXY(47, $checkY); $pdf->Cell(22, 5, 'NO Autorizo', 0, 1, 'L'); $pdf->Rect(70, $checkY + 0.8, 3.5, 3.5);
            $pdf->SetDrawColor(230, 230, 230); $pdf->Ln(3);
            $pdf->SetX(15);
            $pdf->SetFont('Helvetica', '', 6.5);
            $pdf->SetTextColor(80, 80, 80);
            $pdf->MultiCell($contentW, 3.2, 'El CLIENTE es responsable de garantizar que dispone de los consentimientos y autorizaciones legales necesarias para la publicacion de imagenes o datos personales de trabajadores y terceros. TIC TAC COMUNICACION DIGITAL SL quedara exonerada de cualquier responsabilidad derivada de incumplimientos en materia de proteccion de datos por parte del cliente.', 0, 'J');
        }
        $pdf->SetTextColor(51, 51, 51);
        $pdf->Ln(6);

        // ── Firmas ───────────────────────────────────────────────────────
        if (($pdf->GetY() + 55) > $pdf->getPageHeight() - 45) $pdf->AddPage();
        $firmasStartY = $pdf->GetY();
        $firmaColW    = ($contentW - 10) / 2;
        $pdf->SetDrawColor($brand_r, $brand_g, $brand_b);
        $pdf->SetLineWidth(0.5);
        $pdf->Line(15, $firmasStartY, 15 + $contentW, $firmasStartY);
        $pdf->Ln(5);
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetTextColor($brand_r, $brand_g, $brand_b);
        $pdf->SetX(15);
        $pdf->Cell($firmaColW, 5, 'FIRMA Y SELLO DEL PROVEEDOR', 0, 0, 'C');
        $pdf->SetX(15 + $firmaColW + 10);
        $pdf->Cell($firmaColW, 5, 'FIRMA Y SELLO DEL CLIENTE', 0, 1, 'C');
        $pdf->Ln(18);
        $firmaLineY = $pdf->GetY();
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->SetLineWidth(0.4);
        $pdf->Line(15 + 5, $firmaLineY, 15 + $firmaColW - 5, $firmaLineY);
        $pdf->Line(15 + $firmaColW + 15, $firmaLineY, 15 + $contentW - 5, $firmaLineY);
        $pdf->Ln(4);
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(51, 51, 51);
        $pdf->SetX(15);
        $nombre_firma_proveedor = $es_tress ? 'Proyecto Tress Azafatas SL' : 'Tictac Comunicacion Digital SL';
        $pdf->Cell($firmaColW, 5, $nombre_firma_proveedor, 0, 0, 'C');
        $pdf->SetX(15 + $firmaColW + 10);
        $pdf->Cell($firmaColW, 5, $client_info->company_name ?? '', 0, 1, 'C');

        // ── Output ───────────────────────────────────────────────────────
        $client_name_pdf = '';
        if (!empty($client_info->company_name)) {
            $client_name_pdf = '_' . preg_replace('/[^a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\-]/u', '', $client_info->company_name);
            $client_name_pdf = trim(str_replace(' ', '_', $client_name_pdf));
        }
        $filename = 'Contrato' . $client_name_pdf . '.pdf';
        if ($mode === 'save') {
            $tmpFile = sys_get_temp_dir() . '/contract_' . $contract_id . '_' . time() . '.pdf';
            $pdf->Output($tmpFile, 'F');
            return file_exists($tmpFile) ? $tmpFile : null;
        } else {
            $pdf->Output($filename, 'D');
            exit;
        }
    }

    private function _copy_related_items_to_contract($copy_items_from_proposal, $contract_id) {
        if (!$copy_items_from_proposal) {
            return false;
        }

        $copy_items = null;
        if ($copy_items_from_proposal) {
            $copy_items = $this->Proposal_items_model->get_details(array("proposal_id" => $copy_items_from_proposal))->getResult();
        }

        if (!$copy_items) {
            return false;
        }

        foreach ($copy_items as $data) {
            $contract_item_data = array(
                "contract_id" => $contract_id,
                "title" => $data->title ? $data->title : "",
                "description" => $data->description ? $data->description : "",
                "quantity" => $data->quantity ? $data->quantity : 0,
                "unit_type" => $data->unit_type ? $data->unit_type : "",
                "rate" => $data->rate ? $data->rate : 0,
                "total" => $data->total ? $data->total : 0,
                "item_id" => $data->item_id ? $data->item_id : 0
            );
            $this->Contract_items_model->ci_save($contract_item_data);
        }
    }

    // Notificación interna tras firma Lleida (llamado desde lleida_webhook.php)
    function lleida_notify_internal($contract_id = 0) {
        validate_numeric_value($contract_id);
        $contract_info = $this->Contracts_model->get_one($contract_id);
        if (!$contract_info->id) return;

        try {
            log_notification("contract_accepted", array("contract_id" => $contract_id), "999999996");
        } catch (\Throwable $e) {}

        try {
            $client_info   = $this->Clients_model->get_one($contract_info->client_id);
            $client_nombre = $client_info->company_name ?? 'Cliente #' . $contract_info->client_id;
            $contract_ref  = get_contract_id($contract_id);
            $meta_raw      = @unserialize($contract_info->meta_data ?? '') ?: array();
            $firmante      = $meta_raw['lleida_phone'] ?? 'vía SMS';
            $contract_url  = get_uri('contracts/view/' . $contract_id);

            $subj = 'Contrato firmado por SMS: ' . $contract_ref . ' — ' . $client_nombre;
            $msg  = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;background:#f5f5f5;">
<div style="max-width:580px;margin:30px auto;background:#fff;border-radius:10px;overflow:hidden;">
  <div style="background:#d72173;padding:28px;text-align:center;"><h1 style="color:#fff;margin:0;">✅ Contrato Firmado por SMS</h1></div>
  <div style="padding:30px;">
    <p>El cliente <strong>' . htmlspecialchars($client_nombre) . '</strong> ha firmado el contrato <strong>' . htmlspecialchars($contract_ref) . '</strong>.</p>
    <p>Teléfono firmante: <strong>' . htmlspecialchars($firmante) . '</strong></p>
    <p><a href="' . $contract_url . '">' . $contract_url . '</a></p>
  </div>
</div></body></html>';
            send_app_mail('hola@tictac-comunicacion.es', $subj, $msg);
        } catch (\Throwable $e) {
            log_message('error', '[Lleida] notify_internal email error: ' . $e->getMessage());
        }
        echo 'OK';
    }

    // Endpoint interno para clonar proyecto tipo — llamado desde lleida_webhook.php
    // Autenticado por API key, no por sesión de usuario
    function clonar_proyecto_interno() {
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

        // Deadline proporcional
        $deadline = null;
        if ($orig->deadline && $orig->start_date) {
            $diff     = max(0, floor((strtotime($orig->deadline) - strtotime($orig->start_date)) / 86400));
            $deadline = date('Y-m-d', strtotime($today . ' +' . $diff . ' days'));
        }

        // Crear proyecto nuevo con los mismos campos que usa save_cloned_project
        $project_data = array(
            'title'        => $title,
            'description'  => $orig->description ?? '',
            'client_id'    => $client_id,
            'start_date'   => $today,
            'deadline'     => $deadline ?: null,
            'project_type' => $orig->project_type ?? 'client_project',
            'price'        => $orig->price ?? 0,
            'labels'       => $orig->labels ?? '',
            'status_id'    => 1,
            'created_date' => $now,
            'created_by'   => 1,
        );
        $project_data = clean_data($project_data);
        $new_id = $this->Projects_model->ci_save($project_data);

        if (!$new_id) {
            echo json_encode(array('success' => false, 'message' => 'Error creando proyecto'));
            return;
        }

        log_message('info', "[Tictac] clonar_proyecto_interno: nuevo proyecto #$new_id '$title'");

        // Clonar milestones
        $milestones = $this->Milestones_model->get_all_where(array('project_id' => $proyecto_tipo_id, 'deleted' => 0))->getResult();
        $ms_map     = array();
        foreach ($milestones as $ms) {
            $ms_data = (array) $ms;
            unset($ms_data['id']);
            $ms_data['project_id'] = $new_id;
            if ($ms->due_date && $orig->start_date) {
                $diff = max(0, floor((strtotime($ms->due_date) - strtotime($orig->start_date)) / 86400));
                $ms_data['due_date'] = date('Y-m-d', strtotime($today . ' +' . $diff . ' days'));
            }
            $new_ms_id = $this->Milestones_model->ci_save($ms_data);
            $ms_map[$ms->id] = $new_ms_id;
        }

        // Clonar tareas (primero las principales, luego subtareas)
        $task_map = array();
        foreach (array(0, 1) as $pass) {
            $where = $pass ? array('project_id' => $proyecto_tipo_id, 'deleted' => 0, 'parent_task_id !=' => 0)
                           : array('project_id' => $proyecto_tipo_id, 'deleted' => 0, 'parent_task_id' => 0);
            $tasks = $this->Tasks_model->get_all_where($where)->getResult();
            foreach ($tasks as $task) {
                $task_data = (array) $task;
                unset($task_data['id']);
                $task_data['project_id']     = $new_id;
                $task_data['milestone_id']   = $ms_map[$task->milestone_id] ?? 0;
                $task_data['status']         = 'to_do';
                $task_data['status_id']      = 1;
                $task_data['parent_task_id'] = $pass ? ($task_map[$task->parent_task_id] ?? 0) : 0;

                // Ajustar fechas relativas al inicio
                if ($task->start_date && $orig->start_date) {
                    $diff = max(0, floor((strtotime($task->start_date) - strtotime($orig->start_date)) / 86400));
                    $task_data['start_date'] = date('Y-m-d', strtotime($today . ' +' . $diff . ' days'));
                } else {
                    $task_data['start_date'] = null;
                }
                if ($task->deadline && $orig->start_date) {
                    $diff = max(0, floor((strtotime($task->deadline) - strtotime($orig->start_date)) / 86400));
                    $task_data['deadline'] = date('Y-m-d', strtotime($today . ' +' . $diff . ' days'));
                } else {
                    $task_data['deadline'] = null;
                }

                $task_data['created_by']   = 1;
                $task_data['created_date'] = $now;
                $task_data = clean_data($task_data);
                $new_task_id = $this->Tasks_model->ci_save($task_data);
                if ($new_task_id) $task_map[$task->id] = $new_task_id;
            }
        }

        // Clonar miembros del proyecto
        $members = $this->Project_members_model->get_all_where(array('project_id' => $proyecto_tipo_id, 'deleted' => 0))->getResult();
        foreach ($members as $member) {
            $this->Project_members_model->save_member(array(
                'project_id' => $new_id,
                'user_id'    => $member->user_id,
                'is_leader'  => $member->is_leader,
            ));
        }

        // Guardar en meta_data del contrato
        if ($contract_id) {
            $contract_raw = $this->Contracts_model->get_one($contract_id);
            $meta         = @unserialize($contract_raw->meta_data ?? '') ?: array();
            $meta['proyecto_clonado_id'] = $new_id;
            $db = \Config\Database::connect();
            $ct = $db->prefixTable('contracts');
            $ms = $db->escapeString(serialize($meta));
            $db->query("UPDATE {$ct} SET meta_data='{$ms}' WHERE id={$contract_id}");
        }

        log_message('info', "[Tictac] clonar_proyecto_interno: OK #$new_id con " . count($task_map) . " tareas y " . count($members) . " miembros");
        echo json_encode(array('success' => true, 'id' => $new_id, 'tareas' => count($task_map), 'miembros' => count($members)));
    }

    function compact_view($contract_id = 0) {
        validate_numeric_value($contract_id);

        if ($this->login_user->user_type === "client") {
            app_redirect("contracts/preview/$contract_id");
        }

        return $this->index($contract_id);
    }

    // ══════════════════════════════════════════════════════════════════
    // LLEIDA / CLICK&SIGN — Firma por SMS
    // ══════════════════════════════════════════════════════════════════

    // Credenciales Click&Sign
    private function _lleida_credentials() {
        return array(
            'user'      => 'ticta-comunicacion',
            'apikey'    => 'nfb70Z9BhqgqpMnJAJRX0ga80tuTqQvW',
            'endpoint'  => 'https://api.lleida.net/cs/v1/',
        );
    }

    // Versión pública del generador de PDF (sin login requerido)
    function _generate_tictac_contract_pdf_public($contract_id) {
        return $this->_generate_tictac_contract_pdf($contract_id, 'save');
    }

    // Método estático para generar PDF de contrato sin requerir sesión
    public static function generate_contract_pdf_static($contract_id, $mode = 'download') {
        $rc = new \ReflectionClass(\App\Controllers\Contracts::class);
        $instance = $rc->newInstanceWithoutConstructor();
        $instance->Contracts_model      = model('App\Models\Contracts_model', false);
        $instance->Contract_items_model = model('App\Models\Contract_items_model', false);
        $instance->Clients_model        = model('App\Models\Clients_model', false);
        $instance->Companies_model      = model('App\Models\Companies_model', false);
        return $instance->_generate_tictac_contract_pdf($contract_id, $mode);
    }

    // Obtener o crear config_id en Click&Sign
    private function _lleida_get_or_create_config($creds, $webhook_url) {
        // Buscar config existente activa
        $list_payload = array(
            "request" => "GET_CONFIG_LIST",
            "user"    => $creds['user'],
            "status"  => "enabled",
        );
        $ch = curl_init($creds['endpoint'] . 'get_config_list');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($list_payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: x-api-key ' . $creds['apikey'],
        ));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $resp = curl_exec($ch);
        curl_close($ch);

        $list = @json_decode($resp, true);
        log_message('info', '[Lleida] GET_CONFIG_LIST response: ' . $resp);

        if (!empty($list['config'])) {
            foreach ($list['config'] as $cfg) {
                if (($cfg['name'] ?? '') === 'TictacContrato') {
                    return $cfg['config_id'];
                }
            }
        }

        // Crear nueva configuración
        $set_payload = array(
            "request" => "SET_CONFIG",
            "user"    => $creds['user'],
            "config"  => array(
                "name"                      => "TictacContrato",
                "expire_lapse"              => 168,
                "default_sms_sender"        => "Tictac",
                "default_email_from_name"   => "Tictac Comunicacion",
                "signatory_cb_url"          => $webhook_url,
                "registered_company_name"   => "Tictac Comunicacion Digital SL",
                "registered_company_vat_number" => "B09912478",
                "registered_langs"          => "ES",
                "lang"                      => "ES",
                "sms" => array(
                    array(
                        "registered" => "Y",
                        "type"       => "start",
                        "sender"     => "Tictac",
                        "text"       => "Hola #name#, tiene un contrato pendiente de firma. Acceda aqui: #url#",
                    ),
                    array(
                        "registered" => "Y",
                        "type"       => "otp",
                        "sender"     => "Tictac",
                        "text"       => "Su codigo de firma es: #otp#",
                    ),
                ),
                "landing" => array(
                    "signature_type" => "on_sign",
                    "signature_on_sign_required_elements" => array(
                        "otp"        => "Y",
                        "otp_length" => 6,
                    ),
                    "enable_button" => "on_open",
                    "landing_access_max_retries" => 5,
                    "declinable_signature" => "N",
                ),
            ),
        );

        $ch = curl_init($creds['endpoint'] . 'set_config');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($set_payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: x-api-key ' . $creds['apikey'],
        ));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $resp = curl_exec($ch);
        curl_close($ch);

        $result = @json_decode($resp, true);
        log_message('info', '[Lleida] SET_CONFIG response: ' . $resp);

        return $result['config']['config_id'] ?? null;
    }

    // Modal de confirmación antes de enviar
    function lleida_modal_form($contract_id = 0) {
        validate_numeric_value($contract_id);

        if (!$this->can_edit_contracts($contract_id)) {
            app_redirect("forbidden");
        }

        $contract_info = $this->Contracts_model->get_one($contract_id);
        $client_info   = $this->Clients_model->get_one($contract_info->client_id);

        // Obtener contacto principal
        $contact = $this->Users_model->get_details(array(
            "client_id"          => $contract_info->client_id,
            "is_primary_contact" => true,
            "user_type"          => "client",
        ))->getRow();

        $view_data = array(
            'contract_info' => $contract_info,
            'client_info'   => $client_info,
            'contact'       => $contact,
            'contract_ref'  => get_contract_id($contract_id),
        );

        return $this->template->view("contracts/lleida_modal_form", $view_data);
    }

    // Enviar a Lleida desde el preview público (sin login de staff)
    function send_to_lleida_from_preview($contract_id = 0, $public_key = "") {
        validate_numeric_value($contract_id);

        $contract_info = $this->Contracts_model->get_one($contract_id);

        // Verificar clave pública
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
        $email = trim($this->request->getPost('email') ?? '');

        // Validar móvil
        $phone_clean = preg_replace('/\s+/', '', $phone);
        $phone_clean = preg_replace('/^\+34/', '', $phone_clean);
        $phone_clean = preg_replace('/^0034/', '', $phone_clean);
        if (!preg_match('/^[67]\d{8}$/', $phone_clean)) {
            echo json_encode(array('success' => false, 'message' => 'Introduce un número de móvil válido (debe empezar por 6 o 7)'));
            return;
        }
        $phone_e164 = '+34' . $phone_clean;

        // Generar PDF
        try {
            $pdf_path = $this->_generate_tictac_contract_pdf($contract_id, 'save');
        } catch (\Throwable $e) {
            echo json_encode(array('success' => false, 'message' => 'Error generando PDF'));
            return;
        }

        if (!$pdf_path || !file_exists($pdf_path)) {
            echo json_encode(array('success' => false, 'message' => 'No se pudo generar el PDF'));
            return;
        }

        $contract_ref = get_contract_id($contract_id);
        $creds        = $this->_lleida_credentials();
        $webhook_url  = get_uri('contract/lleida_webhook_global');

        $config_id = $this->_lleida_get_or_create_config($creds, $webhook_url);
        if (!$config_id) {
            @unlink($pdf_path);
            echo json_encode(array('success' => false, 'message' => 'Error conectando con Click&Sign'));
            return;
        }

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
                        "email"       => $email ?: null,
                        "name"        => explode(' ', $name)[0] ?? ($name ?: 'Cliente'),
                        "surname"     => implode(' ', array_slice(explode(' ', $name), 1)) ?: '',
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

        @unlink($pdf_path);

        log_message('info', '[Lleida] from_preview HTTP=' . $http_code . ' ' . $response);

        if ($curl_err) {
            echo json_encode(array('success' => false, 'message' => 'Error de conexión: ' . $curl_err));
            return;
        }

        $result = @json_decode($response, true);

        if ($http_code === 200 && ($result['code'] ?? 0) == 200) {
            $signature_id = $result['signature']['signature_id'] ?? ('lleida_' . time());
            $meta_raw     = @unserialize($contract_info->meta_data ?? '') ?: array();
            $meta_raw['lleida_transaction_id'] = $signature_id;
            $meta_raw['lleida_phone']          = $phone_e164;
            $meta_raw['lleida_sent_at']        = date('Y-m-d H:i:s');
            $meta_raw['lleida_config_id']      = $config_id;

            $db = \Config\Database::connect();
            $contracts_table = $db->prefixTable('contracts');
            $meta_serialized = $db->escapeString(serialize($meta_raw));
            $db->query("UPDATE {$contracts_table} SET meta_data='{$meta_serialized}' WHERE id={$contract_id}");

            echo json_encode(array('success' => true, 'message' => '¡Perfecto! Te hemos enviado un SMS con el código para firmar.'));
        } else {
            $err = $result['status'] ?? ('Error HTTP ' . $http_code);
            echo json_encode(array('success' => false, 'message' => 'Error Click&Sign: ' . $err));
        }
    }

    // Enviar a Click&Sign desde el CRM (requiere login de staff)
    function send_to_lleida() {
        $contract_id = $this->request->getPost('contract_id');
        $phone       = trim($this->request->getPost('phone') ?? '');
        $name        = trim($this->request->getPost('signer_name') ?? '');
        $email       = trim($this->request->getPost('signer_email') ?? '');

        if (!$contract_id || !is_numeric($contract_id)) {
            echo json_encode(array('success' => false, 'message' => 'ID de contrato no válido'));
            return;
        }
        if (!$this->can_edit_contracts($contract_id)) {
            echo json_encode(array('success' => false, 'message' => 'Sin permisos'));
            return;
        }

        // Validar que sea móvil (empieza por 6 o 7, con o sin +34)
        $phone_clean = preg_replace('/\s+/', '', $phone);
        $phone_clean = preg_replace('/^\+34/', '', $phone_clean);
        $phone_clean = preg_replace('/^0034/', '', $phone_clean);
        if (!preg_match('/^[67]\d{8}$/', $phone_clean)) {
            echo json_encode(array('success' => false, 'message' => 'El número debe ser un móvil español (empieza por 6 o 7)'));
            return;
        }
        $phone_e164 = '+34' . $phone_clean;

        // Generar PDF del contrato
        try {
            $pdf_path = $this->_generate_tictac_contract_pdf($contract_id, 'save');
        } catch (\Throwable $e) {
            echo json_encode(array('success' => false, 'message' => 'Error generando PDF: ' . $e->getMessage()));
            return;
        }

        if (!$pdf_path || !file_exists($pdf_path)) {
            echo json_encode(array('success' => false, 'message' => 'No se pudo generar el PDF del contrato'));
            return;
        }

        $contract_info = $this->Contracts_model->get_one($contract_id);
        $contract_ref  = get_contract_id($contract_id);
        $creds         = $this->_lleida_credentials();

        // Webhook URL para recibir la notificación de firma
        $webhook_url = get_uri('contract/lleida_webhook_global');

        // Obtener o crear config_id
        $config_id = $this->_lleida_get_or_create_config($creds, $webhook_url);
        if (!$config_id) {
            echo json_encode(array('success' => false, 'message' => 'Error creando configuración en Click&Sign. Revisa el log del servidor.'));
            return;
        }

        // Llamada a la API de Click&Sign — START_SIGNATURE
        $payload = array(
            "request"    => "START_SIGNATURE",
            "request_id" => 'crm-contract-' . $contract_id . '-' . time(),
            "user"       => $creds['user'],
            "signature"  => array(
                "config_id"   => $config_id,
                "contract_id" => 'TICTAC-' . $contract_ref,
                "level"       => array(
                    array(
                        "level_order"                            => 0,
                        "required_signatories_to_complete_level" => 1,
                        "signatories" => array(
                            array(
                                "phone"       => $phone_e164,
                                "email"       => $email,
                                "name"        => explode(' ', $name)[0] ?? $name,
                                "surname"     => implode(' ', array_slice(explode(' ', $name), 1)) ?: '',
                                "external_id" => "1",
                            )
                        ),
                    )
                ),
                "file" => array(
                    array(
                        "filename"        => 'Contrato_' . $contract_ref . '.pdf',
                        "content"         => base64_encode(file_get_contents($pdf_path)),
                        "file_group"      => "contract_files",
                        "sign_on_landing" => "Y",
                    )
                ),
            ),
        );

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
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $response     = curl_exec($ch);
        $http_code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error   = curl_error($ch);
        curl_close($ch);

        @unlink($pdf_path);

        if ($curl_error) {
            log_message('error', '[Lleida] curl error: ' . $curl_error);
            echo json_encode(array('success' => false, 'message' => 'Error de conexión con Click&Sign: ' . $curl_error));
            return;
        }

        $result = @json_decode($response, true);
        log_message('info', '[Lleida] send_to_lleida HTTP=' . $http_code . ' response=' . $response);

        if ($http_code === 200 || $http_code === 201) {
            $signature_id = $result['signature']['signature_id'] ?? $result['signature_id'] ?? ('lleida_' . time());

            $meta_raw = @unserialize($contract_info->meta_data ?? '') ?: array();
            $meta_raw['lleida_transaction_id'] = $signature_id;
            $meta_raw['lleida_phone']          = $phone_e164;
            $meta_raw['lleida_sent_at']        = date('Y-m-d H:i:s');
            $meta_raw['lleida_config_id']      = $config_id;

            $db = \Config\Database::connect();
            $contracts_table = $db->prefixTable('contracts');
            $meta_serialized = $db->escapeString(serialize($meta_raw));
            $db->query("UPDATE {$contracts_table} SET status='sent', meta_data='{$meta_serialized}' WHERE id={$contract_id}");

            echo json_encode(array(
                'success' => true,
                'message' => 'Contrato enviado a Click&Sign correctamente. El cliente recibirá un SMS para firmar.',
            ));
        } else {
            $error_msg = $result['message'] ?? $result['error'] ?? ('Error HTTP ' . $http_code . ': ' . $response);
            log_message('error', '[Lleida] Error: ' . $error_msg);
            echo json_encode(array('success' => false, 'message' => 'Error Click&Sign: ' . $error_msg));
        }
    }

    // Consultar estado del envío en Click&Sign (para polling desde el CRM si se necesita)
    function lleida_check_status($contract_id = 0) {
        validate_numeric_value($contract_id);

        $contract_info = $this->Contracts_model->get_one($contract_id);
        $meta_raw      = @unserialize($contract_info->meta_data ?? '') ?: array();
        $transaction_id = $meta_raw['lleida_transaction_id'] ?? null;

        if (!$transaction_id) {
            echo json_encode(array('success' => false, 'message' => 'No hay envío pendiente en Click&Sign para este contrato'));
            return;
        }

        $creds = $this->_lleida_credentials();
        $check_payload = array(
            "request"      => "GET_SIGNATURE_STATUS",
            "user"         => $creds['user'],
            "signature_id" => $transaction_id,
        );
        $ch = curl_init($creds['endpoint'] . 'get_signature_status');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($check_payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: x-api-key ' . $creds['apikey'],
        ));
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response  = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = @json_decode($response, true);
        echo json_encode(array('success' => true, 'http_code' => $http_code, 'data' => $result));
    }
}

/* End of file Contracts.php */
/* Location: ./app/Controllers/Contracts.php */