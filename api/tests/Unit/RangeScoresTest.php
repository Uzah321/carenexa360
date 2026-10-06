<?php

namespace Tests\Unit;

use App\Modules\Observations\Support\RangeScores;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RangeScoresTest extends TestCase
{
    public static function diastolicBands(): array
    {
        return [
            '40' => [40, 3, 'low'],
            '41' => [41, 2, 'low'],
            '49' => [49, 2, 'low'],
            '50' => [50, 1, 'low'],
            '59' => [59, 1, 'low'],
            '59.5' => [59.5, 1, 'low'],
            '60' => [60, 0, 'normal'],
            '120' => [120, 0, 'normal'],
            '121' => [121, 3, 'high'],
        ];
    }

    #[DataProvider('diastolicBands')]
    public function test_diastolic_bands(float $value, int $score, string $direction): void
    {
        $this->assertSame(['score' => $score, 'direction' => $direction], RangeScores::scoreDiastolic($value));
    }

    public static function glucoseBands(): array
    {
        return [
            '53' => [53, 3, 'low'],
            '54' => [54, 2, 'low'],
            '69' => [69, 2, 'low'],
            '69.5' => [69.5, 2, 'low'],
            '70' => [70, 0, 'normal'],
            '250' => [250, 0, 'normal'],
            '251' => [251, 1, 'high'],
            '300' => [300, 1, 'high'],
            '301' => [301, 2, 'high'],
            '400' => [400, 2, 'high'],
            '401' => [401, 3, 'high'],
        ];
    }

    #[DataProvider('glucoseBands')]
    public function test_blood_glucose_bands(float $value, int $score, string $direction): void
    {
        $this->assertSame(['score' => $score, 'direction' => $direction], RangeScores::scoreBloodGlucose($value));
    }

    public function test_only_diastolic_and_glucose_are_range_scored(): void
    {
        $this->assertSame(['diastolic'], array_keys(RangeScores::parameterScores('blood_pressure', ['systolic' => 120, 'diastolic' => 80])));
        $this->assertSame([], RangeScores::parameterScores('pulse', ['value' => 70]));
    }
}
