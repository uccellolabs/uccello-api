<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Uccello\Core\Database\Migrations\Migration;
use Uccello\Core\Models\Module;
use Uccello\Core\Models\Domain;
use Uccello\Core\Models\Tab;
use Uccello\Core\Models\Block;
use Uccello\Core\Models\Field;
use Uccello\Core\Models\Filter;

class CreateApiTokenModule extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $this->createTable();
        $module = $this->createModule();
        $this->activateModuleOnDomains($module);
        $this->createTabsBlocksFields($module);
        $this->createFilters($module);
        $this->createRelatedLists($module);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Drop table
        Schema::dropIfExists($this->tablePrefix . 'api_tokens');

        // Delete module
        Module::where('name', 'api-token')->forceDelete();
    }

    protected function initTablePrefix()
    {
        $this->tablePrefix = 'uccello_';

        return $this->tablePrefix;
    }

    protected function createTable()
    {
        Schema::create($this->tablePrefix . 'api_tokens', function (Blueprint $table) {
            $table->increments('id');
            $table->string('label');
            $table->string('token')->nullable();
            $table->string('allowed_ip')->nullable();
            $table->date('valid_until')->nullable();
            $table->longText('permissions')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedInteger('domain_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('domain_id')->references('id')->on('uccello_domains');
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    protected function createModule()
    {
        $module = Module::create([
            'name' => 'api-token',
            'icon' => 'confirmation_number',
            'model_class' => 'Uccello\Api\Models\ApiToken',
            'data' => json_decode('{"package":"uccello\/uccello-api","admin":true}')
        ]);

        return $module;
    }

    protected function activateModuleOnDomains($module)
    {
        $domains = Domain::all();
        foreach ($domains as $domain) {
            $domain->modules()->attach($module);
        }
    }

    protected function createTabsBlocksFields($module)
    {
        // Tab tab.main
        $tab = Tab::create([
            'module_id' => $module->id,
            'label' => 'tab.main',
            'icon' => null,
            'sequence' => $module->tabs()->count(),
            'data' => null
        ]);

        // Block block.general
        $block = Block::create([
            'module_id' => $module->id,
            'tab_id' => $tab->id,
            'label' => 'block.general',
            'icon' => 'info',
            'sequence' => $tab->blocks()->count(),
            'data' => null
        ]);

        // Field label
        Field::create([
            'module_id' => $module->id,
            'block_id' => $block->id,
            'name' => 'label',
            'uitype_id' => uitype('text')->id,
            'displaytype_id' => displaytype('everywhere')->id,
            'sequence' => $block->fields()->count(),
            'data' => json_decode('{"rules":"required|unique:uccello_api_tokens,label,%id%"}')
        ]);

        // Field allowed_ip
        Field::create([
            'module_id' => $module->id,
            'block_id' => $block->id,
            'name' => 'allowed_ip',
            'uitype_id' => uitype('text')->id,
            'displaytype_id' => displaytype('everywhere')->id,
            'sequence' => $block->fields()->count(),
            'data' => null
        ]);

        // Field valid_until
        Field::create([
            'module_id' => $module->id,
            'block_id' => $block->id,
            'name' => 'valid_until',
            'uitype_id' => uitype('date')->id,
            'displaytype_id' => displaytype('everywhere')->id,
            'sequence' => $block->fields()->count(),
            'data' => null
        ]);

        // Field token
        Field::create([
            'module_id' => $module->id,
            'block_id' => $block->id,
            'name' => 'token',
            'uitype_id' => uitype('text')->id,
            'displaytype_id' => displaytype('detail')->id,
            'sequence' => $block->fields()->count(),
            'data' => ['large' => true]
        ]);

    }

    protected function createFilters($module)
    {
        // Filter
        Filter::create([
            'module_id' => $module->id,
            'domain_id' => null,
            'user_id' => null,
            'name' => 'filter.all',
            'type' => 'list',
            'columns' => [ 'label', 'token', 'allowed_ip', 'valid_until' ],
            'conditions' => null,
            'order' => null,
            'is_default' => true,
            'is_public' => false,
            'data' => [ 'readonly' => true ]
        ]);
    }
}
