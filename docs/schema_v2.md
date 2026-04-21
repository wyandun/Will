# SM Portal — Database Schema v2

**Version:** 2.0
**Date:** April 2026
**Engine:** PostgreSQL 16
**ORM:** Laravel 12 Eloquent
**Status:** Authoritative reference for all migrations and models

---

## Table of Contents

1. [Domain Table Inventory (22 tables)](#1-domain-table-inventory-22-tables)
2. [Full Table Definitions](#2-full-table-definitions)
3. [Laravel Migrations — New and Modified Tables](#3-laravel-migrations--new-and-modified-tables)
4. [Laravel Migrations — Cleanup (Dropped Tables)](#4-laravel-migrations--cleanup-dropped-tables)
5. [Eloquent Relationships](#5-eloquent-relationships)
6. [Process Tree ASCII Diagram](#6-process-tree-ascii-diagram)

---

## 1. Domain Table Inventory (22 tables)

| # | Group | Table | Status in v2 |
|---|-------|-------|--------------|
| 1 | Auth | `users` | Unchanged |
| 2 | Auth | `user_permissions` | Modified — `inventory` removed from module enum, `applications` added |
| 3 | Org | `franchises` | Unchanged |
| 4 | Org | `companies` | Modified — QBO fields added |
| 5 | Org | `bb_assignments` | Unchanged |
| 6 | Onboarding | `assessment_contacts` | Modified — redesigned for 4-stage flow |
| 7 | Onboarding | `assessment_decisions` | Unchanged |
| 8 | Feed | `posts` | Unchanged |
| 9 | Feed | `post_interactions` | Unchanged |
| 10 | Contracts | `contracts` | Unchanged |
| 11 | Repository | `repositories` | Unchanged |
| 12 | Repository | `repository_documents` | Modified — `record` section added |
| 13 | Processes | `process_maps` | Unchanged |
| 14 | Processes | `process_categories` | Unchanged |
| 15 | Processes | `processes` | Unchanged |
| 16 | Processes | `sub_processes` | Unchanged |
| 17 | Processes | `sub_sub_processes` | **NEW** |
| 18 | Processes | `process_documents` | Modified — polymorphic + expanded type enum |
| 19 | Accounting | `financial_documents` | Modified — simplified (AI fields removed) |
| 20 | Catalog | `catalog_items` | Unchanged |
| 21 | Tracking | `client_trackings` | Modified — Gantt fields added |
| 22 | Calendar | `events` + `event_shares` | Unchanged |

**Dropped tables (7):** `inventory_items`, `inventory_movements`, `chart_of_accounts`, `journal_entries`, `journal_entry_lines`, `bank_transactions`, `pos_connections`.

**Spatie tables (5, not counted):** `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`.

---

## 2. Full Table Definitions

### 2.1 `user_permissions` (Modified)

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigserial | PK | |
| user_id | bigint | FK → users CASCADE | |
| module | varchar(30) | NOT NULL | Enum: `feed`, `contracts`, `repository`, `processes`, `accounting`, `tracking`, `catalog`, `calendar`, `applications` |
| can_read | boolean | DEFAULT false | |
| can_write | boolean | DEFAULT false | |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Indexes:** UNIQUE `(user_id, module)`.

> `inventory` has been removed from the module enum. `applications` has been added.

---

### 2.2 `companies` (Modified — QBO fields)

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigserial | PK | |
| name | varchar(180) | NOT NULL | |
| industry | varchar(120) | NULL | |
| state | varchar(60) | NULL | |
| country | varchar(60) | NULL | |
| sm_franchise_id | bigint | FK → franchises NOT NULL | Managing SM franchise |
| employees_count | int | NULL | |
| annual_revenue | decimal(15,2) | NULL | |
| years_operating | int | NULL | |
| logo_path | varchar(255) | NULL | |
| address | varchar(255) | NULL | |
| phone | varchar(40) | NULL | |
| website | varchar(180) | NULL | |
| qbo_realm_id | varchar(60) | NULL | QuickBooks Online company ID |
| qbo_access_token | text | NULL | Encrypted via `Crypt::encryptString` |
| qbo_refresh_token | text | NULL | Encrypted |
| qbo_token_expires_at | timestamp | NULL | |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Indexes:** `(sm_franchise_id)`, `(name)`, `(qbo_realm_id)`.

---

### 2.3 `assessment_contacts` (Redesigned — 4-stage Assessment 1)

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigserial | PK | |
| type | varchar(20) | NOT NULL | Enum: `sb_assessment_1`, `sb_assessment_3`, `bb_application` |
| current_stage | varchar(30) | NULL | Only for `sb_assessment_1`: `operational_maturity`, `franchise_alignment`, `bb_simulator`, `results` |
| stage_1_data | jsonb | NULL | Operational maturity — 7 dimensions (answers + scores) |
| stage_2_data | jsonb | NULL | Franchise alignment answers |
| stage_3_data | jsonb | NULL | BB simulator inputs + 5-year projection output |
| stage_4_data | jsonb | NULL | Final result snapshot + PDF metadata |
| data | jsonb | NULL | Raw payload for Assessment 3 and BB application |
| score | decimal(5,2) | NULL | Final aggregated score |
| score_breakdown | jsonb | NULL | Per-dimension breakdown |
| result_pdf_path | varchar(255) | NULL | Generated PDF path (stage 4) |
| status | varchar(20) | NOT NULL DEFAULT 'in_progress' | Enum: `in_progress`, `pending`, `reviewed`, `approved`, `rejected`, `converted` |
| converted_company_id | bigint | FK → companies NULL | Set on "Close Deal" |
| reviewed_by | bigint | FK → users NULL | |
| reviewed_at | timestamp | NULL | |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Indexes:** `(status)`, `(type)`, `(current_stage)`, GIN on `stage_1_data`, `stage_2_data`, `stage_3_data`.

> `sb_assessment_2` is removed. `sb_assessment_3` is added. `in_progress` is added to status enum (users can save progress across stages).

---

### 2.4 `repository_documents` (Modified — record section)

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigserial | PK | |
| repository_id | bigint | FK → repositories CASCADE | |
| section | varchar(20) | NOT NULL | Enum: `setup`, `process`, `record` |
| category | varchar(30) | NULL | Enum: `legal`, `hr`, `certificates`, `marketing`, `sops`, `process_linked`, `record_linked` |
| process_code | varchar(40) | NULL | e.g. `GTH-P01` — required when section ∈ {`process`, `record`} |
| record_date | date | NULL | Required when section = `record` |
| record_period | varchar(40) | NULL | Optional label, e.g. `2026-Q1` |
| title | varchar(200) | NOT NULL | |
| description | text | NULL | |
| file_path | varchar(255) | NOT NULL | |
| file_type | varchar(60) | NOT NULL | |
| file_size | bigint | NOT NULL | Bytes |
| uploaded_by | bigint | FK → users NOT NULL | |
| uploaded_by_type | varchar(10) | NOT NULL | Enum: `sm`, `client` |
| version | int | NOT NULL DEFAULT 1 | |
| parent_id | bigint | FK → repository_documents NULL | Previous version |
| is_current | boolean | NOT NULL DEFAULT true | |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Indexes:** `(repository_id, section, is_current)`, `(process_code)`, `(section, category)`.

**Tab mapping:**
- **Tab 1 — Company Setup** → `section='setup'` — `process_code` is NULL
- **Tab 2 — Process Documents** → `section='process'` — `process_code` required
- **Tab 3 — Records by Process** → `section='record'` — `process_code` + `record_date` required

---

### 2.5 `sub_sub_processes` (NEW)

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigserial | PK | |
| sub_process_id | bigint | FK → sub_processes CASCADE NOT NULL | Parent sub-process |
| code | varchar(60) | NOT NULL | e.g. `GTH-P01-S01` |
| name_es | varchar(200) | NOT NULL | |
| name_en | varchar(200) | NOT NULL | |
| bpmn_xml_es | text | NULL | Optional — leaf may have its own BPMN |
| bpmn_xml_en | text | NULL | Filled by `TranslateBpmnXml` job |
| walkthrough_es | jsonb | NULL | Step-by-step narration tied to BPMN shape IDs |
| walkthrough_en | jsonb | NULL | |
| manual_document_id | bigint | FK → process_documents NULL | "View Manual" shortcut |
| order_index | int | NOT NULL DEFAULT 0 | |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Indexes:** UNIQUE `(code)`, `(sub_process_id)`.

> A sub-subprocess is optional. Some sub_processes have children (sub_sub_processes); others are direct leaves.

---

### 2.6 `process_documents` (Modified — polymorphic + expanded types)

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigserial | PK | |
| documentable_type | varchar(80) | NOT NULL | `App\Models\Process`, `App\Models\SubProcess`, `App\Models\SubSubProcess` |
| documentable_id | bigint | NOT NULL | |
| code | varchar(60) | NOT NULL | e.g. `GTH-P01-FOR-01` |
| type | varchar(5) | NOT NULL | Enum: `MP`, `FOR`, `MN`, `IN`, `AN`, `PO`, `PR`, `CR` |
| title_es | varchar(200) | NOT NULL | |
| title_en | varchar(200) | NOT NULL | |
| description | text | NULL | |
| file_url | varchar(500) | NULL | Replaces legacy `onedrive_url` |
| version | int | NOT NULL DEFAULT 1 | |
| parent_id | bigint | FK → process_documents NULL | |
| is_current | boolean | NOT NULL DEFAULT true | |
| uploaded_by | bigint | FK → users NOT NULL | |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Indexes:** `(documentable_type, documentable_id)`, UNIQUE `(code, version)`.

**Document type reference:**

| Code | Full name |
|------|-----------|
| `MP` | Manual de Procedimiento |
| `FOR` | Formato |
| `MN` | Manual |
| `IN` | Instructivo |
| `AN` | Anexo |
| `PO` | Política |
| `PR` | Procedimiento |
| `CR` | Criterio / Referencia |

---

### 2.7 `financial_documents` (Simplified)

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigserial | PK | |
| company_id | bigint | FK → companies CASCADE NOT NULL | |
| type | varchar(20) | NOT NULL | Enum: `bank_statement`, `invoice`, `receipt`, `other` |
| file_path | varchar(255) | NOT NULL | |
| file_type | varchar(60) | NOT NULL | |
| original_filename | varchar(255) | NOT NULL | |
| period_label | varchar(40) | NULL | e.g. `2026-04` |
| notes | text | NULL | |
| uploaded_by | bigint | FK → users NOT NULL | |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Indexes:** `(company_id, type, created_at)`.

> **Removed vs v1:** `processed_at`, `processing_status`. No AI/OCR fields. Pure file vault.

---

### 2.8 `client_trackings` (Modified — Gantt fields)

| Column | Type | Constraints | Notes |
|--------|------|-------------|-------|
| id | bigserial | PK | |
| company_id | bigint | FK → companies CASCADE NOT NULL | |
| catalog_item_id | bigint | FK → catalog_items NOT NULL | Must be `level='deliverable'` |
| status | varchar(20) | NOT NULL DEFAULT 'pending' | Enum: `pending`, `in_progress`, `review`, `completed`, `cancelled` |
| estimated_start | date | NULL | Gantt bar start |
| estimated_end | date | NULL | Gantt bar end |
| actual_start | date | NULL | Real start date |
| actual_end | date | NULL | Real completion date |
| progress_percent | int | NOT NULL DEFAULT 0 | 0–100 for Gantt bar fill |
| month_number | int | NULL | For recurring monthly deliverables |
| notes | text | NULL | |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Indexes:** `(company_id, status)`, `(estimated_start, estimated_end)`.

> **Added vs v1:** `actual_start`, `progress_percent`. These feed the Gantt view; Kanban uses `status`.

---

## 3. Laravel Migrations — New and Modified Tables

> Migration file naming follows the convention `YYYY_MM_DD_HHMMSS_description.php`.
> Execution order matters: run `sub_sub_processes` before its `manual_document_id` FK on `process_documents`.
> All migrations are written for **Laravel 12 + PHP 8.4**.

---

### 3.1 Create `sub_sub_processes`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_sub_processes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('sub_process_id')
                ->constrained('sub_processes')
                ->cascadeOnDelete();

            $table->string('code', 60)->unique();
            $table->string('name_es', 200);
            $table->string('name_en', 200);

            $table->text('bpmn_xml_es')->nullable();
            $table->text('bpmn_xml_en')->nullable();

            $table->jsonb('walkthrough_es')->nullable();
            $table->jsonb('walkthrough_en')->nullable();

            // Deferred FK: process_documents is created after this table.
            // The FK is added in a separate migration (see 3.2) after both
            // tables exist.
            $table->unsignedBigInteger('manual_document_id')->nullable();

            $table->integer('order_index')->default(0);

            $table->timestamps();

            $table->index('sub_process_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_sub_processes');
    }
};
```

---

### 3.2 Modify `process_documents` — polymorphic parent + expanded type enum

> This migration replaces the `sub_process_id` FK with a polymorphic pair
> (`documentable_type`, `documentable_id`) and updates the `type` column.
> Run **after** `sub_sub_processes` exists so the deferred FK on
> `sub_sub_processes.manual_document_id` can also be wired here.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_documents', function (Blueprint $table) {
            // Add polymorphic columns (replace direct sub_process_id FK).
            // Existing data migration (if any) must be handled before running this.
            $table->string('documentable_type', 80)->after('id');
            $table->unsignedBigInteger('documentable_id')->after('documentable_type');

            // Drop the old direct FK if it exists from v1.
            // Guard with hasColumn to make this migration idempotent.
            if (Schema::hasColumn('process_documents', 'sub_process_id')) {
                $table->dropForeign(['sub_process_id']);
                $table->dropColumn('sub_process_id');
            }

            // Update type column to the new 8-value enum.
            // PostgreSQL does not support modifying enum in-place cleanly;
            // rename the old column, add the new one, then drop the old.
            $table->string('type_new', 5)->after('code')->nullable();
        });

        // Copy existing data to the new type column, mapping old → new codes.
        // Update this mapping to match whatever values were used in v1.
        DB::statement("
            UPDATE process_documents
            SET type_new = CASE
                WHEN type = 'manual'      THEN 'MN'
                WHEN type = 'form'        THEN 'FOR'
                WHEN type = 'record'      THEN 'CR'
                WHEN type = 'policy'      THEN 'PO'
                WHEN type = 'certificate' THEN 'CR'
                ELSE 'MN'
            END
        ");

        Schema::table('process_documents', function (Blueprint $table) {
            $table->dropColumn('type');
            $table->renameColumn('type_new', 'type');

            // Add composite index for polymorphic queries.
            $table->index(['documentable_type', 'documentable_id']);

            // Unique constraint on code + version.
            // Drop old unique on code alone if it existed.
            // Then re-create.
            $table->unique(['code', 'version']);
        });

        // Wire the deferred FK on sub_sub_processes.manual_document_id now that
        // process_documents exists and has its final shape.
        Schema::table('sub_sub_processes', function (Blueprint $table) {
            $table->foreign('manual_document_id')
                ->references('id')
                ->on('process_documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sub_sub_processes', function (Blueprint $table) {
            $table->dropForeign(['manual_document_id']);
        });

        Schema::table('process_documents', function (Blueprint $table) {
            $table->dropIndex(['documentable_type', 'documentable_id']);

            // Restore old sub_process_id column.
            $table->foreignId('sub_process_id')
                ->nullable()
                ->constrained('sub_processes')
                ->nullOnDelete();

            // Restore type as varchar (old values — approximation).
            $table->string('type_old', 20)->nullable();
        });

        DB::statement("
            UPDATE process_documents
            SET type_old = CASE
                WHEN type = 'MN'  THEN 'manual'
                WHEN type = 'FOR' THEN 'form'
                WHEN type = 'PO'  THEN 'policy'
                WHEN type = 'CR'  THEN 'record'
                ELSE 'manual'
            END
        ");

        Schema::table('process_documents', function (Blueprint $table) {
            $table->dropColumn(['documentable_type', 'documentable_id', 'type']);
            $table->renameColumn('type_old', 'type');
        });
    }
};
```

---

### 3.3 Modify `financial_documents` — remove AI fields, add `period_label` and `notes`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_documents', function (Blueprint $table) {
            // Remove AI/OCR processing fields.
            if (Schema::hasColumn('financial_documents', 'processing_status')) {
                $table->dropColumn('processing_status');
            }

            if (Schema::hasColumn('financial_documents', 'processed_at')) {
                $table->dropColumn('processed_at');
            }

            // Add vault-only fields.
            if (!Schema::hasColumn('financial_documents', 'period_label')) {
                $table->string('period_label', 40)->nullable()->after('original_filename');
            }

            if (!Schema::hasColumn('financial_documents', 'notes')) {
                $table->text('notes')->nullable()->after('period_label');
            }

            // Add 'other' to the type enum if not present.
            // PostgreSQL requires ALTER TYPE for native enum; using varchar avoids this.
            // If the column is already varchar, just document the new accepted value.
        });

        // Update compound index to include type for filtered listing queries.
        // Drop old index first if it exists, then create the new one.
        Schema::table('financial_documents', function (Blueprint $table) {
            $table->index(['company_id', 'type', 'created_at'], 'financial_documents_company_type_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('financial_documents', function (Blueprint $table) {
            $table->dropIndexIfExists('financial_documents_company_type_created_idx');

            $table->dropColumn(['period_label', 'notes']);

            $table->string('processing_status', 30)->nullable();
            $table->timestamp('processed_at')->nullable();
        });
    }
};
```

---

### 3.4 Modify `repository_documents` — add `record` section fields

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('repository_documents', function (Blueprint $table) {
            // Add record-specific fields after the existing section column.
            // section already exists as varchar; 'record' is a new accepted value.
            $table->date('record_date')->nullable()->after('process_code');
            $table->string('record_period', 40)->nullable()->after('record_date');

            // Update the section index to include is_current for faster tab queries.
            $table->index(
                ['repository_id', 'section', 'is_current'],
                'repo_docs_repository_section_current_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('repository_documents', function (Blueprint $table) {
            $table->dropIndexIfExists('repo_docs_repository_section_current_idx');
            $table->dropColumn(['record_date', 'record_period']);
        });
    }
};
```

---

### 3.5 Modify `assessment_contacts` — 4-stage redesign

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_contacts', function (Blueprint $table) {
            // New stage columns (after the existing 'type' column).
            $table->string('current_stage', 30)->nullable()->after('type');

            $table->jsonb('stage_1_data')->nullable()->after('current_stage');
            $table->jsonb('stage_2_data')->nullable()->after('stage_1_data');
            $table->jsonb('stage_3_data')->nullable()->after('stage_2_data');
            $table->jsonb('stage_4_data')->nullable()->after('stage_3_data');

            // Generated PDF path for stage 4.
            $table->string('result_pdf_path', 255)->nullable()->after('stage_4_data');

            // Add 'in_progress' to status (users can leave and return between stages).
            // If status column is varchar, no ALTER TYPE needed — just document it.

            // GIN indexes for JSONB stage columns (fast key searches).
            $table->rawIndex("USING GIN (stage_1_data)", 'assessment_contacts_stage_1_data_gin');
            $table->rawIndex("USING GIN (stage_2_data)", 'assessment_contacts_stage_2_data_gin');
            $table->rawIndex("USING GIN (stage_3_data)", 'assessment_contacts_stage_3_data_gin');

            // Standard indexes.
            $table->index('current_stage');
            $table->index('type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_contacts', function (Blueprint $table) {
            $table->dropIndexIfExists('assessment_contacts_stage_1_data_gin');
            $table->dropIndexIfExists('assessment_contacts_stage_2_data_gin');
            $table->dropIndexIfExists('assessment_contacts_stage_3_data_gin');
            $table->dropIndex(['current_stage']);
            $table->dropIndex(['type']);
            $table->dropIndex(['status']);

            $table->dropColumn([
                'current_stage',
                'stage_1_data',
                'stage_2_data',
                'stage_3_data',
                'stage_4_data',
                'result_pdf_path',
            ]);
        });
    }
};
```

---

### 3.6 Modify `companies` — QBO OAuth2 fields

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('qbo_realm_id', 60)->nullable()->after('website');
            $table->text('qbo_access_token')->nullable()->after('qbo_realm_id');
            $table->text('qbo_refresh_token')->nullable()->after('qbo_access_token');
            $table->timestamp('qbo_token_expires_at')->nullable()->after('qbo_refresh_token');

            $table->index('qbo_realm_id');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['qbo_realm_id']);
            $table->dropColumn([
                'qbo_realm_id',
                'qbo_access_token',
                'qbo_refresh_token',
                'qbo_token_expires_at',
            ]);
        });
    }
};
```

