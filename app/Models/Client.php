<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'contact_person', 'email', 'phone', 'whatsapp_number', 'notes'])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use BelongsToTenant, HasFactory, SoftDeletes;

    /**
     * WhatsApp numbers are stored as digits only (with country code) so incoming messages can be matched.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function whatsappNumber(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => ($digits = preg_replace('/\D/', '', (string) $value)) === '' ? null : $digits);
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** @return HasMany<User, $this> */
    public function portalUsers(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<ClientMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(ClientMessage::class);
    }
}
