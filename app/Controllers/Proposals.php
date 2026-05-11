<?php

namespace App\Controllers;

class Proposals extends Security_Controller
{

    function __construct()
    {
        parent::__construct();
        $this->init_permission_checker("proposal");
    }

    private function validate_proposal_access($proposal_id = 0, $check_client = false)
    {
        if (!$this->permission_manager->can_manage_proposals($proposal_id, $check_client)) {
            app_redirect("forbidden");
        }
    }

    /* load proposal list view */

    function index($proposal_id = 0)
    {
        validate_numeric_value($proposal_id);
        $this->check_module_availability("module_proposal");
        $view_data["custom_field_headers"] = $this->Custom_fields_model->get_custom_field_headers_for_table("proposals", $this->login_user->is_admin, $this->login_user->user_type);
        $view_data["custom_field_filters"] = $this->Custom_fields_model->get_custom_field_filters("proposals", $this->login_user->is_admin, $this->login_user->user_type);

        $view_data['proposal_id'] = $proposal_id;
        $view_data["can_edit_proposals"] = $this->permission_manager->can_manage_proposals();

        if ($this->login_user->user_type === "staff") {
            if (!$this->permission_manager->can_view_proposals()) {
                app_redirect("forbidden");
            }

            return $this->template->rander("proposals/index", $view_data);
        } else {
            //client view
            if (!$this->can_client_access("proposal")) {
                app_redirect("forbidden");
            }

            $view_data["client_info"] = $this->Clients_model->get_one($this->login_user->client_id);
            $view_data['client_id'] = $this->login_user->client_id;
            $view_data['page_type'] = "full";

            return $this->template->rander("clients/proposals/client_portal", $view_data);
        }
    }

    /* load new proposal modal */

    function modal_form()
    {
        $this->validate_submitted_data(array(
            "id" => "numeric",
            "client_id" => "numeric"
        ));

        $id = $this->request->getPost('id');
        $is_clone = $this->request->getPost('is_clone');

        $this->validate_proposal_access($id);
        if (!$this->_is_proposal_editable($id, $is_clone)) {
            app_redirect("forbidden");
        }

        $client_id = $this->request->getPost('client_id');
        $view_data['model_info'] = $this->Proposals_model->get_one($id);

        $project_client_id = $client_id;
        if ($view_data['model_info']->client_id) {
            $project_client_id = $view_data['model_info']->client_id;
        }

        //make the drodown lists
        $view_data['taxes_dropdown'] = array("" => "-") + $this->Taxes_model->get_dropdown_list(array("title"));
        $view_data['clients_dropdown'] = $this->get_proposal_clients_and_leads_dropdown();

        //don't show clients dropdown for lead's proposal editing
        $client_info = $this->Clients_model->get_one($view_data['model_info']->client_id);
        if ($client_info->is_lead) {
            $client_id = $client_info->id;
        }

        $view_data['client_id'] = $client_id;

        //clone proposal data
        $view_data['is_clone'] = $is_clone;

        $view_data["custom_fields"] = $this->Custom_fields_model->get_combined_details("proposals", $view_data['model_info']->id, $this->login_user->is_admin, $this->login_user->user_type)->getResult();

        $view_data['companies_dropdown'] = $this->_get_companies_dropdown();
        if (!$view_data['model_info']->company_id) {
            $view_data['model_info']->company_id = get_default_company_id();
        }

        return $this->template->view('proposals/modal_form', $view_data);
    }

    private function get_proposal_clients_and_leads_dropdown()
    {
        $clients_dropdown = array("" => "-");
        $clients = $this->Clients_model->get_all_where(array("deleted" => 0), 0, 0, "is_lead")->getResult();

        foreach ($clients as $client) {
            $company_name = $client->is_lead ? (app_lang("lead") . ": " . $client->company_name) : (app_lang("client") . ": " . $client->company_name);
            $clients_dropdown[$client->id] = $company_name;
        }

        return $clients_dropdown;
    }

    function save_view()
    {
        $this->validate_submitted_data(array(
            "id" => "required|numeric"
        ));

        $id = $this->request->getPost("id");

        $this->validate_proposal_access($id);

        $proposal_data = array(
            "content" => decode_ajax_post_data($this->request->getPost('view'))
        );

        $this->Proposals_model->ci_save($proposal_data, $id);

        echo json_encode(array("success" => true, 'message' => app_lang('record_saved')));
    }

    /* add, edit or clone an proposal */

    function save()
    {
        $this->validate_submitted_data(array(
            "id" => "numeric",
            "proposal_client_id" => "required|numeric",
            "proposal_date" => "required",
            "valid_until" => "required"
        ));

        $client_id = $this->request->getPost('proposal_client_id');
        $id = $this->request->getPost('id');
        $is_clone = $this->request->getPost('is_clone');

        $this->validate_proposal_access($id);
        if (!$this->_is_proposal_editable($id, $is_clone)) {
            app_redirect("forbidden");
        }

        $proposal_data = array(
            "client_id" => $client_id,
            "proposal_date" => $this->request->getPost('proposal_date'),
            "valid_until" => $this->request->getPost('valid_until'),
            "tax_id" => $this->request->getPost('tax_id') ? $this->request->getPost('tax_id') : 0,
            "tax_id2" => $this->request->getPost('tax_id2') ? $this->request->getPost('tax_id2') : 0,
            "company_id" => $this->request->getPost('company_id') ? $this->request->getPost('company_id') : get_default_company_id(),
            "note" => $this->request->getPost('proposal_note')
        );

        //save random code for new proposal
        if (!$id) {
            $proposal_data["created_by"] = $this->login_user->id;
            $proposal_data["public_key"] = make_random_string();

            //add default template
            if (get_setting("default_proposal_template")) {
                $Proposal_templates_model = model("App\\Models\\Proposal_templates_model");
                $proposal_data["content"] = $Proposal_templates_model->get_one(get_setting("default_proposal_template"))->template;
            }
        }

        $main_proposal_id = "";
        if ($is_clone && $id) {
            $main_proposal_id = $id; //store main proposal id to get items later
            $id = ""; //on cloning proposal, save as new
            //save discount when cloning
            $main_proposal_info = $this->Proposals_model->get_one($main_proposal_id);
            $proposal_data["discount_amount"] = $main_proposal_info->discount_amount;
            $proposal_data["discount_amount_type"] = $main_proposal_info->discount_amount_type;
            $proposal_data["discount_type"] = $main_proposal_info->discount_type;
            $proposal_data["content"] = $main_proposal_info->content;
            $proposal_data["public_key"] = make_random_string();
            $proposal_data["created_by"] = $this->login_user->id;
        }

        $proposal_id = $this->Proposals_model->ci_save($proposal_data, $id);
        if ($proposal_id) {

            if ($is_clone && $main_proposal_id) {
                //add proposal items

                save_custom_fields("proposals", $proposal_id, 1, "staff"); //we have to keep this regarding as an admin user because non-admin user also can acquire the access to clone a proposal

                $proposal_items = $this->Proposal_items_model->get_all_where(array("proposal_id" => $main_proposal_id, "deleted" => 0))->getResult();

                foreach ($proposal_items as $proposal_item) {
                    //prepare new proposal item data
                    $proposal_item_data = (array) $proposal_item;
                    unset($proposal_item_data["id"]);
                    $proposal_item_data['proposal_id'] = $proposal_id;

                    $proposal_item = $this->Proposal_items_model->ci_save($proposal_item_data);
                }
            } else {
                save_custom_fields("proposals", $proposal_id, $this->login_user->is_admin, $this->login_user->user_type);
            }

            echo json_encode(array("success" => true, "data" => $this->_row_data($proposal_id), 'id' => $proposal_id, 'message' => app_lang('record_saved')));
        } else {
            echo json_encode(array("success" => false, 'message' => app_lang('error_occurred')));
        }
    }

    //update proposal status
    function update_proposal_status($proposal_id, $status)
    {
        if ($proposal_id && $status) {
            validate_numeric_value($proposal_id);
            $proposal_info = $this->Proposals_model->get_one($proposal_id);
            $this->validate_proposal_access($proposal_id, true);

            if ($this->login_user->user_type == "client") {
                //updating by client
                //client can only update the status once and the value should be either accepted or declined
                if ($proposal_info->status == "sent" && ($status == "accepted" || $status == "declined")) {

                    $proposal_data = array("status" => $status);
                    if ($status == "accepted") {
                        $proposal_data["accepted_by"] = $this->login_user->id;
                    }

                    $proposal_id = $this->Proposals_model->ci_save($proposal_data, $proposal_id);

                    //create notification
                    if ($status == "accepted") {
                        log_notification("proposal_accepted", array("proposal_id" => $proposal_id));
                    } else if ($status == "declined") {
                        log_notification("proposal_rejected", array("proposal_id" => $proposal_id));
                    }
                }
            } else {
                //updating by team members
                if ($status == "accepted" || $status == "declined" || $status == "sent") {
                    $proposal_data = array("status" => $status);
                    $proposal_id = $this->Proposals_model->ci_save($proposal_data, $proposal_id);
                }
            }
        }
    }

    /* delete or undo an proposal */

    function delete()
    {
        $this->validate_submitted_data(array(
            "id" => "required|numeric"
        ));

        $id = $this->request->getPost('id');
        $this->validate_proposal_access($id);

        if ($this->Proposals_model->delete($id)) {
            //delete signature file
            $proposal_info = $this->Proposals_model->get_one($id);
            $signer_info = @unserialize($proposal_info->meta_data);
            if ($signer_info && is_array($signer_info) && get_array_value($signer_info, "signature")) {
                $signature_file = unserialize(get_array_value($signer_info, "signature"));
                delete_app_files(get_setting("timeline_file_path"), $signature_file);
            }

            echo json_encode(array("success" => true, 'message' => app_lang('record_deleted')));
        } else {
            echo json_encode(array("success" => false, 'message' => app_lang('record_cannot_be_deleted')));
        }
    }

