<?php

namespace App\Alarms;

class HeartBreathAlarms implements AlarmInterface
{
    public function evaluate(array $parsed): array
    {
        $alarms = [];

        $hr = $parsed['heart_rate'] ?? null;
        $br = $parsed['breathing'] ?? null;
        $ss = $parsed['sleep_state'] ?? null;

        // --- HEART RATE ---
        $this->applyRuleSet($alarms, $hr, [
            [$hr > 140, 'heart_rate_high_critical', 'perigo', "Frequência cardíaca muito alta: {$hr} bpm"],
            [$hr > 110, 'heart_rate_high', 'aviso', "Frequência cardíaca elevada: {$hr} bpm"],
            [$hr < 30,  'heart_rate_low_critical', 'perigo', "Frequência cardíaca muito baixa: {$hr} bpm"],
            [$hr < 40,  'heart_rate_low', 'aviso', "Frequência cardíaca baixa: {$hr} bpm"],
        ]);

        // --- APNEA ---
        if ($this->isSleeping($ss) && $this->isInvalidBreathing($br)) {
            $alarms[] = $this->makeAlarm(
                'apnea',
                'perigo',
                "Possível apneia durante o sono"
            );
        }

        // --- BREATHING RATE ---
        $this->applyRuleSet($alarms, $br, [
            [$br > 25, 'breathing_high', 'aviso', "Frequência respiratória elevada: {$br} rpm"],
            [$br < 8 && $br > 0, 'breathing_low', 'perigo', "Frequência respiratória baixa: {$br} rpm"],
        ]);

        // --- SENSOR FAILURE ---
        if ($this->isSignalLost($hr, $br)) {
            $alarms[] = $this->makeAlarm(
                'vitals_signal_lost',
                'aviso',
                "Sem leitura de sinais vitais"
            );
        }

        return $alarms;
    }

    private function applyRuleSet(array &$alarms, $value, array $rules): void
    {
        if ($value === null) return;

        foreach ($rules as [$condition, $type, $level, $message]) {
            if ($condition) {
                $alarms[] = $this->makeAlarm($type, $level, $message);
                break; // stop at first match
            }
        }
    }

    private function isSleeping(?string $sleepState): bool
    {
        return in_array($sleepState, ['Light Sleep', 'Deep Sleep'], true);
    }

    private function isInvalidBreathing($br): bool
    {
        return in_array($br, [-1, 0, null], true);
    }

    private function isSignalLost($hr, $br): bool
    {
        return $hr === -1 && $br === -1;
    }

    private function makeAlarm(string $type, string $level, ?string $message = null): array
    {
        return $this->makeEntry('alarm', $type, $level, $message);
    }

    private function makeEvent(string $type, ?string $message = null): array
    {
        return $this->makeEntry('event', $type, 'info', $message);
    }

    private function makeEntry(
        string $category,
        string $type,
        string $level,
        ?string $message = null
    ): array {
        $entry = [
            'category' => $category,
            'alarm_type' => $type,
            'level' => $level,
            'source' => 'heartbreath',
        ];

        if ($message !== null) {
            $entry['message'] = $message;
        }

        return $entry;
    }
}
