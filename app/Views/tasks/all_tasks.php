<div id="page-content" class="page-wrapper clearfix grid-button all-tasks-view">

    <ul class="nav nav-tabs bg-white title" role="tablist">
        <li class="title-tab my-tasks"><h4 class="pl15 pt10 pr15"><?php echo app_lang("tasks"); ?></h4></li>

        <?php echo view("tasks/tabs", array("active_tab" => "tasks_list", "selected_tab" => $tab)); ?>

        <div class="tab-title clearfix no-border">
            <div class="title-button-group">
                <?php
                if ($can_create_tasks) {
                    echo modal_anchor(get_uri("labels/modal_form"), "<i data-feather='tag' class='icon-16'></i> " . app_lang('manage_labels'), array("class" => "btn btn-default", "title" => app_lang('manage_labels'), "data-post-type" => "task"));
                    echo modal_anchor(get_uri("tasks/import_modal_form"), "<i data-feather='upload' class='icon-16'></i> " . app_lang('import_tasks'), array("class" => "btn btn-default", "title" => app_lang('import_tasks')));
                    echo modal_anchor(get_uri("tasks/modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_multiple_tasks'), array("class" => "btn btn-default", "title" => app_lang('add_multiple_tasks'), "data-post-add_type" => "multiple"));
                    echo modal_anchor(get_uri("tasks/modal_form"), "<i data-feather='plus-circle' class='icon-16'></i> " . app_lang('add_task'), array("class" => "btn btn-default", "title" => app_lang('add_task')));
                }
                ?>
            </div>
        </div>

    </ul>

    <div class="card border-top-0 rounded-top-0 xs-no-bottom-margin">
        <div class="table-responsive" id="task-table-container">
            <table id="task-table" class="display xs-hide-dtr-control no-title" cellspacing="0" width="100%">            
            </table>
        </div>
    </div>
</div>

<?php
//if we get any task parameter, we'll show the task details modal automatically
$preview_task_id = get_array_value($_GET, 'task');
if ($preview_task_id) {
    echo modal_anchor(get_uri("tasks/view"), "", array("id" => "preview_task_link", "title" => app_lang('task_info') . " #$preview_task_id", "data-post-id" => $preview_task_id));
}

$statuses = array();

//Check the clickable links from dashboard
$ignore_saved_filter = false;

foreach ($task_statuses as $status) {
    $is_selected = false;

    if (isset($selected_status_id) && $selected_status_id) {
        //if there is any specific status selected, select only the status.
        if ($selected_status_id == $status->id) {
            $is_selected = true;
            $ignore_saved_filter = true;
        }
    } else if ($status->key_name != "done") {
        $is_selected = true;
    }

    $statuses[] = array("text" => ($status->key_name ? app_lang($status->key_name) : $status->title), "value" => $status->id, "isChecked" => $is_selected);
}

if (isset($selected_priority_id) && $selected_priority_id) {
    $ignore_saved_filter = true;
}
?>

<script type="text/javascript">
    $(document).ready(function () {

        var showOption = true,
                showIdColumn = true,
                titleColumnClass = "";

        if (isMobile()) {
            showOption = false;
            showIdColumn = false;
            titleColumnClass = "w75p all";
        }

        var ignoreSavedFilter = false;
        var hasString = window.location.hash.substring(1);
        if (hasString || "<?php echo $ignore_saved_filter; ?>") {
            ignoreSavedFilter = true;
        }

        var deadline_expired = false;
        if ("<?php echo isset($selected_deadline) && $selected_deadline ?>") {
            ignoreSavedFilter = true;
            deadline_expired = true;
        }

        var batchUpdateUrl = "<?php echo get_uri("tasks/batch_update_modal_form"); ?>";
        var batchDeleteUrl = "<?php echo_uri('tasks/delete_selected_tasks'); ?>";

        var selectionHandler = {batchUpdateUrl: batchUpdateUrl, batchDeleteUrl: batchDeleteUrl, hideButton: true};
        if("<?php echo $login_user->user_type == "client"; ?>"){
            selectionHandler = false;
        }

        var mobileView = 0;
        if (isMobile()) {
            mobileView = 1;
        }

        var idColumnClass = "";
        if ("<?php echo get_setting("show_the_status_checkbox_in_tasks_list"); ?>" === "1") {
            idColumnClass = "w10p";
        }

        var dynamicDates = getDynamicDates();
        $("#task-table").appTable({
            source: '<?php echo_uri("tasks/all_tasks_list_data") ?>' + "/0/" + mobileView,
            serverSide: true,
            order: [[1, "desc"]],
            smartFilterIdentity: "all_tasks_list", //a to z and _ only. should be unique to avoid conflicts 
            ignoreSavedFilter: ignoreSavedFilter,
            selectionHandler: selectionHandler,
            filterDropdown: [
                {name: "quick_filter", class: "w200", showHtml: true, options: <?php echo view("tasks/quick_filters_dropdown"); ?>},
                {name: "context", class: "w200", options: <?php echo $contexts_dropdown; ?>, onChangeCallback: function (value, filterParams) {
                        var $tableWrapper = $("#task-table_wrapper");
                        if (!(value == "" || value == "project")) {

                            var $milestoneSelector = $tableWrapper.find("select[name=milestone_id]");
                            var $milestoneFirstOption = $milestoneSelector.find("option:first");
                            $milestoneSelector.html("<option value='" + $milestoneFirstOption.val() + "'>" + $milestoneFirstOption.html() + "</option>");
                            $milestoneSelector.select2("val", $milestoneFirstOption.val());

                            var $projectSelector = $tableWrapper.find("select[name=project_id]");
                            $projectSelector.select2("val", "");

                            filterParams.project_id = "";
                            filterParams.milestone_id = "";
                            if (typeof showHideTheBatchUpdateButton !== "undefined") {
                                showHideTheBatchUpdateButton();
                            }
                            $tableWrapper.find("[name='project_id']").closest(".filter-item-box").addClass("hide");
                            $tableWrapper.find("[name='milestone_id']").closest(".filter-item-box").addClass("hide");
                        } else {
                            $tableWrapper.find("[name='project_id']").closest(".filter-item-box").removeClass("hide");
                            $tableWrapper.find("[name='milestone_id']").closest(".filter-item-box").removeClass("hide");
                        }

                    }
                },
                {name: "project_id", class: "w200", options: <?php echo $projects_dropdown; ?>, dependent: ["milestone_id"]}, //reset milestone on changing of project
                {name: "milestone_id", class: "w200", options: [{id: "", text: "- <?php echo app_lang('milestone'); ?> -"}], dependency: ["project_id"], dataSource: '<?php echo_uri("tasks/get_milestones_for_filter") ?>'}, //milestone is dependent on project
                {name: "specific_user_id", class: "w200", options: <?php echo $team_members_dropdown; ?>},
                {name: "priority_id", class: "w200", options: <?php echo $priorities_dropdown; ?>},
                {name: "client_id", class: "w200", options: <?php echo $clients_dropdown; ?>},
                {name: "label_id", class: "w200", options: <?php echo $labels_dropdown; ?>}

                , <?php echo $custom_field_filters; ?>
            ],
            singleDatepicker: [{name: "deadline", class: "w200", defaultText: "<?php echo app_lang('deadline') ?>",
                    options: [
                        {value: "expired", text: "<?php echo app_lang('expired') ?>", isSelected: deadline_expired},
                        {value: dynamicDates.today, text: "<?php echo app_lang('today') ?>"},
                        {value: dynamicDates.tomorrow, text: "<?php echo app_lang('tomorrow') ?>"},
                        {value: dynamicDates.in_next_7_days, text: "<?php echo sprintf(app_lang('in_number_of_days'), 7); ?>"},
                        {value: dynamicDates.in_next_15_days, text: "<?php echo sprintf(app_lang('in_number_of_days'), 15); ?>"}
                    ]}],
            multiSelect: [
                {
                    class: "w200",
                    name: "status_id",
                    text: "<?php echo app_lang('status'); ?>",
                    options: <?php echo json_encode($statuses); ?>
                }
            ],
            columns: [
                {visible: false, searchable: false},
                {title: "<?php echo app_lang('id') ?>", visible: showIdColumn, "class": idColumnClass, order_by: "id"},
                {title: "<?php echo app_lang('title') ?>", "class": titleColumnClass, order_by: "title"},
                {title: "<?php echo app_lang('title') ?>", visible: false, searchable: false},
                {title: "<?php echo app_lang('label') ?>", visible: false, searchable: false},
                {title: "<?php echo app_lang('priority') ?>", visible: false, searchable: false},
                {title: "<?php echo app_lang('points') ?>", visible: false, searchable: false},
                {visible: false, searchable: false, order_by: "start_date"},
                {title: "<?php echo app_lang('start_date') ?>", "iDataSort": 7, order_by: "start_date"},
                {visible: false, searchable: false, order_by: "deadline"},
                {title: "<?php echo app_lang('deadline') ?>", "iDataSort": 9, order_by: "deadline"},
                {title: "<?php echo app_lang('client') ?>", order_by: "client"},
                {title: "<?php echo app_lang('related_to') ?>"},
                {title: "<?php echo app_lang('assigned_to') ?>", "class": "min-w150", order_by: "assigned_to"},
                {title: "<?php echo app_lang('collaborators') ?>"},
                {title: "<?php echo app_lang('status') ?>", order_by: "status"}
<?php echo $custom_field_headers; ?>,
                {title: '<i data-feather="menu" class="icon-16"></i>', "class": "text-center option w100"}
            ],
            printColumns: combineCustomFieldsColumns([1, 3, 4, 5, 6, 8, 10, 11, 12, 13, 14, 15], '<?php echo $custom_field_headers; ?>'),
            xlsColumns: combineCustomFieldsColumns([1, 3, 4, 5, 6, 8, 10, 11, 12, 13, 14, 15], '<?php echo $custom_field_headers; ?>'),
            rowCallback: tasksTableRowCallback, //load this function from the task_table_common_script.php 
            onInitComplete: function () {
                if (!showOption) {
                    window.scrollTo(0, 210); //scroll to the content for mobile devices
                }
                if (typeof showHideTheBatchUpdateButton === 'function') {
                    showHideTheBatchUpdateButton();

                }
            },
            onRelaodCallback: function () {
                showHideTheBatchUpdateButton();
            }
        });


        //open task details modal automatically 

        if ($("#preview_task_link").length) {
            $("#preview_task_link").trigger("click");
        }

        setTimeout(function () {
            var tab = "<?php echo $tab; ?>";
            if (tab === "tasks_list") {
                $("[data-tab='#tasks_list']").trigger("click");

                //save the selected tab in browser cookie
                setCookie("selected_tab_" + "<?php echo $login_user->id; ?>", "tasks_list");
            }
        }, 210);

    });
</script>

<!-- ===== Timer activo en lista de tareas - Tictac ===== -->
<style>
.task-timer-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #fff3cd;
    border: 1px solid #ffc107;
    border-radius: 20px;
    padding: 1px 7px 1px 4px;
    font-size: 10px;
    font-weight: 600;
    color: #856404;
    margin-left: 6px;
    vertical-align: middle;
    cursor: default;
}
.task-timer-badge .timer-pulse {
    display: inline-block;
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #e91e8c;
    flex-shrink: 0;
    animation: timerPulse 1.4s ease-in-out infinite;
}
@keyframes timerPulse {
    0%   { box-shadow: 0 0 0 0 rgba(233,30,140,.6); opacity:1; }
    50%  { box-shadow: 0 0 0 4px rgba(233,30,140,0); opacity:.7; }
    100% { box-shadow: 0 0 0 0 rgba(233,30,140,0); opacity:1; }
}
</style>

<script type="text/javascript">
(function() {
    "use strict";

    var API_URL      = '<?php echo get_uri("api_tictac/active_timers"); ?>';
    var POLL_SECONDS = 30;
    var activeTimers = {};

    function refreshTimerBadges() {
        $.ajax({
            url: API_URL, method: 'GET', dataType: 'json', timeout: 10000,
            success: function(data) {
                if (!data || !data.success) { return; }
                activeTimers = data.timers || {};
                applyBadgesToTable();
            },
            error: function() {}
        });
    }

    function applyBadgesToTable() {
        // Eliminar badges anteriores
        $('.task-timer-badge').remove();

        // Las filas del datatable tienen data-id en el <tr> o en el primer <td>
        // En RISE el task id está en la columna JS-selection-id
        $.each(activeTimers, function(taskId, users) {
            // Buscar el enlace modal con data-id igual al taskId
            var $link = $('a.js-selection-id[data-id="' + taskId + '"]');
            if (!$link.length) { return; }

            var names = $.map(users, function(u) { return u.user_name; });
            var label = names.length === 1
                ? names[0] + ' trabajando'
                : names[0] + ' +' + (names.length - 1) + ' trabajando';

            var $badge = $(
                '<span class="task-timer-badge" title="' + names.join(', ') + '">' +
                    '<span class="timer-pulse"></span>' +
                    escapeHtml(label) +
                '</span>'
            );

            $link.after($badge);
        });
    }

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    $(document).ready(function() {
        refreshTimerBadges();
        setInterval(refreshTimerBadges, POLL_SECONDS * 1000);

        // Re-aplicar cuando la tabla se recarga (paginación, filtros, etc.)
        $(document).on('draw.dt', '#task-table', function() {
            setTimeout(applyBadgesToTable, 300);
        });
    });
})();
</script>

<?php echo view("tasks/batch_update/batch_update_script"); ?>
<?php echo view("tasks/task_table_common_script"); ?>
<?php echo view("tasks/update_task_read_comments_status_script"); ?>
<?php echo view("tasks/quick_filters_helper_js"); ?>