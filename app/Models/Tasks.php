<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tasks extends Model {

	protected $table = 'tasks';
	public $timestamps = true;

	public function Actions()
	{
		return $this->hasMany(Actions::class);
	}

}