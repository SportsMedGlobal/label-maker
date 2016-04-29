<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Tasks extends Model {

	protected $table = 'tasks';
	public $timestamps = true;
	protected $dates = ['created_at', 'updated_at', 'completed_at'];

	public function Actions()
	{
		return $this->hasMany(Actions::class);
	}

    public static function monthlyStats($dateStart, $dateEnd)
    {
        $dataArray = [];
        // TODO replace parameters with PDO parameters.  There is some weird behaviour with params in Lumen
        $results = app('db')->select("
            SELECT 
                tasks.id,
                tasks.key,
                tasks.state,
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
            WHERE tasks.updated_at BETWEEN '".$dateStart."' AND '".$dateEnd."' 
            ORDER BY 
                tasks.updated_at DESC
        ");

        foreach ($results as $row) {
            if (!empty($row->completed_at)) {
                $row->completed_at = Carbon::parse($row->completed_at);
            }
            $row->created_at = Carbon::parse($row->created_at);
            $row->updated_at = Carbon::parse($row->updated_at);
            $dataArray[] = $row;
        }

        return $dataArray;
    }

}