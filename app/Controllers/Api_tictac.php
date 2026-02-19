<?php

namespace App\Controllers;

class Api_tictac extends Security_Controller
{

    public function __construct()
    {
        parent::__construct();
        $this->access_only_team_members();
    }

    public function active_timers()
    {
        $db = \Config\Database::connect();

        $sql = "
            SELECT 
                pt.task_id,
                pt.user_id,
                pt.start_time,
                CONCAT(u.first_name, ' ', u.last_name) AS user_name,
                u.image AS user_avatar
            FROM crm_project_time pt
            LEFT JOIN crm_users u ON u.id = pt.user_id
            WHERE pt.end_time IS NULL
              AND pt.status = 'open'
              AND pt.task_id > 0
              AND pt.deleted = 0
            ORDER BY pt.start_time ASC
        ";

        $result = $db->query($sql)->getResult();

        $timers = [];
        foreach ($result as $row) {
            $task_id = intval($row->task_id);
            if (!isset($timers[$task_id])) {
                $timers[$task_id] = [];
            }
            $timers[$task_id][] = [
                'user_id'     => intval($row->user_id),
                'user_name'   => $row->user_name,
                'user_avatar' => $row->user_avatar,
                'start_time'  => $row->start_time,
            ];
        }

        return $this->response
            ->setContentType('application/json')
            ->setBody(json_encode(['success' => true, 'timers' => $timers]));
    }
}