<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $type
 * @property string|null $company_name
 * @property string|null $contact_name
 * @property string|null $contact_email
 * @property string $status
 * @property string|null $notes
 * @property string|null $admin_note
 * @property int|null $admin_noted_by_user_id
 * @property Carbon|null $admin_noted_at
 * @property-read User|null $adminNotedByUser
 * @property-read User|null $reviewedByUser
 */
class AssessmentContact extends Model
{
    use HasFactory;

    protected $table = 'assessment_contacts';

    protected $fillable = [
        'type',
        'current_stage',
        'stage_1_data',
        'stage_2_data',
        'stage_3_data',
        'stage_4_data',
        'data',
        'score',
        'score_breakdown',
        'result_pdf_path',
        'status',
        'company_name',
        'company_industry',
        'company_phone',
        'company_email',
        'company_address',
        'company_state',
        'company_zip',
        'years_operating',
        'employees_count',
        'annual_revenue',
        'contact_name',
        'contact_title',
        'contact_phone',
        'contact_email',
        'preferred_lang',
        'best_time',
        'notes',
        'token',
        'admin_note',
        'admin_noted_by_user_id',
        'admin_noted_at',
        'converted_company_id',
        'reviewed_by',
    ];

    protected $casts = [
        'stage_1_data' => 'array',
        'stage_2_data' => 'array',
        'stage_3_data' => 'array',
        'stage_4_data' => 'array',
        'data' => 'array',
        'score_breakdown' => 'array',
        'score' => 'decimal:2',
        'admin_noted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    // ---------------------------------------------------------------------------
    // Relationships
    // ---------------------------------------------------------------------------

    /**
     * The admin_sm user who last saved an internal audit note on this contact.
     *
     * @return BelongsTo<User, $this>
     */
    public function adminNotedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_noted_by_user_id');
    }

    /**
     * The user who reviewed this contact.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * The company created when this contact was converted via Close Deal.
     *
     * @return BelongsTo<Company, $this>
     */
    public function convertedCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'converted_company_id');
    }
}
