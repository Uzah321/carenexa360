<?php

namespace Tests\Unit;

use App\Modules\Observations\Support\News2;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class News2Test extends TestCase
{
    /**
     * Every band edge from the RCP NEWS2 chart (2017).
     */
    public static function parameterBands(): array
    {
        return [
            'RR 8' => ['respiration_rate', 8, 3, 'low'],
            'RR 9' => ['respiration_rate', 9, 1, 'low'],
            'RR 11' => ['respiration_rate', 11, 1, 'low'],
            'RR 12' => ['respiration_rate', 12, 0, 'normal'],
            'RR 20' => ['respiration_rate', 20, 0, 'normal'],
            'RR 21' => ['respiration_rate', 21, 2, 'high'],
            'RR 24' => ['respiration_rate', 24, 2, 'high'],
            'RR 25' => ['respiration_rate', 25, 3, 'high'],

            'SBP 90' => ['systolic', 90, 3, 'low'],
            'SBP 91' => ['systolic', 91, 2, 'low'],
            'SBP 100' => ['systolic', 100, 2, 'low'],
            'SBP 101' => ['systolic', 101, 1, 'low'],
            'SBP 110' => ['systolic', 110, 1, 'low'],
            'SBP 111' => ['systolic', 111, 0, 'normal'],
            'SBP 219' => ['systolic', 219, 0, 'normal'],
            'SBP 220' => ['systolic', 220, 3, 'high'],

            'Pulse 40' => ['pulse', 40, 3, 'low'],
            'Pulse 41' => ['pulse', 41, 1, 'low'],
            'Pulse 50' => ['pulse', 50, 1, 'low'],
            'Pulse 51' => ['pulse', 51, 0, 'normal'],
            'Pulse 90' => ['pulse', 90, 0, 'normal'],
            'Pulse 91' => ['pulse', 91, 1, 'high'],
            'Pulse 110' => ['pulse', 110, 1, 'high'],
            'Pulse 111' => ['pulse', 111, 2, 'high'],
            'Pulse 130' => ['pulse', 130, 2, 'high'],
            'Pulse 131' => ['pulse', 131, 3, 'high'],

            'Temp 35.0' => ['temperature', 35.0, 3, 'low'],
            'Temp 35.05' => ['temperature', 35.05, 1, 'low'],
            'Temp 35.1' => ['temperature', 35.1, 1, 'low'],
            'Temp 36.0' => ['temperature', 36.0, 1, 'low'],
            'Temp 36.1' => ['temperature', 36.1, 0, 'normal'],
            'Temp 38.0' => ['temperature', 38.0, 0, 'normal'],
            'Temp 38.1' => ['temperature', 38.1, 1, 'high'],
            'Temp 39.0' => ['temperature', 39.0, 1, 'high'],
            'Temp 39.1' => ['temperature', 39.1, 2, 'high'],
        ];
    }

    #[DataProvider('parameterBands')]
    public function test_parameter_bands_match_the_rcp_chart(string $parameter, float $value, int $score, string $direction): void
    {
        $this->assertSame(['score' => $score, 'direction' => $direction], News2::scoreParameter($parameter, $value));
    }

    public static function spo2Bands(): array
    {
        return [
            'S1 91' => [91, 1, false, 3],
            'S1 92' => [92, 1, false, 2],
            'S1 93' => [93, 1, false, 2],
            'S1 94' => [94, 1, false, 1],
            'S1 95' => [95, 1, false, 1],
            'S1 96' => [96, 1, false, 0],
            'S1 99 on O2' => [99, 1, true, 0],
            'S2 83' => [83, 2, false, 3],
            'S2 84' => [84, 2, false, 2],
            'S2 86' => [86, 2, false, 1],
            'S2 88' => [88, 2, false, 0],
            'S2 92 on O2' => [92, 2, true, 0],
            'S2 97 on air' => [97, 2, false, 0],
            'S2 93 on O2' => [93, 2, true, 1],
            'S2 95 on O2' => [95, 2, true, 2],
            'S2 97 on O2' => [97, 2, true, 3],
        ];
    }

    #[DataProvider('spo2Bands')]
    public function test_spo2_scales_match_the_rcp_chart(float $value, int $scale, bool $onOxygen, int $score): void
    {
        $this->assertSame($score, News2::scoreSpo2($value, $scale, $onOxygen)['score']);
    }

    public function test_consciousness_scores_three_for_anything_but_alert(): void
    {
        $this->assertSame(0, News2::scoreConsciousness('alert')['score']);
        foreach (['new_confusion', 'voice', 'pain', 'unresponsive'] as $level) {
            $this->assertSame(3, News2::scoreConsciousness($level)['score']);
        }
    }

    public function test_full_set_aggregates_into_the_right_clinical_risk(): void
    {
        $normal = [
            'respiration_rate' => 16, 'spo2' => 97, 'spo2_scale' => 1, 'on_oxygen' => false,
            'systolic' => 125, 'pulse' => 72, 'consciousness' => 'alert', 'temperature' => 36.8,
        ];

        $this->assertSame(['total' => 0, 'risk' => 'none'], array_intersect_key(News2::assess('news2', $normal), ['total' => 1, 'risk' => 1]));

        // Single red-zone parameter → low-medium even though the total is only 3.
        $confused = News2::assess('news2', [...$normal, 'consciousness' => 'new_confusion']);
        $this->assertSame(3, $confused['total']);
        $this->assertSame('low_medium', $confused['risk']);

        // 2 (RR 22) + 2 (on O2) + 1 (temp 38.5) = 5 → medium.
        $medium = News2::assess('news2', [...$normal, 'respiration_rate' => 22, 'on_oxygen' => true, 'temperature' => 38.5]);
        $this->assertSame(5, $medium['total']);
        $this->assertSame('medium', $medium['risk']);

        // 3 (SBP 88) + 2 (pulse 115) + 2 (SpO2 93) = 7 → high.
        $high = News2::assess('news2', [...$normal, 'systolic' => 88, 'pulse' => 115, 'spo2' => 93]);
        $this->assertSame(7, $high['total']);
        $this->assertSame('high', $high['risk']);
    }

    public function test_types_outside_news2_are_not_scored(): void
    {
        $this->assertNull(News2::assess('weight', ['value' => 70]));
        $this->assertNull(News2::assess('blood_glucose', ['value' => 400]));
    }
}
