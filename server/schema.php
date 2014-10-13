<?php
if(php_sapi_name() != "cli"){
	echo 'CLI access only';
	die();
}
require_once __DIR__.'/vendor/autoload.php';
require_once "database.php";

use Illuminate\Database\Capsule\Manager as Capsule;

if(!Capsule::schema()->hasTable('tests')){
	Capsule::schema()->create('tests', function($table){
		$table->increments('id');
		$table->string('name');
		$table->string('mode', 15)->default('practice');
		$table->datetime('start')->nullable();
		$table->datetime('end')->nullable();
		$table->boolean('readonly')->default(false);
		$table->timestamps();
		$table->softDeletes();
	});
	echo "created tests\n";
}
if(!Capsule::schema()->hasTable('problems')){
	Capsule::schema()->create('problems', function($table){
		$table->increments('id');
		$table->string('name');
		$table->text('description');
		$table->integer('point')->default(1);
		$table->string('creator')->nullable();
		$table->text('graders');

		$table->string('input_lang')->nullable()
		$table->string('input_spec')->nullable();
		$table->binary('input')->nullable();

		$table->string('output_lang')->nullable();
		$table->string('output_spec')->nullable();
		$table->binary('output')->nullable();

		$table->string('comparator')->default('hash');

		$table->unsignedInteger('test_id');
		$table->foreign('test_id')->references('id')->on('tests')->onDelete('cascade');
		$table->timestamps();
		$table->softDeletes();
	});
	echo "created problems\n";
}
if(!Capsule::schema()->hasTable('users')){
	Capsule::schema()->create('users', function($table){
		$table->increments('id');
		$table->string('username');
		$table->string('auth')->default('{}');
		$table->timestamps();
		$table->softDeletes();
	});
	echo "created user\n";
}
if(!Capsule::schema()->hasTable('acls')){
	Capsule::schema()->create('acls', function($table){
		$table->increments('id');
		$table->unsignedInteger('user_id')->nullable();
		$table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
		$table->string('object');
		$table->unsignedInteger('object_id');
		$table->string('acl');
		$table->timestamps();
	});
	echo "created acls\n";
	$acl0 = new \Grader\Model\Acl();
	$acl0->unguard();
	$acl0->fill(array(
		'id' => 0,
		'object' => 'tests',
		'object_id' => 0,
		'acl' => 'read'
	));
	$acl0->save();
	echo "initial acl created\n";
}
if(!Capsule::schema()->hasTable('results')){
	Capsule::schema()->create('results', function($table){
		$table->increments('id');
		$table->unsignedInteger('problem_id');
		$table->foreign('problem_id')->references('id')->on('problems')->onDelete('cascade');
		$table->unsignedInteger('user_id')->nullable();
		$table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
		$table->integer('state')->default(0); // 0=created, 1=wait for more task, 2=graded
		// NULL=not graded, 0=wrong, 1=correct, 2=error
		$table->integer('correct')->nullable();
		$table->text('result')->nullable();
		$table->text('code');
		$table->string('lang');
		$table->string('grader');
		$table->integer('closest')->nullable();
		$table->unsignedInteger('closest_id')->nullable();
		$table->foreign('closest_id')->references('id')->on('results')->onDelete('set null');
		$table->text('error')->default('');
		$table->boolean('count_stats')->default(true);

		$table->index('state');
		$table->index('correct');
		$table->timestamps();
	});
	echo "created results\n";
}
if(!Capsule::schema()->hasTable('task_results')){
	Capsule::schema()->create('task_results', function($table){
		$table->unsignedInteger('id')->primary();
		$table->text('result')->nullable();
		$table->timestamps();
	});
	echo "created task_results\n";
}