---

### 3.7 Modify `client_trackings` — Gantt fields

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_trackings', function (Blueprint $table) {
            $table->date('actual_start')->nullable()->after('estimated_end');
            $table->integer('progress_percent')->default(0)->after('actual_end');

            // Index for Gantt date range queries.
            $table->index(['estimated_start', 'estimated_end'], 'trackings_gantt_dates_idx');
        });
    }

    public function down(): void
    {
        Schema::table('client_trackings', function (Blueprint $table) {
            $table->dropIndexIfExists('trackings_gantt_dates_idx');
            $table->dropColumn(['actual_start', 'progress_percent']);
        });
    }
};
```

---

### 3.8 Modify `user_permissions` — update module enum

> PostgreSQL native enum types cannot be altered without dropping and recreating the type.
> The recommended approach for Laravel is to use `varchar` for the module column so that
> adding or removing accepted values only requires application-level validation, not a DDL change.
> If the original migration used a native PostgreSQL enum, use the raw statement approach below.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // If module is stored as varchar: no DDL change needed.
        // The enum constraint is enforced at the application layer (Enum class + FormRequest).
        // Just update the PHP Enum file: remove 'inventory', add 'applications'.

        // If module was defined as a PostgreSQL native enum type, use this approach:
        // DB::statement("ALTER TYPE user_permissions_module_enum ADD VALUE IF NOT EXISTS 'applications'");
        // Note: removing 'inventory' from a native PG enum requires a full column rebuild.
        // See the raw SQL block below if that is the case.

        // Safe approach — convert to varchar if still using native enum:
        if ($this->columnIsNativeEnum()) {
            DB::statement("
                ALTER TABLE user_permissions
                ALTER COLUMN module TYPE varchar(30)
                USING module::text
            ");
        }

        // Remove any existing rows that reference the removed 'inventory' module.
        // This keeps data consistent before the application enforces the new enum.
        DB::table('user_permissions')->where('module', 'inventory')->delete();
    }

    public function down(): void
    {
        // Re-insert is not safe (data was intentionally deleted).
        // No-op on rollback for the data deletion.
        // If the column type was changed, revert to varchar (acceptable — no data loss).
    }

    private function columnIsNativeEnum(): bool
    {
        $result = DB::select("
            SELECT data_type
            FROM information_schema.columns
            WHERE table_name = 'user_permissions'
              AND column_name = 'module'
        ");

        return isset($result[0]) && $result[0]->data_type === 'USER-DEFINED';
    }
};
```

