<?php

/**
 * Used as a callback function
 *
 * @return bool
 */
function ShowGuestMap()
{
    global $cfg;

    return (int) ($cfg['guestlist_guestmap'] ?? 0) > 0;
}
