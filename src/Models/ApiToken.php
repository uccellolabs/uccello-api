<?php

namespace Uccello\Api\Models;

use App\Models\UccelloModel;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Uccello\Core\Support\Traits\UccelloModule;

class ApiToken extends UccelloModel
{
    use SoftDeletes;
    use UccelloModule;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'api_tokens';

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at', 'valid_until'];

    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts = [
        'permissions' => 'object'
    ];

    protected $fillable = [
        'label',
        'token',
        'allowed_ip',
        'valid_until',
        'permissions',
        'user_id',
        'domain_id'
    ];

    public static function booted()
    {
        static::creating(function ($model) {
            $user = $model->createTokenUser();
            $model->user_id = $user->id;
            $model->token = $model->generateToken($user);
        });

        static::saving(function ($model) {
            $model->permissions = request('permissions');
        });
    }

    protected function initTablePrefix()
    {
        $this->tablePrefix = 'uccello_';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
    * Returns record label
    *
    * @return string
    */
    public function getRecordLabelAttribute() : string
    {
        return $this->label;
    }

    protected function generateToken($user)
    {
        return $user->createToken($this->label)->plainTextToken;
    }

    protected function createTokenUser()
    {
        return User::create([
            'username' => Str::uuid(),
            'name' => $this->label,
            'email' => 'token-'.date('YmdHis').'@faker.tld',
            'password' => Hash::make(Str::random(32)),
            'type' => 'token',
            'domain_id' => $this->domain_id
        ]);
    }
}
