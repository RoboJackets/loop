<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Nova\Auth\Impersonatable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Scout\Searchable;
use RoboJackets\MeilisearchIndexSettingsHelper\FirstNameSynonyms;
use Spatie\Permission\Traits\HasPermissions;
use Spatie\Permission\Traits\HasRoles;

/**
 * A user.
 *
 * @property int $id
 * @property string $username
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $name
 * @property-read \Illuminate\Database\Eloquent\Collection|array<\Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection|array<\Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection|array<\Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read \Illuminate\Database\Eloquent\Collection|array<\App\Models\ExternalCommitteeMember> $externalCommitteeMembers
 * @property-read int|null $external_committee_members_count
 * @property-read \Illuminate\Database\Eloquent\Collection|array<\App\Models\ExpenseReport> $expenseReports
 * @property-read int|null $expense_reports_count
 * @property int|null $workday_instance_id
 * @property bool|null $active_employee
 * @property-read string|null $workday_url
 *
 * @method static \Illuminate\Database\Eloquent\Builder|User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|User permission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder|User query()
 * @method static \Illuminate\Database\Eloquent\Builder|User role($roles, $guard = null)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereFirstName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereLastName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereActiveEmployee($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereWorkdayInstanceId($value)
 * @mixin \Barryvdh\LaravelIdeHelper\Eloquent
 */
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
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'workday_instance_id',
        'first_name',
        'last_name',
        'email',
        'active_employee',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'active_employee' => 'boolean',
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
     * Get the ECMs for this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\ExternalCommitteeMember>
     */
    public function externalCommitteeMembers(): HasMany
    {
        return $this->hasMany(ExternalCommitteeMember::class);
    }

    /**
     * Get the expense reports created by this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\ExpenseReport>
     */
    public function expenseReports(): HasMany
    {
        return $this->hasMany(ExpenseReport::class, 'created_by_worker_id', 'workday_instance_id');
    }

    /**
     * Get the name associated with the User.
     */
    public function getNameAttribute(): string
    {
        return $this->first_name.' '.$this->last_name;
    }

    /**
     * Get the workday_url attribute to show this Worker in the Workday UI.
     *
     * @return ?string
     */
    public function getWorkdayUrlAttribute(): ?string
    {
        return $this->workday_instance_id === null ? null : 'https://wd5.myworkday.com/gatech/d/inst/1$37/247$'
            .$this->workday_instance_id.'.htmld';
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
