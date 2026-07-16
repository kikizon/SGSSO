<?php
/**
 * Helpers de semana ISO para el módulo 6S.
 * La auditoría pertenece a una semana (año ISO + número de semana).
 * fecha = lunes de esa semana (para cálculos/orden).
 */

/** [DateTime inicio (lunes), DateTime fin (domingo)] de una semana ISO. */
function s6_rango_semana(int $anio, int $semana): array {
    $ini = (new DateTime())->setISODate($anio, $semana);
    $fin = (clone $ini)->modify('+6 days');
    return [$ini, $fin];
}

/** Etiqueta tipo "Semana 28 · 07–13 jul 2026". */
function s6_label_semana(int $anio, int $semana): string {
    [$i, $f] = s6_rango_semana($anio, $semana);
    $m = ['', 'ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    $di = $i->format('d'); $df = $f->format('d');
    if ($i->format('n') === $f->format('n')) {
        $rango = $di."–". $df ."". $m[(int)$f->format('n')];
    } else {
        $rango = "$di " . $m[(int)$i->format('n')] . "–$df " . $m[(int)$f->format('n')];
    }
    return "Semana $semana · $rango " . $f->format('Y');
}

/** [anioISO, semanaISO] de hoy (o de una fecha dada). */
function s6_semana_actual(?string $fecha = null): array {
    $ts = $fecha ? strtotime($fecha) : time();
    return [(int)date('o', $ts), (int)date('W', $ts)];
}

/** Número de semanas ISO que tiene un año (52 o 53). */
function s6_semanas_en_anio(int $anio): int {
    $d = (new DateTime())->setDate($anio, 12, 28); // 28-dic siempre cae en la última semana ISO
    return (int)$d->format('W');
}

/** Lunes (Y-m-d) de una semana ISO. */
function s6_lunes(int $anio, int $semana): string {
    [$i] = s6_rango_semana($anio, $semana);
    return $i->format('Y-m-d');
}

/** Domingo (Y-m-d) de una semana ISO. */
function s6_domingo(int $anio, int $semana): string {
    [, $f] = s6_rango_semana($anio, $semana);
    return $f->format('Y-m-d');
}
