<?php
/**
 * Sharclean – Cálculos de asistencia (horas trabajadas, extra, retardo)
 * Usado por admin/asistencia.php y portal/checkin_submit.php
 */

declare(strict_types=1);

function calcHorasTrabajadas(?string $entrada, ?string $salida): ?float {
    if (!$entrada || !$salida) return null;
    $e = strtotime("2000-01-01 $entrada");
    $s = strtotime("2000-01-01 $salida");
    return ($s > $e) ? round(($s - $e) / 3600, 2) : null;
}

function calcHorasExtra(?float $trabajadas, int $jornada): float {
    return ($trabajadas !== null) ? max(0, round($trabajadas - $jornada, 2)) : 0;
}

function calcRetardo(?string $hora_entrada, string $hora_base, int $tolerancia): array {
    if (!$hora_entrada) return [false, 0];
    $te = strtotime("2000-01-01 $hora_entrada");
    $tb = strtotime("2000-01-01 $hora_base") + ($tolerancia * 60);
    if ($te > $tb) {
        $min = (int)round(($te - strtotime("2000-01-01 $hora_base")) / 60);
        return [true, $min];
    }
    return [false, 0];
}
