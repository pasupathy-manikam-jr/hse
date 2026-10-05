<?php

namespace App\Models\Concerns;

use App\Models\Signature;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Records that carry electronic signatures: the signer re-enters their password, and the
 * signature keeps who, what it meant ("approved", "closed") and when. Signatures are append-only.
 */
trait HasSignatures
{
    /**
     * @return MorphMany<Signature, $this>
     */
    public function signatures(): MorphMany
    {
        return $this->morphMany(Signature::class, 'signable')->orderBy('signed_at')->orderBy('id');
    }

    /**
     * Check the password first (call before changing anything), then record the signature.
     */
    public function sign(User $user, ?string $password, string $meaning): Signature
    {
        if ($password === null || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages(['password' => __('The password is incorrect.')]);
        }

        return $this->signatures()->create([
            'user_id' => $user->id,
            'meaning' => $meaning,
            'signer_name' => $user->name,
            'ip_address' => request()->ip(),
            'signed_at' => now(),
        ]);
    }
}
