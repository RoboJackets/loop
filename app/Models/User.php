<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Nova\Auth\Impersonatable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Scout\Searchable;
use RoboJackets\MeilisearchIndexSettingsHelper\FirstNameSynonyms;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use FirstNameSynonyms;
    use HasApiTokens;
    use HasPermissions;
    use HasRoles;
    use Impersonatable;
    use Searchable;

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<string>
     */
    protected $appends = [
        'name',
    ];

    /**
     * The attributes that should be searchable in Meilisearch.
     *
     * @var array<string>
     */
    public array $searchable_attributes = [
        'first_name',
        'last_name',
        'username',
        'email',
    ];

    /**
     * The attributes that can be used for filtering in Meilisearch.
     *
     * @var array<string>
     */
    public array $filterable_attributes = [
        'permission_id',
        'role_id',
    ];

    protected string $guard_name = 'web';

    /**
     * Get the name associated with the User.
     */
    public function getNameAttribute(): string
    {
        return $this->first_name.' '.$this->last_name;
    }

    /**
     * Get the indexable data array for the model.
     *
     * @return array<string,int|string>
     */
    public function toSearchableArray(): array
    {
        $array = $this->toArray();

        $array['permission_id'] = $this->permissions->modelKeys();

        $array['role_id'] = $this->roles->modelKeys();

        return $array;
    }
}
