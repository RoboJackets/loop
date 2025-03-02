<?php

declare(strict_types=1);

namespace App\Models;

use App\Util\QuickBooks;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Nova\Auth\Impersonatable;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Scout\Searchable;
use QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2AccessToken;
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
 * @property string|null $quickbooks_access_token
 * @property \Illuminate\Support\Carbon|null $quickbooks_access_token_expires_at
 * @property string|null $quickbooks_refresh_token
 * @property \Illuminate\Support\Carbon|null $quickbooks_refresh_token_expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read string $name
 * @property-read \Illuminate\Database\Eloquent\Collection<int,\Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int,\Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int,\Laravel\Sanctum\PersonalAccessToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int,\App\Models\ExternalCommitteeMember> $externalCommitteeMembers
 * @property-read int|null $external_committee_members_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int,\App\Models\ExpenseReport> $expenseReports
 * @property-read int|null $expense_reports_count
 * @property int|null $workday_instance_id
 * @property bool|null $active_employee
 * @property-read string|null $workday_url
 * @property ?OAuth2AccessToken $quickbooks_tokens
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
 * @method static \Illuminate\Database\Eloquent\Builder|User whereQuickbooksAccessToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereQuickbooksAccessTokenExpiresAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereQuickbooksRefreshToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|User whereQuickbooksRefreshTokenExpiresAt($value)
 *
 * @mixin \Barryvdh\LaravelIdeHelper\Eloquent
 */
class User extends Authenticatable
{
    use HasApiTokens;
    use HasPermissions;
    use HasRoles;
    use Impersonatable;
    use Searchable;

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int,string>
     *
     * @phan-read-only
     */
    protected $appends = [
        'name',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     *
     * @phan-read-only
     */
    protected $fillable = [
        'workday_instance_id',
        'first_name',
        'last_name',
        'email',
        'active_employee',
        'username',
    ];

    protected string $guard_name = 'web';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'active_employee' => 'boolean',
            'quickbooks_access_token_expires_at' => 'datetime',
            'quickbooks_refresh_token_expires_at' => 'datetime',
        ];
    }

    /**
     * Get the ECMs for this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\ExternalCommitteeMember, self>
     */
    public function externalCommitteeMembers(): HasMany
    {
        return $this->hasMany(ExternalCommitteeMember::class);
    }

    /**
     * Get the expense reports created by this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\ExpenseReport, self>
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
     */
    public function getWorkdayUrlAttribute(): ?string
    {
        return $this->workday_instance_id === null ? null : 'https://wd5.myworkday.com/gatech/d/inst/1$37/247$'
            .$this->workday_instance_id.'.htmld';
    }

    /**
     * Serialize QuickBooks tokens to the database model.
     *
     * @phan-suppress PhanTypeMismatchProperty
     */
    public function setQuickbooksTokensAttribute(OAuth2AccessToken $tokens): void
    {
        $this->quickbooks_access_token = $tokens->getAccessToken();
        $this->quickbooks_access_token_expires_at = $tokens->getAccessTokenExpiresAt();
        $this->quickbooks_refresh_token = $tokens->getRefreshToken();
        $this->quickbooks_refresh_token_expires_at = $tokens->getRefreshTokenExpiresAt();
    }

    public function getQuickbooksTokensAttribute(): ?OAuth2AccessToken
    {
        if ($this->quickbooks_access_token === null) {
            return null;
        } elseif (
            $this->quickbooks_access_token_expires_at !== null &&
            $this->quickbooks_access_token_expires_at < Carbon::now()
        ) {
            $newTokens = QuickBooks::getDataService()
                ->getOAuth2LoginHelper()
                ->refreshAccessTokenWithRefreshToken($this->quickbooks_refresh_token);

            $newTokens->setRealmID(config('quickbooks.company.id'));

            $this->quickbooks_tokens = $newTokens;
            $this->save();

            return $newTokens;
        } else {
            $tokens = new OAuth2AccessToken(
                cID: config('quickbooks.client.id'),
                cS: config('quickbooks.client.secret'),
                atk: $this->quickbooks_access_token,
                refreshtk: $this->quickbooks_refresh_token
            );

            $tokens->setRealmID(config('quickbooks.company.id'));

            return $tokens;
        }
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
