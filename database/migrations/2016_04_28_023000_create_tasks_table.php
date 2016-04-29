<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTasksTable extends Migration {

	public function up()
	{
		Schema::create('tasks', function(Blueprint $table) {
			$table->increments('id');
			$table->string('key', 50);
            $table->string('title', 255);
			$table->integer('assignee_id');
			$table->enum('state', array('development','needs_cr', 'needs_testing', 'in_testing', 'changes_needed', 'completed'));
			$table->dateTime('completed_at')->nullable()->default(null);
			$table->timestamps();
		});
	}

	public function down()
	{
		Schema::drop('tasks');
	}
}