# Plan — Metode Pengadaan + Tahapan (CRUD master data)

## Goal

Add `procurement_methods` (metode pengadaan) and its ordered `procurement_method_stages` (tahapan) as master data with full CRUD + reorder + schedule-preview REST endpoints, following the repo's existing controller/model/validation conventions.

---

## Current context / assumptions

Facts gathered from the repo (`/Volumes/Data/Yuko/project/Eproc/eproc-api`, branch `feat/metode-pengadaan`, clean):

- **Stack**: Laravel **12.70**, PHP **8.4.21**, database **Oracle** via `yajra/laravel-oci8` (`DB_CONNECTION=oracle`, host `127.0.0.1:1521`, service `FREEPDB1`, user `EPROC`). JWT auth (`tymon/jwt-auth`, guard `api`), PHPUnit 11 (`vendor/bin/phpunit`, `php artisan test`).
- **House conventions you must copy, not improve**:
  - Migrations: anonymous class, `Schema::create`, `$table->id()`, `boolean('is_active')->default(true)`, `timestamps()`, `softDeletes()`. See `database/migrations/2026_10_20_000002_create_business_types_table.php`.
  - Master-data CRUD = one model + one controller. Reference implementations: `app/Models/FundingSource.php`, `app/Http/Controllers/FundingSourceController.php`, nested-parent example `app/Http/Controllers/ClassificationController.php`.
  - Models use `App\Traits\ModelTrait` + `$filterable` + `scopeGetX` + `scopeFilter` + `scopeOrder` (list filters `?search=`, `?filter[field]=`, `?order_by=`, `?order_direction=`, `?page=`, `?page_size=`, `?all=true`).
  - Validation lives centrally in `app/Traits/RuleTrait.php`, keyed by **controller basename + calling method name**; controllers call `$this->check_input_validation()` as the *first statement* of the action (`debug_backtrace()[1]['function']` — do not wrap it in a helper).
  - Responses come from `App\Http\Controllers\Controller`: `baseResponse()` returns **HTTP 201 even for GET** (keep it, frontend depends on it), `invalidResponse()` → 403, `errorValidateResponse()` → 400, `errorResponses()` → 400.
  - Updates use **POST** `/{id}`, not PUT/PATCH. Delete uses DELETE `/{id}`.
  - Oracle returns `boolean` columns as `char(1)` (`'1'`/`'0'`) — cast with `(bool)` in payloads or Eloquent `$casts`.
  - Permission middleware `app/Http/Middleware/Permission.php` currently **returns `$next($request)` immediately** (permissions are not enforced yet). Still add `permission:*` middleware on routes for consistency.
- **Database is currently unreachable**: `php artisan migrate:status` → `ORA-12541: Cannot connect. No listener at host 127.0.0.1 port 1521`. Oracle is lazy-connected (only on first query), so `tests/Unit` runs fine today (`php artisan test --testsuite=Unit` → `Tests: 1 passed`). **Feature tests and migrations are blocked until Oracle is up** — see Task 0.
- New migrations must use the repo's date-prefix sequence style: latest existing is `2026_10_21_000010_*`, so start at `2026_10_22_000011_*`.
- No factories for existing master data; feature tests create rows through the API.
- `APP_TIMEZONE` is unset → `UTC`. All dates in this feature are **date-only strings** (`Y-m-d`), never timestamps, so timezone never shifts a date.

### Domain rules (from the request, made concrete)

| Requirement | Decision |
|---|---|
| `Urutan tahapan nomor 1 berarti tahap awal` | `sequence` unsigned int, **1-based, contiguous, unique per method**. Ordering is owned by one reorder endpoint, never by hand-edited `sequence` values. |
| `Lama hari tahapan itu dibuka` | `duration_days` int, `min:1`. |
| `jenis hari: kalendar` | `day_type = 'calendar'` → next stage starts `start + duration_days` calendar days (5 → literally +5 days, even if it lands on a weekend). |
| `jenis hari: hari kerja` | `day_type = 'working_day'` → next stage starts `start + duration_days` **Mon–Fri** days (Carbon `addWeekdays`, skips Sat/Sun). |
| `opsi file checklist boolean` | `requires_file` boolean, default `false`. Upload/storage itself is **out of scope** (open question 3). |
| `tiap tahapan punya halaman dan proses` | `page_key` (frontend route/page identifier) and `process_key` (backend process identifier), both required strings, free-form. |

Effective dates are derived, never stored:

```
start_date(stage 1) = start_date input
next_start(stage)   = start + duration_days        (calendar)
                    = start + duration_days Mon-Fri (working_day)
end_date(stage)     = next_start - 1 day           (last day the stage is open)
start_date(stage N) = next_start(stage N-1)
```

---

## Architecture / proposed approach

One master table `procurement_methods` and one child table `procurement_method_stages` (1:N, `cascadeOnDelete` FK, unique `(procurement_method_id, sequence)`); a thin `App\Services\StageSchedule` pure function turns an ordered stage list + a start date into per-stage `start_date`/`end_date`, so no dates are persisted and the frontend gets one authoritative preview. Two controllers (`ProcurementMethodController`, `ProcurementMethodStageController`) mirror the existing FundingSource/Classification CRUD shape, with validation added to `RuleTrait` and routes in `routes/api.php`. Sequence compaction lives in a single static model method so create/delete/reorder cannot drift apart.

---

## API contract (frontend-facing — build against this, don't invent variants)

All routes require JWT `Authorization: Bearer <token>`. All return HTTP **201** on success (house style) with body `{ "message": string, "result": ..., "paginate"?: {...} }`.

### Metode pengadaan

| Method | Path | Body / Query | Result |
|---|---|---|---|
| GET | `/api/procurement-method` | `page`, `page_size`, `all`, `search`, `filter[name]`, `order_by`, `order_direction` | list of method objects + `paginate` |
| GET | `/api/procurement-method/{id}` | — | one method **with `stages[]` ordered by sequence** |
| POST | `/api/procurement-method` | `name` (req, unique), `description` | created method |
| POST | `/api/procurement-method/{id}` | `name`, `description`, `is_active` | updated method |
| DELETE | `/api/procurement-method/{id}` | — | soft-deletes method + its stages |

Method object:
```json
{
  "id": 1,
  "name": "Tender",
  "description": "Metode pemilihan penyedia",
  "is_active": true,
  "stages_count": 5,
  "created_at": "2026-10-22 14:00:00",
  "updated_at": "2026-10-22 14:00:00",
  "stages": [
    {
      "id": 11,
      "procurement_method_id": 1,
      "sequence": 1,
      "name": "Pengumuman",
      "duration_days": 5,
      "day_type": "calendar",
      "requires_file": false,
      "page_key": "procurement/stage/announcement",
      "process_key": "stage.announcement",
      "description": null,
      "is_active": true,
      "created_at": "2026-10-22 14:00:00",
      "updated_at": "2026-10-22 14:00:00"
    }
  ]
}
```

### Tahapan