**Required PHP Enum update** (`app/Enums/PermissionModule.php`):

```php
<?php

namespace App\Enums;

enum PermissionModule: string
{
    case Feed         = 'feed';
    case Contracts    = 'contracts';
    case Repository   = 'repository';
    case Processes    = 'processes';
    case Accounting   = 'accounting';
    case Tracking     = 'tracking';
    case Catalog      = 'catalog';
    case Calendar     = 'calendar';
    case Applications = 'applications';
    // 'inventory' removed in v2.0
}
```

---

## 4. Laravel Migrations — Cleanup (Dropped Tables)

These migrations drop the 7 tables that no longer exist in v2. Run them **after** verifying that no FK references point to them from remaining tables.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop tables removed in v2.0.
     * Order matters: child tables must be dropped before parent tables.
     *
     * Inventory tables have no external FKs pointing to them from v2 tables.
     * Accounting tables: bank_transactions depends on financial_documents (already simplified)
     * and journal_entries; journal_entry_lines depends on journal_entries and chart_of_accounts.
     */
    public function up(): void
    {
        // Inventory (no external deps in v2)
        Schema::dropIfExists('inventory_movements');
        Schema::dropIfExists('inventory_items');

        // Accounting — child tables first
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('chart_of_accounts');

        // POS integrations
        Schema::dropIfExists('pos_connections');
    }

    public function down(): void
    {
        // These tables are intentionally not restored on rollback.
        // To restore them, re-run the original v1 migrations individually.
        // This is a one-way migration.
    }
};
```

---

## 5. Eloquent Relationships

### 5.1 `SubSubProcess` model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SubSubProcess extends Model
{
    protected $fillable = [
        'sub_process_id',
        'code',
        'name_es',
        'name_en',
        'bpmn_xml_es',
        'bpmn_xml_en',
        'walkthrough_es',
        'walkthrough_en',
        'manual_document_id',
        'order_index',
    ];

    protected $casts = [
        'walkthrough_es' => 'array',
        'walkthrough_en' => 'array',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function subProcess(): BelongsTo
    {
        return $this->belongsTo(SubProcess::class);
    }

    public function manualDocument(): BelongsTo
    {
        return $this->belongsTo(ProcessDocument::class, 'manual_document_id');
    }

    /**
     * All process documents attached to this sub-sub-process (polymorphic).
     */
    public function processDocuments(): MorphMany
    {
        return $this->morphMany(ProcessDocument::class, 'documentable');
    }
}
```

