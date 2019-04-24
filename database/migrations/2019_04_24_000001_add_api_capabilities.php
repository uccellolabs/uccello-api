<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Uccello\Core\Models\Capability;

class AddApiCapabilities extends Migration
{
    protected $capabilities = [
        'api-retrieve',
        'api-create',
        'api-update',
        'api-delete',
    ];

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        foreach ($this->capabilities as $name) {
            Capability::create([
                'name' => $name,
                'data' => [ 'package' => 'uccello/uccello-api' ]
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        foreach ($this->capabilities as $name) {
            Capability::where('name', $name)->delete();
        }
    }
}