| Method | Path | Body | Result |
|---|---|---|---|
| GET | `/api/procurement-method/{id}/stage` | — | `result`: array ordered by `sequence` |
| POST | `/api/procurement-method/{id}/stage` | `name`, `duration_days`, `day_type` (`calendar`\|`working_day`), `requires_file`, `page_key`, `process_key`, `description` | created stage, **always appended as `sequence = max+1`** (client-supplied sequence is ignored) |
| POST | `/api/procurement-method/{id}/stage/{stageId}` | same fields as create (`name` required), `is_active` | updated stage |
| DELETE | `/api/procurement-method/{id}/stage/{stageId}` | — | deletion + remaining sequences compacted to `1..N` |
| POST | `/api/procurement-method/{id}/stage/reorder` | `stage_ids: [3,1,2]` — **must be exactly all stage ids of that method** | reordered array |
| POST | `/api/procurement-method/{id}/preview-schedule` | `start_date: "2026-01-05"` (`Y-m-d`) | per-stage effective dates, active stages only |

Preview result:
```json
{
  "message": "Success preview procurement method schedule",
  "result": {
    "start_date": "2026-01-05",
    "stages": [
      { "stage_id": 11, "sequence": 1, "name": "Pengumuman", "day_type": "calendar",
        "duration_days": 5, "start_date": "2026-01-05", "end_date": "2026-01-09",
        "next_start_date": "2026-01-10" }
    ]
  }
}
```

---

# Tasks

Every task ends with a commit. Work top-to-bottom; each task is small enough to finish and commit on its own.

## Task 0 — Preflight: Oracle must be reachable

```bash
cd /Volumes/Data/Yuko/project/Eproc/eproc-api
php artisan migrate:status
echo "exit=$?"
```

**Expected**: a table of already-applied migrations (rows include `2026_10_21_000010_modify_users_table`) or `No migrations found`, followed by `exit=0`.

**If you see** `ORA-12541: Cannot connect. No listener at host 127.0.0.1 port 1521` → **stop**. Oracle is not running (it is currently not: verified on 2026-10-22). Ask the user to start the Oracle instance for `FREEPDB1` on port 1521, then re-run the command. Do not start containers on your own initiative and do not point `.env` at a different database — `.env` is the shared dev database.

Also confirm the seeded admin user (feature tests log in as it):

```bash
php artisan tinker --execute="echo App\Models\User::where('email','admin@gmail.com')->exists() ? 'admin-ok' : 'admin-missing';"
```
**Expected**: `admin-ok`. If `admin-missing`: `php artisan db:seed --class=UserSeeder`.

Unit tests (Task 4) can be done even while Oracle is down; migrations and feature tests cannot.

---

## Task 1 — Migration: `procurement_methods`

**File**: `database/migrations/2026_10_22_000011_create_procurement_methods_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_methods');
    }
};
```

Verify:

```bash
php artisan migrate --path=database/migrations/2026_10_22_000011_create_procurement_methods_table.php
```
**Expected**: `INFO  Running migrations.` then a line ending in `DONE` for `2026_10_22_000011_create_procurement_methods_table`, exit code 0.

Column check:

```bash
php artisan tinker --execute="echo implode(',', array_map(fn(\$c) => \$c->column_name, DB::select(\"select column_name from user_tab_columns where table_name = 'procurement_methods' order by column_id\")));"
```
**Expected**: `id,name,description,is_active,created_at,updated_at,deleted_at` (Oracle may report them upper-case in some clients; through this connection they come back lower-case — that is what `tests/Feature/OracleLowercaseTest.php` enforces).

Commit: `git add -A && git commit -m "feat(procurement): add procurement_methods migration"`

---

## Task 2 — Migration: `procurement_method_stages`

**File**: `database/migrations/2026_10_22_000012_create_procurement_method_stages_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_method_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_method_id')
                ->constrained('procurement_methods', 'id', 'pms_method_fk')
                ->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('name', 150);
            $table->unsignedSmallInteger('duration_days');
            $table->string('day_type', 20);
            $table->boolean('requires_file')->default(false);
            $table->string('page_key', 100);
            $table->string('process_key', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['procurement_method_id', 'sequence'], 'pms_method_seq_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_method_stages');
    }
};
```

Notes that matter:
- Index/FK names are given explicitly (`pms_method_fk`, `pms_method_seq_uq`) because Oracle identifier limits and because `down()` must drop cleanly with the table.
- `day_type` is `string(20)`, **not** `enum()`: yajra compiles `enum` to a bare `varchar2` anyway and adds no CHECK constraint, so an enum would be a lie. Allowed values are enforced in validation (`in:calendar,working_day`).
- `unsignedInteger`/`unsignedSmallInteger` compile to `number(10,0)`/`number(5,0)` on Oracle. There is no unsigned CHECK, so **negative sequence values are writable** — the reorder algorithm relies on this. Do not "fix" it.

Verify:

```bash
php artisan migrate --path=database/migrations/2026_10_22_000012_create_procurement_method_stages_table.php
```
**Expected**: `DONE` for `2026_10_22_000012_create_procurement_method_stages_table`, exit code 0.

```bash
php artisan tinker --execute="echo implode(',', array_map(fn(\$i) => \$i->index_name, DB::select(\"select index_name from user_indexes where table_name = 'procurement_method_stages'\")));"
```
**Expected**: output contains `pms_method_seq_uq`.

Commit: `git add -A && git commit -m "feat(procurement): add procurement_method_stages migration"`

---

## Task 3 — Models (payload builders + sequence engine)

**File**: `app/Models/ProcurementMethod.php`

```php
<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProcurementMethod extends Model
{
    use HasFactory, ModelTrait, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public $filterable = [
        'name',
        'description',
    ];

    public function stages()
    {
        return $this->hasMany(ProcurementMethodStage::class, 'procurement_method_id');
    }

    public function scopeGetProcurementMethod($query, $request, $page, $pageSize, $all)
    {
        $data = $query->withCount('stages')->filter()->order();
        if ($all == 'true') {
            return $data->orderBy('name')->get();
        }
        return $data->orderBy('created_at', 'desc')->paginate($pageSize, ['*'], 'page', $page);
    }

    public function scopeFilter($query)
    {
        return $this->filter_model($query, $this);
    }

    public function scopeOrder($query)
    {
        return $this->order_model($query, $this);
    }

    public function toApiArray(bool $withStages = false): array
    {
        $payload = [
            'id'           => $this->id,
            'name'         => $this->name,
            'description'  => $this->description,
            'is_active'    => (bool) $this->is_active,
            'stages_count' => $this->stages_count
                ?? ($this->relationLoaded('stages') ? $this->stages->count() : null),
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
        ];

        if ($withStages) {
            $payload['stages'] = $this->stages
                ->sortBy('sequence')
                ->values()
                ->map(fn (ProcurementMethodStage $stage) => $stage->toApiArray())
                ->all();
        }

        return $payload;
    }
}
```

**File**: `app/Models/ProcurementMethodStage.php`

