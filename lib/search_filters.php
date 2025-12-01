<?php declare(strict_types=1);

/**
 * Build a safe SQL + params for the strains search form.
 * Rules:
 *  - If only minNumber OR maxNumber: exact Strain match.
 *  - If both set: Strain BETWEEN min and max (auto-swaps if reversed).
 *  - If genotype given: Genotype LIKE %term%.
 *  - Limit defaults to 1000, clamps to 1..1000000.
 */
function build_strain_search_sql(PDO $dbh, array $q): array {
    $where = [];
    $params = [];

    // Read inputs (names must match your form)
    $min = isset($q['minNumber']) && $q['minNumber'] !== ''
        ? (int)preg_replace('/\D+/', '', (string)$q['minNumber']) : null;
    $max = isset($q['maxNumber']) && $q['maxNumber'] !== ''
        ? (int)preg_replace('/\D+/', '', (string)$q['maxNumber']) : null;
    $limit = isset($q['limit']) && $q['limit'] !== ''
        ? (int)preg_replace('/\D+/', '', (string)$q['limit']) : 1000;
    if ($limit < 1) $limit = 1;
    if ($limit > 1000000) $limit = 1000000;

    $geno = isset($q['genotype']) ? trim((string)$q['genotype']) : '';

    // Number filter logic
    if ($min !== null && $max === null) {
        $where[] = "`Strain` = :strain_exact";
        $params[':strain_exact'] = [$min, PDO::PARAM_INT];
    } elseif ($max !== null && $min === null) {
        $where[] = "`Strain` = :strain_exact";
        $params[':strain_exact'] = [$max, PDO::PARAM_INT];
    } elseif ($min !== null && $max !== null) {
        if ($min > $max) { $t = $min; $min = $max; $max = $t; }
        $where[] = "`Strain` BETWEEN :strain_min AND :strain_max";
        $params[':strain_min'] = [$min, PDO::PARAM_INT];
        $params[':strain_max'] = [$max, PDO::PARAM_INT];
    }

    // Genotype LIKE
    if ($geno !== '') {
        $where[] = "`Genotype` LIKE :geno";
        $params[':geno'] = ['%'.$geno.'%', PDO::PARAM_STR];
    }

    $sql = "SELECT `Strain`,`Genotype`,`Recipient`,`Donor`,`Comment`,`Signature`,`Created`
            FROM `strains`";

    if ($where) {
        $sql .= " WHERE " . implode(" AND ", $where);
    }

    $sql .= " ORDER BY `Strain`";
    $sql .= " LIMIT :lim";

    return [$sql, $params, $limit];
}

