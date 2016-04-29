<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUsersTable extends Migration {

	public function up()
	{
		Schema::create('users', function(Blueprint $table) {
			$table->increments('id');
			$table->string('username', 255);
			$table->string('full_name', 255);
			$table->dateTime('last_action');
			$table->string('slack_handle', 255);
			$table->string('github_username', 255);
			$table->timestamps();
		});
	}

	public function down()
	{
		Schema::drop('users');
	}
}