```php
<?php

namespace App\Models;

use App\Traits\ModelTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class ProcurementMethodStage extends Model
{
    use HasFactory, ModelTrait, SoftDeletes;

    public const DAY_TYPE_CALENDAR = 'calendar';
    public const DAY_TYPE_WORKING_DAY = 'working_day';
    public const DAY_TYPES = [self::DAY_TYPE_CALENDAR, self::DAY_TYPE_WORKING_DAY];

    protected $guarded = [];

    protected $casts = [
        'sequence'      => 'integer',
        'duration_days' => 'integer',
        'requires_file' => 'boolean',
        'is_active'     => 'boolean',
    ];

    public $filterable = [
        'name',
    ];

    public function procurementMethod()
    {
        return $this->belongsTo(ProcurementMethod::class, 'procurement_method_id');
    }

    public function toApiArray(): array
    {
        return [
            'id'                    => $this->id,
            'procurement_method_id' => $this->procurement_method_id,
            'sequence'              => (int) $this->sequence,
            'name'                  => $this->name,
            'duration_days'         => (int) $this->duration_days,
            'day_type'              => $this->day_type,
            'requires_file'         => (bool) $this->requires_file,
            'page_key'              => $this->page_key,
            'process_key'           => $this->process_key,
            'description'           => $this->description,
            'is_active'             => (bool) $this->is_active,
            'created_at'            => $this->created_at,
            'updated_at'            => $this->updated_at,
        ];
    }

    public static function nextSequence(int $procurementMethodId): int
    {
        return (int) static::where('procurement_method_id', $procurementMethodId)->max('sequence') + 1;
    }

    /** @return int[] */
    public static function orderedIds(int $procurementMethodId): array
    {
        return static::where('procurement_method_id', $procurementMethodId)
            ->orderBy('sequence')
            ->pluck('id')
            ->all();
    }

    /**
     * Rewrites `sequence` to 1..N following $orderedIds.
     *
     * Two-phase on purpose: Oracle validates the pms_method_seq_uq index at the end
     * of every statement, so a direct swap (1->2 while 2 exists) raises ORA-00001.
     * Phase 1 negates every involved row in one statement (still unique), phase 2
     * assigns the final 1..N values into now-free slots.
     *
     * @param  int[]  $orderedIds
     */
    public static function reorderWithin(int $procurementMethodId, array $orderedIds): void
    {
        $orderedIds = array_values($orderedIds);
        if ($orderedIds === []) {
            return;
        }

        static::withTrashed()
            ->where('procurement_method_id', $procurementMethodId)
            ->whereIn('id', $orderedIds)
            ->update(['sequence' => DB::raw('-sequence')]);

        foreach ($orderedIds as $index => $id) {
            static::withTrashed()->where('id', $id)->update(['sequence' => $index + 1]);
        }
    }
}
```

Verify both files parse and are autoloadable **without touching the database**:

```bash
php -l app/Models/ProcurementMethod.php && php -l app/Models/ProcurementMethodStage.php
```
**Expected**:
```
No syntax errors detected in app/Models/ProcurementMethod.php
No syntax errors detected in app/Models/ProcurementMethodStage.php
```

`hasMany` in `ProcurementMethod::stages()` deliberately has **no** `orderBy`: it is also used by `withCount()`, and an `order by` inside an aggregate subquery is invalid on Oracle. Ordering is applied at the call sites.

Commit: `git add -A && git commit -m "feat(procurement): add procurement method models"`

---

## Task 4 — TDD: schedule calculator (`App\Services\StageSchedule`)

Pure date math, **no database**, so this task works even while Oracle is down.

### 4a. Write the failing test

**File**: `tests/Unit/StageScheduleTest.php`

```php
<?php

namespace Tests\Unit;

use App\Models\ProcurementMethodStage;
use App\Services\StageSchedule;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class StageScheduleTest extends TestCase
{
    private function stage(int $id, int $sequence, string $dayType, int $days): ProcurementMethodStage
    {
        return (new ProcurementMethodStage)->forceFill([
            'id'            => $id,
            'sequence'      => $sequence,
            'name'          => "stage-{$sequence}",
            'day_type'      => $dayType,
            'duration_days' => $days,
        ]);
    }

    public function test_calendar_days_add_literal_days(): void
    {
        // Monday 2026-01-05 + 5 calendar days = Saturday 2026-01-10
        $rows = StageSchedule::build(
            [$this->stage(1, 1, 'calendar', 5)],
            CarbonImmutable::parse('2026-01-05')
        );

        $this->assertSame('2026-01-05', $rows[0]['start_date']);
        $this->assertSame('2026-01-09', $rows[0]['end_date']);
        $this->assertSame('2026-01-10', $rows[0]['next_start_date']);
    }

    public function test_working_days_skip_weekends(): void
    {
        // Friday 2026-01-09 + 5 working days = Friday 2026-01-16
        $rows = StageSchedule::build(
            [$this->stage(1, 1, 'working_day', 5)],
            CarbonImmutable::parse('2026-01-09')
        );

        $this->assertSame('2026-01-09', $rows[0]['start_date']);
        $this->assertSame('2026-01-15', $rows[0]['end_date']);
        $this->assertSame('2026-01-16', $rows[0]['next_start_date']);
    }

    public function test_stages_chain_and_mix_day_types(): void
    {
        $stages = [
            $this->stage(3, 3, 'calendar', 2),
            $this->stage(1, 1, 'calendar', 5),
            $this->stage(2, 2, 'working_day', 3),
        ];

        $rows = StageSchedule::build($stages, CarbonImmutable::parse('2026-01-05'));

        $this->assertSame([1, 2, 3], array_column($rows, 'sequence'));

        $this->assertSame('2026-01-05', $rows[0]['start_date']);
        $this->assertSame('2026-01-09', $rows[0]['end_date']);
        $this->assertSame('2026-01-10', $rows[0]['next_start_date']);

        // starts on a Saturday (calendar overflow), then 3 working days -> Wednesday
        $this->assertSame('2026-01-10', $rows[1]['start_date']);
        $this->assertSame('2026-01-13', $rows[1]['end_date']);
        $this->assertSame('2026-01-14', $rows[1]['next_start_date']);

        $this->assertSame('2026-01-14', $rows[2]['start_date']);
        $this->assertSame('2026-01-15', $rows[2]['end_date']);
        $this->assertSame('2026-01-16', $rows[2]['next_start_date']);
    }

    public function test_empty_stage_list_returns_empty_array(): void
    {
        $this->assertSame([], StageSchedule::build([], CarbonImmutable::parse('2026-01-05')));
    }
}
```

Run it (must fail):

```bash
php artisan test tests/Unit/StageScheduleTest.php
```
**Expected**: failure, `Tests: 4 failed`, first error mentioning `Class "App\Services\StageSchedule" not found`.

### 4b. Implement

**File**: `app/Services/StageSchedule.php`

