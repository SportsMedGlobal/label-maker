<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class Users extends Model {

	protected $table = 'users';
	public $timestamps = true;
	protected $dates = ['created_at', 'updated_at', 'last_action'];

	public function Actions()
	{
		return $this->hasMany(Actions::class);
	}

	public static function monthlyStats($dateStart, $dateEnd)
	{
        $dataArray = [];
        // TODO replace parameters with PDO parameters. There is some weird behaviour with params in Lumen
        $results = app('db')->select("
            SELECT 
                users.id, 
                users.username, 
                users.full_name,
                (SELECT count(*) FROM tasks WHERE tasks.assignee_id = users.id and tasks.created_at BETWEEN '".$dateStart."' AND '".$dateEnd."') as assigned, 
                (SELECT count(*) FROM tasks WHERE tasks.assignee_id = users.id AND tasks.state = 'completed' and tasks.completed_at BETWEEN '".$dateStart."' AND '".$dateEnd."') as completed,
                (SELECT count(*) from actions WHERE actions.user_id = users.id AND actions.action IN ('cr_passed', 'cr_failed') and actions.created_at BETWEEN '".$dateStart."' AND '".$dateEnd."') as crs_actioned,
                (SELECT count(*) from actions WHERE actions.user_id = users.id AND actions.action IN ('testing_passed', 'testing_failed') and actions.created_at BETWEEN '".$dateStart."' AND '".$dateEnd."') as testing_actioned,
                (SELECT count(*) FROM tasks INNER JOIN actions ON (actions.task_id = tasks.id) WHERE tasks.assignee_id = users.id AND actions.action = 'cr_failed' and actions.created_at BETWEEN '".$dateStart."' AND '".$dateEnd."') as failed_cr,
                (SELECT count(*) FROM tasks INNER JOIN actions ON (actions.task_id = tasks.id) WHERE tasks.assignee_id = users.id AND actions.action = 'testing_failed' and actions.created_at BETWEEN '".$dateStart."' AND '".$dateEnd."') as failed_testing
            FROM 
                users 
            ORDER BY 
                users.last_action DESC
        ");
    
        foreach ($results as $row) {
            $dataArray[] = $row;
        }

        return $dataArray;
	}

}