    /* list of proposals, prepared for datatable  */

    function list_data($is_mobile = 0)
    {
        validate_numeric_value($is_mobile);
        if (!$this->permission_manager->can_view_proposals()) {
            app_redirect("forbidden");
        }

        $custom_fields = $this->Custom_fields_model->get_available_fields_for_table("proposals", $this->login_user->is_admin, $this->login_user->user_type);

        $options = array(
            "status" => $this->request->getPost("status"),
            "start_date" => $this->request->getPost("start_date"),
            "end_date" => $this->request->getPost("end_date"),
            "last_email_seen_start_date" => $this->request->getPost("last_email_seen_start_date"),
            "last_email_seen_end_date" => $this->request->getPost("last_email_seen_end_date"),
            "last_preview_seen_start_date" => $this->request->getPost("last_preview_seen_start_date"),
            "last_preview_seen_end_date" => $this->request->getPost("last_preview_seen_end_date"),
            "custom_fields" => $custom_fields,
            "custom_field_filter" => $this->prepare_custom_field_filter_values("proposals", $this->login_user->is_admin, $this->login_user->user_type),
            "show_own_proposals_only_user_id" => $this->show_own_proposals_only_user_id(),
            "show_own_client_proposals_user_id" => $this->show_own_clients_proposals_user_id(),
            "show_own_lead_proposals_user_id" => $this->show_own_leads_proposals_user_id(),
            "show_own_clients_and_leads_proposals_user_id" => $this->show_own_clients_and_leads_proposals_user_id()
        );

        $list_data = $this->Proposals_model->get_details($options)->getResult();
        $getResult = array();
        foreach ($list_data as $data) {
            $getResult[] = $this->_make_row($data, $custom_fields, $is_mobile);
        }

        echo json_encode(array("data" => $getResult));
    }

    /* list of proposal of a specific client, prepared for datatable  */

    function proposal_list_data_of_client($client_id)
    {
        validate_numeric_value($client_id);
        $this->access_only_allowed_members_or_client_contact($client_id);

        $custom_fields = $this->Custom_fields_model->get_available_fields_for_table("proposals", $this->login_user->is_admin, $this->login_user->user_type);

        $options = array("client_id" => $client_id, "status" => $this->request->getPost("status"), "custom_fields" => $custom_fields, "custom_field_filter" => $this->prepare_custom_field_filter_values("proposals", $this->login_user->is_admin, $this->login_user->user_type));

        if ($this->login_user->user_type == "client") {
            //don't show draft proposals to clients.
            $options["exclude_draft"] = true;
        }

        $list_data = $this->Proposals_model->get_details($options)->getResult();
        $getResult = array();
        foreach ($list_data as $data) {
            $getResult[] = $this->_make_row($data, $custom_fields);
        }
        echo json_encode(array("data" => $getResult));
    }

    /* return a row of proposal list table */

    private function _row_data($id)
    {
        $custom_fields = $this->Custom_fields_model->get_available_fields_for_table("proposals", $this->login_user->is_admin, $this->login_user->user_type);

        $options = array("id" => $id, "custom_fields" => $custom_fields);
        $data = $this->Proposals_model->get_details($options)->getRow();
        return $this->_make_row($data, $custom_fields);
    }

    /* prepare a row of proposal list table */

    private function _make_row($data, $custom_fields, $is_mobile = 0)
    {
        $proposal_url = "";
        $can_manage_proposals = $this->permission_manager->can_manage_proposals($data->id);
        if ($this->login_user->user_type == "staff" && $can_manage_proposals) {
            $proposal_url = anchor(get_uri("proposals/view/" . $data->id), get_proposal_id($data->id));
        } else {
            //for client client
            $proposal_url = anchor(get_uri("proposals/preview/" . $data->id), get_proposal_id($data->id));
        }

        $client = anchor(get_uri("clients/view/" . $data->client_id), $data->company_name ? $data->company_name : "");
        if ($data->is_lead) {
            $client = anchor(get_uri("leads/view/" . $data->client_id), $data->company_name ? $data->company_name : "");
        }

        $last_email_read_time = "-";
        if ($data->last_email_read_time) {
            $last_email_read_time = format_to_relative_time($data->last_email_read_time);
        }

        $last_preview_seen = "-";
        if ($data->last_preview_seen) {
            $last_preview_seen = format_to_relative_time($data->last_preview_seen);
        }

        $proposal_status = $this->_get_proposal_status_label($data);

        if ($is_mobile) {
            $title_content = "
                            <div class='text-default'>
                                <div class='clearfix'>
                                    <span class='truncate-ellipsis w60p float-start'>
                                        <span class='fw-bold'>" . get_proposal_id($data->id) . "</span>
                                    </span>
                                    <small class='text-off float-end'>" . to_currency($data->proposal_value, $data->currency_symbol) . "</small>
                                </div>
                                <div class='clearfix'>
                                    <div class='float-start'>" . ($data->company_name ? $data->company_name : "-") . "</div>
                                    <div class='float-end'>" . format_to_date($data->proposal_date, false) . "</div>
                                </div>
                                <div class='clearfix'>
                                    " . $proposal_status . "
                                    <div class='float-end spinning-btn'></div>
                                </div>
                            </div>";

            $link = js_anchor($title_content, array(
                "class" => "box-label",
                "data-action-url" => get_uri("proposals/view/" . $data->id),
                "data-action" => "load_compact_view",
                "data-compact_view_id" => $data->id
            ));

            $proposal_url = "<div class='box-wrapper mini-list-item'>" . $link . "</div>";
        }

        $row_data = array(
            $proposal_url,
            $client,
            $data->proposal_date,
            format_to_date($data->proposal_date, false),
            $data->valid_until,
            format_to_date($data->valid_until, false),
            $last_email_read_time,
            $last_preview_seen,
            to_currency($data->proposal_value, $data->currency_symbol),
            $proposal_status,
        );

        $comment_link = "";

        $total_comments = "";
        if ($data->total_comments) {
            $total_comments = $data->total_comments;
        }

        if (get_setting("enable_comments_on_proposals") && $data->status !== "draft") {
            $comment_link = modal_anchor(get_uri("proposals/comment_modal_form"), $total_comments . " <i data-feather='message-circle' class='icon-16'></i>", array("class" => "text-muted", "title" => app_lang("proposal") . " #" . $data->id . " " . app_lang("comments"), "data-post-proposal_id" => $data->id));
        }

        $row_data[] = $comment_link;

        foreach ($custom_fields as $field) {
            $cf_id = "cfv_" . $field->id;
            $row_data[] = $this->template->view("custom_fields/output_" . $field->field_type, array("value" => $data->$cf_id));
        }

        $edit = "";
        if ($this->_is_proposal_editable($data)) {
            $edit = '<li role="presentation">' . modal_anchor(get_uri("proposals/modal_form"), "<i data-feather='edit' class='icon-16 mr5'></i>" . app_lang('edit'), array("class" => "dropdown-item", "title" => app_lang('edit_proposal'), "data-post-id" => $data->id)) . '</li>';
        }

        $delete = '<li role="presentation">' . js_anchor("<i data-feather='x' class='icon-16 mr5'></i>" . app_lang('delete'), array('title' => app_lang('delete_proposal'), "class" => "dropdown-item", "data-id" => $data->id, "data-action-url" => get_uri("proposals/delete"), "data-action" => "delete-confirmation")) . '</li>';

        $action_options = '<span class="dropdown inline-block">
                            <button class="action-option dropdown-toggle mt0 mb0" type="button" data-bs-toggle="dropdown" aria-expanded="true" data-bs-display="static">
                                <i data-feather="more-horizontal" class="icon-16"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" role="menu">' . $edit . $delete . '</ul>
                        </span>';

        $proposal_public_url = anchor(get_uri("offer/preview/" . $data->id . "/" . $data->public_key), "<i data-feather='external-link' class='icon-16'></i>", array("class" => "action-option", "title" => app_lang('proposal') . " " . app_lang("url"), "target" => "_blank"));

        $compact_view_btn = "";
        if (!$is_mobile) {
            $compact_view_btn = anchor(get_uri("proposals/compact_view/" . $data->id), "<i data-feather='sidebar' class='icon-16'></i>", array("title" => "", "class" => "action-option"));
        }

        $row_data[] = $compact_view_btn . $proposal_public_url . $action_options;

        return $row_data;
    }

    //prepare proposal status label
    private function _get_proposal_status_label($proposal_info, $return_html = true, $extra_classes = "")
    {
        $proposal_status_class = "bg-secondary";

        //don't show sent status to client, change the status to 'new' from 'sent'

        if ($this->login_user->user_type == "client") {
            if ($proposal_info->status == "sent") {
                $proposal_info->status = "new";
            } else if ($proposal_info->status == "declined") {
                $proposal_info->status = "rejected";
            }
        }

        if ($proposal_info->status == "draft") {
            $proposal_status_class = "bg-secondary";
        } else if ($proposal_info->status == "declined" || $proposal_info->status == "rejected") {
            $proposal_status_class = "bg-danger";
        } else if ($proposal_info->status == "accepted") {
            $proposal_status_class = "bg-success";
        } else if ($proposal_info->status == "sent") {
            $proposal_status_class = "bg-primary";
        } else if ($proposal_info->status == "new") {
            $proposal_status_class = "bg-warning";
        }

        $proposal_status = "<span class='mt0 badge $proposal_status_class $extra_classes'>" . app_lang($proposal_info->status) . "</span>";
        if ($return_html) {
            return $proposal_status;
        } else {
            return $proposal_info->status;
        }
    }