```php
<?php

namespace App\Services;

use App\Models\ProcurementMethodStage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class StageSchedule
{
    /**
     * Derives the effective open window of every stage, in sequence order.
     *
     * `end_date` is the last day the stage is open; `next_start_date` is when the
     * following stage opens. Nothing here is persisted.
     *
     * @param  iterable<ProcurementMethodStage>  $stages
     * @return array<int, array<string, mixed>>
     */
    public static function build(iterable $stages, CarbonImmutable $startDate): array
    {
        $cursor = $startDate->startOfDay();
        $rows = [];

        $ordered = Collection::make($stages)->sortBy('sequence')->values();

        foreach ($ordered as $stage) {
            $durationDays = (int) $stage->duration_days;

            $nextStart = $stage->day_type === ProcurementMethodStage::DAY_TYPE_WORKING_DAY
                ? $cursor->addWeekdays($durationDays)
                : $cursor->addDays($durationDays);

            $rows[] = [
                'stage_id'        => $stage->id,
                'sequence'        => (int) $stage->sequence,
                'name'            => $stage->name,
                'day_type'        => $stage->day_type,
                'duration_days'   => $durationDays,
                'start_date'      => $cursor->toDateString(),
                'end_date'        => $nextStart->subDay()->toDateString(),
                'next_start_date' => $nextStart->toDateString(),
            ];

            $cursor = $nextStart;
        }

        return $rows;
    }
}
```

Run again (must pass):

```bash
php artisan test tests/Unit/StageScheduleTest.php
```
**Expected**: `PASS  Tests\Unit\StageScheduleTest`, `Tests: 4 passed`.

Commit: `git add -A && git commit -m "feat(procurement): add stage schedule calculator"`

---

## Task 5 — Validation rules in `RuleTrait`

**File**: `app/Traits/RuleTrait.php` — add two `case` blocks inside the `switch ($controller)` in `rules_lists()`, immediately **before** `case 'PasswordActivateController':`.

```php
            case 'ProcurementMethodController':
                if ($method == 'index') {
                    return [
                        'page'      => 'integer|min:1|nullable',
                        'page_size' => 'integer|min:1|nullable',
                        'all'       => 'boolean|nullable',
                    ];
                } elseif ($method == 'store') {
                    return [
                        'name'        => 'required|unique:procurement_methods,name,NULL,id,deleted_at,NULL|string|max:100',
                        'description' => 'nullable|string|max:500',
                    ];
                } elseif ($method == 'update') {
                    return [
                        'name'        => ['required', 'max:100', new IsUniqueOtherRule('procurement_methods', 'name', $params['own_id'])],
                        'description' => 'nullable|string|max:500',
                        'is_active'   => 'nullable|boolean',
                    ];
                }
                break;

            case 'ProcurementMethodStageController':
                if ($method == 'store') {
                    return [
                        'name'          => 'required|string|max:150',
                        'duration_days' => 'required|integer|min:1|max:3650',
                        'day_type'      => 'required|in:calendar,working_day',
                        'requires_file' => 'nullable|boolean',
                        'page_key'      => 'required|string|max:100',
                        'process_key'   => 'required|string|max:100',
                        'description'   => 'nullable|string|max:500',
                    ];
                } elseif ($method == 'update') {
                    return [
                        'name'          => 'required|string|max:150',
                        'duration_days' => 'required|integer|min:1|max:3650',
                        'day_type'      => 'required|in:calendar,working_day',
                        'requires_file' => 'nullable|boolean',
                        'page_key'      => 'required|string|max:100',
                        'process_key'   => 'required|string|max:100',
                        'description'   => 'nullable|string|max:500',
                        'is_active'     => 'nullable|boolean',
                    ];
                } elseif ($method == 'reorder') {
                    return [
                        'stage_ids'   => 'required|array|min:1',
                        'stage_ids.*' => 'required|integer',
                    ];
                } elseif ($method == 'previewSchedule') {
                    return [
                        'start_date' => 'required|date_format:Y-m-d',
                    ];
                }
                break;
```

Verify: `php -l app/Traits/RuleTrait.php` → `No syntax errors detected`.

Commit: `git add -A && git commit -m "feat(procurement): add validation rules"`

---

## Task 6 — TDD: `ProcurementMethodController` + routes

### 6a. Test base class (needed once, reused by Tasks 6–9)

**File**: `tests/Feature/ApiTestCase.php`

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

abstract class ApiTestCase extends TestCase
{
    use DatabaseTransactions;

    protected function actingAsApiUser(): static
    {
        $user = User::where('email', 'admin@gmail.com')->firstOrFail();
        $token = auth('api')->login($user);

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }
}
```

`DatabaseTransactions` (not `RefreshDatabase`) is mandatory: the shared Oracle schema is the team's dev database, tests must roll back and never truncate it.

### 6b. Failing test

**File**: `tests/Feature/ProcurementMethodTest.php`

```php
<?php

namespace Tests\Feature;

