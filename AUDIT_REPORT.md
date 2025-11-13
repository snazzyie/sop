# COMPREHENSIVE AUDIT REPORT - SOP Recorder

## CRITICAL ISSUES FOUND

### 1. TABLE NAME MISMATCHES

| Schema Has | Code Expects | Status |
|------------|-------------|---------|
| `sessions` | `recording_sessions` | ❌ MISMATCH |
| `steps` | `sop_steps` | ❌ MISMATCH |
| `shares` | `sop_shares` | ❌ MISMATCH |
| `exports` | `sop_exports` | ❌ MISMATCH |

### 2. COLUMN MISMATCHES IN `users` TABLE

| Schema Has | Code Needs | Missing |
|------------|-----------|---------|
| `user_id`, `email`, `password_hash`, `name` | ✓ | |
| `status` | ✓ | |
| - | `user_group` (for super admin level 10) | ❌ |
| - | `company_id` (for multi-tenancy) | ❌ |

### 3. COLUMN MISMATCHES IN `sops` TABLE

| Schema Has | Code Needs | Missing |
|------------|-----------|---------|
| `user_id` | `created_by` | ❌ DIFFERENT NAME |
| - | `company_id` | ❌ MISSING |
| - | `tags` | ❌ MISSING |
| `status` (draft/published/archived) | ✓ | |

### 4. COLUMN MISMATCHES IN `sessions` TABLE

| Issue | Details |
|-------|---------|
| Table name | Schema: `sessions`, Code: `recording_sessions` |
| Missing columns | `company_id`, `paused_at` |
| Column names | `start_time` vs `created_at` |

### 5. COLUMN MISMATCHES IN `steps` TABLE

| Schema Has | Code Needs |
|------------|-----------|
| `steps` | `sop_steps` (table name) |
| `step_number` | `step_order` |
| `action_type` | ✓ |
| `element_data` (JSON) | `element_selector`, `element_text` (separate) |
| `page_data` (JSON) | `url` (separate) |
| `screenshot_path` | `screenshot_url` |
| `ai_description` | `description` |

### 6. API KEYS TABLE MISMATCHES

| Schema Has | Code Expects |
|------------|-------------|
| `api_secret` (plain VARCHAR) | `api_secret_hash` (hashed) |
| `key_name` | `name` |
| `user_id` | May not need |
| - | `status` enum ('active','revoked') |

### 7. SHARES TABLE MISMATCHES

| Schema Has | Code Expects |
|------------|-------------|
| `visibility` enum | Not used |
| `views_count` | `view_count` |
| - | `created_by`, `allow_comments`, `max_views`, `last_viewed_at` |

### 8. MISSING TABLES

1. `session_actions` - For individual actions during recording
2. `settings` - For system settings (fn_core_settings.php)
3. `team_invitations` - For inviting members

### 9. MULTI-TENANCY ISSUES

Most tables missing `company_id` column needed for isolation:
- `sops` ❌
- `recording_sessions` ❌
- `api_keys` has `team_id` ✓
- `shares` ❌

### 10. FOREIGN KEY ISSUES

- `sops.user_id` should be `sops.created_by`
- Missing FK from `users.company_id` to `teams.team_id`
- `sops.company_id` FK needed

## RESOLUTION STRATEGY

Create a comprehensive migration SQL file that:
1. Renames tables to match code expectations
2. Adds all missing columns
3. Renames columns for consistency
4. Creates missing tables
5. Updates foreign keys
6. Preserves all existing data