    /* load proposal details view */

    function view($proposal_id = 0)
    {
        validate_numeric_value($proposal_id);
        $this->validate_proposal_access($proposal_id);

        if ($proposal_id) {

            $view_data = get_proposal_making_data($proposal_id);

            $sort_as_decending = get_setting("show_most_recent_proposal_comments_at_the_top");
            $comments_options = array(
                "proposal_id" => $proposal_id,
                "sort_as_decending" => $sort_as_decending
            );
            $view_data['comments'] = $this->Proposal_comments_model->get_details($comments_options)->getResult();
            $view_data["sort_as_decending"] = $sort_as_decending;

            if ($view_data) {
                $view_data['proposal_status_label'] = $this->_get_proposal_status_label($view_data["proposal_info"], true, "large rounded-pill");
                $view_data['proposal_status'] = $this->_get_proposal_status_label($view_data["proposal_info"], false);

                $view_data["can_create_projects"] = $this->can_create_projects();

                $access_info = $this->get_access_info("invoice");
                $view_data["show_invoice_option"] = (get_setting("module_invoice") && $access_info->access_type == "all") ? true : false;

                $access_info = $this->get_access_info("estimate");
                $view_data["show_estimate_option"] = (get_setting("module_estimate") && $access_info->access_type == "all") ? true : false;

                $access_contract = $this->get_access_info("contract");
                $view_data["show_contract_option"] = (get_setting("module_contract") && $access_contract->access_type == "all") ? true : false;

                $view_data["proposal_id"] = $proposal_id;
                $view_data["is_proposal_editable"] = $this->_is_proposal_editable($proposal_id);

                $view_data["custom_field_headers_of_task"] = $this->Custom_fields_model->get_custom_field_headers_for_table("tasks", $this->login_user->is_admin, $this->login_user->user_type);

                $view_type = $this->request->getPost('view_type');
                if ($view_type == "proposal_meta") {
                    echo json_encode(array(
                        "success" => true,
                        "top_bar" => $this->template->view("proposals/proposal_top_bar",  $view_data),
                    ));
                } else if ($view_type == "compact_view") {
                    echo json_encode(array(
                        "success" => true,
                        "content" => $this->template->view("proposals/view",  $view_data),
                    ));
                } else {
                    return $this->template->rander("proposals/view", $view_data);
                }
            } else {
                show_404();
            }
        }
    }

    /* proposal total section */

    private function _get_proposal_total_view($proposal_id = 0)
    {
        $view_data["proposal_total_summary"] = $this->Proposals_model->get_proposal_total_summary($proposal_id);
        $view_data["proposal_id"] = $proposal_id;
        $view_data["is_proposal_editable"] = $this->_is_proposal_editable($proposal_id);
        return $this->template->view('proposals/proposal_total_section', $view_data);
    }

    /* load discount modal */

    function discount_modal_form()
    {
        $this->validate_submitted_data(array(
            "proposal_id" => "required|numeric"
        ));

        $proposal_id = $this->request->getPost('proposal_id');
        $this->validate_proposal_access($proposal_id);
        if (!$this->_is_proposal_editable($proposal_id)) {
            app_redirect("forbidden");
        }

        $view_data['model_info'] = $this->Proposals_model->get_one($proposal_id);

        return $this->template->view('proposals/discount_modal_form', $view_data);
    }

    /* save discount */

    function save_discount()
    {
        $this->validate_submitted_data(array(
            "proposal_id" => "required|numeric",
            "discount_type" => "required",
            "discount_amount" => "numeric",
            "discount_amount_type" => "required"
        ));

        $proposal_id = $this->request->getPost('proposal_id');
        $this->validate_proposal_access($proposal_id);
        if (!$this->_is_proposal_editable($proposal_id)) {
            app_redirect("forbidden");
        }

        $data = array(
            "discount_type" => $this->request->getPost('discount_type'),
            "discount_amount" => $this->request->getPost('discount_amount'),
            "discount_amount_type" => $this->request->getPost('discount_amount_type')
        );

        $data = clean_data($data);

        $save_data = $this->Proposals_model->ci_save($data, $proposal_id);
        if ($save_data) {
            echo json_encode(array("success" => true, "proposal_total_view" => $this->_get_proposal_total_view($proposal_id), 'message' => app_lang('record_saved'), "proposal_id" => $proposal_id));
        } else {
            echo json_encode(array("success" => false, 'message' => app_lang('error_occurred')));
        }
    }

    /* load item modal */

    function item_modal_form()
    {
        $this->validate_submitted_data(array(
            "id" => "numeric"
        ));

        $proposal_id = $this->request->getPost('proposal_id');
        $this->validate_proposal_access($proposal_id);
        if (!$this->_is_proposal_editable($proposal_id)) {
            app_redirect("forbidden");
        }

        $view_data['model_info'] = $this->Proposal_items_model->get_one($this->request->getPost('id'));
        if (!$proposal_id) {
            $proposal_id = $view_data['model_info']->proposal_id;
        }
        $view_data['proposal_id'] = $proposal_id;
        return $this->template->view('proposals/item_modal_form', $view_data);
    }

    /* add or edit an proposal item */

    function save_item()
    {
        $this->validate_submitted_data(array(
            "id" => "numeric",
            "proposal_id" => "required|numeric"
        ));

        $proposal_id = $this->request->getPost('proposal_id');
        $this->validate_proposal_access($proposal_id);
        if (!$this->_is_proposal_editable($proposal_id)) {
            app_redirect("forbidden");
        }

        $id = $this->request->getPost('id');
        $rate = unformat_currency($this->request->getPost('proposal_item_rate'));
        $quantity = unformat_currency($this->request->getPost('proposal_item_quantity'));
        $proposal_item_title = $this->request->getPost('proposal_item_title');
        $item_id = 0;

        if (!$id) {
            //on adding item for the first time, get the id to store
            $item_id = $this->request->getPost('item_id');
        }

        //check if the add_new_item flag is on, if so, add the item to libary.
        $add_new_item_to_library = $this->request->getPost('add_new_item_to_library');
        if ($add_new_item_to_library) {
            $library_item_data = array(
                "title" => $proposal_item_title,
                "description" => $this->request->getPost('proposal_item_description'),
                "unit_type" => $this->request->getPost('proposal_unit_type'),
                "rate" => unformat_currency($this->request->getPost('proposal_item_rate'))
            );
            $item_id = $this->Items_model->ci_save($library_item_data);
        }

        $proposal_item_data = array(
            "proposal_id" => $proposal_id,
            "title" => $this->request->getPost('proposal_item_title'),
            "description" => $this->request->getPost('proposal_item_description'),
            "quantity" => $quantity,
            "unit_type" => $this->request->getPost('proposal_unit_type'),
            "rate" => unformat_currency($this->request->getPost('proposal_item_rate')),
            "total" => $rate * $quantity,
        );

        if ($item_id) {
            $proposal_item_data["item_id"] = $item_id;
        }

        $proposal_item_id = $this->Proposal_items_model->ci_save($proposal_item_data, $id);
        if ($proposal_item_id) {
            $options = array("id" => $proposal_item_id);
            $item_info = $this->Proposal_items_model->get_details($options)->getRow();
            echo json_encode(array("success" => true, "proposal_id" => $item_info->proposal_id, "data" => $this->_make_item_row($item_info), "proposal_total_view" => $this->_get_proposal_total_view($item_info->proposal_id), 'id' => $proposal_item_id, 'message' => app_lang('record_saved')));
        } else {
            echo json_encode(array("success" => false, 'message' => app_lang('error_occurred')));
        }
    }

    /* delete or undo an proposal item */

    function delete_item()
    {
        $this->validate_submitted_data(array(
            "id" => "required|numeric"
        ));

        $id = $this->request->getPost('id');
        $item_info = $this->Proposal_items_model->get_one($id);
        $this->validate_proposal_access($item_info->proposal_id);
        if (!$this->_is_proposal_editable($item_info->proposal_id)) {
            app_redirect("forbidden");
        }

        if ($this->request->getPost('undo')) {
            if ($this->Proposal_items_model->delete($id, true)) {
                $options = array("id" => $id);
                $item_info = $this->Proposal_items_model->get_details($options)->getRow();
                echo json_encode(array("success" => true, "proposal_id" => $item_info->proposal_id, "data" => $this->_make_item_row($item_info), "proposal_total_view" => $this->_get_proposal_total_view($item_info->proposal_id), "message" => app_lang('record_undone')));
            } else {
                echo json_encode(array("success" => false, app_lang('error_occurred')));
            }
        } else {
            if ($this->Proposal_items_model->delete($id)) {
                $item_info = $this->Proposal_items_model->get_one($id);
                echo json_encode(array("success" => true, "proposal_id" => $item_info->proposal_id, "proposal_total_view" => $this->_get_proposal_total_view($item_info->proposal_id), 'message' => app_lang('record_deleted')));
            } else {
                echo json_encode(array("success" => false, 'message' => app_lang('record_cannot_be_deleted')));
            }
        }
    }

    /* list of proposal items, prepared for datatable  */

    function item_list_data($proposal_id = 0)
    {
        validate_numeric_value($proposal_id);
        $this->validate_proposal_access($proposal_id);

        $list_data = $this->Proposal_items_model->get_details(array("proposal_id" => $proposal_id))->getResult();
        $getResult = array();
        foreach ($list_data as $data) {
            $getResult[] = $this->_make_item_row($data);
        }
        echo json_encode(array("data" => $getResult));
    }