class ProcurementMethodTest extends ApiTestCase
{
    public function test_can_create_list_show_update_and_delete_a_method(): void
    {
        $create = $this->actingAsApiUser()->postJson('/api/procurement-method', [
            'name'        => 'Tender Uji',
            'description' => 'Metode untuk pengujian',
        ]);

        $create->assertStatus(201)
            ->assertJsonPath('result.name', 'Tender Uji')
            ->assertJsonPath('result.is_active', true)
            ->assertJsonPath('result.stages_count', 0);

        $id = $create->json('result.id');

        $this->actingAsApiUser()
            ->getJson('/api/procurement-method?all=true')
            ->assertStatus(201)
            ->assertJsonPath('result.0.name', 'Tender Uji');

        $this->actingAsApiUser()
            ->getJson("/api/procurement-method/{$id}")
            ->assertStatus(201)
            ->assertJsonPath('result.id', $id)
            ->assertJsonPath('result.stages', []);

        $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$id}", [
                'name'        => 'Tender Uji Revisi',
                'description' => 'Metode untuk pengujian',
            ])
            ->assertStatus(201)
            ->assertJsonPath('result.name', 'Tender Uji Revisi');

        $this->actingAsApiUser()
            ->deleteJson("/api/procurement-method/{$id}")
            ->assertStatus(201);

        $this->actingAsApiUser()
            ->getJson("/api/procurement-method/{$id}")
            ->assertStatus(403);
    }

    public function test_name_is_required(): void
    {
        $this->actingAsApiUser()
            ->postJson('/api/procurement-method', ['description' => 'tanpa nama'])
            ->assertStatus(400);
    }
}
```

Run: `php artisan test tests/Feature/ProcurementMethodTest.php`
**Expected**: fails — `404` on `/api/procurement-method` (route not defined yet).

### 6c. Implement controller

**File**: `app/Http/Controllers/ProcurementMethodController.php`

```php
<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\ProcurementMethod;
use App\Traits\RuleTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcurementMethodController extends Controller
{
    use RuleTrait;

    public function index(Request $request)
    {
        if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

        $page = $request->has('page') ? $request->page : 1;
        $pageSize = $request->has('page_size') ? $request->page_size : 10;
        $all = $request->has('all') ? $request->all : false;

        $data = ProcurementMethod::getProcurementMethod($request, $page, $pageSize, $all);

        $result = $data->map(function ($item) {
            return $item->toApiArray();
        });

        return $this->baseResponse('Success get list procurement method', $result, !$all ? $this->pagination($data) : null);
    }

    public function store(Request $request)
    {
        try {
            if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

            DB::beginTransaction();

            $result = ProcurementMethod::create([
                'name'        => $request->name,
                'description' => $request->description,
                'is_active'   => true,
            ]);

            DB::commit();
            return $this->baseResponse('Success create procurement method', $result->toApiArray());
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function show($id)
    {
        $data = ProcurementMethod::with(['stages' => function ($query) {
            $query->orderBy('sequence');
        }])->find($id);

        if ($data) {
            return $this->baseResponse('Success', $data->toApiArray(true));
        }
        return $this->invalidResponse('No data');
    }

    public function update(Request $request, $id)
    {
        try {
            if ($input_error = $this->check_input_validation(['own_id' => $id])) return $this->errorResponses($input_error);

            $data = ProcurementMethod::find($id);
            if ($data) {
                DB::beginTransaction();

                $data->update([
                    'name'        => $request->name,
                    'description' => $request->description,
                    'is_active'   => $request->has('is_active') ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN) : $data->is_active,
                ]);

                DB::commit();
                return $this->baseResponse('Success update procurement method', $data->toApiArray());
            }
            return $this->invalidResponse('No data');
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $data = ProcurementMethod::find($id);
            if ($data) {
                DB::beginTransaction();

                $data->stages()->delete();
                $data->delete();

                DB::commit();
                return $this->baseResponse('Success delete procurement method', null);
            }
            return $this->invalidResponse('No data');
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }
}
```

### 6d. Routes

**File**: `routes/api.php` — add the import next to the other controller imports:

```php
use App\Http\Controllers\ProcurementMethodController;
use App\Http\Controllers\ProcurementMethodStageController;
```

and add this group inside the existing `Route::middleware(['auth:api', 'throttle:600,1'])->group(function () {` block, right after the `classification` group:

```php
    Route::prefix('procurement-method')->group(function () {
        Route::get('/', [ProcurementMethodController::class, 'index'])->middleware('permission:procurement_method_read');
        Route::get('/{id}', [ProcurementMethodController::class, 'show'])->middleware('permission:procurement_method_read');
        Route::post('/', [ProcurementMethodController::class, 'store'])->middleware('permission:procurement_method_create');
        Route::post('/{id}', [ProcurementMethodController::class, 'update'])->middleware('permission:procurement_method_update');
        Route::delete('/{id}', [ProcurementMethodController::class, 'destroy'])->middleware('permission:procurement_method_delete');

        Route::get('/{id}/stage', [ProcurementMethodStageController::class, 'index'])->middleware('permission:procurement_method_read');
        Route::post('/{id}/stage', [ProcurementMethodStageController::class, 'store'])->middleware('permission:procurement_method_create');
        // must stay above POST /{id}/stage/{stageId}, otherwise "reorder" is captured as a stage id
        Route::post('/{id}/stage/reorder', [ProcurementMethodStageController::class, 'reorder'])->middleware('permission:procurement_method_update');
        Route::post('/{id}/stage/{stageId}', [ProcurementMethodStageController::class, 'update'])->middleware('permission:procurement_method_update');
        Route::delete('/{id}/stage/{stageId}', [ProcurementMethodStageController::class, 'destroy'])->middleware('permission:procurement_method_delete');
        Route::post('/{id}/preview-schedule', [ProcurementMethodStageController::class, 'previewSchedule'])->middleware('permission:procurement_method_read');
    });
```

`ProcurementMethodStageController` does not exist yet, so the route file will fatal on boot. Create the placeholder in Task 7 **before** running route-sensitive commands, or accept that this task's verification is:

```bash
php artisan route:list --path=procurement
```
**Expected after Task 7** (not now): 10 rows starting with `api/procurement-method`.

To keep Task 6 self-verifiable, do the minimal placeholder now — **File**: `app/Http/Controllers/ProcurementMethodStageController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Traits\RuleTrait;
use Illuminate\Http\Request;

class ProcurementMethodStageController extends Controller
{
    use RuleTrait;
}
```

Then run the test:

```bash
php artisan test tests/Feature/ProcurementMethodTest.php
```
**Expected**: `PASS  Tests\Feature\ProcurementMethodTest`, `Tests: 2 passed`.

Commit: `git add -A && git commit -m "feat(procurement): add procurement method CRUD"`

---

## Task 7 — TDD: stage index + store

### 7a. Failing test

**File**: `tests/Feature/ProcurementMethodStageTest.php`

```php
<?php

namespace Tests\Feature;

class ProcurementMethodStageTest extends ApiTestCase
{
    private function createMethod(string $name = 'Tender Uji'): int
    {
        return (int) $this->actingAsApiUser()
            ->postJson('/api/procurement-method', ['name' => $name])
            ->json('result.id');
    }

    private function stagePayload(array $overrides = []): array
    {
        return array_merge([
            'name'          => 'Pengumuman',
            'duration_days' => 5,
            'day_type'      => 'calendar',
            'requires_file' => false,
            'page_key'      => 'procurement/stage/announcement',
            'process_key'   => 'stage.announcement',
            'description'   => null,
        ], $overrides);
    }

    public function test_stage_is_appended_with_incrementing_sequence(): void
    {
        $methodId = $this->createMethod();

        $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$methodId}/stage", $this->stagePayload())
            ->assertStatus(201)
            ->assertJsonPath('result.sequence', 1)
            ->assertJsonPath('result.requires_file', false);

        $second = $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$methodId}/stage", $this->stagePayload([
                'name'          => 'Evaluasi',
                'day_type'      => 'working_day',
                'duration_days' => 3,
                'requires_file' => true,
                'page_key'      => 'procurement/stage/evaluation',
                'process_key'   => 'stage.evaluation',
            ]));

        $second->assertStatus(201)
            ->assertJsonPath('result.sequence', 2)
            ->assertJsonPath('result.day_type', 'working_day')
            ->assertJsonPath('result.requires_file', true);

        $this->actingAsApiUser()
            ->getJson("/api/procurement-method/{$methodId}/stage")
            ->assertStatus(201)
            ->assertJsonPath('result.0.sequence', 1)
            ->assertJsonPath('result.1.sequence', 2);

        $this->actingAsApiUser()
            ->getJson("/api/procurement-method/{$methodId}")
            ->assertStatus(201)
            ->assertJsonPath('result.stages_count', 2)
            ->assertJsonPath('result.stages.1.name', 'Evaluasi');
    }

    public function test_day_type_must_be_calendar_or_working_day(): void
    {
        $methodId = $this->createMethod();

        $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$methodId}/stage", $this->stagePayload(['day_type' => 'bulan']))
            ->assertStatus(400);
    }

    public function test_duration_days_must_be_at_least_one(): void
    {
        $methodId = $this->createMethod();

        $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$methodId}/stage", $this->stagePayload(['duration_days' => 0]))
            ->assertStatus(400);
    }

    public function test_stage_on_unknown_method_returns_403(): void
    {
        $this->actingAsApiUser()
            ->postJson('/api/procurement-method/999999/stage', $this->stagePayload())
            ->assertStatus(403);
    }
}
```

Run: `php artisan test tests/Feature/ProcurementMethodStageTest.php` → **Expected**: fails (`500`/`404`, stage routes not implemented).

### 7b. Implement — replace the placeholder controller

**File**: `app/Http/Controllers/ProcurementMethodStageController.php`

```php
<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\ProcurementMethod;
use App\Models\ProcurementMethodStage;
use App\Services\StageSchedule;
use App\Traits\RuleTrait;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProcurementMethodStageController extends Controller
{
    use RuleTrait;

    public function index(Request $request, $id)
    {
        $method = ProcurementMethod::find($id);
        if (!$method) return $this->invalidResponse('No data');

        $result = ProcurementMethodStage::where('procurement_method_id', $method->id)
            ->orderBy('sequence')
            ->get()
            ->map(fn (ProcurementMethodStage $stage) => $stage->toApiArray());

        return $this->baseResponse('Success get list procurement method stage', $result);
    }

    public function store(Request $request, $id)
    {
        try {
            if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

            $method = ProcurementMethod::find($id);
            if (!$method) return $this->invalidResponse('No data');

            DB::beginTransaction();

            $result = ProcurementMethodStage::create([
                'procurement_method_id' => $method->id,
                'sequence'              => ProcurementMethodStage::nextSequence($method->id),
                'name'                  => $request->name,
                'duration_days'         => (int) $request->duration_days,
                'day_type'              => $request->day_type,
                'requires_file'         => filter_var($request->requires_file ?? false, FILTER_VALIDATE_BOOLEAN),
                'page_key'              => $request->page_key,
                'process_key'           => $request->process_key,
                'description'           => $request->description,
                'is_active'             => true,
            ]);

            DB::commit();
            return $this->baseResponse('Success create procurement method stage', $result->toApiArray());
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }
}
```

Run: `php artisan test tests/Feature/ProcurementMethodStageTest.php`
**Expected**: `PASS  Tests\Feature\ProcurementMethodStageTest`, `Tests: 4 passed`.

Commit: `git add -A && git commit -m "feat(procurement): add stage list and create"`

---

## Task 8 — TDD: stage update + delete with sequence compaction

### 8a. Failing test — append to `tests/Feature/ProcurementMethodStageTest.php`

```php
    public function test_stage_update_keeps_sequence(): void
    {
        $methodId = $this->createMethod();
        $stageId = (int) $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$methodId}/stage", $this->stagePayload())
            ->json('result.id');

        $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$methodId}/stage/{$stageId}", $this->stagePayload([
                'name'          => 'Pengumuman Revisi',
                'duration_days' => 7,
                'requires_file' => true,
                'is_active'     => false,
            ]))
            ->assertStatus(201)
            ->assertJsonPath('result.name', 'Pengumuman Revisi')
            ->assertJsonPath('result.duration_days', 7)
            ->assertJsonPath('result.requires_file', true)
            ->assertJsonPath('result.is_active', false)
            ->assertJsonPath('result.sequence', 1);
    }

    public function test_delete_compacts_sequence(): void
    {
        $methodId = $this->createMethod();

        $ids = [];
        foreach (['A', 'B', 'C'] as $name) {
            $ids[$name] = (int) $this->actingAsApiUser()
                ->postJson("/api/procurement-method/{$methodId}/stage", $this->stagePayload(['name' => $name]))
                ->json('result.id');
        }

        $this->actingAsApiUser()
            ->deleteJson("/api/procurement-method/{$methodId}/stage/{$ids['B']}")
            ->assertStatus(201);

        $remaining = $this->actingAsApiUser()
            ->getJson("/api/procurement-method/{$methodId}/stage")
            ->assertStatus(201);

        $this->assertSame(['A', 'C'], array_column($remaining->json('result'), 'name'));
        $this->assertSame([1, 2], array_column($remaining->json('result'), 'sequence'));
    }

    public function test_stage_of_another_method_is_not_reachable(): void
    {
        $first = $this->createMethod('Metode Satu');
        $second = $this->createMethod('Metode Dua');

        $stageId = (int) $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$first}/stage", $this->stagePayload())
            ->json('result.id');

        $this->actingAsApiUser()
            ->deleteJson("/api/procurement-method/{$second}/stage/{$stageId}")
            ->assertStatus(403);
    }