---

### 5.2 `SubProcess` model (additions for new level)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SubProcess extends Model
{
    protected $fillable = [
        'process_id',
        'code',
        'name_es',
        'name_en',
        'bpmn_xml_es',
        'bpmn_xml_en',
        'walkthrough_es',
        'walkthrough_en',
        'manual_document_id',
        'order_index',
    ];

    protected $casts = [
        'walkthrough_es' => 'array',
        'walkthrough_en' => 'array',
    ];

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }

    public function manualDocument(): BelongsTo
    {
        return $this->belongsTo(ProcessDocument::class, 'manual_document_id');
    }

    /**
     * Third-level children.
     */
    public function subSubProcesses(): HasMany
    {
        return $this->hasMany(SubSubProcess::class)->orderBy('order_index');
    }

    /**
     * Documents attached directly to this sub-process (polymorphic).
     */
    public function processDocuments(): MorphMany
    {
        return $this->morphMany(ProcessDocument::class, 'documentable');
    }

    /**
     * Returns true if this sub-process has leaf children (sub_sub_processes).
     */
    public function hasChildren(): bool
    {
        return $this->subSubProcesses()->exists();
    }
}
```

---

### 5.3 `ProcessDocument` model (polymorphic)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProcessDocument extends Model
{
    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'code',
        'type',
        'title_es',
        'title_en',
        'description',
        'file_url',
        'version',
        'parent_id',
        'is_current',
        'uploaded_by',
    ];

    protected $casts = [
        'is_current' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * The owning model: Process, SubProcess, or SubSubProcess.
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProcessDocument::class, 'parent_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ProcessDocument::class, 'parent_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}
```