    /* prepare a row of proposal item list table */

    private function _make_item_row($data)
    {
        $move_icon = "";
        $desc_style = "";

        if ($this->_is_proposal_editable($data->proposal_id)) {
            $move_icon = "<div class='float-start move-icon'><i data-feather='menu' class='icon-16'></i></div>";
            $desc_style = " style='margin-left:30px'";
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
            modal_anchor(get_uri("proposals/item_modal_form"), "<i data-feather='edit' class='icon-16'></i>", array("class" => "edit", "title" => app_lang('edit_proposal'), "data-post-id" => $data->id, "data-post-proposal_id" => $data->proposal_id))
                . js_anchor("<i data-feather='x' class='icon-16'></i>", array('title' => app_lang('delete'), "class" => "delete", "data-id" => $data->id, "data-action-url" => get_uri("proposals/delete_item"), "data-action" => "delete"))
        );
    }

    /* prepare suggestion of proposal item */

    function get_proposal_item_suggestion()
    {
        $key = $this->request->getPost("q");
        $suggestion = array();

        $items = $this->Invoice_items_model->get_item_suggestion($key);

        foreach ($items as $item) {
            $suggestion[] = array("id" => $item->id, "text" => $item->title);
        }

        $suggestion[] = array("id" => "+", "text" => "+ " . app_lang("create_new_item"));

        echo json_encode($suggestion);
    }