```

Run: `php artisan test tests/Feature/ProcurementMethodStageTest.php` → **Expected**: 3 new failures.

### 8b. Implement — add these two methods to `ProcurementMethodStageController`

```php
    public function update(Request $request, $id, $stageId)
    {
        try {
            if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

            $data = ProcurementMethodStage::where('procurement_method_id', $id)->find($stageId);
            if (!$data) return $this->invalidResponse('No data');

            DB::beginTransaction();

            // `sequence` is intentionally not updatable here: order changes go through reorder().
            $data->update([
                'name'          => $request->name,
                'duration_days' => (int) $request->duration_days,
                'day_type'      => $request->day_type,
                'requires_file' => filter_var($request->requires_file ?? false, FILTER_VALIDATE_BOOLEAN),
                'page_key'      => $request->page_key,
                'process_key'   => $request->process_key,
                'description'   => $request->description,
                'is_active'     => $request->has('is_active') ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN) : $data->is_active,
            ]);

            DB::commit();
            return $this->baseResponse('Success update procurement method stage', $data->fresh()->toApiArray());
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }

    public function destroy($id, $stageId)
    {
        try {
            $data = ProcurementMethodStage::where('procurement_method_id', $id)->find($stageId);
            if (!$data) return $this->invalidResponse('No data');

            DB::beginTransaction();

            $remaining = ProcurementMethodStage::where('procurement_method_id', $id)
                ->where('id', '<>', $data->id)
                ->orderBy('sequence')
                ->pluck('id')
                ->all();

            // Park the slot on a negative, globally unique value before the soft delete
            // so pms_method_seq_uq is never violated when the list is compacted.
            $data->update(['sequence' => -1 * $data->id]);
            $data->delete();

            ProcurementMethodStage::reorderWithin((int) $id, $remaining);

            DB::commit();
            return $this->baseResponse('Success delete procurement method stage', null);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }
```

Run: `php artisan test tests/Feature/ProcurementMethodStageTest.php`
**Expected**: `Tests: 7 passed`.

Commit: `git add -A && git commit -m "feat(procurement): add stage update and delete"`

---

## Task 9 — TDD: reorder

### 9a. Failing test — append to `tests/Feature/ProcurementMethodStageTest.php`

```php
    public function test_reorder_rewrites_sequence_contiguously(): void
    {
        $methodId = $this->createMethod();

        $ids = [];
        foreach (['A', 'B', 'C'] as $name) {
            $ids[$name] = (int) $this->actingAsApiUser()
                ->postJson("/api/procurement-method/{$methodId}/stage", $this->stagePayload(['name' => $name]))
                ->json('result.id');
        }

        $response = $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$methodId}/stage/reorder", [
                'stage_ids' => [$ids['C'], $ids['A'], $ids['B']],
            ])
            ->assertStatus(201);

        $this->assertSame(['C', 'A', 'B'], array_column($response->json('result'), 'name'));
        $this->assertSame([1, 2, 3], array_column($response->json('result'), 'sequence'));

        // and the direct "swap two neighbours" case, which a naive UPDATE would fail on ORA-00001
        $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$methodId}/stage/reorder", [
                'stage_ids' => [$ids['A'], $ids['C'], $ids['B']],
            ])
            ->assertStatus(201)
            ->assertJsonPath('result.0.name', 'A')
            ->assertJsonPath('result.1.name', 'C');
    }

    public function test_reorder_rejects_incomplete_id_list(): void
    {
        $methodId = $this->createMethod();

        $first = (int) $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$methodId}/stage", $this->stagePayload(['name' => 'A']))
            ->json('result.id');

        $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$methodId}/stage", $this->stagePayload(['name' => 'B']))
            ->assertStatus(201);

        $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$methodId}/stage/reorder", ['stage_ids' => [$first]])
            ->assertStatus(400);
    }
