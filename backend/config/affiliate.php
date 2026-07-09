<?php

return [
    // Flat commission rate paid to the referring affiliate on a referred
    // trader's first paid challenge order. Kept simple (single flat rate) for
    // the MVP; tiered rates by affiliate volume are a natural follow-up.
    'commission_pct' => (float) env('AFFILIATE_COMMISSION_PCT', 10.0),

    // Only the referred trader's FIRST paid order generates a commission,
    // not every subsequent challenge they buy — prevents commission farming
    // via repeat purchases by the same referred account.
    'first_order_only' => true,
];
