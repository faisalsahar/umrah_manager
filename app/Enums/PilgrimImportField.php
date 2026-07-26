<?php

namespace App\Enums;

/**
 * Every field an uploaded pilgrim list can be mapped onto. Covers both the
 * core Pilgrim columns and the agent/sub-agent identifying columns a real
 * export carries (e.g. "Main External Agent Code") — those don't map onto a
 * pilgrim column directly, the importer uses them to find-or-create the
 * Organization the pilgrim belongs to. See PilgrimImportService.
 */
enum PilgrimImportField: string
{
    case Name = 'name';
    case PassportNumber = 'passport_number';
    case Nationality = 'nationality';
    case Gender = 'gender';
    case DateOfBirth = 'date_of_birth';
    case Age = 'age';
    case Phone = 'phone';
    case VisaStatus = 'visa_status';
    case VisaNumber = 'visa_number';
    case BiometricStatus = 'biometric_status';
    case MofaNumber = 'mofa_number';
    case BookingType = 'booking_type';
    case AgentCode = 'agent_code';
    case AgentName = 'agent_name';
    case SubAgentCode = 'sub_agent_code';
    case SubAgentName = 'sub_agent_name';
    case Ignore = 'ignore';

    public function label(): string
    {
        return match ($this) {
            self::Name => __('Name'),
            self::PassportNumber => __('Passport Number'),
            self::Nationality => __('Nationality'),
            self::Gender => __('Gender'),
            self::DateOfBirth => __('Date of Birth'),
            self::Age => __('Age'),
            self::Phone => __('Phone'),
            self::VisaStatus => __('Visa Status'),
            self::VisaNumber => __('Visa Number'),
            self::BiometricStatus => __('Biometric Status'),
            self::MofaNumber => __('MOFA Number'),
            self::BookingType => __('Booking Type'),
            self::AgentCode => __('Agent Code'),
            self::AgentName => __('Agent Name'),
            self::SubAgentCode => __('Sub-Agent Code'),
            self::SubAgentName => __('Sub-Agent Name'),
            self::Ignore => __('— Ignore this column —'),
        };
    }

    /**
     * Fields that must resolve to a non-blank value for every row before the
     * import can be committed. Gender is required by the schema but is
     * deliberately not listed here — it is common for a source export to omit
     * it entirely, so a missing value is treated as "needs manual review" per
     * row instead of rejecting the whole file up front.
     *
     * @return list<self>
     */
    public static function required(): array
    {
        return [self::Name, self::PassportNumber];
    }

    /**
     * A best-effort guess at which field a given Excel column header means,
     * used to pre-fill the mapping step so a recognizable export (even one
     * never seen before) needs little to no manual mapping.
     */
    public static function guessFromHeader(string $header): ?self
    {
        $normalized = mb_strtolower(trim($header));

        return match (true) {
            str_contains($normalized, 'sub') && str_contains($normalized, 'code') => self::SubAgentCode,
            str_contains($normalized, 'sub') && str_contains($normalized, 'name') => self::SubAgentName,
            str_contains($normalized, 'agent') && str_contains($normalized, 'code') => self::AgentCode,
            str_contains($normalized, 'agent') && str_contains($normalized, 'name') => self::AgentName,
            str_contains($normalized, 'passport') => self::PassportNumber,
            str_contains($normalized, 'nationality') => self::Nationality,
            str_contains($normalized, 'gender') || str_contains($normalized, 'sex') => self::Gender,
            str_contains($normalized, 'birth') => self::DateOfBirth,
            str_contains($normalized, 'age') => self::Age,
            str_contains($normalized, 'phone') || str_contains($normalized, 'mobile') => self::Phone,
            str_contains($normalized, 'biometric') => self::BiometricStatus,
            str_contains($normalized, 'visa') && str_contains($normalized, 'number') => self::VisaNumber,
            str_contains($normalized, 'visa') => self::VisaStatus,
            str_contains($normalized, 'mofa') => self::MofaNumber,
            str_contains($normalized, 'type') => self::BookingType,
            str_contains($normalized, 'name') => self::Name,
            default => null,
        };
    }
}