```

Run: `php artisan test --filter=reorder tests/Feature/ProcurementMethodStageTest.php` → **Expected**: failures.

### 9b. Implement — add to `ProcurementMethodStageController`

```php
    public function reorder(Request $request, $id)
    {
        try {
            if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

            $method = ProcurementMethod::find($id);
            if (!$method) return $this->invalidResponse('No data');

            $incoming = array_map('intval', $request->stage_ids);

            $current = ProcurementMethodStage::orderedIds((int) $method->id);
            $sortedCurrent = $current;
            $sortedIncoming = $incoming;
            sort($sortedCurrent);
            sort($sortedIncoming);

            if ($sortedCurrent !== $sortedIncoming) {
                return $this->errorValidateResponse('stage_ids must contain exactly all stage ids of this procurement method');
            }

            DB::beginTransaction();
            ProcurementMethodStage::reorderWithin((int) $method->id, $incoming);
            DB::commit();

            $result = ProcurementMethodStage::where('procurement_method_id', $method->id)
                ->orderBy('sequence')
                ->get()
                ->map(fn (ProcurementMethodStage $stage) => $stage->toApiArray());

            return $this->baseResponse('Success reorder procurement method stage', $result);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->invalidResponse($e->getMessage());
        }
    }
```

Run: `php artisan test tests/Feature/ProcurementMethodStageTest.php`
**Expected**: `Tests: 9 passed`.

Commit: `git add -A && git commit -m "feat(procurement): add stage reorder"`

---

## Task 10 — TDD: schedule preview endpoint

### 10a. Failing test — **File**: `tests/Feature/ProcurementMethodScheduleTest.php`

```php
<?php

namespace Tests\Feature;

class ProcurementMethodScheduleTest extends ApiTestCase
{
    public function test_preview_returns_effective_dates_for_each_stage(): void
    {
        $methodId = (int) $this->actingAsApiUser()
            ->postJson('/api/procurement-method', ['name' => 'Tender Jadwal'])
            ->json('result.id');

        $this->actingAsApiUser()->postJson("/api/procurement-method/{$methodId}/stage", [
            'name' => 'Pengumuman', 'duration_days' => 5, 'day_type' => 'calendar',
            'page_key' => 'procurement/stage/announcement', 'process_key' => 'stage.announcement',
        ])->assertStatus(201);

        $this->actingAsApiUser()->postJson("/api/procurement-method/{$methodId}/stage", [
            'name' => 'Evaluasi', 'duration_days' => 3, 'day_type' => 'working_day',
            'page_key' => 'procurement/stage/evaluation', 'process_key' => 'stage.evaluation',
        ])->assertStatus(201);

        $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$methodId}/preview-schedule", ['start_date' => '2026-01-05'])
            ->assertStatus(201)
            ->assertJsonPath('result.start_date', '2026-01-05')
            ->assertJsonPath('result.stages.0.start_date', '2026-01-05')
            ->assertJsonPath('result.stages.0.end_date', '2026-01-09')
            ->assertJsonPath('result.stages.0.next_start_date', '2026-01-10')
            ->assertJsonPath('result.stages.1.start_date', '2026-01-10')
            ->assertJsonPath('result.stages.1.end_date', '2026-01-13')
            ->assertJsonPath('result.stages.1.next_start_date', '2026-01-14');
    }

    public function test_preview_ignores_inactive_stages(): void
    {
        $methodId = (int) $this->actingAsApiUser()
            ->postJson('/api/procurement-method', ['name' => 'Tender Nonaktif'])
            ->json('result.id');

        // created active, then deactivated: store() always forces is_active = true
        $skippedId = (int) $this->actingAsApiUser()->postJson("/api/procurement-method/{$methodId}/stage", [
            'name' => 'Dilewati', 'duration_days' => 9, 'day_type' => 'calendar',
            'page_key' => 'procurement/stage/skipped', 'process_key' => 'stage.skipped',
        ])->json('result.id');

        $this->actingAsApiUser()->postJson("/api/procurement-method/{$methodId}/stage/{$skippedId}", [
            'name' => 'Dilewati', 'duration_days' => 9, 'day_type' => 'calendar',
            'page_key' => 'procurement/stage/skipped', 'process_key' => 'stage.skipped',
            'is_active' => false,
        ])->assertStatus(201);

        $this->actingAsApiUser()->postJson("/api/procurement-method/{$methodId}/stage", [
            'name' => 'Diproses', 'duration_days' => 2, 'day_type' => 'calendar',
            'page_key' => 'procurement/stage/processed', 'process_key' => 'stage.processed',
        ])->assertStatus(201);

        $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$methodId}/preview-schedule", ['start_date' => '2026-01-05'])
            ->assertStatus(201)
            ->assertJsonCount(1, 'result.stages')
            ->assertJsonPath('result.stages.0.name', 'Diproses')
            ->assertJsonPath('result.stages.0.start_date', '2026-01-05');
    }

    public function test_start_date_is_required(): void
    {
        $methodId = (int) $this->actingAsApiUser()
            ->postJson('/api/procurement-method', ['name' => 'Tender Tanpa Tanggal'])
            ->json('result.id');

        $this->actingAsApiUser()
            ->postJson("/api/procurement-method/{$methodId}/preview-schedule", [])
            ->assertStatus(400);
    }
}
```

Run: `php artisan test tests/Feature/ProcurementMethodScheduleTest.php` → **Expected**: failures (`404`).

### 10b. Implement — add to `ProcurementMethodStageController`

```php
    public function previewSchedule(Request $request, $id)
    {
        try {
            if ($input_error = $this->check_input_validation()) return $this->errorResponses($input_error);

            $method = ProcurementMethod::find($id);
            if (!$method) return $this->invalidResponse('No data');

            $stages = ProcurementMethodStage::where('procurement_method_id', $method->id)
                ->where('is_active', true)
                ->orderBy('sequence')
                ->get();

            return $this->baseResponse('Success preview procurement method schedule', [
                'start_date' => $request->start_date,
                'stages'     => StageSchedule::build($stages, CarbonImmutable::parse($request->start_date)),
            ]);
        } catch (Exception $e) {
            return $this->invalidResponse($e->getMessage());
        }
    }