**Morph map registration** (add to `AppServiceProvider::boot`):

```php
use Illuminate\Database\Eloquent\Relations\Relation;

Relation::morphMap([
    'process'         => \App\Models\Process::class,
    'sub_process'     => \App\Models\SubProcess::class,
    'sub_sub_process' => \App\Models\SubSubProcess::class,
]);
```

> Using a morph map stores short strings (`process`, `sub_process`, `sub_sub_process`)
> instead of full class names in `documentable_type`. This keeps data portable if namespaces change.

---

### 5.4 `AssessmentContact` model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentContact extends Model
{
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
        'converted_company_id',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'stage_1_data'    => 'array',
        'stage_2_data'    => 'array',
        'stage_3_data'    => 'array',
        'stage_4_data'    => 'array',
        'data'            => 'array',
        'score_breakdown' => 'array',
        'reviewed_at'     => 'datetime',
        'score'           => 'decimal:2',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function convertedCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'converted_company_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function isAssessment1(): bool
    {
        return $this->type === 'sb_assessment_1';
    }

    public function isConverted(): bool
    {
        return $this->status === 'converted' && $this->converted_company_id !== null;
    }

    public function advanceToStage(string $stage): void
    {
        $this->update(['current_stage' => $stage, 'status' => 'in_progress']);
    }
}
```

---

### 5.5 `RepositoryDocument` model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RepositoryDocument extends Model
{
    protected $fillable = [
        'repository_id',
        'section',
        'category',
        'process_code',
        'record_date',
        'record_period',
        'title',
        'description',
        'file_path',
        'file_type',
        'file_size',
        'uploaded_by',
        'uploaded_by_type',
        'version',
        'parent_id',
        'is_current',
    ];

    protected $casts = [
        'record_date' => 'date',
        'is_current'  => 'boolean',
        'file_size'   => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function repository(): BelongsTo
    {
        return $this->belongsTo(Repository::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(RepositoryDocument::class, 'parent_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(RepositoryDocument::class, 'parent_id');
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeSetup($query)
    {
        return $query->where('section', 'setup');
    }

    public function scopeProcess($query)
    {
        return $query->where('section', 'process');
    }

    public function scopeRecord($query)
    {
        return $query->where('section', 'record');
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    public function scopeForProcess($query, string $processCode)
    {
        return $query->where('process_code', $processCode);
    }
}
```

