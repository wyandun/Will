<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int|null $client_user_id
 * @property string $title
 * @property string|null $description
 * @property string|null $draft_url
 * @property string $status
 * @property string|null $docuseal_template_id
 * @property string|null $docuseal_submission_id
 * @property int|null $elaborated_by
 * @property int|null $reviewed_by
 * @property int|null $approved_by
 * @property string|null $signed_document_url
 * @property string|null $certificate_url
 * @property array<int, array<string, mixed>>|null $signers
 * @property Carbon|null $signed_at
 * @property Carbon|null $sent_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Company|null $company
 * @property-read User|null $client
 */
class Contract extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_SIGNED = 'signed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'client_user_id',
        'title',
        'description',
        'draft_url',
        'status',
        'docuseal_template_id',
        'docuseal_submission_id',
        'elaborated_by',
        'reviewed_by',
        'approved_by',
        'signed_document_url',
        'signed_at',
        'certificate_url',
        'signers',
        'sent_at',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'signers' => 'array',
            'signed_at' => 'datetime',
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    /**
     * The client (sb_owner / bb_employee) the contract is for.
     *
     * @return BelongsTo<User, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function elaboratedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'elaborated_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
