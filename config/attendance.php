<?php

return [
    // Tap beruntun kartu yang sama untuk arah yang sama dalam jendela ini
    // dianggap pembacaan ganda.
    'debounce_seconds' => (int) env('ATTENDANCE_DEBOUNCE_SECONDS', 3),
];
