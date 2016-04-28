<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTasksTable extends Migration {

	public function up()
	{
		Schema::create('tasks', function(Blueprint $table) {
			$table->increments('id');
			$table->timestamps();
			$table->string('key', 50);
			$table->integer('assignee_id');
			$table->enum('state', array('needs_cr', 'needs_testing', 'changes_needed', 'completed'));
		});
	}

	public function down()
	{
		Schema::drop('tasks');
	}
}