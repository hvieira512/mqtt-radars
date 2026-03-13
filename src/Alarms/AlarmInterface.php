<?php

namespace App\Alarms;

interface AlarmInterface
{
    /**
     * Evaluate the parsed data and return generated alarms
     *
     * @param array $parsed
     * @return array
     */
    public function evaluate(array $parsed): array;
}
