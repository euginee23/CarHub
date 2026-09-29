<?php

namespace App\Models;

use Database\Factories\TermsVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * A published revision of the rental terms renters agree to at checkout.
 *
 * @property int $id
 * @property string $version
 * @property string $body Markdown.
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['version', 'body', 'published_at'])]
class TermsVersion extends Model
{
    /** @use HasFactory<TermsVersionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /**
     * The most recently published revision, which new bookings must accept.
     */
    public static function current(): ?self
    {
        return static::whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->latest('published_at')
            ->latest('id')
            ->first();
    }

    /**
     * The terms rendered as HTML. The body is written by administrators only,
     * but raw HTML is still stripped to be safe.
     */
    public function html(): HtmlString
    {
        return new HtmlString(Str::markdown($this->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]));
    }
}
