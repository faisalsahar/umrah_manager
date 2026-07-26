<?php

namespace App\Services\PilgrimImport;

use App\Enums\Gender;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Enums\PilgrimImportField;
use App\Models\Organization;
use App\Models\Pilgrim;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Turns a parsed Excel file plus a column mapping into pilgrim records —
 * matching or creating the agent/sub-agent along the way. See docs/PLAN.md
 * for the sub-agent hierarchy this reuses (App\Models\Organization::parent()).
 */
class PilgrimImportService
{
    /**
     * Apply the mapping to every row and validate it, without writing
     * anything — the "review" step shows this before the user commits.
     *
     * @param  list<array<string, string>>  $rows
     * @param  array<string, string>  $mapping  Excel header => PilgrimImportField::value
     * @return list<array{data: array<string, mixed>, errors: list<string>}>
     */
    public function preview(array $rows, array $mapping): array
    {
        $previewed = [];

        foreach ($rows as $row) {
            $data = $this->mapRow($row, $mapping);
            $errors = $this->validateRow($data);
            $previewed[] = ['data' => $data, 'errors' => $errors];
        }

        return $previewed;
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<string, string>  $mapping
     * @return array<string, mixed>
     */
    public function mapRow(array $row, array $mapping): array
    {
        $data = [];

        foreach ($mapping as $header => $fieldValue) {
            if ($fieldValue === PilgrimImportField::Ignore->value || ! isset($row[$header])) {
                continue;
            }

            $data[$fieldValue] = $row[$header];
        }

        // Age is a convenience: converted to an approximate date of birth so
        // AgeBand/age-based reporting works, since the schema stores a real date.
        if (filled($data[PilgrimImportField::Age->value] ?? null) && blank($data[PilgrimImportField::DateOfBirth->value] ?? null)) {
            $age = (int) $data[PilgrimImportField::Age->value];

            if ($age >= 0 && $age < 130) {
                $data[PilgrimImportField::DateOfBirth->value] = now()->subYears($age)->format('Y-m-d');
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public function validateRow(array $data): array
    {
        $errors = [];

        if (blank($data[PilgrimImportField::Name->value] ?? null)) {
            $errors[] = __('Name is required.');
        }

        if (blank($data[PilgrimImportField::PassportNumber->value] ?? null)) {
            $errors[] = __('Passport number is required.');
        }

        $gender = $data[PilgrimImportField::Gender->value] ?? null;

        if (filled($gender) && self::normalizeGender((string) $gender) === null) {
            $errors[] = __('Gender ":value" was not recognized — pick one manually.', ['value' => $gender]);
        }

        return $errors;
    }

    /**
     * Commit already-reviewed rows. Each row's `data` may have been edited by
     * the user during review (e.g. a manually-picked gender) — this trusts
     * exactly what it's given rather than re-deriving anything, since the
     * review step is where corrections happen.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return int pilgrims created
     */
    public function commit(Organization $operator, array $rows, ?int $groupId = null): int
    {
        return DB::transaction(function () use ($operator, $rows, $groupId): int {
            $created = 0;

            foreach ($rows as $data) {
                $agentId = $this->resolveAgent($operator, $data);

                Pilgrim::create([
                    'operator_id' => $operator->id,
                    'agent_id' => $agentId,
                    'group_id' => $groupId,
                    'name' => $data[PilgrimImportField::Name->value],
                    'passport_number' => $data[PilgrimImportField::PassportNumber->value],
                    'nationality' => self::nullIfBlank($data[PilgrimImportField::Nationality->value] ?? null),
                    'gender' => self::normalizeGender((string) ($data[PilgrimImportField::Gender->value] ?? '')),
                    'date_of_birth' => self::nullIfBlank($data[PilgrimImportField::DateOfBirth->value] ?? null),
                    'phone' => self::nullIfBlank($data[PilgrimImportField::Phone->value] ?? null),
                    'visa_status' => self::nullIfBlank($data[PilgrimImportField::VisaStatus->value] ?? null),
                    'visa_number' => self::nullIfBlank($data[PilgrimImportField::VisaNumber->value] ?? null),
                    'biometric_status' => self::nullIfBlank($data[PilgrimImportField::BiometricStatus->value] ?? null),
                    'mofa_number' => self::nullIfBlank($data[PilgrimImportField::MofaNumber->value] ?? null),
                    'booking_type' => self::nullIfBlank($data[PilgrimImportField::BookingType->value] ?? null),
                ]);

                $created++;
            }

            return $created;
        });
    }

    /**
     * Accepts the common spellings a real export uses ("Male", "M", "female", …)
     * rather than requiring an exact match to the enum's own value.
     */
    public static function normalizeGender(string $value): ?Gender
    {
        return match (mb_strtolower(trim($value))) {
            'male', 'm' => Gender::Male,
            'female', 'f' => Gender::Female,
            default => null,
        };
    }

    /**
     * A source file's blank cell arrives as an empty (or whitespace-only)
     * string rather than a missing key — coerced to a real null so every
     * optional field is left genuinely unset instead of stored as "".
     */
    private static function nullIfBlank(mixed $value): mixed
    {
        return blank($value) ? null : $value;
    }

    /**
     * Find-or-create the agent (and, if present, the sub-agent beneath it)
     * this row's pilgrim belongs to, matching by external_code scoped to
     * agents this operator has already dealt with — see the migration that
     * added `external_code` for why that scoping matters.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveAgent(Organization $operator, array $data): ?int
    {
        $agentCode = $data[PilgrimImportField::AgentCode->value] ?? null;
        $agentName = $data[PilgrimImportField::AgentName->value] ?? null;

        if (blank($agentCode) && blank($agentName)) {
            return null;
        }

        $agent = $this->findOrCreateOrganization($operator, $agentCode, $agentName, null);

        $subAgentCode = $data[PilgrimImportField::SubAgentCode->value] ?? null;
        $subAgentName = $data[PilgrimImportField::SubAgentName->value] ?? null;

        if (blank($subAgentCode) && blank($subAgentName)) {
            return $agent->id;
        }

        $subAgent = $this->findOrCreateOrganization($operator, $subAgentCode, $subAgentName, $agent);

        return $subAgent->id;
    }

    private function findOrCreateOrganization(Organization $operator, ?string $code, ?string $name, ?Organization $parent): Organization
    {
        $existing = null;

        if (filled($code)) {
            $existing = Organization::ofType(OrganizationType::Agent)
                ->where('external_code', $code)
                ->whereHas('pilgrims', fn ($query) => $query->where('operator_id', $operator->id))
                ->first();
        }

        if ($existing) {
            return $existing;
        }

        $label = filled($name) ? $name : __('Agent :code', ['code' => $code]);

        $organization = Organization::create([
            'type' => OrganizationType::Agent,
            'name_ar' => $label,
            'name_en' => $label,
            'external_code' => $code,
            'parent_organization_id' => $parent?->id,
        ]);

        // The importing operator's own staff can manage this new agent's
        // membership immediately — mirrors "start a new organization" giving
        // the creator ownership, since otherwise nobody could ever add its
        // first real member by onboarding code.
        if (Auth::user() instanceof User) {
            $organization->addMember(Auth::user(), OrganizationRole::Owner);
        }

        return $organization;
    }
}
