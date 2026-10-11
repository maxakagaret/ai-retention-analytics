<?php

namespace App\Enums;

/** Classic RFM segment map (R and F scores 1..5; M is reported, not used for the class). */
enum Segment: string
{
    case Champions = 'champions';
    case Loyal = 'loyal';
    case PotentialLoyalist = 'potential_loyalist';
    case NewCustomers = 'new_customers';
    case Promising = 'promising';
    case NeedAttention = 'need_attention';
    case AtRisk = 'at_risk';
    case CantLoseThem = 'cant_lose_them';
    case Hibernating = 'hibernating';
    case Lost = 'lost';

    /** Rule order matters: the first matching rule wins. */
    public static function classify(int $r, int $f): self
    {
        return match (true) {
            $r <= 1 && $f >= 4 => self::CantLoseThem,
            $r <= 2 && $f >= 3 => self::AtRisk,
            $r <= 1 => self::Lost,
            $r === 2 => self::Hibernating,
            $r >= 4 && $f >= 4 => self::Champions,
            $f >= 4 => self::Loyal,
            $r >= 4 && $f === 1 => self::NewCustomers,
            $r >= 4 => self::PotentialLoyalist,
            $f <= 2 => self::Promising,
            default => self::NeedAttention,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Champions => 'Champions',
            self::Loyal => 'Loyal',
            self::PotentialLoyalist => 'Potential loyalists',
            self::NewCustomers => 'New customers',
            self::Promising => 'Promising',
            self::NeedAttention => 'Need attention',
            self::AtRisk => 'At risk',
            self::CantLoseThem => "Can't lose them",
            self::Hibernating => 'Hibernating',
            self::Lost => 'Lost',
        };
    }

    /** What retention work means for this segment — part of the LLM brief. */
    public function goal(): string
    {
        return match ($this) {
            self::Champions => 'Keep them delighted, turn them into referrers; avoid discounting.',
            self::Loyal => 'Increase visit value with upsell/cross-sell of complementary services.',
            self::PotentialLoyalist => 'Build a habit: secure the next booking before the usual interval ends.',
            self::NewCustomers => 'Convert a first visit into a second one within the typical interval.',
            self::Promising => 'Raise frequency with a reason to come back soon.',
            self::NeedAttention => 'Re-engage before they slip into at-risk.',
            self::AtRisk => 'Win back valuable customers who are overdue; personal outreach.',
            self::CantLoseThem => 'Highest-value lapsing customers: personal call, strong offer.',
            self::Hibernating => 'Low-cost reactivation; accept that some are gone.',
            self::Lost => 'Last-chance campaign or stop spending on them.',
        };
    }

    public function isRetentionPriority(): bool
    {
        return in_array($this, [self::AtRisk, self::CantLoseThem, self::NeedAttention, self::Hibernating], true);
    }
}
