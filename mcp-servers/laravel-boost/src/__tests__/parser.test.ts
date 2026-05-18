import { describe, it, expect } from 'vitest';
import { parseModel, parseService, parseController, parseRequest, parsePolicy } from '../utils/parser.js';

// Fixtures — simplified but representative PHP snippets from the SM Portal

const USER_MODEL = `<?php
namespace App\\Models;
use Illuminate\\Database\\Eloquent\\SoftDeletes;
use Laravel\\Sanctum\\HasApiTokens;
use Spatie\\Permission\\Traits\\HasRoles;
use Illuminate\\Database\\Eloquent\\Relations\\BelongsTo;
use Illuminate\\Database\\Eloquent\\Relations\\HasMany;

class User extends Authenticatable {
    use HasApiTokens, HasRoles, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'sm_franchise_id', 'company_id',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'invitation_accepted_at' => 'datetime',
        'email_verified_at' => 'datetime',
    ];

    public function franchise(): BelongsTo {
        return $this->belongsTo(Franchise::class, 'sm_franchise_id');
    }

    public function company(): BelongsTo {
        return $this->belongsTo(Company::class);
    }

    public function posts(): HasMany {
        return $this->hasMany(Post::class, 'author_id');
    }

    public function scopePendingInvitation($query) {
        return $query->whereNotNull('invitation_token');
    }
}`;

const COMPANY_SERVICE = `<?php
namespace App\\Services;
use App\\Models\\Company;
use App\\Models\\User;
use Illuminate\\Support\\Facades\\DB;

class CompanyService {
    public function list(User $authUser) {
        return Company::paginate(25);
    }

    public function store(array $data): Company {
        return DB::transaction(function () use ($data) {
            return Company::create($data);
        });
    }

    public function update(Company $company, array $data): Company {
        $company->lockForUpdate();
        $company->update($data);
        return $company;
    }
}`;

const FRANCHISE_CONTROLLER = `<?php
namespace App\\Http\\Controllers\\Api;
use App\\Services\\FranchiseService;
use App\\Http\\Requests\\StoreFranchiseRequest;

class FranchiseController extends Controller {
    public function __construct(private FranchiseService $franchiseService) {}

    public function index() { $this->authorize('viewAny', Franchise::class); }
    public function store(StoreFranchiseRequest $request) {}
    public function show(Franchise $franchise) {}
    public function update(UpdateFranchiseRequest $request, Franchise $franchise) {}
    public function destroy(Franchise $franchise) {}
}`;

const SEND_INVITATION_REQUEST = `<?php
namespace App\\Http\\Requests;
use Illuminate\\Validation\\Rule;

class SendInvitationRequest extends FormRequest {
    public function rules(): array {
        return [
            'email' => ['required', 'email', Rule::unique('users')->whereNull('deleted_at')],
            'role' => ['required', 'string'],
            'sm_franchise_id' => ['nullable', 'integer'],
        ];
    }
}`;

const FRANCHISE_POLICY = `<?php
namespace App\\Policies;
use App\\Enums\\Role;

class FranchisePolicy {
    public function viewAny(User $user): bool {
        return $user->hasRole(Role::SUPERADMIN) || $user->hasRole(Role::ADMIN_SM);
    }
    public function update(User $user, Franchise $franchise): bool {
        if ($user->hasRole(Role::SUPERADMIN)) return true;
        return $user->sm_franchise_id === $franchise->id;
    }
    public function delete(User $user, Franchise $franchise): bool {
        return $user->hasRole(Role::SUPERADMIN);
    }
}`;

describe('parseModel', () => {
  it('extracts fillable', () => {
    const result = parseModel(USER_MODEL);
    expect(result.fillable).toContain('name');
    expect(result.fillable).toContain('email');
    expect(result.fillable).toContain('sm_franchise_id');
  });

  it('extracts hidden fields', () => {
    const result = parseModel(USER_MODEL);
    expect(result.hidden).toContain('password');
  });

  it('extracts casts', () => {
    const result = parseModel(USER_MODEL);
    expect(result.casts['invitation_accepted_at']).toBe('datetime');
  });

  it('detects SoftDeletes trait', () => {
    const result = parseModel(USER_MODEL);
    expect(result.softDeletes).toBe(true);
  });

  it('extracts relationships', () => {
    const result = parseModel(USER_MODEL);
    const relNames = result.relationships.map(r => r.name);
    expect(relNames).toContain('franchise');
    expect(relNames).toContain('company');
    expect(relNames).toContain('posts');
  });

  it('extracts relationship types', () => {
    const result = parseModel(USER_MODEL);
    const franchise = result.relationships.find(r => r.name === 'franchise');
    expect(franchise?.type).toBe('belongsTo');
    const posts = result.relationships.find(r => r.name === 'posts');
    expect(posts?.type).toBe('hasMany');
  });

  it('extracts scopes', () => {
    const result = parseModel(USER_MODEL);
    expect(result.scopes).toContain('PendingInvitation');
  });

  it('extracts class name', () => {
    const result = parseModel(USER_MODEL);
    expect(result.name).toBe('User');
  });
});

describe('parseService', () => {
  it('extracts class name', () => {
    const result = parseService(COMPANY_SERVICE);
    expect(result.name).toBe('CompanyService');
  });

  it('extracts public methods', () => {
    const result = parseService(COMPANY_SERVICE);
    expect(result.publicMethods).toContain('list');
    expect(result.publicMethods).toContain('store');
    expect(result.publicMethods).toContain('update');
  });

  it('detects DB::transaction usage', () => {
    const result = parseService(COMPANY_SERVICE);
    expect(result.usesTransaction).toBe(true);
  });

  it('detects lockForUpdate usage', () => {
    const result = parseService(COMPANY_SERVICE);
    expect(result.usesLockForUpdate).toBe(true);
  });

  it('extracts imported models', () => {
    const result = parseService(COMPANY_SERVICE);
    expect(result.imports).toContain('Company');
    expect(result.imports).toContain('User');
  });
});

describe('parseController', () => {
  it('extracts class name', () => {
    const result = parseController(FRANCHISE_CONTROLLER);
    expect(result.name).toBe('FranchiseController');
  });

  it('detects injected service', () => {
    const result = parseController(FRANCHISE_CONTROLLER);
    expect(result.service).toBe('FranchiseService');
  });

  it('extracts public methods', () => {
    const result = parseController(FRANCHISE_CONTROLLER);
    expect(result.publicMethods).toContain('index');
    expect(result.publicMethods).toContain('store');
  });

  it('detects policy usage', () => {
    const result = parseController(FRANCHISE_CONTROLLER);
    expect(result.usesPolicy).toBe(true);
  });
});

describe('parseRequest', () => {
  it('extracts class name', () => {
    const result = parseRequest(SEND_INVITATION_REQUEST);
    expect(result.name).toBe('SendInvitationRequest');
  });

  it('extracts rules keys', () => {
    const result = parseRequest(SEND_INVITATION_REQUEST);
    expect(Object.keys(result.rules)).toContain('email');
    expect(Object.keys(result.rules)).toContain('role');
  });
});

describe('parsePolicy', () => {
  it('extracts class name', () => {
    const result = parsePolicy(FRANCHISE_POLICY);
    expect(result.name).toBe('FranchisePolicy');
  });

  it('extracts policy methods', () => {
    const result = parsePolicy(FRANCHISE_POLICY);
    const names = result.methods.map(m => m.name);
    expect(names).toContain('viewAny');
    expect(names).toContain('update');
    expect(names).toContain('delete');
  });
});