---

### 5.6 `Company` model (QBO additions)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Facades\Crypt;

class Company extends Model
{
    protected $fillable = [
        'name',
        'industry',
        'state',
        'country',
        'sm_franchise_id',
        'employees_count',
        'annual_revenue',
        'years_operating',
        'logo_path',
        'address',
        'phone',
        'website',
        'qbo_realm_id',
        'qbo_access_token',
        'qbo_refresh_token',
        'qbo_token_expires_at',
    ];

    protected $casts = [
        'annual_revenue'       => 'decimal:2',
        'qbo_token_expires_at' => 'datetime',
    ];

    // Columns that hold encrypted values — never expose raw in JSON.
    protected $hidden = [
        'qbo_access_token',
        'qbo_refresh_token',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function franchise(): BelongsTo
    {
        return $this->belongsTo(Franchise::class, 'sm_franchise_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function bbAssignment(): HasOne
    {
        return $this->hasOne(BbAssignment::class);
    }

    public function bb(): HasOneThrough
    {
        return $this->hasOneThrough(
            User::class,
            BbAssignment::class,
            'company_id',
            'id',
            'id',
            'bb_user_id'
        );
    }

    public function processMaps(): HasMany
    {
        return $this->hasMany(ProcessMap::class);
    }

    public function franquiciadoraMap(): HasOne
    {
        return $this->hasOne(ProcessMap::class)->where('type', 'franquiciadora');
    }

    public function franquiciadaMap(): HasOne
    {
        return $this->hasOne(ProcessMap::class)->where('type', 'franquiciada');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function financialDocuments(): HasMany
    {
        return $this->hasMany(FinancialDocument::class);
    }

    public function repositories(): HasMany
    {
        return $this->hasMany(Repository::class);
    }

    public function clientTrackings(): HasMany
    {
        return $this->hasMany(ClientTracking::class);
    }

    // -------------------------------------------------------------------------
    // QBO helpers
    // -------------------------------------------------------------------------

    public function isQboConnected(): bool
    {
        return $this->qbo_realm_id !== null;
    }

    public function getQboAccessToken(): ?string
    {
        return $this->qbo_access_token
            ? Crypt::decryptString($this->qbo_access_token)
            : null;
    }

    public function getQboRefreshToken(): ?string
    {
        return $this->qbo_refresh_token
            ? Crypt::decryptString($this->qbo_refresh_token)
            : null;
    }

    public function setQboAccessTokenAttribute(?string $value): void
    {
        $this->attributes['qbo_access_token'] = $value
            ? Crypt::encryptString($value)
            : null;
    }

    public function setQboRefreshTokenAttribute(?string $value): void
    {
        $this->attributes['qbo_refresh_token'] = $value
            ? Crypt::encryptString($value)
            : null;
    }

    public function qboTokenIsExpired(): bool
    {
        return $this->qbo_token_expires_at !== null
            && $this->qbo_token_expires_at->isPast();
    }
}
```

---

### 5.7 `ClientTracking` model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientTracking extends Model
{
    protected $fillable = [
        'company_id',
        'catalog_item_id',
        'status',
        'estimated_start',
        'estimated_end',
        'actual_start',
        'actual_end',
        'progress_percent',
        'month_number',
        'notes',
    ];

    protected $casts = [
        'estimated_start'  => 'date',
        'estimated_end'    => 'date',
        'actual_start'     => 'date',
        'actual_end'       => 'date',
        'progress_percent' => 'integer',
    ];

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    // -------------------------------------------------------------------------
    // Scopes
    // -------------------------------------------------------------------------

    public function scopeForGantt($query)
    {
        return $query
            ->whereNotNull('estimated_start')
            ->whereNotNull('estimated_end')
            ->orderBy('estimated_start');
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
```

---

## 6. Process Tree ASCII Diagram

```
process_maps
  id, company_id, type (franquiciadora | franquiciada)
  UNIQUE (company_id, type)
  │
  └── process_categories
        id, process_map_id, type (strategic | value_chain | support)
        order_index
        │
        └── processes
              id, category_id, code (e.g. GTH), name_es, name_en
              order_index
              │
              └── sub_processes
                    id, process_id, code (e.g. GTH-P01)
                    name_es, name_en
                    bpmn_xml_es, bpmn_xml_en
                    walkthrough_es, walkthrough_en (JSONB)
                    manual_document_id → process_documents
                    order_index
                    │
                    ├── [OPTION A — leaf sub-process]
                    │     No sub_sub_processes children.
                    │     Documents attached via process_documents
                    │     WHERE documentable_type = 'sub_process'
                    │       AND documentable_id = sub_processes.id
                    │
                    └── [OPTION B — has children]
                          │
                          └── sub_sub_processes                      ← NEW v2
                                id, sub_process_id
                                code (e.g. GTH-P01-S01)
                                name_es, name_en
                                bpmn_xml_es, bpmn_xml_en
                                walkthrough_es, walkthrough_en (JSONB)
                                manual_document_id → process_documents
                                order_index
                                │
                                └── process_documents (polymorphic)
                                      documentable_type = 'sub_sub_process'
                                      documentable_id = sub_sub_processes.id
                                      type ∈ {MP, FOR, MN, IN, AN, PO, PR, CR}
                                      code (e.g. GTH-P01-S01-FOR-01)
                                      version, parent_id, is_current

process_documents also attaches to processes and sub_processes:
  WHERE documentable_type = 'process'     AND documentable_id = processes.id
  WHERE documentable_type = 'sub_process' AND documentable_id = sub_processes.id
```

### Migration execution order for the process tree

```
1. create_process_maps_table
2. create_process_categories_table
3. create_processes_table
4. create_sub_processes_table           (manual_document_id left as unsignedBigInteger, no FK yet)
5. create_sub_sub_processes_table       (manual_document_id left as unsignedBigInteger, no FK yet)
6. create_process_documents_table       (polymorphic — no direct FK to sub_processes/sub_sub_processes)
7. add_manual_document_fk_to_sub_processes_table      (FK now that process_documents exists)
8. add_manual_document_fk_to_sub_sub_processes_table  (FK now that process_documents exists)
```

> The circular reference (`sub_processes.manual_document_id → process_documents`,
> `process_documents.documentable_id → sub_processes`) is broken by making
> `manual_document_id` a deferred FK added in a separate migration after both tables exist.
> This is the same pattern used in step 3.2 above.

---

*Schema v2 — SM Portal. Generated by the Database Specialist Agent.*
