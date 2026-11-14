<?php

/**
 * Prices the signed-up user is allowed to pay.
 *
 * A signup without price_id still sees party prices that have no group,
 * or that match the user's group. group_id 0 means the price is for everyone.
 *
 * @return array<int, array<string, mixed>>
 */
function StripePartyPricesForPayment(int $userId, int $groupId, ?int $priceId = null): array
{
    global $database;

    $sql = '
      SELECT
        p.party_id,
        p.name,
        pu.user_id,
        pu.price_id AS user_price_id,
        pp.price_id,
        pp.price_text,
        pp.price,
        pp.depot_price,
        pp.depot_desc
      FROM %prefix%party_user AS pu
      INNER JOIN %prefix%partys AS p ON p.party_id = pu.party_id
      INNER JOIN %prefix%party_prices AS pp ON pp.party_id = pu.party_id
        AND (
          pu.price_id = pp.price_id
          OR (
            (
              pu.price_id = 0
              OR pu.price_id IS NULL
              OR NOT EXISTS (
                SELECT 1
                FROM %prefix%party_prices AS assigned
                WHERE assigned.price_id = pu.price_id
              )
            )
            AND (pp.group_id = 0 OR pp.group_id IS NULL OR pp.group_id = ?)
          )
        )
      WHERE
        pu.user_id = ?
        AND pu.paid = 0
        AND p.senddate > NOW()';

    $parameters = [$groupId, $userId];
    if ($priceId !== null) {
        $sql .= ' AND pp.price_id = ?';
        $parameters[] = $priceId;
    }
    $sql .= ' ORDER BY pp.price_id';

    return $database->queryWithFullResult($sql, $parameters);
}

function StripeGetPriceDetails($priceID): ?array
{
    global $auth;

    $priceID = (int) $priceID;
    if ($priceID <= 0) {
        return null;
    }

    $rows = StripePartyPricesForPayment((int) $auth['userid'], (int) ($auth['group_id'] ?? 0), $priceID);
    if ($rows === []) {
        return null;
    }

    $result = $rows[0];
    $result['price'] = StripeSanitizeVal($result['price'] ?? null);
    if ($result['price'] <= 0) {
        return null;
    }

    return $result;
}

/**
 * @param mixed $string
 */
function StripeSanitizeVal($string): float
{
    if ($string === null || $string === '') {
        return 0.0;
    }

    $string = str_replace(',', '.', (string) $string);
    return (float) $string;
}

/**
 * Party id and price id are stored in the article number.
 */
function StripeSkuToParty(string $sku): array|bool
{
    $parts = explode('-', $sku);
    if (($parts[0] ?? '') === 'PARTY' && isset($parts[1], $parts[2])) {
        return [
            'party_id' => $parts[1],
            'price_id' => $parts[2],
        ];
    }

    return false;
}