    function get_proposal_item_info_suggestion()
    {
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
    function preview($proposal_id = 0, $show_close_preview = false, $is_editor_preview = false)
    {
        validate_numeric_value($proposal_id);

        $view_data = array();

        if ($proposal_id) {

            $proposal_data = get_proposal_making_data($proposal_id);
            $this->_check_proposal_access_permission($proposal_data);

            //get the label of the proposal
            $proposal_info = get_array_value($proposal_data, "proposal_info");

            if ($this->login_user->user_type == "client" && $proposal_info->status == "sent") {

                $this->Proposals_model->update_proposal_preview_activity($proposal_id);
                log_notification("proposal_preview_opened", array("proposal_id" => $proposal_id), $this->login_user->id);
            }

            $proposal_data['proposal_status_label'] = $this->_get_proposal_status_label($proposal_info);

            $view_data['proposal_preview'] = prepare_proposal_view($proposal_data);

            //show a back button
            $view_data['show_close_preview'] = $show_close_preview && $this->login_user->user_type === "staff" ? true : false;

            $view_data['can_manage_proposals'] = $this->permission_manager->can_manage_proposals($proposal_info->id, true);

            $view_data['proposal_id'] = $proposal_id;

            $sort_as_decending = get_setting("show_most_recent_proposal_comments_at_the_top");
            $comments_options = array(
                "proposal_id" => $proposal_id,
                "sort_as_decending" => $sort_as_decending
            );
            $view_data['comments'] = $this->Proposal_comments_model->get_details($comments_options)->getResult();
            $view_data["sort_as_decending"] = $sort_as_decending;

            $view_data["has_pdf_access"] = $this->check_proposal_pdf_access_for_clients($this->login_user->user_type);

            if ($is_editor_preview) {
                $view_data["is_editor_preview"] = clean_data($is_editor_preview);
                return $this->template->view("proposals/proposal_preview", $view_data);
            } else {
                return $this->template->rander("proposals/proposal_preview", $view_data);
            }
        } else {
            show_404();
        }
    }

    private function _check_proposal_access_permission($proposal_data)
    {
        //check for valid proposal
        if (!$proposal_data) {
            show_404();
        }

        //check for security
        $proposal_info = get_array_value($proposal_data, "proposal_info");
        if ($this->login_user->user_type == "client") {
            if ($this->login_user->client_id != $proposal_info->client_id || $proposal_info->status === "draft" || !$this->can_client_access("proposal")) {
                app_redirect("forbidden");
            }
        } else {
            if (!$this->permission_manager->can_view_proposals($proposal_info->id)) {
                app_redirect("forbidden");
            }
        }
    }

    function send_proposal_modal_form($proposal_id)
    {
        validate_numeric_value($proposal_id);
        $this->validate_proposal_access($proposal_id);

        if ($proposal_id) {
            $options = array("id" => $proposal_id);
            $proposal_info = $this->Proposals_model->get_details($options)->getRow();
            $view_data['proposal_info'] = $proposal_info;

            $is_lead = $this->request->getPost('is_lead');
            if ($is_lead) {
                $contacts_options = array("user_type" => "lead", "client_id" => $proposal_info->client_id);
            } else {
                $contacts_options = array("user_type" => "client", "client_id" => $proposal_info->client_id);
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

            $template_data = $this->get_send_proposal_template($proposal_id, 0, "", $proposal_info, $primary_contact_info);
            $view_data['message'] = get_array_value($template_data, "message");
            $view_data['subject'] = get_array_value($template_data, "subject");
            $view_data["has_pdf_access"] = $this->check_proposal_pdf_access_for_clients();

            return $this->template->view('proposals/send_proposal_modal_form', $view_data);
        } else {
            show_404();
        }
    }

    function get_send_proposal_template($proposal_id = 0, $contact_id = 0, $return_type = "", $proposal_info = "", $contact_info = "")
    {
        $this->validate_proposal_access($proposal_id);

        validate_numeric_value($proposal_id);
        validate_numeric_value($contact_id);

        if (!$proposal_info) {
            $options = array("id" => $proposal_id);
            $proposal_info = $this->Proposals_model->get_details($options)->getRow();
        }

        if (!$contact_info) {
            $contact_info = $this->Users_model->get_one($contact_id);
        }

        $contact_language = $contact_info->language;

        $email_template = $this->Email_templates_model->get_final_template("proposal_sent", true);

        $parser_data["PROPOSAL_ID"] = $proposal_info->id;
        $parser_data["CONTACT_FIRST_NAME"] = $contact_info->first_name;
        $parser_data["CONTACT_LAST_NAME"] = $contact_info->last_name;
        $parser_data["PROPOSAL_URL"] = get_uri("proposals/preview/" . $proposal_info->id);
        $parser_data["PUBLIC_PROPOSAL_URL"] = get_uri("offer/preview/" . $proposal_info->id . "/" . $proposal_info->public_key);
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

    function send_proposal()
    {
        $this->validate_submitted_data(array(
            "id" => "required|numeric"
        ));

        $proposal_id = $this->request->getPost('id');
        $this->validate_proposal_access($proposal_id);

        $contact_id  = $this->request->getPost('contact_id');
        $cc          = $this->request->getPost('proposal_cc');
        $custom_bcc  = $this->request->getPost('proposal_bcc');
        $attach_pdf  = $this->request->getPost('attach_pdf');

        // ── Datos de la propuesta ────────────────────────────────────────
        $proposal_data = get_proposal_making_data($proposal_id);
        if (!$proposal_data) {
            echo json_encode(array('success' => false, 'message' => 'No se encontró la propuesta'));
            return;
        }

        $proposal_info  = $proposal_data['proposal_info'];
        $total_summary  = $proposal_data['proposal_total_summary'];
        $contact        = $this->Users_model->get_one($contact_id);

        if (!$contact->email) {
            echo json_encode(array('success' => false, 'message' => 'El contacto no tiene email'));
            return;
        }

        $to = $contact->email;

        // ── Email HTML corporativo Tictac ────────────────────────────────
        $subject = 'Presupuesto para ' . ($proposal_info->company_name ?? 'su proyecto') . ' - Tictac Comunicación';

        $fecha_propuesta = format_to_date($proposal_info->proposal_date, false);
        $valido_hasta    = format_to_date($proposal_info->valid_until, false);
        $total_str       = to_currency($total_summary->proposal_total, $total_summary->currency_symbol);
        $nombre_contacto = htmlspecialchars($contact->first_name . ' ' . $contact->last_name);

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
        .footer{background:#1a1a1a;color:white;padding:30px;text-align:center;font-size:13px;}
        .footer a{color:#d72173;text-decoration:none;}
        .contacto-info{margin-top:15px;line-height:1.8;}
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <img src="https://tictac-comunicacion.es/wp-content/uploads/2026/02/LOGO-1-2.png" alt="Tictac Comunicación">
            <h1>Tu Presupuesto Está Listo</h1>
        </div>
        <div class="content">
            <p>Estimado/a <strong>' . $nombre_contacto . '</strong>,</p>
            <p>Gracias por confiar en Tictac Comunicación Digital. Adjunto encontrarás el presupuesto detallado para tu proyecto con todos los servicios propuestos.</p>
            <div class="resumen-box">
                <strong>📋 Resumen del Presupuesto</strong><br><br>
                <strong>Fecha de emisión:</strong> ' . $fecha_propuesta . '<br>
                <strong>Válido hasta:</strong> ' . $valido_hasta . '<br>
                <div class="total-destacado">Total: ' . $total_str . '</div>
            </div>
            <p>Hemos diseñado esta propuesta pensando específicamente en tus necesidades y objetivos. Si tienes alguna duda o quieres comentar cualquier aspecto del presupuesto, estaremos encantados de atenderte.</p>
            <p><strong>¿Necesitas más información?</strong><br>No dudes en contactarnos. Estamos aquí para ayudarte.</p>
        </div>
        <div class="footer">
            <strong>Tictac Comunicación Digital SL</strong>
            <div class="contacto-info">
                📍 Plaza de los Carrillos, 5 · 14001 Córdoba<br>
                📞 <a href="tel:+34957048147">957 048 147</a><br>
                ✉ <a href="mailto:hola@tictac-comunicacion.es">hola@tictac-comunicacion.es</a><br>
                🌐 <a href="https://www.tictac-comunicacion.es" target="_blank">www.tictac-comunicacion.es</a>
            </div>
        </div>
    </div>
</body>
</html>';

        // ── Adjuntar PDF Tictac generado localmente ──────────────────────
        $attachments   = array();
        $tmp_pdf_path  = null;

        if ($attach_pdf) {
            $tmp_pdf_path = $this->_generate_tictac_pdf($proposal_id, 'save');

            if (!$tmp_pdf_path || !file_exists($tmp_pdf_path)) {
                echo json_encode(array('success' => false, 'message' => 'Error: No se pudo generar el PDF. Por favor, inténtalo de nuevo.'));
                return;
            }

            if (filesize($tmp_pdf_path) / 1000000 > 10) {
                @unlink($tmp_pdf_path);
                echo json_encode(array("success" => false, 'message' => app_lang("attachment_size_is_too_large")));
                return;
            }

            $attachments[] = array("file_path" => $tmp_pdf_path);
        }

        // ── Tracking de apertura de email ────────────────────────────────
        $now = get_current_utc_time();
        $event_tracker_data = array(
            "context"    => "proposal",
            "context_id" => $proposal_id,
            "event_type" => "proposal_email",
            "random_id"  => make_random_string(),
            "created_at" => $now
        );
        $event_tracker_model = model("App\\Models\\Event_tracker_model");
        $event_tracker_model->ci_save($event_tracker_data);

        $src      = get_uri('event_tracker/load/' . $event_tracker_data["random_id"]);
        $message .= "<img src='$src' alt='.' />";

        // ── BCC ──────────────────────────────────────────────────────────
        $default_bcc = get_setting('send_proposal_bcc_to');
        $bcc_emails  = "";
        if ($default_bcc && $custom_bcc) {
            $bcc_emails = $default_bcc . "," . $custom_bcc;
        } else if ($default_bcc) {
            $bcc_emails = $default_bcc;
        } else if ($custom_bcc) {
            $bcc_emails = $custom_bcc;
        }

        // ── Enviar ───────────────────────────────────────────────────────
        if (send_app_mail($to, $subject, $message, array("attachments" => $attachments, "cc" => $cc, "bcc" => $bcc_emails))) {

            // Limpiar archivo temporal
            if ($tmp_pdf_path && file_exists($tmp_pdf_path)) {
                @unlink($tmp_pdf_path);
            }

            // Cambiar estado a enviado
            $status_data = array("status" => "sent", "last_email_sent_date" => get_my_local_time());
            if ($this->Proposals_model->ci_save($status_data, $proposal_id)) {
                echo json_encode(array('success' => true, 'message' => app_lang("proposal_sent_message"), "proposal_id" => $proposal_id));
            }
        } else {
            if ($tmp_pdf_path && file_exists($tmp_pdf_path)) {
                @unlink($tmp_pdf_path);
            }
            echo json_encode(array('success' => false, 'message' => app_lang('error_occurred')));
        }
    }

    // ════════════════════════════════════════════════════════════════════
    // GENERADOR DE PDF TICTAC — NATIVO CON TCPDF
    // ════════════════════════════════════════════════════════════════════

    /**
     * Genera el PDF con el diseño Tictac usando TCPDF.
     *
     * @param int    $proposal_id  ID de la propuesta en el CRM
     * @param string $mode         'download' → fuerza descarga | 'save' → guarda en tmp y devuelve la ruta
     * @return string|void         Ruta al archivo temporal si $mode='save', void en otro caso
     */
    private function _generate_tictac_pdf($proposal_id, $mode = 'download')
    {
        // ── Cargar TCPDF ─────────────────────────────────────────────────
        $tcpdf_path = APPPATH . '../app/ThirdParty/tcpdf/tcpdf.php';
        if (!file_exists($tcpdf_path)) {
            // Fallback al TCPDF del dashboard
            $tcpdf_path = APPPATH . '../dashboard/tcpdf/tcpdf.php';
        }
        if (!file_exists($tcpdf_path)) {
            log_message('error', 'Tictac PDF: No se encontró TCPDF en ninguna ruta conocida');
            return null;
        }
        require_once($tcpdf_path);

        // TictacProposalPDF extiende TCPDF — debe cargarse DESPUÉS de tcpdf.php
        // El archivo está en namespace global (sin namespace) para evitar conflictos con CI4
        require_once(APPPATH . '../app/Libraries/TictacProposalPDF.php');

        // ── Obtener datos ────────────────────────────────────────────────
        $proposal_data  = get_proposal_making_data($proposal_id);
        if (!$proposal_data) {
            return null;
        }

        $proposal_info  = $proposal_data['proposal_info'];
        $client_info    = $proposal_data['client_info'];
        $items          = $proposal_data['proposal_items'];
        $total_summary  = $proposal_data['proposal_total_summary'];

        // ── Colores corporativos ─────────────────────────────────────────
        $brand_r = 215; $brand_g = 33; $brand_b = 115;

        // ── Helpers inline ───────────────────────────────────────────────
        $pdf_text_plain = function ($html) {
            if ($html === null) return '';
            $txt = (string)$html;
            $txt = preg_replace('~<style[^>]*>.*?</style>~is', '', $txt);
            $txt = preg_replace('~<script[^>]*>.*?</script>~is', '', $txt);
            $txt = preg_replace('~</t[dh]>~i', " | ", $txt);
            $txt = preg_replace('~</tr>~i', "\n", $txt);
            $txt = preg_replace('~<br\s*/?>~i', "\n", $txt);
            $txt = preg_replace('~</(?:p|div|h[1-6]|li)>~i', "\n", $txt);
            $txt = strip_tags($txt);
            $txt = html_entity_decode($txt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $txt = str_replace("\xC2\xA0", ' ', $txt);
            $txt = preg_replace("/[ \t]+/", " ", $txt);
            $txt = str_replace(["\r\n", "\r"], "\n", $txt);
            $txt = preg_replace("/\n{3,}/", "\n\n", $txt);
            return trim($txt);
        };

        $pdf_html_clean = function ($html) {
            if ($html === null || trim($html) === '' || trim($html) === '<p><br></p>') return '';
            $html = preg_replace('~<style[^>]*>.*?</style>~is', '', $html);
            $html = preg_replace('~<script[^>]*>.*?</script>~is', '', $html);
            $html = preg_replace('~<t[dh][^>]*>~i', '<p>', $html);
            $html = preg_replace('~</t[dh]>~i', '</p>', $html);
            $html = preg_replace('~</?tr[^>]*>~i', '', $html);
            $html = preg_replace('~</?t(?:able|body|head|foot)[^>]*>~i', '', $html);
            $html = preg_replace('~<br\s*/?>~i', "\n", $html);
            $html = preg_replace('~<div[^>]*>~i', '<p>', $html);
            $html = str_ireplace('</div>', '</p>', $html);
            $html = preg_replace('~<h[1-6][^>]*>~i', '<p><strong>', $html);
            $html = preg_replace('~</h[1-6]>~i', '</strong></p>', $html);
            $html = preg_replace('~<span[^>]*>~i', '', $html);
            $html = str_ireplace('</span>', '', $html);
            $html = preg_replace('~(<(?:p|strong|b|em|i|u|ul|ol|li))\s[^>]*>~i', '$1>', $html);
            $html = strip_tags($html, '<p><strong><b><em><i><u><ul><ol><li>');
            $html = preg_replace('~<p>\s*</p>~i', '', $html);
            $html = preg_replace('/\n{3,}/', "\n\n", $html);
            return trim($html);
        };

        $tiene_contenido = function ($html) use ($pdf_text_plain) {
            if ($html === null) return false;
            return $pdf_text_plain($html) !== '';
        };

        // ── Instanciar PDF ───────────────────────────────────────────────
        $pdf = new \TictacProposalPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->brand_r   = $brand_r;
        $pdf->brand_g   = $brand_g;
        $pdf->brand_b   = $brand_b;

        // Ruta al logo blanco (ajusta si está en otra ubicación)
        $logo_candidatos = [
            APPPATH . '../assets/images/logoblanco.png',
            APPPATH . '../uploads/logoblanco.png',
            APPPATH . '../dashboard/assets/img/logoblanco.png',
        ];
        foreach ($logo_candidatos as $candidato) {
            if (file_exists($candidato)) {
                $pdf->logo_path = $candidato;
                break;
            }
        }

        $pdf->SetAutoPageBreak(true, 20);
        $pdf->SetCreator('Tictac Comunicación');
        $pdf->SetAuthor('Tictac Comunicación Digital SL');
        $pdf->SetTitle('Presupuesto ' . get_proposal_id($proposal_info->id));
        $pdf->AddPage();
        $pdf->SetMargins(15, 58, 15);
        $pdf->SetY(58);

        $pageW    = $pdf->getPageWidth();
        $contentW = $pageW - 30;
        $colW     = 85;
        $startX   = 15;
        $startY   = $pdf->GetY();

        // ════════════════════════════════════════════════════════════════
        // BLOQUE INFO: dos cajas side-by-side con borde lateral de acento
        // ════════════════════════════════════════════════════════════════

        // ── Caja izquierda: Datos de la Propuesta ────────────────────────
        $cajaH = 42;

        // Fondo gris muy suave
        $pdf->SetFillColor(250, 250, 250);
        $pdf->SetDrawColor(230, 230, 230);
        $pdf->Rect($startX, $startY, $colW, $cajaH, 'DF');

        // Borde izquierdo de acento (3mm de grosor)
        $pdf->SetFillColor($brand_r, $brand_g, $brand_b);
        $pdf->Rect($startX, $startY, 3, $cajaH, 'F');

        // Título
        $pdf->SetXY($startX + 7, $startY + 5);
        $pdf->SetTextColor($brand_r, $brand_g, $brand_b);
        $pdf->SetFont('Helvetica', 'B', 7.5);
        $pdf->Cell($colW - 10, 4, strtoupper('Datos de la Propuesta'), 0, 1, 'L');

        // Separador fino
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->SetLineWidth(0.2);
        $pdf->Line($startX + 7, $pdf->GetY() + 1, $startX + $colW - 3, $pdf->GetY() + 1);
        $pdf->Ln(4);

        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetX($startX + 7);
        $pdf->SetFont('Helvetica', 'B', 8); $pdf->Cell(28, 5, 'Referencia:', 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 8);  $pdf->Cell(0, 5, get_proposal_id($proposal_info->id), 0, 1, 'L');

        $pdf->SetX($startX + 7);
        $pdf->SetFont('Helvetica', 'B', 8); $pdf->Cell(28, 5, 'Fecha:', 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 8);  $pdf->Cell(0, 5, !empty($proposal_info->proposal_date) ? date('d/m/Y', strtotime($proposal_info->proposal_date)) : '', 0, 1, 'L');

        $pdf->SetX($startX + 7);
        $pdf->SetFont('Helvetica', 'B', 8); $pdf->Cell(28, 5, 'Válida hasta:', 0, 0, 'L');
        $pdf->SetFont('Helvetica', '', 8);  $pdf->Cell(0, 5, !empty($proposal_info->valid_until) ? date('d/m/Y', strtotime($proposal_info->valid_until)) : '', 0, 1, 'L');

        // ── Caja derecha: Info Cliente ────────────────────────────────────
        $rightX    = $startX + $colW + 5;
        $rightColW = $colW;
        $labelW    = 22;
        $valueW    = $rightColW - $labelW - 10;

        $campos_cliente = [];
        $campos_cliente[] = ['Empresa:', $client_info->company_name ?? ''];
        $direccion = trim($client_info->address ?? '');
        if ($direccion !== '') $campos_cliente[] = ['Dirección:', $direccion];
        $ciudad = trim($client_info->city ?? '');
        $cp     = trim($client_info->zip ?? '');
        $ciudadCompleta = $ciudad . ($cp !== '' ? ', ' . $cp : '');
        if (trim($ciudadCompleta, ', ') !== '') $campos_cliente[] = ['Ciudad:', $ciudadCompleta];
        $pais = trim($client_info->country ?? '');
        if ($pais !== '') $campos_cliente[] = ['País:', $pais];
        $cif = trim($client_info->vat_number ?? '');
        if ($cif !== '') $campos_cliente[] = ['CIF/NIF:', $cif];

        $pdf->SetFont('Helvetica', '', 8);
        $totalClienteH = 16;
        foreach ($campos_cliente as $campo) {
            $lineas = max(1, ceil($pdf->GetStringWidth($campo[1]) / $valueW));
            $totalClienteH += $lineas * 5;
        }
        $cajaClienteH = max($cajaH, $totalClienteH + 4);

        // Fondo
        $pdf->SetFillColor(250, 250, 250);
        $pdf->SetDrawColor(230, 230, 230);
        $pdf->Rect($rightX, $startY, $rightColW, $cajaClienteH, 'DF');

        // Borde izquierdo acento
        $pdf->SetFillColor($brand_r, $brand_g, $brand_b);
        $pdf->Rect($rightX, $startY, 3, $cajaClienteH, 'F');

        // Título
        $pdf->SetXY($rightX + 7, $startY + 5);
        $pdf->SetTextColor($brand_r, $brand_g, $brand_b);
        $pdf->SetFont('Helvetica', 'B', 7.5);
        $pdf->Cell($rightColW - 10, 4, strtoupper('Información del Cliente'), 0, 1, 'L');

        $pdf->SetDrawColor(220, 220, 220);
        $pdf->SetLineWidth(0.2);
        $pdf->Line($rightX + 7, $pdf->GetY() + 1, $rightX + $rightColW - 3, $pdf->GetY() + 1);
        $pdf->Ln(4);

        foreach ($campos_cliente as $campo) {
            $curY = $pdf->GetY();
            $pdf->SetXY($rightX + 7, $curY);
            $pdf->SetTextColor(80, 80, 80);
            $pdf->SetFont('Helvetica', 'B', 8);
            $pdf->Cell($labelW, 5, $campo[0], 0, 0, 'L');
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetXY($rightX + 7 + $labelW, $curY);
            $pdf->MultiCell($valueW, 5, $campo[1] ?? '', 0, 'L', false, 1);
        }

        $afterClienteY = $pdf->GetY();
        $afterDatosY   = $startY + $cajaH;
        $pdf->SetY(max($afterClienteY, $afterDatosY));

        // ════════════════════════════════════════════════════════════════
        // BANDA INTRODUCTORIA
        // ════════════════════════════════════════════════════════════════
        $pdf->SetY($pdf->GetY() + 8);

        // Línea decorativa con punto rosa
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
        $pdf->Cell($contentW, 7, 'Propuesta Económica', 0, 1, 'L');
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->SetX(15);
        $pdf->Cell($contentW, 5, 'Servicios incluidos en esta propuesta  ·  Precios sin IVA (' . number_format($total_summary->tax_percentage ?? 21, 0) . '%)', 0, 1, 'L');

        $pdf->SetY($pdf->GetY() + 5);

        // ════════════════════════════════════════════════════════════════
        // TABLA DE ARTÍCULOS
        // ════════════════════════════════════════════════════════════════

        $cArticulo = 80;
        $cCantidad = 22;
        $cTarifa   = 28;
        $cTotal    = 30;
        $tableW    = $cArticulo + $cCantidad + $cTarifa + $cTotal;

        $printTableHeader = function () use ($pdf, $cArticulo, $cCantidad, $cTarifa, $cTotal, $tableW, $brand_r, $brand_g, $brand_b) {
            $headerY = $pdf->GetY();
            // Fondo rosa corporativo
            $pdf->SetFillColor($brand_r, $brand_g, $brand_b);
            $pdf->Rect(15, $headerY, $tableW, 7, 'F');
            $pdf->SetXY(18, $headerY + 0.8);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('Helvetica', 'B', 7.5);
            $pdf->Cell($cArticulo - 3, 6, 'SERVICIO / DESCRIPCIÓN', 0, 0, 'L');
            $pdf->Cell($cCantidad,     6, 'CANT.',   0, 0, 'C');
            $pdf->Cell($cTarifa,       6, 'PRECIO',  0, 0, 'R');
            $pdf->Cell($cTotal - 3,    6, 'TOTAL',   0, 1, 'R');
            $pdf->SetTextColor(51, 51, 51);
        };

        $printTableHeader();

        $rowAlternate = false;
        foreach ($items as $item) {
            $cantidad = floatval($item->quantity ?? 0);
            $precio   = floatval($item->rate ?? 0);
            $unidad   = (string)($item->unit_type ?? '');
            $total    = $cantidad * $precio;
            $nombre   = $pdf_text_plain($item->title ?? '');

            $descRaw       = $item->description ?? '';
            $descHtmlClean = $pdf_html_clean($descRaw);
            $descPlain     = $pdf_text_plain($descRaw);
            $hayDesc       = ($descHtmlClean !== '' && trim(strip_tags($descHtmlClean)) !== '') || $descPlain !== '';

            $cabH        = 7;
            $bottomLimit = $pdf->getPageHeight() - 25;

            if (($pdf->GetY() + $cabH) > $bottomLimit) {
                $pdf->AddPage();
                $printTableHeader();
                $rowAlternate = false;
            }

            $rowY = $pdf->GetY();

            // Fondo alternado muy suave
            if ($rowAlternate) {
                $pdf->SetFillColor(250, 250, 252);
                $pdf->Rect(15, $rowY, $tableW, $cabH + ($hayDesc ? 8 : 0), 'F');
            }

            // Barra lateral izquierda de acento en cada fila (1px)
            $pdf->SetFillColor($brand_r, $brand_g, $brand_b);
            $pdf->Rect(15, $rowY, 1.5, $cabH, 'F');

            // Nombre en rosa oscuro
            $pdf->SetXY(19, $rowY + 1.5);
            $pdf->SetFont('Helvetica', 'B', 8.5);
            $pdf->SetTextColor(40, 40, 40);
            $pdf->Cell($cArticulo - 4, 5, $nombre, 0, 0, 'L');

            // Cantidad
            $cantTexto = number_format($cantidad, 2, ',', '.') . ($unidad ? ' ' . $unidad : '');
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetTextColor(90, 90, 90);
            $pdf->Cell($cCantidad, 5, $cantTexto, 0, 0, 'C');

            // Tarifa
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->Cell($cTarifa, 5, number_format($precio, 2, ',', '.') . ' €', 0, 0, 'R');

            // Total en rosa
            $totalX = 15 + $cArticulo + $cCantidad + $cTarifa;
            $pdf->SetXY($totalX, $rowY + 1.5);
            $pdf->SetFont('Helvetica', 'B', 8.5);
            $pdf->SetTextColor($brand_r, $brand_g, $brand_b);
            $pdf->Cell($cTotal - 3, 5, number_format($total, 2, ',', '.') . ' €', 0, 1, 'R');

            // Descripción
            if ($hayDesc) {
                $pdf->SetFont('Helvetica', '', 7.5);
                $pdf->SetTextColor(110, 110, 110);
                $pdf->SetXY(19, $rowY + $cabH);
                $pdf->SetLeftMargin(19);
                $pdf->SetRightMargin(15);

                if ($descHtmlClean !== '') {
                    $pdf->SetX(19);
                    $pdf->writeHTML($descHtmlClean, true, false, true, false, 'L');
                } else {
                    $pdf->SetX(19);
                    $pdf->MultiCell($tableW - 4, 3.8, $descPlain, 0, 'L');
                }

                $pdf->SetLeftMargin(15);
                $pdf->SetRightMargin(15);
                $pdf->SetTextColor(51, 51, 51);
                $pdf->Ln(1.5);
            } else {
                $pdf->SetY($rowY + $cabH);
            }

            // Línea separadora fina
            $rowEndY = $pdf->GetY();
            $pdf->SetDrawColor(235, 235, 235);
            $pdf->SetLineWidth(0.15);
            $pdf->Line(15, $rowEndY, 15 + $tableW, $rowEndY);
            $pdf->Ln(0.5);

            $rowAlternate = !$rowAlternate;
        }

        // ════════════════════════════════════════════════════════════════
        // TOTALES
        // ════════════════════════════════════════════════════════════════
        $pdf->Ln(3);

        $subtotal   = floatval($total_summary->proposal_subtotal ?? 0);
        $tax_pct    = floatval($total_summary->tax_percentage ?? 0);
        $tax_pct2   = floatval($total_summary->tax_percentage2 ?? 0);
        $tax_amount = floatval($total_summary->tax ?? 0);
        $tax2_amount= floatval($total_summary->tax2 ?? 0);
        $descuento  = floatval($total_summary->discount_total ?? 0);
        $totalFinal = floatval($total_summary->proposal_total ?? 0);
        $tax_name   = $total_summary->tax_name ?? ('IVA ' . number_format($tax_pct, 0) . '%');
        $tax_name2  = $total_summary->tax_name2 ?? ('2º Impuesto ' . number_format($tax_pct2, 0) . '%');
        $rightMargin = 15 + $tableW;
        $labelW_t    = 38;
        $valW_t      = 27;

        // Línea superior de totales
        $pdf->SetDrawColor($brand_r, $brand_g, $brand_b);
        $pdf->SetLineWidth(0.4);
        $pdf->Line($rightMargin - $labelW_t - $valW_t, $pdf->GetY(), $rightMargin, $pdf->GetY());
        $pdf->Ln(3);

        // Subtotal
        $pdf->SetX($rightMargin - $labelW_t - $valW_t);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell($labelW_t, 5, 'Subtotal (sin IVA)', 0, 0, 'R');
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->SetTextColor(50, 50, 50);
        $pdf->Cell($valW_t, 5, number_format($subtotal, 2, ',', '.') . ' €', 0, 1, 'R');

        if ($tax_amount > 0) {
            $pdf->SetX($rightMargin - $labelW_t - $valW_t);
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell($labelW_t, 5, $tax_name, 0, 0, 'R');
            $pdf->SetTextColor(50, 50, 50);
            $pdf->Cell($valW_t, 5, number_format($tax_amount, 2, ',', '.') . ' €', 0, 1, 'R');
        }

        if ($tax2_amount > 0) {
            $pdf->SetX($rightMargin - $labelW_t - $valW_t);
            $pdf->SetFont('Helvetica', '', 8);
            $pdf->SetTextColor(100, 100, 100);
            $pdf->Cell($labelW_t, 5, $tax_name2, 0, 0, 'R');
            $pdf->SetTextColor(50, 50, 50);
            $pdf->Cell($valW_t, 5, number_format($tax2_amount, 2, ',', '.') . ' €', 0, 1, 'R');
        }

        if ($descuento > 0) {
            $pdf->SetX($rightMargin - $labelW_t - $valW_t);
            $pdf->SetFont('Helvetica', 'B', 8);
            $pdf->SetTextColor(34, 120, 34);
            $pdf->Cell($labelW_t, 5, 'Descuento aplicado', 0, 0, 'R');
            $pdf->Cell($valW_t,   5, '- ' . number_format($descuento, 2, ',', '.') . ' €', 0, 1, 'R');
        }

        // Bloque total final — rosa corporativo
        $pdf->Ln(2);
        $totalY = $pdf->GetY();
        $totalBlockW = $labelW_t + $valW_t;
        $pdf->SetFillColor($brand_r, $brand_g, $brand_b);
        $pdf->Rect($rightMargin - $totalBlockW, $totalY, $totalBlockW, 10, 'F');
        // Pequeño triángulo decorativo a la izquierda del bloque
        $pdf->SetFillColor(180, 20, 90);
        $pdf->Rect($rightMargin - $totalBlockW, $totalY, 3, 10, 'F');

        $pdf->SetXY($rightMargin - $totalBlockW + 3, $totalY + 1.5);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Helvetica', '', 8);
        $pdf->Cell($labelW_t - 3, 7, 'TOTAL (IVA incluido)', 0, 0, 'R');
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Cell($valW_t, 7, number_format($totalFinal, 2, ',', '.') . ' €', 0, 1, 'R');
        $pdf->SetTextColor(51, 51, 51);
        $pdf->SetDrawColor(230, 230, 230);

        // ── Notas adicionales ─────────────────────────────────────────────
        $notasHtml = $proposal_info->note ?? '';
        if ($tiene_contenido($notasHtml)) {
            $cleanNotasHtml = $pdf_html_clean($notasHtml);
            $notasTexto     = $pdf_text_plain($notasHtml);

            $pdf->Ln(6);
            $yAntesNotas  = $pdf->GetY();
            $bottomLimit2 = $pdf->getPageHeight() - 45;

            // Medir altura
            $pdf->startTransaction();
            $pdf->SetAutoPageBreak(false, 0);
            $pdf->SetXY(20, $yAntesNotas + 10);
            $pdf->SetFont('Helvetica', '', 8);
            if (!empty($cleanNotasHtml)) {
                $pdf->writeHTMLCell($contentW - 10, 0, 20, $pdf->GetY(), $cleanNotasHtml, 0, 1, false, true, 'L');
            } else {
                $pdf->SetX(20);
                $pdf->MultiCell($contentW - 10, 3.5, $notasTexto, 0, 'L');
            }
            $alturaNotas = $pdf->GetY() - $yAntesNotas;
            $pdf->rollbackTransaction(true);
            $pdf->SetAutoPageBreak(true, 40);

            if ($alturaNotas > ($bottomLimit2 - $yAntesNotas)) $pdf->AddPage();

            $notasStartY = $pdf->GetY();
            // Render contenido (para medir altura real post-render)
            $pdf->SetXY(20, $notasStartY + 10);
            $pdf->SetFont('Helvetica', '', 8);
            if (!empty($cleanNotasHtml)) {
                $pdf->writeHTMLCell($contentW - 10, 0, 20, $pdf->GetY(), $cleanNotasHtml, 0, 1, false, true, 'L');
            } else {
                $pdf->SetX(20);
                $pdf->MultiCell($contentW - 10, 3.5, $notasTexto, 0, 'L');
            }
            $notasH = $pdf->GetY() - $notasStartY + 6;

            // Fondo y título sobre el contenido ya renderizado
            $pdf->SetFillColor(255, 240, 247);
            $pdf->SetDrawColor(255, 240, 247);
            $pdf->Rect(15, $notasStartY, $contentW, $notasH, 'F');
            $pdf->SetXY(20, $notasStartY + 4);
            $pdf->SetTextColor($brand_r, $brand_g, $brand_b);
            $pdf->SetFont('Helvetica', 'B', 11);
            $pdf->Cell($contentW - 10, 5, 'Notas Adicionales', 0, 1, 'L');
            // Re-render contenido encima del fondo
            $pdf->SetXY(20, $notasStartY + 10);
            $pdf->SetTextColor(51, 51, 51);
            $pdf->SetFont('Helvetica', '', 8);
            if (!empty($cleanNotasHtml)) {
                $pdf->writeHTMLCell($contentW - 10, 0, 20, $pdf->GetY(), $cleanNotasHtml, 0, 1, false, true, 'L');
            } else {
                $pdf->SetX(20);
                $pdf->MultiCell($contentW - 10, 3.5, $notasTexto, 0, 'L');
            }
            $pdf->Ln(4);
        }

        // ── Cláusulas legales RGPD ────────────────────────────────────────
        $pdf->Ln(8);
        $bottomLimitCl = $pdf->getPageHeight() - 45;
        if (($pdf->GetY() + 85) > $bottomLimitCl) $pdf->AddPage();

        $clY = $pdf->GetY();
        $pdf->SetDrawColor($brand_r, $brand_g, $brand_b);
        $pdf->SetLineWidth(0.4);
        $pdf->Line(15, $clY, 15 + $contentW, $clY);
        $pdf->Ln(4);

        $pdf->SetX(15);
        $pdf->SetTextColor($brand_r, $brand_g, $brand_b);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->Cell($contentW, 5, 'PROTECCIÓN DE DATOS Y CLÁUSULAS LEGALES', 0, 1, 'L');
        $pdf->Ln(2);

        $pdf->SetX(15);
        $pdf->SetFont('Helvetica', 'B', 6.5);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->MultiCell($contentW, 3.2,
            'Responsable: TIC TAC COMUNICACION DIGITAL SL - CIF: B09912478  ·  Dir. postal: C/ Cruz Conde, 19, Planta 6ª, 14001 de Córdoba  ·  Teléfono: 957786914  ·  E-mail: hola@tictac-comunicacion.es',
            0, 'L');
        $pdf->Ln(2);

        $pdf->SetX(15);
        $pdf->SetFont('Helvetica', '', 6.5);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->MultiCell($contentW, 3.2,
            'Tratamos la información que nos facilita con el fin de prestarles el servicio solicitado. Los datos proporcionados se conservarán durante el tiempo necesario para cumplir con las finalidades previstas. Los datos no se cederán a terceros salvo en los casos en que exista una obligación legal. Usted tiene derecho de acceso, rectificación, supresión y portabilidad de sus datos y oposición y limitación a su tratamiento en la dirección postal o correo electrónico facilitados, adjuntando copia de su DNI o documento equivalente.',
            0, 'J');
        $pdf->Ln(2);

        $pdf->SetX(15);
        $pdf->MultiCell($contentW, 3.2,
            'Asimismo, solicitamos su autorización para enviarle publicidad relacionada con nuestros productos y servicios por cualquier medio (postal, email o teléfono) e invitarle a eventos organizados por la empresa.',
            0, 'J');
        $pdf->Ln(3);

        // Checkboxes SI/NO
        $checkY = $pdf->GetY();
        $pdf->SetFont('Helvetica', 'B', 7.5);
        $pdf->SetTextColor(51, 51, 51);
        $pdf->SetDrawColor(100, 100, 100);
        $pdf->SetLineWidth(0.4);
        $pdf->SetXY(15, $checkY);
        $pdf->Cell(22, 5, 'SI Autorizo', 0, 0, 'L');
        $pdf->Rect(38, $checkY + 0.8, 3.5, 3.5);
        $pdf->SetXY(47, $checkY);
        $pdf->Cell(22, 5, 'NO Autorizo', 0, 1, 'L');
        $pdf->Rect(70, $checkY + 0.8, 3.5, 3.5);
        $pdf->SetDrawColor(230, 230, 230);
        $pdf->Ln(3);

        $pdf->SetX(15);
        $pdf->SetFont('Helvetica', '', 6.5);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->MultiCell($contentW, 3.2,
            'El CLIENTE es responsable de garantizar que dispone de los consentimientos y autorizaciones legales necesarias para la publicación de imágenes o datos personales de trabajadores y terceros. TIC TAC COMUNICACION DIGITAL SL quedará exonerada de cualquier responsabilidad derivada de incumplimientos en materia de protección de datos por parte del cliente.',
            0, 'J');

        $pdf->SetTextColor(51, 51, 51);
        $pdf->Ln(6);

        // ── Sección de Firmas ─────────────────────────────────────────────
        $firmasH           = 55;
        $bottomLimitFirmas = $pdf->getPageHeight() - 45;
        if (($pdf->GetY() + $firmasH) > $bottomLimitFirmas) $pdf->AddPage();

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
        $pdf->Line(15 + 5,               $firmaLineY, 15 + $firmaColW - 5,      $firmaLineY);
        $pdf->Line(15 + $firmaColW + 15, $firmaLineY, 15 + $contentW - 5,        $firmaLineY);
        $pdf->Ln(4);

        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetTextColor(51, 51, 51);
        $pdf->SetX(15);
        $pdf->Cell($firmaColW, 5, 'Tictac Comunicacion Digital SL', 0, 0, 'C');
        $pdf->SetX(15 + $firmaColW + 10);
        $pdf->Cell($firmaColW, 5, $client_info->company_name ?? '', 0, 1, 'C');

        // ── Output ────────────────────────────────────────────────────────
        $filename = 'Presupuesto_' . get_proposal_id($proposal_info->id) . '.pdf';

        if ($mode === 'save') {
            $tmpFile = sys_get_temp_dir() . '/proposal_' . $proposal_id . '_' . time() . '.pdf';
            $pdf->Output($tmpFile, 'F');
            return file_exists($tmpFile) ? $tmpFile : null;
        } else {
            $pdf->Output($filename, 'D');
            exit;
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // RESTO DE MÉTODOS NATIVOS DEL CRM — SIN CAMBIOS
    // ════════════════════════════════════════════════════════════════════════

    //update the sort value for proposal item
    function update_item_sort_values($id = 0)
    {
        validate_numeric_value($id);

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
                $this->Proposal_items_model->ci_save($data, $id);
            }
        }
    }

    function editor($proposal_id = 0)
    {
        validate_numeric_value($proposal_id);
        $view_data['proposal_info'] = $this->Proposals_model->get_details(array("id" => $proposal_id))->getRow();
        return $this->template->view("proposals/proposal_editor", $view_data);
    }

    //prevent editing of proposal after certain state
    private function _is_proposal_editable($_proposal, $is_clone = 0)
    {
        if (get_setting("enable_proposal_lock_state")) {
            $proposal_info = is_object($_proposal) ? $_proposal : $this->Proposals_model->get_one($_proposal);
            if (!$proposal_info->id || $is_clone) {
                return true;
            }

            if ($proposal_info->status != "accepted") {
                return true;
            }
        } else {
            return true;
        }
    }

    function email_view_report($proposal_id)
    {
        validate_numeric_value($proposal_id);

        $this->validate_proposal_access($proposal_id);

        $options = array(
            "context" => "proposal",
            "context_id" => $proposal_id
        );

        $tracking_info = $this->Event_tracker_model->get_details($options)->getResult();

        $all_logs = array();
        foreach ($tracking_info as $tracking_data) {
            $logs = unserialize($tracking_data->logs);

            if (is_array($logs)) {
                foreach ($logs as $log) {
                    if (isset($log['read_at'])) {
                        $all_logs[] = $log['read_at'];
                    }
                }
            }
        }

        rsort($all_logs);
        $view_data["email_read_logs"] = $all_logs;

        return $this->template->view("proposals/email_view_report", $view_data);
    }

    /**
     * Descarga el PDF de la propuesta con el diseño Tictac.
     * Sustituye al download_pdf nativo que usaba el PDF genérico del CRM.
     */
    function download_pdf($proposal_id = 0, $mode = "download", $user_language = "")
    {
        if (!$proposal_id) {
            show_404();
        }

        if (!$this->check_proposal_pdf_access_for_clients($this->login_user->user_type)) {
            show_404();
        }

        validate_numeric_value($proposal_id);

        $proposal_data = get_proposal_making_data($proposal_id);
        $this->_check_proposal_access_permission($proposal_data);

        // Generar PDF con diseño Tictac directamente
        $this->_generate_tictac_pdf($proposal_id, 'download');
    }

    /* load proposal comment modal */

    function comment_modal_form()
    {
        $this->validate_submitted_data(array(
            "proposal_id" => "numeric|required"
        ));

        if (get_setting("enable_comments_on_proposals") !== "1") {
            app_redirect("forbidden");
        }

        $proposal_id = $this->request->getPost('proposal_id');
        $this->validate_proposal_access($proposal_id);

        $view_data['proposal_id'] = $proposal_id;

        $sort_as_decending = get_setting("show_most_recent_proposal_comments_at_the_top");

        $view_data = get_proposal_making_data($proposal_id);

        $comments_options = array(
            "proposal_id" => $proposal_id,
            "sort_as_decending" => $sort_as_decending
        );
        $view_data['comments'] = $this->Proposal_comments_model->get_details($comments_options)->getResult();
        $view_data["sort_as_decending"] = $sort_as_decending;

        return $this->template->view('proposals/comment_form', $view_data);
    }

    /* save proposal comments */

    function save_comment()
    {
        $proposal_id = $this->request->getPost('proposal_id');
        $this->validate_proposal_access($proposal_id, true);

        $now = get_current_utc_time();

        $target_path = get_setting("timeline_file_path");
        $files_data = move_files_from_temp_dir_to_permanent_dir($target_path, "proposal");

        $this->validate_submitted_data(array(
            "description" => "required",
            "proposal_id" => "required|numeric"
        ));

        $comment_data = array(
            "description" => $this->request->getPost('description'),
            "proposal_id" => $proposal_id,
            "created_by" => $this->login_user->id,
            "created_at" => $now,
            "files" => $files_data
        );

        $comment_data = clean_data($comment_data);
        $comment_data["files"] = $files_data; //don't clean serilized data

        $comment_id = $this->Proposal_comments_model->ci_save($comment_data);
        if ($comment_id) {
            $comments_options = array("id" => $comment_id);
            $view_data['comment'] = $this->Proposal_comments_model->get_details($comments_options)->getRow();
            $comment_view = $this->template->view("proposals/comment_row", $view_data, true);

            echo json_encode(array("success" => true, "data" => $comment_view, 'message' => app_lang('comment_submited')));
            log_notification("proposal_commented", array("proposal_id" => $proposal_id, "proposal_comment_id" => $comment_id));
        } else {
            echo json_encode(array("success" => false, 'message' => app_lang('error_occurred')));
        }
    }

    /* delete proposal comments */

    function delete_comment($id = 0)
    {
        if (!$id) {
            exit();
        }

        $comment_info = $this->Proposal_comments_model->get_one($id);

        //only admin and creator can delete the comment
        if (!($this->login_user->is_admin || $comment_info->created_by == $this->login_user->id)) {
            app_redirect("forbidden");
        }

        //delete the comment and files
        if ($this->Proposal_comments_model->delete($id) && $comment_info->files) {

            //delete the files
            $file_path = get_setting("timeline_file_path");
            $files = unserialize($comment_info->files);

            foreach ($files as $file) {
                $source_path = $file_path . get_array_value($file, "file_name");
                delete_file_from_directory($source_path);
            }
        }
    }

    /* download files by zip */

    function download_comment_files($id)
    {
        validate_numeric_value($id);

        $files = $this->Proposal_comments_model->get_one($id)->files;
        return $this->download_app_files(get_setting("timeline_file_path"), $files);
    }

    function compact_view($proposal_id = 0)
    {
        validate_numeric_value($proposal_id);

        if ($this->login_user->user_type === "client") {
            app_redirect("proposals/preview/$proposal_id");
        }

        return $this->index($proposal_id);
    }
}

/* End of file Proposals.php */
/* Location: ./app/Controllers/Proposals.php */