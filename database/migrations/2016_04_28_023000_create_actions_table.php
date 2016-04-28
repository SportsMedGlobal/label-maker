<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateActionsTable extends Migration {

	public function up()
	{
		Schema::create('actions', function(Blueprint $table) {
			$table->increments('id');
			$table->timestamps();
			$table->integer('user_id');
			$table->integer('task_id');
			$table->enum('action', array('created_task', 'cr_passed', 'cr_failed', 'finished_task', 'testing_passed', 'testing_failed'));
		});
	}

	public function down()
	{
		Schema::drop('actions');
	}
}