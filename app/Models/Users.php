<?php

namespace App\Models;

use Carbon\Carbon;
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
                DISTINCT users.id, 
                users.username, 
                users.full_name,
                (SELECT count(*) FROM tasks WHERE tasks.assignee_id = users.id and tasks.created_at BETWEEN '".$dateStart."' AND '".$dateEnd."') as assigned, 
                (SELECT AVG(TIMESTAMPDIFF(SECOND, 
                          tasks.created_at, 
                          tasks.completed_at)
           ) FROM tasks WHERE tasks.assignee_id = users.id AND tasks.state = 'completed' and tasks.completed_at BETWEEN '".$dateStart."' AND '".$dateEnd."')/60/60 as avg_completed_time,
                (SELECT count(*) FROM tasks WHERE tasks.assignee_id = users.id AND tasks.state = 'completed' and tasks.completed_at BETWEEN '".$dateStart."' AND '".$dateEnd."') as completed,
                (SELECT count(*) from actions WHERE actions.user_id = users.id AND actions.action IN ('cr_passed', 'cr_failed') and actions.created_at BETWEEN '".$dateStart."' AND '".$dateEnd."') as crs_actioned,
                (SELECT count(*) from actions WHERE actions.user_id = users.id AND actions.action IN ('testing_passed', 'testing_failed') and actions.created_at BETWEEN '".$dateStart."' AND '".$dateEnd."') as testing_actioned,
                (SELECT count(*) FROM tasks INNER JOIN actions ON (actions.task_id = tasks.id) WHERE tasks.assignee_id = users.id AND actions.action = 'cr_failed' and actions.created_at BETWEEN '".$dateStart."' AND '".$dateEnd."') as failed_cr,
                (SELECT count(*) FROM tasks INNER JOIN actions ON (actions.task_id = tasks.id) WHERE tasks.assignee_id = users.id AND actions.action = 'testing_failed' and actions.created_at BETWEEN '".$dateStart."' AND '".$dateEnd."') as failed_testing,
                (SELECT MAX(actions.created_at) FROM actions WHERE actions.user_id = users.id) as last_action_table
            FROM 
                users
            INNER JOIN actions ON (actions.user_id = users.id)    
            WHERE
                actions.created_at BETWEEN '".$dateStart."' AND '".$dateEnd."' 
            ORDER BY 
                users.last_action DESC
        ");
    
        foreach ($results as $row) {
            $dataArray[] = $row;
        }

        return $dataArray;
	}

    public function userStats()
    {
        $dataArray = [];
        // TODO replace parameters with PDO parameters.  There is some weird behaviour with params in Lumen
        $results = app('db')->select("
            SELECT 
                tasks.id,
                tasks.key,
                tasks.state,
                tasks.title,
                tasks.completed_at,
                tasks.created_at,
                tasks.updated_at,
                users.id as user_id,
                users.full_name,
                users.username,
                (SELECT count(*) FROM actions where actions.task_id = tasks.id AND actions.action IN ('cr_failed')) as cr_failed,
                (SELECT count(*) FROM actions where actions.task_id = tasks.id AND actions.action IN ('testing_failed')) as testing_failed
              
            FROM 
                tasks
            INNER JOIN users ON (users.id = tasks.assignee_id)
            WHERE tasks.assignee_id = ".$this->id." 
            ORDER BY 
                tasks.updated_at DESC
        ");

        $totalFailedReview = 0;
        $totalFailedTesting = 0;
        foreach ($results as $row) {
            if (!empty($row->completed_at)) {
                $row->completed_at = Carbon::parse($row->completed_at);
            }
            $row->created_at = Carbon::parse($row->created_at);
            $row->updated_at = Carbon::parse($row->updated_at);
            $totalFailedReview = $totalFailedReview + $row->cr_failed;
            $totalFailedTesting = $totalFailedTesting + $row->testing_failed;
            $dataArray[] = $row;
        }

        $dataArray['totals'] = ['cr_failed' => $totalFailedReview, 'testing_failed' => $totalFailedTesting ];
        return $dataArray;
    }

    public function graphStats()
    {
        $dataArray = [];
        $dateEnd = Carbon::now()->endOfMonth();
        $dateStart = Carbon::now()->subYear()->startOfMonth();
        $results = app('db')->select("
            select 
            date_format(actions.created_at, '%Y-%m') as month_string, 
            SUM(case when actions.action = 'cr_failed' then 1 else 0 end) as crs_failed, 
            SUM(case when actions.action = 'testing_failed' then 1 else 0 end) as testing_failed,
            SUM(case when actions.action = 'testing_passed' then 1 else 0 end) as completed
            from tasks inner join actions ON (actions.task_id = tasks.id) where tasks.assignee_id = ".$this->id." and tasks.updated_at BETWEEN '".$dateStart->toDateTimeString()."' AND '".$dateEnd->toDateTimeString()."' group by month_string
        ");

        foreach ($results as $row) {
            $dataArray[] = $row;
        }
        return $dataArray;
    }

}