```

Add the missing import at the top of the controller (Task 7's version did not include it):

```php
use App\Services\StageSchedule;
use Carbon\CarbonImmutable;
```

Run the whole feature set:

```bash
php artisan test tests/Feature/ProcurementMethodTest.php tests/Feature/ProcurementMethodStageTest.php tests/Feature/ProcurementMethodScheduleTest.php
```
**Expected**: `Tests: 14 passed`.

Commit: `git add -A && git commit -m "feat(procurement): add schedule preview"`

---

## Task 11 — Permissions seed + `SCHEMA.md`

**File**: `database/seeders/PermissionSeeder.php` — append four entries to the `$permissions` array (before its closing `];`):

```php
            [
                "name" => "Manage Procurement Method",
                "subject" => "procurement_method",
                "action" => "read",
                "permission" => "procurement_method_read"
            ],
            [
                "name" => "Manage Procurement Method",
                "subject" => "procurement_method",
                "action" => "create",
                "permission" => "procurement_method_create"
            ],
            [
                "name" => "Manage Procurement Method",
                "subject" => "procurement_method",
                "action" => "update",
                "permission" => "procurement_method_update"
            ],
            [
                "name" => "Manage Procurement Method",
                "subject" => "procurement_method",
                "action" => "delete",
                "permission" => "procurement_method_delete"
            ],
```

Seed:

```bash
php artisan db:seed --class=PermissionSeeder
```
**Expected**: `INFO  Seeding database.` and exit code 0.

Verify:

```bash
php artisan tinker --execute="echo App\Models\Permission::where('subject','procurement_method')->count();"
```
**Expected**: `4`.

**File**: `SCHEMA.md` — add two table sections after `### Classifications` and mention the relation under `## Relationships`:

```markdown
### Procurement Methods

| Field       | Type     | Description                     |
|-------------|----------|---------------------------------|
| id          | BigInt   | Primary key                     |
| name        | String   | Method name (unique, active)    |
| description | Text     | Method description              |
| is_active   | Boolean  | Active status                   |
| stages      | Relation | One-to-many with stages         |
| timestamps  | Timestamps| Created at and updated at      |
| softdeletes | SoftDeletes | Soft delete function         |

### Procurement Method Stages

| Field                 | Type     | Description                                              |
|-----------------------|----------|----------------------------------------------------------|
| id                    | BigInt   | Primary key                                              |
| procurement_method_id | Relation | Foreign key to procurement_methods (cascade delete)      |
| sequence              | Number   | Order of the stage, 1 = first step, unique per method    |
| name                  | String   | Stage name                                               |
| duration_days         | Number   | How long the stage stays open                            |
| day_type              | String   | `calendar` (literal days) or `working_day` (Mon–Fri)     |
| requires_file         | Boolean  | Stage requires a file upload                             |
| page_key              | String   | Frontend page identifier for this stage                  |
| process_key           | String   | Backend process identifier for this stage                |
| description           | Text     | Stage description                                        |
| is_active             | Boolean  | Active status                                            |
| timestamps            | Timestamps| Created at and updated at                               |
| softdeletes           | SoftDeletes | Soft delete function                                  |

Effective dates are never stored: `App\Services\StageSchedule::build()` derives
`start_date` / `end_date` / `next_start_date` from an ordered stage list plus a start date.
```

Commit: `git add -A && git commit -m "chore(procurement): seed permissions and document schema"`

---

# Tests / validation

## Per task

Each CRUD task above is RED → GREEN: write the test, run it, read the failure, implement, re-run to `PASS`, then commit. Do not batch tasks 7–10 into one edit — the reorder and delete paths are where unique-index violations surface, and you want the failure to be yours, not a leftover.

## Full suite

```bash
php artisan test --testsuite=Unit
```
**Expected**: `Tests: 5 passed` (ExampleTest + 4 from `StageScheduleTest`).

```bash
php artisan test tests/Feature/ProcurementMethodTest.php \
  tests/Feature/ProcurementMethodStageTest.php \
  tests/Feature/ProcurementMethodScheduleTest.php
```
**Expected**: `Tests: 14 passed`.

```bash
php artisan test
```
**Expected**: everything green, including the pre-existing `OracleLowercaseTest` (new tables/columns are lower-case by construction; if it fails, you created a column with an explicit upper-case name — fix the migration, not the test).

## Manual smoke (once, after Task 10)

```bash
php artisan route:list --path=procurement
```
**Expected**: 10 route rows, including `POST api/procurement-method/{id}/stage/reorder` listed *before* `POST api/procurement-method/{id}/stage/{stageId}`.

---

# Risks, tradeoffs, open questions

1. **Oracle is down right now** (`ORA-12541`). Everything from Task 1 on needs it; only Task 4's unit tests run without a database. This is the one blocker that can stall the whole plan.
2. **`baseResponse` returns HTTP 201 for GET/DELETE too.** It is the existing contract for every endpoint in this repo; do not "fix" it in this feature, or the frontend breaks. If it should change, it is a separate, repo-wide decision.
3. **Unique index `pms_method_seq_uq` + Oracle's per-statement constraint check** is why `reorderWithin()` negates before assigning, and why `destroy()` parks the deleted row at `-id`. A naive `update(['sequence' => $i + 1])` loop throws `ORA-00001` whenever two stages swap places. Both paths are covered by tests (`test_reorder_rewrites_sequence_contiguously` swaps neighbours).
4. **Sequence is not client-writable on create/update.** Clients send an ordered id list to `/stage/reorder`. Cost: one extra request from a drag-and-drop UI. Benefit: `sequence` can never go sparse or duplicate. If the frontend wants a single "save the whole builder" call later, add one sync endpoint that calls `reorderWithin()` after upserts — do not give `sequence` back to the client.
5. **Permission middleware is currently a no-op** (`Permission::handle()` returns `$next($request)` before its checks). The new routes carry `permission:*` middleware for future use, but nothing is enforced today. Do not treat the endpoints as secured.
6. **`DatabaseTransactions` on a shared dev Oracle schema**: tests roll back, so they leave no rows, but a failing test can hold locks until the process exits. Do not switch these tests to `RefreshDatabase` — that would wipe the team's seeded dev data.
7. **`requires_file` only flags the requirement.** There is no upload/storage endpoint, no per-procurement file table, and no validation of file type/size. Judge scope: uploading is a separate feature.
8. **No instance tables.** Nothing snapshots the effective dates per actual procurement. When procurement execution is built, it must copy `start_date`/`end_date` into an instance table (`procurement_x_stages`) so that editing a method later cannot rewrite history. Do not calculate dates on the fly for real procurements.
9. **Working-day math ignores Indonesian public holidays.** `addWeekdays` only skips Sat/Sun, exactly as specified. A holiday calendar (`procurement_holidays` + a custom calculator) is a follow-up; it changes `StageSchedule::build()` only.
10. **Calendar-mode overflow starts stages on weekends** (e.g. 5 calendar days from Monday 2026-01-05 opens the next stage on Saturday 2026-01-10). That follows "tambah langsung 5 hari". If the business wants the next stage to snap to the next working day, add a `shift_to_working_day` flag to the method — not a silent default.

### Open questions (ask the user; do not guess if they block you)

1. Is `end_date` inclusive? This plan treats it as the **last day the stage is open** (`next_start - 1 day`). If the frontend shows "deadline", that is what to display.
2. Should a stage be reorderable across methods? Assumed no — a stage belongs to exactly one method.
3. File checklist: one file or many per stage? A single boolean is what was asked; per-file metadata needs its own table.
4. Do `page_key`/`process_key` need to be a closed list (validated against `config/procurement.php`) so a typo can't silently point at a non-existent page? Currently free-form strings.
