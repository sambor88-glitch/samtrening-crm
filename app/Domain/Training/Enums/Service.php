<?php

namespace App\Domain\Training\Enums;

/**
 * What the studio sells — docs/SPEC-EKRANY.md §Wbij sesję. Sessions keep the name they were
 * logged with, so renaming an item here never rewrites history.
 */
enum Service: string
{
    case Personal = 'Trening personalny 1:1';
    case Pregnancy = 'Zdrowa ciąża 1:1';
    case OnlineConsultation = 'E-trening — konsultacja';
    case OnlinePlan = 'E-trening — plan miesięczny';
    case PlanReview = 'Konsultacja i korekta planu';
    case FirstConsultation = 'Konsultacja wstępna';

    /**
     * Grosze, or null when the service goes at whatever rate this client agreed.
     */
    public function fixedPrice(): ?int
    {
        return match ($this) {
            self::Personal, self::Pregnancy => null,
            self::OnlineConsultation => 15000,
            self::OnlinePlan => 30000,
            self::PlanReview => 8000,
            self::FirstConsultation => 0,
        };
    }

    public function priceFor(int $clientRate): int
    {
        return $this->fixedPrice() ?? $clientRate;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $service) => [$service->value => $service->value])->all();
    }
}
